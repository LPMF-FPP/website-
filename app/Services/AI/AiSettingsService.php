<?php

namespace App\Services\AI;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiSettingsService
{
    public function save(array $input, User $actor): array
    {
        $lock = app(AiConfigurationLock::class)->acquire('Konfigurasi AI sedang disimpan atau digunakan. Coba kembali sebentar lagi.');
        try {
            DB::transaction(function () use ($input, $actor): void {
                $configuration = app(AiConfiguration::class);
                $current = $configuration->values();
                if (isset($input['expected_revision']) && $input['expected_revision'] !== $current['revision']) {
                    throw new AiFailure('AI_CONFIGURATION_CHANGED', 'Konfigurasi AI berubah sejak panel dibuka. Muat ulang sebelum menyimpan.', 409);
                }
                $before = $configuration->status();
                $provider = $input['ai_provider'];
                $reset = (bool) ($input['ai_reset_overrides'] ?? false);
                $remove = (bool) ($input['ai_remove_key'] ?? false);
                $key = trim((string) ($input['ai_api_key'] ?? ''));
                $replace = $key !== '' && ! in_array($key, ['••••••••', '********'], true);
                $changedProvider = $provider !== $before['ai_provider'];
                if ($remove && $replace) {
                    throw ValidationException::withMessages(['ai_api_key' => 'Jangan mengganti dan menghapus kunci sekaligus.']);
                }
                if ($reset && $remove) {
                    throw ValidationException::withMessages(['ai_remove_key' => 'Reset URL/model dan penghapusan kunci harus dilakukan sebagai tindakan terpisah.']);
                }
                if ($reset && (trim((string) ($input['ai_base_url'] ?? '')) !== '' || trim((string) ($input['ai_model'] ?? '')) !== '')) {
                    throw ValidationException::withMessages(['ai_reset_overrides' => 'Reset tidak boleh disertai URL atau model pengganti.']);
                }
                if ($reset && $replace) {
                    throw ValidationException::withMessages(['ai_api_key' => 'Reset URL/model tidak boleh mengganti kunci sekaligus.']);
                }
                if ($remove) {
                    if ($changedProvider) {
                        throw ValidationException::withMessages(['ai_provider' => 'Hapus kunci provider tersimpan sebelum mengganti provider.']);
                    }
                    SystemSetting::where('key', 'notifications.whatsapp.ai.api_key')->first()?->delete();
                    foreach (['key_removed' => true, 'revision' => (string) \Illuminate\Support\Str::uuid()] as $name => $value) {
                        SystemSetting::updateOrCreate(['key' => 'notifications.whatsapp.ai.'.$name], ['value' => $value, 'updated_by' => $actor->id]);
                    }
                    ActivityLogger::log('whatsapp.ai.settings.saved', before: $before, after: $configuration->status(), meta: ['key_action' => 'removed'], actorUserId: $actor->id);

                    return;
                }
                if ($changedProvider && ! $replace && ! $remove) {
                    throw ValidationException::withMessages(['ai_api_key' => 'Pergantian provider memerlukan kunci baru atau penghapusan kunci secara eksplisit.']);
                }
                $defaults = $configuration->defaultsFor($provider);
                $baseUrl = $reset ? $defaults['base_url'] : trim((string) ($input['ai_base_url'] ?? ($changedProvider ? $defaults['base_url'] : $before['ai_base_url'])));
                $model = $reset ? $defaults['model'] : trim((string) ($input['ai_model'] ?? ($changedProvider ? $defaults['model'] : $before['ai_model'])));
                if ($baseUrl === '' || $model === '') {
                    throw ValidationException::withMessages(['ai_base_url' => 'URL dan model harus lengkap. Provider custom memerlukan default environment atau override eksplisit yang lengkap.']);
                }
                $baseUrl = app(AiEndpointPolicy::class)->validate($baseUrl);
                if (($changedProvider || $baseUrl !== $before['ai_base_url']) && ! ($input['ai_confirm_destination'] ?? false)) {
                    throw ValidationException::withMessages(['ai_confirm_destination' => 'Konfirmasikan tujuan pengiriman instruksi sebelum mengganti provider atau endpoint.']);
                }
                $values = ['provider' => $provider, 'base_url' => $reset ? null : $baseUrl, 'model' => $reset ? null : $model, 'revision' => (string) \Illuminate\Support\Str::uuid()];
                if ($replace) {
                    if (preg_match('/[\x00-\x20\x7f]/', $key)) {
                        throw ValidationException::withMessages(['ai_api_key' => 'Kunci tidak boleh memuat spasi atau karakter kontrol.']);
                    }
                    $values['api_key'] = encrypt($key);
                    $values['key_removed'] = false;
                }
                foreach ($values as $name => $value) {
                    $settingKey = 'notifications.whatsapp.ai.'.$name;
                    if ($value === null) {
                        SystemSetting::where('key', $settingKey)->first()?->delete();
                    } else {
                        SystemSetting::updateOrCreate(['key' => $settingKey], ['value' => $value, 'updated_by' => $actor->id]);
                    }
                }
                $after = $configuration->status();
                ActivityLogger::log('whatsapp.ai.settings.saved', before: $before, after: $after, meta: ['key_action' => $remove ? 'removed' : ($replace ? 'replaced' : 'preserved')], actorUserId: $actor->id);
            });

            return app(AiConfiguration::class)->status();
        } finally {
            app(AiConfigurationLock::class)->release($lock);
        }
    }
}
