<?php

declare(strict_types=1);

namespace App\Application\Lead\Exporter\Dto;

/**
 * Transport DTO representing a lead sent to the external API.
 */
final readonly class ExportLeadDto
{
    /** @param array<string, mixed> $attributes */
    /** @param ExportConsentDto[] $consents */
    public function __construct(
        public ?int $id,
        public string $email,
        public string $firstName,
        public string $lastName,
        public array $attributes,
        public string $status,
        public string $createdAt,
        public ?ExportCampaignDto $campaign,
        public array $consents,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'attributes' => $this->attributes,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
            'campaign' => $this->campaign?->toArray(),
            'consents' => array_map(
                static fn (ExportConsentDto $consent): array => $consent->toArray(),
                $this->consents
            ),
        ];
    }
}
