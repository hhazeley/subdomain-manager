<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudflareClient
{
    private function request(): PendingRequest
    {
        $token = config('services.cloudflare.api_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('The Cloudflare API token is not configured.');
        }

        return Http::baseUrl(config('services.cloudflare.base_url'))
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 300);
    }

    public function verifyZone(string $zoneId, string $expectedName): void
    {
        $result = $this->send('GET', "/zones/{$zoneId}");
        $actualName = strtolower((string) ($result['name'] ?? ''));

        if ($actualName !== strtolower($expectedName)) {
            throw new RuntimeException("Cloudflare zone {$zoneId} belongs to {$actualName}, not {$expectedName}.");
        }
    }

    public function createDnsRecord(string $zoneId, array $payload): array
    {
        return $this->send('POST', "/zones/{$zoneId}/dns_records", $payload);
    }

    public function updateDnsRecord(string $zoneId, string $recordId, array $payload): array
    {
        return $this->send('PUT', "/zones/{$zoneId}/dns_records/{$recordId}", $payload);
    }

    public function deleteDnsRecord(string $zoneId, string $recordId): void
    {
        $this->send('DELETE', "/zones/{$zoneId}/dns_records/{$recordId}");
    }

    private function send(string $method, string $path, array $payload = []): array
    {
        $response = $this->request()->send($method, $path, [
            'json' => $payload,
        ]);

        if ($response->failed()) {
            $response->throw();
        }

        $body = $response->json();

        if (! is_array($body) || ($body['success'] ?? false) !== true) {
            $messages = collect($body['errors'] ?? [])
                ->pluck('message')
                ->filter()
                ->implode('; ');

            throw new RuntimeException($messages ?: 'Cloudflare returned an unsuccessful response.');
        }

        return is_array($body['result'] ?? null) ? $body['result'] : [];
    }
}
