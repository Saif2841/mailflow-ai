<?php

namespace App\Security;

use RuntimeException;
use JsonException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SupabaseJwtVerifier
{
    private ?array $jwksCache = null;

    public function __construct(
        private string $jwtSecret,
        private string $supabaseUrl,
        private string $supabaseAnonKey,
        private HttpClientInterface $httpClient,
    )
    {
        $this->jwtSecret = trim($jwtSecret);
        $this->supabaseUrl = rtrim(trim($supabaseUrl), '/');
        $this->supabaseAnonKey = trim($supabaseAnonKey);
    }

    public function verify(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid JWT format.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = $this->decodeJson($encodedHeader);
        $payload = $this->decodeJson($encodedPayload);

        $algorithm = strtoupper((string) ($header['alg'] ?? ''));
        if ($algorithm === '') {
            throw new RuntimeException('JWT algorithm is missing.');
        }

        if (str_starts_with($algorithm, 'HS')) {
            if ($this->jwtSecret === '') {
                throw new RuntimeException('SUPABASE_JWT_SECRET is missing.');
            }
            $hash = $this->mapHash($algorithm);
            $expected = $this->signWithHash($encodedHeader.'.'.$encodedPayload, $hash);
            if (!hash_equals($expected, $encodedSignature)) {
                throw new RuntimeException('JWT signature mismatch.');
            }
        } elseif (str_starts_with($algorithm, 'RS')) {
            $opensslAlg = $this->mapOpenSslAlg($algorithm);
            $this->verifyRs($header, $encodedHeader.'.'.$encodedPayload, $encodedSignature, $opensslAlg);
        } elseif (str_starts_with($algorithm, 'ES')) {
            $opensslAlg = $this->mapOpenSslAlg($algorithm);
            $this->verifyEs($header, $encodedHeader.'.'.$encodedPayload, $encodedSignature, $opensslAlg, $algorithm);
        } else {
            throw new RuntimeException('Unsupported JWT algorithm: '.$algorithm);
        }

        $exp = $payload['exp'] ?? null;
        if (is_numeric($exp) && (int) $exp < time()) {
            throw new RuntimeException('JWT has expired.');
        }

        return $payload;
    }

    private function decodeJson(string $input): array
    {
        $decoded = json_decode($this->base64UrlDecode($input), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JWT payload.');
        }

        return $decoded;
    }

    private function sign(string $data): string
    {
        $signature = hash_hmac('sha256', $data, $this->jwtSecret, true);

        return $this->base64UrlEncode($signature);
    }

    private function signWithHash(string $data, string $hash): string
    {
        $signature = hash_hmac($hash, $data, $this->jwtSecret, true);

        return $this->base64UrlEncode($signature);
    }

    private function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($input, '-_', '+/')) ?: '';
    }

    private function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    private function verifyRs(array $header, string $data, string $encodedSignature, int $opensslAlg): void
    {
        if ($this->supabaseUrl === '') {
            throw new RuntimeException('SUPABASE_URL is missing.');
        }

        $kid = (string) ($header['kid'] ?? '');
        if ($kid === '') {
            throw new RuntimeException('JWT header kid is missing.');
        }

        $jwk = $this->findJwk($kid);
        $publicKey = $this->jwkToPem($jwk);
        $signature = $this->base64UrlDecode($encodedSignature);

        if (!function_exists('openssl_verify')) {
            throw new RuntimeException('OpenSSL is required to verify RS256 JWTs.');
        }

        $ok = openssl_verify($data, $signature, $publicKey, $opensslAlg);
        if ($ok !== 1) {
            throw new RuntimeException('JWT signature mismatch.');
        }
    }

    private function verifyEs(array $header, string $data, string $encodedSignature, int $opensslAlg, string $algorithm): void
    {
        if ($this->supabaseUrl === '') {
            throw new RuntimeException('SUPABASE_URL is missing.');
        }

        $kid = (string) ($header['kid'] ?? '');
        if ($kid === '') {
            throw new RuntimeException('JWT header kid is missing.');
        }

        $jwk = $this->findJwk($kid);
        $publicKey = $this->jwkToPem($jwk);
        $rawSignature = $this->base64UrlDecode($encodedSignature);
        $derSignature = $this->ecdsaSignatureToDer($rawSignature, $algorithm);

        if (!function_exists('openssl_verify')) {
            throw new RuntimeException('OpenSSL is required to verify ES256 JWTs.');
        }

        $ok = openssl_verify($data, $derSignature, $publicKey, $opensslAlg);
        if ($ok !== 1) {
            throw new RuntimeException('JWT signature mismatch.');
        }
    }

    private function mapHash(string $algorithm): string
    {
        return match ($algorithm) {
            'HS256' => 'sha256',
            'HS384' => 'sha384',
            'HS512' => 'sha512',
            default => throw new RuntimeException('Unsupported JWT algorithm: '.$algorithm),
        };
    }

    private function mapOpenSslAlg(string $algorithm): int
    {
        return match ($algorithm) {
            'RS256' => OPENSSL_ALGO_SHA256,
            'RS384' => OPENSSL_ALGO_SHA384,
            'RS512' => OPENSSL_ALGO_SHA512,
            'ES256' => OPENSSL_ALGO_SHA256,
            default => throw new RuntimeException('Unsupported JWT algorithm: '.$algorithm),
        };
    }

    private function pemEncode(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".
            chunk_split(base64_encode($der), 64, "\n").
            "-----END PUBLIC KEY-----\n";
    }

    private function findJwk(string $kid): array
    {
        $jwks = $this->getJwks();
        foreach ($jwks as $jwk) {
            if (($jwk['kid'] ?? '') === $kid) {
                return $jwk;
            }
        }

        throw new RuntimeException('Unable to find matching JWK for token.');
    }

    private function getJwks(): array
    {
        if (is_array($this->jwksCache)) {
            return $this->jwksCache;
        }

        if ($this->supabaseAnonKey === '') {
            throw new RuntimeException('SUPABASE_ANON_KEY is missing.');
        }

        $url = $this->supabaseUrl.'/auth/v1/.well-known/jwks.json';
        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'apikey' => $this->supabaseAnonKey,
                'Authorization' => 'Bearer '.$this->supabaseAnonKey,
            ],
        ]);

        $status = $response->getStatusCode();
        $body = $response->getContent(false);

        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf(
                'Unable to load Supabase JWKS (status %d). Response was not valid JSON.',
                $status
            ), 0, $exception);
        }

        $keys = $payload['keys'] ?? null;

        if (!is_array($keys)) {
            throw new RuntimeException(sprintf(
                'Unable to load Supabase JWKS (status %d). Response: %s',
                $status,
                $body === '' ? 'empty' : $body
            ));
        }

        $this->jwksCache = $keys;

        return $keys;
    }

    private function jwkToPem(array $jwk): string
    {
        $keyType = (string) ($jwk['kty'] ?? '');
        if ($keyType === 'RSA') {
            $modulus = $this->base64UrlDecode((string) ($jwk['n'] ?? ''));
            $exponent = $this->base64UrlDecode((string) ($jwk['e'] ?? ''));

            if ($modulus === '' || $exponent === '') {
                throw new RuntimeException('Invalid JWK parameters.');
            }

            $rsaPublicKey = $this->asn1Sequence(
                $this->asn1Integer($modulus).
                $this->asn1Integer($exponent)
            );

            $algorithmIdentifier = $this->asn1Sequence(
                $this->asn1ObjectIdentifier("\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01").
                $this->asn1Null()
            );

            $subjectPublicKeyInfo = $this->asn1Sequence(
                $algorithmIdentifier.
                $this->asn1BitString($rsaPublicKey)
            );

            return $this->pemEncode($subjectPublicKeyInfo);
        }

        if ($keyType === 'EC') {
            return $this->jwkEcToPem($jwk);
        }

        throw new RuntimeException('Unsupported JWK key type.');
    }

    private function jwkEcToPem(array $jwk): string
    {
        $curve = (string) ($jwk['crv'] ?? '');
        if ($curve !== 'P-256') {
            throw new RuntimeException('Unsupported EC curve: '.$curve);
        }

        $x = $this->base64UrlDecode((string) ($jwk['x'] ?? ''));
        $y = $this->base64UrlDecode((string) ($jwk['y'] ?? ''));
        if ($x === '' || $y === '') {
            throw new RuntimeException('Invalid EC JWK parameters.');
        }

        $publicKey = "\x04".$x.$y;
        $algorithmIdentifier = $this->asn1Sequence(
            $this->asn1ObjectIdentifier("\x2a\x86\x48\xce\x3d\x02\x01").
            $this->asn1ObjectIdentifier("\x2a\x86\x48\xce\x3d\x03\x01\x07")
        );

        $subjectPublicKeyInfo = $this->asn1Sequence(
            $algorithmIdentifier.
            $this->asn1BitString($publicKey)
        );

        return $this->pemEncode($subjectPublicKeyInfo);
    }

    private function ecdsaSignatureToDer(string $signature, string $algorithm): string
    {
        $length = $this->ecdsaSignatureLength($algorithm);
        if (strlen($signature) !== $length) {
            throw new RuntimeException('Invalid ECDSA signature length.');
        }

        $half = (int) ($length / 2);
        $r = substr($signature, 0, $half);
        $s = substr($signature, $half);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        if ($r === '' || (ord($r[0]) & 0x80)) {
            $r = "\x00".$r;
        }
        if ($s === '' || (ord($s[0]) & 0x80)) {
            $s = "\x00".$s;
        }

        return $this->asn1Sequence(
            $this->asn1Integer($r).
            $this->asn1Integer($s)
        );
    }

    private function ecdsaSignatureLength(string $algorithm): int
    {
        return match ($algorithm) {
            'ES256' => 64,
            default => throw new RuntimeException('Unsupported JWT algorithm: '.$algorithm),
        };
    }

    private function asn1Sequence(string $value): string
    {
        return "\x30".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Integer(string $value): string
    {
        if ($value !== '' && (ord($value[0]) & 0x80)) {
            $value = "\x00".$value;
        }

        return "\x02".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1ObjectIdentifier(string $value): string
    {
        return "\x06".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Null(): string
    {
        return "\x05\x00";
    }

    private function asn1BitString(string $value): string
    {
        return "\x03".$this->asn1Length(strlen($value) + 1)."\x00".$value;
    }

    private function asn1Length(int $length): string
    {
        if ($length <= 0x7f) {
            return chr($length);
        }

        $temp = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($temp)).$temp;
    }
}
