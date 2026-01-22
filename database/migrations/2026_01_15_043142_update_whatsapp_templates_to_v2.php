<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $key = 'notifications.whatsapp.templates';
        $setting = DB::table('settings')->where('key', $key)->first();

        if ($setting) {
            $value = json_decode($setting->value, true);

            // Ensure value is an array
            if (! is_array($value)) {
                $value = [];
            }

            $newTemplates = [
                'REQUEST_RECEIVED' => '{greetings}, {pangkat} {nama} telah diterima dengan nomor surat {nomor surat} atas nama tersangka {tersangka} berikut {resi} anda. Salam Staff Laboratorium Farmapol Pusdokkes Polri, Salam Presisi',
                'REQUEST_REJECTED' => '{greetings}, {pangkat} {nama} permintaan anda dengan nomor surat {nomor surat} atas nama tersangka {tersangka} ditolak, harap menghubungi kami kembali untuk informasi selanjutnya. Salam Staff Laboratorium Farmapol Pusdokkes Polri, Salam Presisi',
                'READY_FOR_PICKUP' => '{greetings}, {pangkat} {nama} {resi} anda atas nama tersangka {tersangka} sudah dapat diambil. Salam Staff Laboratorium Farmapol Pusdokkes Polri, Salam Presisi',
                'HANDOVER_COMPLETED' => '{greetings}, {pangkat} {nama} {resi} anda atas nama tersangka {tersangka} sudah selesai serah terima. Salam Staff Laboratorium Farmapol Pusdokkes Polri, Salam Presisi',
            ];

            $updatedValue = array_merge($value, $newTemplates);

            DB::table('settings')
                ->where('id', $setting->id)
                ->update(['value' => json_encode($updatedValue)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No operation as we cannot restore unknown previous state reliably.
    }
};
