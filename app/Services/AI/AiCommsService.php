<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AiCommsService
{
    public function generateMessage(string $prompt, ?string $currentText = null, string $contextType = 'general'): string
    {
        $baseUrl = config('services.ai.base_url');
        $apiKey = config('services.ai.key');
        $model = config('services.ai.model');

        if (empty($apiKey)) {
            Log::error('AI API Key is missing.');
            throw new RuntimeException('AI service is not configured.');
        }

        $systemPrompt = 'You are an AI assistant for a Laboratory Information System (LIMS). Your task is to draft professional messages for **WhatsApp**. RULES: 1. Use *Bold* for importance. 2. Use _Italic_ for secondary text. 3. Use ~Strike~ for corrections. 4. Use ```Monospace``` for codes. 5. Use professional emojis. 6. Keep it concise.';

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
                Log::error('AI Service Error: '.$response->body());
                throw new RuntimeException('AI service request failed: '.$response->status());
            }

            $data = $response->json();

            return $data['choices'][0]['message']['content'] ?? '';
        } catch (\Exception $e) {
            Log::error('AI Service Exception: '.$e->getMessage());
            throw new RuntimeException('Failed to generate message via AI.');
        }
    }
}
