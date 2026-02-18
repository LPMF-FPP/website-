<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AiCommsService
{
    public function generateMessage(string $prompt, ?string $currentText = null, string $contextType = 'general', array $allowedPlaceholderKeys = []): string
    {
        $baseUrl = rtrim((string) config('services.ai.base_url'), '/');
        $apiKey = config('services.ai.key');
        $model = config('services.ai.model');

        if (empty($apiKey)) {
            Log::error('AI API Key is missing.');
            throw new RuntimeException('AI service is not configured.');
        }

        $systemPrompt = 'You are an AI assistant for a Laboratory Information System (LIMS). Your task is to draft professional messages for **WhatsApp**. RULES: 1. Use *Bold* for importance. 2. Use _Italic_ for secondary text. 3. Use ~Strike~ for corrections. 4. Use ```Monospace``` for codes. 5. Use professional emojis. 6. Keep it concise.';

        $sanitizedPlaceholderKeys = $this->sanitizeAllowedPlaceholderKeys($allowedPlaceholderKeys);
        if (! empty($sanitizedPlaceholderKeys)) {
            $placeholders = array_map(static fn (string $key): string => '{'.$key.'}', $sanitizedPlaceholderKeys);
            $systemPrompt .= ' Allowed placeholders: '.implode(', ', $placeholders).'. Use ONLY the provided placeholders exactly as written (including braces). Do NOT invent placeholders.';
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        if ($currentText) {
            $messages[] = ['role' => 'user', 'content' => "Current draft: \"{$currentText}\""];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$apiKey,
            ])->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.7,
            ]);

            if ($response->failed()) {
                $responseBody = (string) $response->body();
                Log::error('AI Service Error: '.$responseBody);

                if (str_contains($responseBody, 'ERR_NGROK_3200')) {
                    throw new RuntimeException('AI endpoint sedang offline (ERR_NGROK_3200).');
                }

                throw new RuntimeException('AI service request failed: '.$response->status());
            }

            $data = $response->json();

            return $data['choices'][0]['message']['content'] ?? '';
        } catch (RuntimeException $e) {
            Log::error('AI Service Exception: '.$e->getMessage());

            throw $e;
        } catch (\Throwable $e) {
            Log::error('AI Service Exception: '.$e->getMessage());

            throw new RuntimeException('Failed to generate message via AI.');
        }
    }

    private function sanitizeAllowedPlaceholderKeys(array $keys): array
    {
        $unique = [];

        foreach ($keys as $key) {
            if (! is_string($key)) {
                continue;
            }

            $key = trim($key);
            $key = trim($key, '{}');
            $key = trim($key);

            if ($key === '') {
                continue;
            }

            if (! preg_match('/^[A-Za-z0-9 _\/-]+$/', $key)) {
                continue;
            }

            $unique[$key] = true;

            if (count($unique) >= 100) {
                break;
            }
        }

        return array_keys($unique);
    }
}
