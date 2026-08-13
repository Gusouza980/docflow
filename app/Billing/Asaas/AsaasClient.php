<?php

namespace App\Billing\Asaas;

use App\Exceptions\AsaasApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasClient
{
    public function __construct(
        private ?string $apiKey = null,
        private ?string $baseUrl = null,
    ) {
        $this->apiKey ??= (string) config('docflow.billing.asaas_api_key');
        $this->baseUrl ??= rtrim((string) config('docflow.billing.asaas_base_url'), '/');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('get', $path, query: $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = []): array
    {
        return $this->send('post', $path, payload: $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function put(string $path, array $payload = []): array
    {
        return $this->send('put', $path, payload: $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $path): array
    {
        return $this->send('delete', $path);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $query = [], array $payload = []): array
    {
        if ($this->apiKey === '') {
            throw new AsaasApiException('ASAAS_API_KEY is not configured.');
        }

        $url = $this->baseUrl.'/'.ltrim($path, '/');

        /** @var Response $response */
        $response = match ($method) {
            'get' => $this->http()->get($url, $query),
            'post' => $this->http()->post($url, $payload),
            'put' => $this->http()->put($url, $payload),
            'delete' => $this->http()->delete($url),
            default => throw new AsaasApiException("Unsupported HTTP method [{$method}]."),
        };

        if ($response->failed()) {
            Log::warning('Asaas API request failed', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new AsaasApiException(
                message: 'Asaas API request failed: '.$response->body(),
                status: $response->status(),
                context: [
                    'method' => $method,
                    'path' => $path,
                    'response' => $response->json(),
                ],
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'access_token' => $this->apiKey,
                'User-Agent' => 'Docflow/1.0',
            ])
            ->timeout(30);
    }
}
