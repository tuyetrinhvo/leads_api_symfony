<?php

declare(strict_types=1);

namespace App\Domain\Lead;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class Lead
{
    private ?int $id = null;
    private string $email;
    private string $firstName;
    private string $lastName;

    /** @var array<string, mixed> */
    private array $attributes = [];

    private LeadStatus $status = LeadStatus::NEW;
    private ?\DateTimeImmutable $exportedAt = null;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $deletedAt = null;
    private ?Campaign $campaign = null;

    /** @var Collection<int, Consent> */
    private Collection $consents;

    public function __construct()
    {
        $this->consents = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /** @param array<string, mixed> $attributes */
    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getStatus(): LeadStatus
    {
        return $this->status;
    }

    public function setStatus(LeadStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getExportedAt(): ?\DateTimeImmutable
    {
        return $this->exportedAt;
    }

    public function setExportedAt(?\DateTimeImmutable $exportedAt): self
    {
        $this->exportedAt = $exportedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getCampaign(): ?Campaign
    {
        return $this->campaign;
    }

    public function setCampaign(?Campaign $campaign): self
    {
        $this->campaign = $campaign;

        return $this;
    }

    /** @return Collection<int, Consent> */
    public function getConsents(): Collection
    {
        return $this->consents;
    }

    public function addConsent(Consent $consent): self
    {
        if (!$this->consents->contains($consent)) {
            $this->consents->add($consent);
            $consent->setLead($this);
        }

        return $this;
    }

    public function removeConsent(Consent $consent): self
    {
        if ($this->consents->removeElement($consent) && $consent->getLead() === $this) {
            $consent->setLead(null);
        }

        return $this;
    }

    public function markDeleted(?\DateTimeImmutable $deletedAt = null): self
    {
        $this->deletedAt = $deletedAt ?? new \DateTimeImmutable();
        $this->status = LeadStatus::DELETED;

        return $this;
    }

    public function markExported(?\DateTimeImmutable $exportedAt = null): self
    {
        $this->exportedAt = $exportedAt ?? new \DateTimeImmutable();
        $this->status = LeadStatus::EXPORTED;

        return $this;
    }

}
