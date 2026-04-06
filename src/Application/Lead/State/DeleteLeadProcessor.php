<?php

declare(strict_types=1);

namespace App\Application\Lead\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Lead\Lead;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DeleteLeadProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Lead) {
            $data->markDeleted();
            $this->entityManager->flush();
        }

        return $data;
    }
}
