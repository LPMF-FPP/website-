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
            ['value' => null]
        );

        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.basic_pass'],
            ['value' => null]
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
        ])->delete();

        settings_forget_cache();
    }
};
