<?php

declare(strict_types=1);

namespace App\Application\Lead\Exporter\Mapper;

use App\Application\Lead\Exporter\Dto\ExportCampaignDto;
use App\Application\Lead\Exporter\Dto\ExportConsentDto;
use App\Application\Lead\Exporter\Dto\ExportLeadDto;
use App\Domain\Lead\Lead;

/**
 * Maps a lead aggregate into the outbound export DTO.
 */
final readonly class LeadToExportLeadDtoMapper
{
    public function map(Lead $lead): ExportLeadDto
    {
        $campaign = $lead->getCampaign();
        $consents = [];

        foreach ($lead->getConsents() as $consent) {
            $consents[] = new ExportConsentDto(
                id: $consent->getId(),
                scope: $consent->getScope(),
                policyVersion: $consent->getPolicyVersion(),
                givenAt: $consent->getGivenAt()->format(\DateTimeInterface::ATOM),
                source: $consent->getSource(),
                ipAddress: $consent->getIpAddress(),
            );
        }

        return new ExportLeadDto(
            id: $lead->getId(),
            email: $lead->getEmail(),
            firstName: $lead->getFirstName(),
            lastName: $lead->getLastName(),
            attributes: $lead->getAttributes(),
            status: $lead->getStatus()->value,
            createdAt: $lead->getCreatedAt()->format(\DateTimeInterface::ATOM),
            campaign: null !== $campaign ? new ExportCampaignDto(
                id: $campaign->getId(),
                name: $campaign->getName(),
                partner: $campaign->getPartner(),
            ) : null,
            consents: $consents,
        );
    }
}
