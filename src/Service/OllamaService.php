<?php

namespace App\Service;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OllamaService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(LLM_BASE_URL)%')]
        string $llmBaseUrl,
        #[Autowire('%env(default::LLM_API_KEY)%')]
        string $llmApiKey,
        #[Autowire('%env(LLM_MODEL)%')]
        private string $defaultModel
    ) {
        $this->baseUrl = rtrim($llmBaseUrl, '/');
        $this->apiKey = trim($llmApiKey);
    }

    public function generate(string $model, string $prompt, string $system = ''): string
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException('LLM base URL is missing. Set LLM_BASE_URL.');
        }

        if ($this->apiKey === '') {
            throw new RuntimeException('LLM API key is missing. Set LLM_API_KEY.');
        }

        if (trim($model) === '') {
            throw new RuntimeException('LLM model is missing. Set LLM_MODEL or pass a model.');
        }

        $headers = [
            'Authorization' => 'Bearer '.$this->apiKey,
        ];

        $httpResponse = $this->httpClient->request('POST', $this->baseUrl.'/api/generate', [
            'json' => [
                'model' => $model,
                'prompt' => $prompt,
                'system' => $system,
                'stream' => false,
            ],
            'headers' => $headers,
            'timeout' => 60,
        ]);

        $statusCode = $httpResponse->getStatusCode();
        $body = $httpResponse->getContent(false);
        $response = [];

        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $response = $decoded;
            }
        }

        if ($statusCode >= 400) {
            $errorMessage = $response['error'] ?? $response['message'] ?? $body;
            throw new RuntimeException('LLM request failed (HTTP '.$statusCode.'): '.$errorMessage);
        }

        if (!isset($response['response']) || !is_string($response['response'])) {
            $knownKeys = implode(', ', array_keys($response));
            throw new RuntimeException('LLM response missing output text. Keys: '.$knownKeys);
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
        if (trim($this->defaultModel) === '') {
            throw new RuntimeException('Default LLM model is missing. Set LLM_MODEL.');
        }

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
