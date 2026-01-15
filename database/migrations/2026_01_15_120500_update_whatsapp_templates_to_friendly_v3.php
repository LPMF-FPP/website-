<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $key = 'notifications.whatsapp.templates';
        $templates = SystemSetting::where('key', $key)->value('value');
        $templates = is_array($templates) ? $templates : [];

        $friendlyTemplates = [
            'REQUEST_RECEIVED' => '{greetings}, {pangkat} {nama} 👋. Kami informasikan bahwa surat permintaan Anda nomor {nomor surat} atas nama tersangka {tersangka} sudah kami terima ✅. Kode resi Anda: {resi}. Salam Staff Laboratorium Farmapol Pusdokkes Polri. Salam Presisi 🙏',
            'REQUEST_REJECTED' => '{greetings}, {pangkat} {nama} 👋. Mohon maaf, permintaan Anda nomor {nomor surat} atas nama tersangka {tersangka} belum dapat kami proses dan dinyatakan ditolak ❌. Silakan menghubungi kami kembali untuk informasi selanjutnya. Salam Staff Laboratorium Farmapol Pusdokkes Polri. Salam Presisi 🙏',
            'READY_FOR_PICKUP' => '{greetings}, {pangkat} {nama} 👋. Dokumen dengan kode resi {resi} atas nama tersangka {tersangka} sudah dapat diambil 📦. Salam Staff Laboratorium Farmapol Pusdokkes Polri. Salam Presisi 🙏',
            'HANDOVER_COMPLETED' => '{greetings}, {pangkat} {nama} 👋. Dokumen dengan kode resi {resi} atas nama tersangka {tersangka} telah selesai serah terima 🤝✅. Salam Staff Laboratorium Farmapol Pusdokkes Polri. Salam Presisi 🙏',
        ];

        SystemSetting::updateOrCreate(
            ['key' => $key],
            ['value' => array_merge($templates, $friendlyTemplates)]
        );
    }

    public function down(): void
    {
        // No-op: previous template values are not reliably recoverable.
    }
};
