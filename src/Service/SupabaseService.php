<?php

namespace App\Service;

use RuntimeException;
use Supabase\CreateClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SupabaseService
{
    private CreateClient $client;

    public function __construct(
        #[Autowire('%env(SUPABASE_URL)%')]
        string $supabaseUrl,
        #[Autowire('%env(SUPABASE_SERVICE_ROLE_KEY)%')]
        string $serviceRoleKey
    ) {
        $parsed = parse_url($supabaseUrl);
        $host = $parsed['host'] ?? '';
        $scheme = $parsed['scheme'] ?? 'https';

        if ($host === '') {
            throw new RuntimeException('Invalid SUPABASE_URL: missing host.');
        }

        $hostParts = explode('.', $host);
        $referenceId = array_shift($hostParts);
        $domain = implode('.', $hostParts);

        if ($referenceId === null || $referenceId === '') {
            throw new RuntimeException('Invalid SUPABASE_URL: missing reference id.');
        }

        if ($domain === '') {
            $domain = 'supabase.co';
        }

        $this->client = new CreateClient($serviceRoleKey, $referenceId, [], $domain, $scheme);
    }

    public function insert(string $table, array $data): array
    {
        $response = $this->client->from($table)->insert($data)->select()->execute();

        return $this->unwrapResponse($response, 'insert');
    }

    public function select(string $table, array $filters = []): array
    {
        $query = $this->client->from($table)->select('*');

        foreach ($filters as $column => $value) {
            if ($value === null) {
                $query = $query->is($column, 'null');
                continue;
            }

            $query = $query->eq($column, $value);
        }

        $response = $query->execute();

        return $this->unwrapResponse($response, 'select');
    }

    public function update(string $table, string $id, array $data): array
    {
        $response = $this->client->from($table)->update($data)->eq('id', $id)->select()->execute();

        return $this->unwrapResponse($response, 'update');
    }

    public function uploadFile(string $bucket, string $path, string $fileContent, string $mimeType): string
    {
        $this->client->storage->from($bucket)->upload($path, $fileContent, [
            'contentType' => $mimeType,
            'upsert' => true,
        ]);

        return $path;
    }

    public function getPublicUrl(string $bucket, string $path): string
    {
        return $this->client->storage->from($bucket)->getPublicUrl($path);
    }

    public function deleteFile(string $bucket, string $path): void
    {
        $this->client->storage->from($bucket)->remove([$path]);
    }

    private function unwrapResponse(object $response, string $operation): array
    {
        $error = $response->error ?? null;
        if ($error) {
            throw new RuntimeException('Supabase '.$operation.' failed: '.json_encode($error));
        }

        $data = $response->data ?? [];
        return is_array($data) ? $data : [$data];
    }
}
