<?php

namespace App\Security;

use App\Service\Supabase\SupabaseRestClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class SupabaseAuthAuthenticator implements AuthenticatorInterface, AuthenticationEntryPointInterface
{
    public function __construct(
        private SupabaseJwtVerifier $verifier,
        private SupabaseRestClient $supabase,
        private ?string $adminEmails = null,
    )
    {
        $this->adminEmails = trim((string) $this->adminEmails);
    }

    public function supports(Request $request): bool
    {
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/login') || str_starts_with($path, '/auth/session') || str_starts_with($path, '/approvals')) {
            return false;
        }

        return $this->extractToken($request) !== null;
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $token = $this->extractToken($request);
        if ($token === null) {
            throw new AuthenticationException('Missing bearer token.');
        }

        $claims = $this->verifier->verify($token);
        $userId = (string) ($claims['sub'] ?? $claims['user_id'] ?? '');

        if ($userId === '') {
            throw new AuthenticationException('JWT subject is missing.');
        }

        $roles = $this->mapRoles($userId, $claims);

        return new SelfValidatingPassport(
            new UserBadge($userId, fn () => new SupabaseUser($userId, $roles, $claims))
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $user = $passport->getUser();

        return new PostAuthenticationToken($user, $firewallName, $user->getRoles());
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse('/login');
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse('/login');
    }

    private function extractToken(Request $request): ?string
    {
        $authHeader = $request->headers->get('Authorization');
        if (is_string($authHeader) && str_starts_with($authHeader, 'Bearer ')) {
            return trim(substr($authHeader, 7));
        }

        $cookieToken = $request->cookies->get('sb-access-token');
        if (is_string($cookieToken) && $cookieToken !== '') {
            return $cookieToken;
        }

        return null;
    }

    private function mapRoles(string $userId, array $claims): array
    {
        $databaseRoles = $this->loadRolesFromDatabase($userId);
        if ($databaseRoles !== []) {
            return $databaseRoles;
        }

        $roles = [];

        foreach ($this->extractRoleValues($claims) as $roleValue) {
            $mappedRoles = match ($roleValue) {
                'admin', 'role_admin' => ['ROLE_ADMIN'],
                'approver', 'role_approver' => ['ROLE_APPROVER'],
                'finance', 'role_finance' => ['ROLE_FINANCE'],
                'support', 'role_support' => ['ROLE_SUPPORT'],
                default => [],
            };

            foreach ($mappedRoles as $mappedRole) {
                $roles[] = $mappedRole;
            }
        }

        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        if ($email !== '' && in_array($email, $this->getAdminEmailAllowlist(), true)) {
            $roles[] = 'ROLE_ADMIN';
        }

        return array_values(array_unique($roles));
    }

    private function loadRolesFromDatabase(string $userId): array
    {
        if ($userId === '') {
            return [];
        }

        $rows = $this->supabase->select('user_roles', [
            'user_id' => $userId,
        ]);

        $roles = [];
        foreach ($rows as $row) {
            $role = strtoupper(trim((string) ($row['role'] ?? '')));
            if ($role !== '') {
                $roles[] = $role;
            }
        }

        return array_values(array_unique($roles));
    }

    private function extractRoleValues(array $claims): array
    {
        $candidates = [
            $claims['role'] ?? null,
            $claims['roles'] ?? null,
            is_array($claims['app_metadata'] ?? null) ? ($claims['app_metadata']['role'] ?? null) : null,
            is_array($claims['app_metadata'] ?? null) ? ($claims['app_metadata']['roles'] ?? null) : null,
            is_array($claims['user_metadata'] ?? null) ? ($claims['user_metadata']['role'] ?? null) : null,
            is_array($claims['user_metadata'] ?? null) ? ($claims['user_metadata']['roles'] ?? null) : null,
        ];

        $roles = [];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $roles[] = strtolower(trim($candidate));
            }

            if (is_array($candidate)) {
                foreach ($candidate as $value) {
                    if (is_string($value) && trim($value) !== '') {
                        $roles[] = strtolower(trim($value));
                    }
                }
            }
        }

        return array_values(array_unique($roles));
    }

    private function getAdminEmailAllowlist(): array
    {
        if ($this->adminEmails === '') {
            return [];
        }

        $emails = preg_split('/\s*,\s*/', strtolower($this->adminEmails)) ?: [];

        return array_values(array_filter($emails, static fn (string $email): bool => $email !== ''));
    }
}
