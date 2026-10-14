<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
        'updated_by',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    protected static function booted(): void
    {
        $clearCache = static function (SystemSetting $setting): void {
            if (str_starts_with($setting->key, 'notifications.whatsapp.ai.') && DB::transactionLevel() > 0) {
                DB::afterCommit(static fn () => settings_forget_cache());

                return;
            }
            if (function_exists('settings_forget_cache')) {
                settings_forget_cache();

                return;
            }

            cache()->forget('sys_settings_all');
        };

        static::saved($clearCache);
        static::deleted($clearCache);
    }
}
