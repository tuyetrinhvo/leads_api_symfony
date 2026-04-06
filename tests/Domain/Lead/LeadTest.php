<?php

declare(strict_types=1);

namespace App\Tests\Domain\Lead;

use App\Domain\Lead\Campaign;
use App\Domain\Lead\Consent;
use App\Domain\Lead\Lead;
use App\Domain\Lead\LeadStatus;
use PHPUnit\Framework\TestCase;

final class LeadTest extends TestCase
{
    public function testMarkDeletedSetsStatusAndDeletedAt(): void
    {
        $lead = (new Lead())
            ->setEmail('john@example.com')
            ->setFirstName('John')
            ->setLastName('Doe');

        self::assertNull($lead->getDeletedAt());
        self::assertSame(LeadStatus::NEW, $lead->getStatus());

        $lead->markDeleted();

        self::assertSame(LeadStatus::DELETED, $lead->getStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $lead->getDeletedAt());
    }

    public function testMarkExportedSetsStatusAndExportedAt(): void
    {
        $lead = (new Lead())
            ->setEmail('john@example.com')
            ->setFirstName('John')
            ->setLastName('Doe');

        self::assertNull($lead->getExportedAt());
        self::assertSame(LeadStatus::NEW, $lead->getStatus());

        $lead->markExported();

        self::assertSame(LeadStatus::EXPORTED, $lead->getStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $lead->getExportedAt());
    }

    public function testAddAndRemoveConsentKeepBothSidesInSync(): void
    {
        $lead = (new Lead())
            ->setEmail('john@example.com')
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setCampaign((new Campaign())
                ->setName('Spring Campaign')
                ->setPartner('Partner A')
                ->setStartsAt(new \DateTimeImmutable('-1 day')));

        $consent = (new Consent())
            ->setScope('upd_marketing')
            ->setPolicyVersion('v1')
            ->setGivenAt(new \DateTimeImmutable())
            ->setSource('partner_form')
            ->setIpAddress('127.0.0.1');

        $lead->addConsent($consent);

        self::assertCount(1, $lead->getConsents());
        self::assertSame($lead, $consent->getLead());

        $lead->removeConsent($consent);

        self::assertCount(0, $lead->getConsents());
        self::assertNull($consent->getLead());
    }
}
