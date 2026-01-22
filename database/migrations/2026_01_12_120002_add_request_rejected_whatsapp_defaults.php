<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $enabled = SystemSetting::where('key', 'notifications.whatsapp.enabled_milestones')->value('value');
        $enabled = is_array($enabled) ? $enabled : [];

        if (! in_array('REQUEST_REJECTED', $enabled, true)) {
            $enabled[] = 'REQUEST_REJECTED';
            SystemSetting::updateOrCreate(
                ['key' => 'notifications.whatsapp.enabled_milestones'],
                ['value' => $enabled]
            );
        }

        $templates = SystemSetting::where('key', 'notifications.whatsapp.templates')->value('value');
        $templates = is_array($templates) ? $templates : [];

        if (! array_key_exists('REQUEST_REJECTED', $templates)) {
            $templates['REQUEST_REJECTED'] = '{greetings}, {pangkat} {nama} 👋. Mohon maaf, permintaan Anda nomor {nomor surat} atas nama tersangka {tersangka} belum dapat kami proses dan dinyatakan ditolak ❌. Silakan menghubungi kami kembali untuk informasi selanjutnya. Salam Staff Laboratorium Farmapol Pusdokkes Polri. Salam Presisi 🙏';
            SystemSetting::updateOrCreate(
                ['key' => 'notifications.whatsapp.templates'],
                ['value' => $templates]
            );
        }

        if (function_exists('settings_forget_cache')) {
            settings_forget_cache();
        }
    }

    public function down(): void
    {
        $enabled = SystemSetting::where('key', 'notifications.whatsapp.enabled_milestones')->value('value');
        $enabled = is_array($enabled) ? $enabled : [];
        $enabled = array_values(array_filter($enabled, fn ($key) => $key !== 'REQUEST_REJECTED'));

        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.enabled_milestones'],
            ['value' => $enabled]
        );

        $templates = SystemSetting::where('key', 'notifications.whatsapp.templates')->value('value');
        $templates = is_array($templates) ? $templates : [];
        unset($templates['REQUEST_REJECTED']);

        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.templates'],
            ['value' => $templates]
        );

        if (function_exists('settings_forget_cache')) {
            settings_forget_cache();
        }
    }
};
