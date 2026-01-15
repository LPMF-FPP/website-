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

        $formalTemplates = [
            'REQUEST_RECEIVED' => "{greetings}, {pangkat} {nama}.\n\nKami informasikan bahwa permintaan Anda dengan:\n📄 Nomor Surat: {nomor surat}\n👤 Tersangka: {tersangka}\n🔖 Kode Resi: {resi}\n\ntelah kami terima dan segera kami tindak lanjuti. ✅\n\nTerima kasih atas kepercayaan Anda.\n\nSalam Presisi 🙏\nStaff Laboratorium Farmapol Pusdokkes Polri",
            'REQUEST_REJECTED' => "{greetings}, {pangkat} {nama}.\n\nMohon maaf, permintaan Anda dengan:\n🔖 Kode Resi: {resi}\n👤 Tersangka: {tersangka}\n\nbelum dapat kami proses dan dinyatakan ditolak. ❌\n\nSilakan hubungi kami untuk informasi lebih lanjut.\n\nSalam Presisi 🙏\nStaff Laboratorium Farmapol Pusdokkes Polri",
            'READY_FOR_PICKUP' => "{greetings}, {pangkat} {nama}.\n\nKami informasikan bahwa dokumen Anda dengan:\n🔖 Kode Resi: {resi}\n👤 Tersangka: {tersangka}\n\nsudah selesai diproses dan siap untuk diambil. 📦\n\nSilakan datang ke Laboratorium Farmapol Pusdokkes Polri pada jam kerja.\n\nSalam Presisi 🙏\nStaff Laboratorium Farmapol Pusdokkes Polri",
            'HANDOVER_COMPLETED' => "{greetings}, {pangkat} {nama}.\n\nKami informasikan bahwa dokumen Anda dengan:\n🔖 Kode Resi: {resi}\n👤 Tersangka: {tersangka}\n\ntelah selesai diserahterimakan. ✅\n\nTerima kasih atas kepercayaan Anda menggunakan layanan kami.\n\nSalam Presisi 🙏\nStaff Laboratorium Farmapol Pusdokkes Polri",
        ];

        SystemSetting::updateOrCreate(
            ['key' => $key],
            ['value' => array_merge($templates, $formalTemplates)]
        );
    }

    public function down(): void
    {
        // No-op: previous template values are not reliably recoverable.
    }
};
