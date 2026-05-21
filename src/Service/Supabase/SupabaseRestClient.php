<?php

namespace App\Service\Supabase;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SupabaseRestClient
{
    private string $baseUrl;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $supabaseUrl,
        private string $serviceRoleKey
    ) {
        $this->baseUrl = rtrim($this->supabaseUrl, '/');
    }

    public function select(string $table, array $filters = [], array $options = []): array
    {
        $query = ['select' => '*'];

        foreach ($filters as $column => $value) {
            $filterValue = $this->buildFilterValue($value);
            if ($filterValue === null) {
                continue;
            }

            $query[$column] = $filterValue;
        }

        if (isset($options['order'])) {
            $order = $options['order'];
            $direction = ($order['ascending'] ?? true) ? 'asc' : 'desc';
            $query['order'] = ($order['column'] ?? 'created_at').'.'.$direction;
        }

        if (isset($options['limit'])) {
            $query['limit'] = (string) $options['limit'];
        }

        if (isset($options['offset'])) {
            $query['offset'] = (string) $options['offset'];
        }

        $url = '/rest/v1/'.$table.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $this->requestJson('GET', $url);
    }

    public function insert(string $table, array $data): array
    {
        return $this->requestJson('POST', '/rest/v1/'.$table, [
            'json' => $data,
            'headers' => [
                'Prefer' => 'return=representation',
            ],
        ]);
    }

    public function upsert(string $table, array $data, string $onConflict): array
    {
        $url = '/rest/v1/'.$table.'?'.http_build_query(['on_conflict' => $onConflict], '', '&', PHP_QUERY_RFC3986);

        return $this->requestJson('POST', $url, [
            'json' => $data,
            'headers' => [
                'Prefer' => 'resolution=merge-duplicates,return=representation',
            ],
        ]);
    }

    public function update(string $table, string $id, array $data): array
    {
        $url = '/rest/v1/'.$table.'?'.http_build_query(['id' => 'eq.'.$id], '', '&', PHP_QUERY_RFC3986);

        return $this->requestJson('PATCH', $url, [
            'json' => $data,
            'headers' => [
                'Prefer' => 'return=representation',
            ],
        ]);
    }

    public function count(string $table, array $filters = []): int
    {
        $query = ['select' => 'id'];

        foreach ($filters as $column => $value) {
            $filterValue = $this->buildFilterValue($value);
            if ($filterValue === null) {
                continue;
            }

            $query[$column] = $filterValue;
        }

        $url = '/rest/v1/'.$table.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        $response = $this->httpClient->request('GET', $this->baseUrl.$url, [
            'headers' => $this->buildHeaders([
                'Prefer' => 'count=exact',
            ]),
        ]);

        $headers = $response->getHeaders(false);
        $contentRange = $headers['content-range'][0] ?? '';
        if (is_string($contentRange) && str_contains($contentRange, '/')) {
            $parts = explode('/', $contentRange);
            $count = $parts[1] ?? '';
            if (is_numeric($count)) {
                return (int) $count;
            }
        }

        $data = $response->toArray(false);
        return is_array($data) ? count($data) : 0;
    }

    public function uploadFile(string $bucket, string $path, string $fileContent, string $mimeType): string
    {
        $storagePath = $this->encodePath($path);
        $url = '/storage/v1/object/'.$bucket.'/'.$storagePath;

        $this->httpClient->request('POST', $this->baseUrl.$url, [
            'headers' => $this->buildHeaders([
                'Content-Type' => $mimeType,
                'x-upsert' => 'true',
            ]),
            'body' => $fileContent,
        ])->getStatusCode();

        return $path;
    }

    public function getPublicUrl(string $bucket, string $path): string
    {
        $storagePath = $this->encodePath($path);

        return $this->baseUrl.'/storage/v1/object/public/'.$bucket.'/'.$storagePath;
    }

    public function deleteFile(string $bucket, string $path): void
    {
        $storagePath = $this->encodePath($path);
        $url = '/storage/v1/object/'.$bucket.'/'.$storagePath;

        $this->httpClient->request('DELETE', $this->baseUrl.$url, [
            'headers' => $this->buildHeaders(),
        ])->getStatusCode();
    }

    public function createSignedUrl(string $bucket, string $path, int $expiresIn): ?string
    {
        $storagePath = $this->encodePath($path);
        $url = '/storage/v1/object/sign/'.$bucket.'/'.$storagePath;

        $response = $this->requestJson('POST', $url, [
            'json' => [
                'expiresIn' => $expiresIn,
            ],
        ]);

        if (isset($response['signedURL'])) {
            return $response['signedURL'];
        }

        if (isset($response['signedUrl'])) {
            return $response['signedUrl'];
        }

        return null;
    }

    private function requestJson(string $method, string $path, array $options = []): array
    {
        $options['headers'] = $this->buildHeaders($options['headers'] ?? []);

        return $this->httpClient->request($method, $this->baseUrl.$path, $options)->toArray();
    }

    private function buildHeaders(array $extra = []): array
    {
        return array_merge([
            'apikey' => $this->serviceRoleKey,
            'Authorization' => 'Bearer '.$this->serviceRoleKey,
        ], $extra);
    }

    private function encodePath(string $path): string
    {
        $segments = array_map('rawurlencode', explode('/', trim($path, '/')));

        return implode('/', $segments);
    }

    private function buildFilterValue(mixed $value): ?string
    {
        if (is_array($value) && isset($value['op'], $value['value'])) {
            $operator = (string) $value['op'];
            $operand = $value['value'];

            if ($operator === 'in' && is_array($operand)) {
                if (count($operand) === 0) {
                    return null;
                }
                $escaped = array_map('rawurlencode', array_map('strval', $operand));
                return 'in.('.implode(',', $escaped).')';
            }

            if ($operand === '') {
                return null;
            }

            return $operator.'.'.rawurlencode((string) $operand);
        }

        if (is_bool($value)) {
            return 'eq.'.($value ? 'true' : 'false');
        }

        if ($value === null) {
            return 'is.null';
        }

        if ($value === '') {
            return null;
        }

        return 'eq.'.rawurlencode((string) $value);
    }
}
