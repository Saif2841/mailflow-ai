<?php

namespace App\Twig;

use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RoleExtension extends AbstractExtension
{
    public function __construct(private Security $security)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_role', [$this, 'isRole']),
        ];
    }

    public function isRole(string $role): bool
    {
        return $this->security->isGranted($role);
    }
}
