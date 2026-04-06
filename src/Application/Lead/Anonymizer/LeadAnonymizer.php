<?php

declare(strict_types=1);

namespace App\Application\Lead\Anonymizer;

use App\Domain\Lead\Lead;
use Doctrine\ORM\EntityManagerInterface;


final readonly class LeadAnonymizer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array{processed:int, anonymizable:int, cutoff:\DateTimeImmutable} */
    public function previewOlderThanYears(int $years): array
    {
        $years = max(1, $years);
        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d years', $years));
        $processed = 0;
        $anonymizable = 0;

        foreach ($this->iterateLeadsOlderThan($cutoff) as $lead) {
            if (!$lead instanceof Lead) {
                continue;
            }

            ++$processed;

            if (!$this->isAlreadyAnonymized($lead)) {
                ++$anonymizable;
            }
        }

        return [
            'processed' => $processed,
            'anonymizable' => $anonymizable,
            'cutoff' => $cutoff,
        ];
    }

    /** @return array{processed:int, anonymized:int, cutoff:\DateTimeImmutable} */
    public function anonymizeOlderThanYears(int $years, int $batchSize): array
    {
        $years = max(1, $years);
        $batchSize = max(1, $batchSize);
        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d years', $years));
        $processed = 0;
        $anonymized = 0;

        foreach ($this->iterateLeadsOlderThan($cutoff) as $lead) {
            if (!$lead instanceof Lead) {
                continue;
            }

            ++$processed;

            if ($this->isAlreadyAnonymized($lead)) {
                continue;
            }

            $this->anonymizeLead($lead);
            ++$anonymized;

            if (0 === ($anonymized % $batchSize)) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        return [
            'processed' => $processed,
            'anonymized' => $anonymized,
            'cutoff' => $cutoff,
        ];
    }

    private function iterateLeadsOlderThan(\DateTimeImmutable $cutoff): iterable
    {
        return $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Lead::class, 'l')
            ->andWhere('l.createdAt <= :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('l.id', 'ASC')
            ->getQuery()
            ->toIterable();
    }

    private function isAlreadyAnonymized(Lead $lead): bool
    {
        return str_starts_with($lead->getEmail(), 'anon+')
            && 'ANONYMIZED' === $lead->getFirstName()
            && 'ANONYMIZED' === $lead->getLastName();
    }

    private function anonymizeLead(Lead $lead): void
    {
        $id = $lead->getId();
        $anonymizedEmail = null !== $id
            ? sprintf('anon+%d@example.invalid', $id)
            : sprintf('anon+%s@example.invalid', bin2hex(random_bytes(8)));

        $lead
            ->setEmail($anonymizedEmail)
            ->setFirstName('ANONYMIZED')
            ->setLastName('ANONYMIZED')
            ->setAttributes([])
            ->markDeleted();
    }
}
