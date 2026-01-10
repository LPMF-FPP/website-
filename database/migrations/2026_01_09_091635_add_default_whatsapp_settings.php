<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.enabled'],
            ['value' => false]
        );

        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.base_url'],
            ['value' => 'http://localhost:3000']
        );

        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.basic_user'],
            ['value' => '']
        );

        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.basic_pass'],
            ['value' => '']
        );

        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.enabled_milestones'],
            ['value' => [
                'REQUEST_RECEIVED',
                'REVIEW_DONE_READY_FOR_TEST',
                'PREPARATION_DONE',
                'INSTRUMENTATION_DONE',
                'INTERPRETATION_DONE',
                'READY_FOR_PICKUP',
                'HANDOVER_COMPLETED',
            ]]
        );

        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.templates'],
            ['value' => [
                'REQUEST_RECEIVED' => 'Permintaan Anda telah diterima. Resi: {resi}.',
                'REVIEW_DONE_READY_FOR_TEST' => 'Permintaan {resi} telah selesai dikaji ulang dan siap dilakukan pengujian.',
                'PREPARATION_DONE' => 'Permintaan {resi} telah selesai dipreparasi sampel.',
                'INSTRUMENTATION_DONE' => 'Permintaan {resi} telah selesai diuji instrumen.',
                'INTERPRETATION_DONE' => 'Permintaan {resi} telah selesai dilakukan interpretasi hasil.',
                'READY_FOR_PICKUP' => 'Permintaan {resi} siap diambil.',
                'HANDOVER_COMPLETED' => 'Permintaan {resi} telah diambil dan serah terima telah dicatat.',
            ]]
        );

        settings_forget_cache();
    }

    public function down(): void
    {
        SystemSetting::whereIn('key', [
            'notifications.whatsapp.enabled',
            'notifications.whatsapp.base_url',
            'notifications.whatsapp.basic_user',
            'notifications.whatsapp.basic_pass',
            'notifications.whatsapp.enabled_milestones',
            'notifications.whatsapp.templates',
        ])->delete();

        settings_forget_cache();
    }
};
