<?php

declare(strict_types=1);

namespace App\Application\Lead\Exporter\Dto;

/**
 * Consent payload included in exported lead data.
 */
final readonly class ExportConsentDto
{
    public function __construct(
        public ?int $id,
        public string $scope,
        public string $policyVersion,
        public string $givenAt,
        public string $source,
        public ?string $ipAddress,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'scope' => $this->scope,
            'policyVersion' => $this->policyVersion,
            'givenAt' => $this->givenAt,
            'source' => $this->source,
            'ipAddress' => $this->ipAddress,
        ];
    }
}
