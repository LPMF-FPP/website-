<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $requestReceivedTemplate = "{greetings}, {pangkat} {nama}.\n\nKami informasikan bahwa permintaan Anda dengan:\n📄 Nomor Surat: {nomor surat}\n👤 Tersangka: {tersangka}\n🔖 Kode Resi: {resi}\n\ntelah kami terima dan segera kami tindak lanjuti. ✅\n\nApabila ingin melacak bisa mengetikan /resi {resi}\n\nTerima kasih atas kepercayaan Anda.\n\nSalam Presisi 🙏\nStaff Laboratorium Farmapol Pusdokkes Polri";

        $setting = SystemSetting::query()
            ->where('key', 'notifications.whatsapp.templates')
            ->first();

        $templates = is_array($setting?->value) ? $setting->value : [];
        $templates['REQUEST_RECEIVED'] = $requestReceivedTemplate;

        SystemSetting::query()->updateOrCreate(
            ['key' => 'notifications.whatsapp.templates'],
            ['value' => $templates]
        );

        if (function_exists('settings_forget_cache')) {
            settings_forget_cache();
        }
    }

    public function down(): void
    {
        // No-op: preserve configured production template state.
    }
};
