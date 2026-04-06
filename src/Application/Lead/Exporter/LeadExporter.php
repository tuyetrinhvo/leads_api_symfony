<?php

declare(strict_types=1);

namespace App\Application\Lead\Exporter;

use App\Application\Lead\Exporter\Mapper\LeadToExportLeadDtoMapper;
use App\Domain\Lead\Lead;
use App\Domain\Lead\LeadStatus;
use App\Infrastructure\Lead\Api\UpdLeadApiClient;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Selects exportable leads and sends them to the remote API.
 */
final readonly class LeadExporter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UpdLeadApiClient $updLeadApiClient,
        private LeadToExportLeadDtoMapper $leadToExportLeadDtoMapper,
    ) {
    }

    /** @return array{selected:int} */
    public function preview(int $limit): array
    {
        $limit = max(1, $limit);

        return [
            'selected' => count($this->findExportableLeadIds($limit)),
        ];
    }

    /** @return int[] */
    public function findExportableLeadIds(int $limit): array
    {
        $limit = max(1, $limit);

        $rows = $this->entityManager->createQueryBuilder()
            ->select('l.id AS id')
            ->from(Lead::class, 'l')
            ->andWhere('l.deletedAt IS NULL')
            ->andWhere('l.status != :exportedStatus')
            ->setParameter('exportedStatus', LeadStatus::EXPORTED)
            ->orderBy('l.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row): int => (int) $row['id'],
            $rows
        );
    }

    /** @return array{selected:int, exported:int, failed:int} */
    public function export(int $limit, int $batchSize): array
    {
        $limit = max(1, $limit);
        $batchSize = max(1, $batchSize);
        $leads = $this->findExportableLeads($limit);

        if ([] === $leads) {
            return [
                'selected' => 0,
                'exported' => 0,
                'failed' => 0,
            ];
        }

        $exportedCount = 0;
        $failedCount = 0;
        $processedCount = 0;

        foreach ($leads as $lead) {
            ++$processedCount;

            try {
                $this->updLeadApiClient->exportLead($this->leadToExportLeadDtoMapper->map($lead));
                $lead->markExported();
                ++$exportedCount;
            } catch (\Throwable) {
                ++$failedCount;
            }

            if (0 === ($processedCount % $batchSize)) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();

        return [
            'selected' => count($leads),
            'exported' => $exportedCount,
            'failed' => $failedCount,
        ];
    }

    public function exportOneById(int $leadId): void
    {
        $lead = $this->entityManager->createQueryBuilder()
            ->select('l', 'c', 'consents')
            ->from(Lead::class, 'l')
            ->leftJoin('l.campaign', 'c')
            ->leftJoin('l.consents', 'consents')
            ->andWhere('l.id = :id')
            ->setParameter('id', $leadId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$lead instanceof Lead) {
            return;
        }

        if (null !== $lead->getDeletedAt() || $lead->getStatus() === LeadStatus::EXPORTED) {
            return;
        }

        $this->updLeadApiClient->exportLead($this->leadToExportLeadDtoMapper->map($lead));
        $lead->markExported();
        $this->entityManager->flush();
    }

    /** @return Lead[] */
    private function findExportableLeads(int $limit): array
    {
        /** @var Lead[] $leads */
        $leads = $this->entityManager->createQueryBuilder()
            ->select('l', 'c', 'consents')
            ->from(Lead::class, 'l')
            ->leftJoin('l.campaign', 'c')
            ->leftJoin('l.consents', 'consents')
            ->andWhere('l.deletedAt IS NULL')
            ->andWhere('l.status != :exportedStatus')
            ->setParameter('exportedStatus', LeadStatus::EXPORTED)
            ->orderBy('l.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $leads;
    }
}
