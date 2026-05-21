<?php

namespace App\Service;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OllamaService
{
    private string $baseUrl;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(OLLAMA_HOST)%')]
        string $ollamaHost,
        #[Autowire('%env(OLLAMA_MODEL)%')]
        private string $defaultModel
    ) {
        $this->baseUrl = rtrim($ollamaHost, '/');
    }

    public function generate(string $model, string $prompt, string $system = ''): string
    {
        $response = $this->httpClient->request('POST', $this->baseUrl.'/api/generate', [
            'json' => [
                'model' => $model,
                'prompt' => $prompt,
                'system' => $system,
                'stream' => false,
            ],
            'timeout' => 60,
        ])->toArray(false);

        if (!isset($response['response']) || !is_string($response['response'])) {
            throw new RuntimeException('Ollama response missing output text.');
        }

        return trim($response['response']);
    }

    public function generateJson(string $model, string $prompt, string $system = ''): array
    {
        $text = $this->generate($model, $prompt, $system);
        $clean = $this->stripMarkdownFences($text);
        $decoded = json_decode($clean, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Ollama JSON parse failed: '.$clean);
        }

        return $decoded;
    }

    public function generateDefault(string $prompt, string $system = ''): string
    {
        return $this->generate($this->defaultModel, $prompt, $system);
    }

    public function generateJsonDefault(string $prompt, string $system = ''): array
    {
        return $this->generateJson($this->defaultModel, $prompt, $system);
    }

    private function stripMarkdownFences(string $text): string
    {
        $trimmed = trim($text);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```[a-zA-Z]*\n/', '', $trimmed);
            $trimmed = preg_replace('/\n```$/', '', $trimmed);
        }

        return trim($trimmed);
    }
}
