<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const SETTING_KEY = 'notifications.whatsapp.templates';

    public function up(): void
    {
        $this->replaceTagline('Salam Presisi', 'Polri Bersama Masyarakat');
    }

    public function down(): void
    {
        // Preserve administrator edits made after the forward migration.
    }

    private function replaceTagline(string $from, string $to): void
    {
        $setting = SystemSetting::query()->where('key', self::SETTING_KEY)->first();

        if ($setting === null || ! is_array($setting->value)) {
            return;
        }

        $updatedValue = $this->replaceInValue($setting->value, $from, $to);

        if ($updatedValue !== $setting->value) {
            $setting->value = $updatedValue;
            $setting->save();
        }
    }

    private function replaceInValue(mixed $value, string $from, string $to): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->replaceInValue($item, $from, $to);
            }

            return $value;
        }

        return is_string($value) ? str_replace($from, $to, $value) : $value;
    }
};
