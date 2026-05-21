<?php

namespace App\Controller;

use App\Service\OllamaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class LlmDebugController extends AbstractController
{
    #[Route('/debug/llm-test', name: 'debug_llm_test', methods: ['GET'])]
    public function test(OllamaService $ollama): JsonResponse
    {
        $prompt = 'Return only JSON: {"status":"ok","message":"LLM reachable"}';
        $result = $ollama->generateJsonDefault($prompt);

        return $this->json($result);
    }
}
