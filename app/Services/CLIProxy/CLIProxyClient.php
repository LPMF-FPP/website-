<?php

namespace App\Services\CLIProxy;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CLIProxyClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.cliproxy.base_url', 'http://localhost:8317');
        $this->timeout = config('services.cliproxy.timeout', 30);
    }

    /**
     * Make a request to CLIProxyAPI
     *
     * @param  string  $endpoint  The endpoint path (e.g., 'v1/chat/completions')
     * @param  array  $payload  Request payload
     * @param  string  $method  HTTP method (GET, POST, etc.)
     * @return array
     */
    public function request(string $endpoint, array $payload = [], string $method = 'POST'): array
    {
        try {
            $http = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ]);

            $url = "{$this->baseUrl}/{$endpoint}";

            Log::info('[CLIProxy] Making request', [
                'url' => $url,
                'method' => $method,
            ]);

            $response = match (strtoupper($method)) {
                'GET' => $http->get($url, $payload),
                'POST' => $http->post($url, $payload),
                'PUT' => $http->put($url, $payload),
                'DELETE' => $http->delete($url, $payload),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            if ($response->successful()) {
                Log::info('[CLIProxy] Request successful', [
                    'status' => $response->status(),
                ]);

                return [
                    'success' => true,
                    'data' => $response->json(),
                    'status' => $response->status(),
                ];
            }

            Log::error('[CLIProxy] Request failed', [
                'status' => $response->status(),
                'error' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status(),
            ];

        } catch (\Throwable $e) {
            Log::error('[CLIProxy] Exception during request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Stream a request to CLIProxyAPI (for streaming responses)
     *
     * @param  string  $endpoint  The endpoint path
     * @param  array  $payload  Request payload
     * @return \Illuminate\Http\Client\Response
     */
    public function stream(string $endpoint, array $payload)
    {
        $http = Http::timeout($this->timeout)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'text/event-stream',
            ]);

        $url = "{$this->baseUrl}/{$endpoint}";

        Log::info('[CLIProxy] Making streaming request', [
            'url' => $url,
        ]);

        return $http->post($url, $payload);
    }

    /**
     * Check if CLIProxyAPI is reachable
     *
     * @return array
     */
    public function checkHealth(): array
    {
        try {
            $http = Http::timeout(10);
            $response = $http->get("{$this->baseUrl}/health");

            return [
                'reachable' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json(),
            ];

        } catch (\Throwable $e) {
            return [
                'reachable' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
