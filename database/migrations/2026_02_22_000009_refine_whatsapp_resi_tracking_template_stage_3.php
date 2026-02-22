<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $resiTrackingTemplate = "📋 *PELACAKAN RESI PERMINTAAN*\n\n🔖 *Kode Resi:* {resi}\n📄 *Nomor Permintaan:* {request_number}\n👮 *Penyidik:* {investigator}\n📦 *Jumlah Sampel:* {sample_count}\n\n🧭 *Tahapan Proses (1-5)*\n{milestones}\n\n📌 *Status Terkini*\n{current_status}\n\nKeterangan: ✅ selesai | 🟡 sedang berjalan | ⚪️ menunggu\n─────────────────\nℹ️ Cek ulang kapan saja dengan ketik:\n*/resi {resi}*";

        $setting = SystemSetting::query()
            ->where('key', 'notifications.whatsapp.command_templates')
            ->first();

        $templates = is_array($setting?->value) ? $setting->value : [];
        $templates['RESI_TRACKING'] = $resiTrackingTemplate;

        SystemSetting::query()->updateOrCreate(
            ['key' => 'notifications.whatsapp.command_templates'],
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
