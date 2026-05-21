<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final class SupabaseUser implements UserInterface
{
    public function __construct(
        private string $userIdentifier,
        private array $roles,
        private array $claims
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function getClaims(): array
    {
        return $this->claims;
    }

    public function eraseCredentials(): void
    {
    }
}
