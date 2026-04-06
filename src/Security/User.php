<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;

final class User implements JWTUserInterface
{
    /** @param string[] $roles */
    public function __construct(
        private readonly string $userIdentifier,
        private readonly array $roles = [],
    ) {
    }

    public static function createFromPayload($username, array $payload): self
    {
        $roles = $payload['roles'] ?? [];
        if (!\is_array($roles)) {
            $roles = [];
        }

        return new self((string) $username, array_values(array_filter($roles, 'is_string')));
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    /** @return string[] */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }
}
