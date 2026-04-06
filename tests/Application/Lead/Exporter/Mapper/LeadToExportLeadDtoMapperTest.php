<?php

declare(strict_types=1);

namespace App\Tests\Application\Lead\Exporter\Mapper;

use App\Application\Lead\Exporter\Mapper\LeadToExportLeadDtoMapper;
use App\Domain\Lead\Campaign;
use App\Domain\Lead\Consent;
use App\Domain\Lead\Lead;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class LeadToExportLeadDtoMapperTest extends TestCase
{
    public function testMapBuildsExpectedExportDto(): void
    {
        $campaign = (new Campaign())
            ->setName('Spring Campaign')
            ->setPartner('Partner A')
            ->setStartsAt(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $this->setPrivateProperty($campaign, 'id', 10);

        $lead = (new Lead())
            ->setEmail('john@example.com')
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setAttributes(['animalType' => 'dog'])
            ->setCampaign($campaign)
            ->setCreatedAt(new \DateTimeImmutable('2026-03-03T10:00:00+00:00'));
        $this->setPrivateProperty($lead, 'id', 42);

        $consent = (new Consent())
            ->setScope('upd_marketing')
            ->setPolicyVersion('v1')
            ->setGivenAt(new \DateTimeImmutable('2026-03-03T09:00:00+00:00'))
            ->setSource('partner_form')
            ->setIpAddress('203.0.113.10');
        $this->setPrivateProperty($consent, 'id', 99);

        $lead->addConsent($consent);

        $dto = (new LeadToExportLeadDtoMapper())->map($lead);
        $payload = $dto->toArray();

        self::assertSame(42, $payload['id']);
        self::assertSame('john@example.com', $payload['email']);
        self::assertSame('John', $payload['firstName']);
        self::assertSame('Doe', $payload['lastName']);
        self::assertSame(['animalType' => 'dog'], $payload['attributes']);
        self::assertSame('new', $payload['status']);
        self::assertSame('2026-03-03T10:00:00+00:00', $payload['createdAt']);
        self::assertSame(
            ['id' => 10, 'name' => 'Spring Campaign', 'partner' => 'Partner A'],
            $payload['campaign']
        );
        self::assertCount(1, $payload['consents']);
        self::assertSame(
            [
                'id' => 99,
                'scope' => 'upd_marketing',
                'policyVersion' => 'v1',
                'givenAt' => '2026-03-03T09:00:00+00:00',
                'source' => 'partner_form',
                'ipAddress' => '203.0.113.10',
            ],
            $payload['consents'][0]
        );
    }

    private function setPrivateProperty(object $object, string $propertyName, mixed $value): void
    {
        $property = new ReflectionProperty($object, $propertyName);
        $property->setValue($object, $value);
    }
}
