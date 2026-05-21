<?php

namespace App\Controller;

use App\Security\SupabaseJwtVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('auth/login.html.twig', [
            'show_sidebar' => false,
        ]);
    }

    #[Route('/auth/session', name: 'auth_session', methods: ['POST'])]
    public function session(Request $request, SupabaseJwtVerifier $verifier): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $token = (string) ($payload['access_token'] ?? '');

        if ($token === '') {
            return new JsonResponse(['error' => 'missing token'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $verifier->verify($token);
        } catch (\RuntimeException $exception) {
            return new JsonResponse([
                'error' => 'invalid token',
                'details' => $exception->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $secure = $request->isSecure();
        $cookie = Cookie::create('sb-access-token')
            ->withValue($token)
            ->withExpires(strtotime('+1 hour'))
            ->withPath('/')
            ->withSecure($secure)
            ->withHttpOnly(true)
            ->withSameSite('Lax');

        $response = new JsonResponse(['ok' => true]);
        $response->headers->setCookie($cookie);

        return $response;
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): RedirectResponse
    {
        $response = new RedirectResponse('/login');
        $response->headers->clearCookie('sb-access-token');

        return $response;
    }
}
