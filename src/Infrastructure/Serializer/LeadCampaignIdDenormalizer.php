<?php

declare(strict_types=1);

namespace App\Infrastructure\Serializer;

use App\Domain\Lead\Lead;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Accepts campaign integer IDs on Lead POST and rewrites them as IRIs.
 */
final class LeadCampaignIdDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'lead_campaign_id_denormalizer_already_called';

    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): mixed {
        if (!\is_array($data)) {
            return $this->denormalizer->denormalize($data, $type, $format, $context);
        }

        if (isset($data['campaign']) && \is_int($data['campaign'])) {
            $data['campaign'] = sprintf('/api/campaigns/%d', $data['campaign']);
        }

        $context[self::ALREADY_CALLED] = true;

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        if (($context[self::ALREADY_CALLED] ?? false) === true) {
            return false;
        }

        return $type === Lead::class
            && \is_array($data)
            && isset($data['campaign'])
            && \is_int($data['campaign']);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Lead::class => false,
        ];
    }
}
