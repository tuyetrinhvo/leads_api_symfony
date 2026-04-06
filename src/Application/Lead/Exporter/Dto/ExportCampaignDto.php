<?php

declare(strict_types=1);

namespace App\Application\Lead\Exporter\Dto;

/**
 * Campaign payload included in exported lead data.
 */
final readonly class ExportCampaignDto
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $partner,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'partner' => $this->partner,
        ];
    }
}
