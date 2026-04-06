<?php

declare(strict_types=1);

namespace App\Application\Lead\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Lead\Exporter\Message\ExportLeadMessage;
use App\Domain\Lead\Campaign;
use App\Domain\Lead\Lead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateLeadProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private MessageBusInterface $messageBus,
        private int $duplicateWindowMinutes = 1440,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Lead
    {
        if (!$data instanceof Lead) {
            throw new \LogicException(sprintf('Expected %s input.', Lead::class));
        }

        $campaign = $data->getCampaign();
        if (!$campaign instanceof Campaign) {
            throw new UnprocessableEntityHttpException('Campaign is required.');
        }

        $this->assertCampaignIsActive($campaign);
        $this->assertLeadIsNotDuplicate($data->getEmail(), $campaign);

        if ($data->getConsents()->isEmpty()) {
            throw new UnprocessableEntityHttpException('Consent is required to create a lead.');
        }

        foreach ($data->getConsents() as $consent) {
            $consent->setIpAddress($this->resolveClientIp($consent->getIpAddress()));
        }

        $this->entityManager->persist($data);
        $this->entityManager->flush();
        if (null !== $data->getId()) {
            $this->messageBus->dispatch(new ExportLeadMessage($data->getId()));
        }

        return $data;
    }

    private function assertCampaignIsActive(Campaign $campaign): void
    {
        $now = new \DateTimeImmutable();

        if ($campaign->getStartsAt() > $now) {
            throw new UnprocessableEntityHttpException('Campaign is not active yet.');
        }

        if (null !== $campaign->getEndsAt() && $campaign->getEndsAt() < $now) {
            throw new UnprocessableEntityHttpException('Campaign is no longer active.');
        }
    }

    private function assertLeadIsNotDuplicate(string $email, Campaign $campaign): void
    {
        $windowMinutes = max(1, $this->duplicateWindowMinutes);
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d minutes', $windowMinutes));

        $existingLead = $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Lead::class, 'l')
            ->andWhere('l.email = :email')
            ->andWhere('l.campaign = :campaign')
            ->andWhere('l.deletedAt IS NULL')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('email', $email)
            ->setParameter('campaign', $campaign)
            ->setParameter('since', $since)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingLead instanceof Lead) {
            throw new ConflictHttpException(sprintf(
                'A lead with email "%s" already exists for this campaign in the last %d minutes.',
                $email,
                $windowMinutes
            ));
        }
    }

    private function resolveClientIp(?string $fallbackIp): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request) {
            $clientIp = $request->getClientIp();
            if (null !== $clientIp && '' !== trim($clientIp)) {
                return $clientIp;
            }
        }

        return $fallbackIp;
    }
}
