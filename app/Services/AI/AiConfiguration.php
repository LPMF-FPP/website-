<?php

namespace App\Services\AI;

class AiConfiguration
{
    public function defaultsFor(string $provider): array
    {
        $baseUrl = match ($provider) {
            'openrouter' => 'https://openrouter.ai/api/v1',
            'deepseek' => 'https://api.deepseek.com/v1',
            'custom' => config('services.ai.base_url_configured') ? (string) config('services.ai.base_url') : '',
            default => (string) config('services.ai.base_url'),
        };

        return ['base_url' => $baseUrl, 'model' => $this->defaultModel($provider, $baseUrl)];
    }

    private function defaultModel(string $provider, string $baseUrl): string
    {
        if (config('services.ai.model_configured') && rtrim($baseUrl, '/') === rtrim((string) config('services.ai.base_url'), '/')) {
            return (string) config('services.ai.model');
        }

        return match ($provider) {
            'openrouter' => 'openrouter/auto',
            'deepseek' => 'deepseek-chat',
            'custom' => '',
            default => (string) config('services.ai.model'),
        };
    }

    public function values(): array
    {
        // A single settings snapshot prevents mixing values from two committed configurations.
        $all = \App\Models\SystemSetting::query()->where('key', 'like', 'notifications.whatsapp.ai.%')->get()
            ->mapWithKeys(fn ($row) => [$row->key => $row->value])->all();
        $all = array_merge($all, $GLOBALS['__settings_overrides'] ?? []);
        $get = static fn (string $key, mixed $default = null) => $all['notifications.whatsapp.ai.'.$key] ?? $default;
        $provider = (string) $get('provider', 'openai');
        $baseUrl = trim((string) $get('base_url', ''));
        if ($baseUrl === '') {
            $baseUrl = $this->defaultsFor($provider)['base_url'];
        }
        $model = trim((string) $get('model', ''));
        if ($model === '') {
            $model = $this->defaultModel($provider, $baseUrl);
        }
        $key = null;
        $stored = $get('api_key');
        if (! $get('key_removed', false)) {
            if (is_string($stored) && trim($stored) !== '') {
                try {
                    $decrypted = decrypt($stored);
                    $key = is_string($decrypted) ? trim($decrypted) : null;
                } catch (\Throwable) {
                    // An invalid persisted credential must never fall back to ciphertext or another key.
                }
            } elseif (rtrim($baseUrl, '/') === rtrim((string) config('services.ai.base_url'), '/')) {
                $fallback = config('services.ai.key');
                $key = is_string($fallback) ? trim($fallback) : null;
            }
        }

        if ($key !== null && preg_match('/[\x00-\x20\x7f]/', $key)) {
            $key = null;
        }

        $baseUrl = rtrim($baseUrl, '/');
        $revision = hash('sha256', json_encode([(string) $get('revision', 'legacy'), $provider, $baseUrl, $model, (bool) $key]));

        return ['provider' => $provider, 'base_url' => $baseUrl, 'model' => $model, 'key' => $key, 'revision' => $revision];
    }

    public function status(): array
    {
        $values = $this->values();
        $ready = (bool) $values['key'] && $values['model'] !== '';
        $displayUrl = $values['base_url'];
        try {
            app(AiEndpointPolicy::class)->validate($values['base_url']);
        } catch (AiFailure) {
            $ready = false;
            $displayUrl = '';
        }

        return [
            'ai_provider' => $values['provider'], 'ai_base_url' => $displayUrl,
            'ai_model' => $values['model'], 'ai_api_key_configured' => (bool) $values['key'],
            'ai_configured' => $ready,
            'ai_revision' => $values['revision'],
        ];
    }
}
