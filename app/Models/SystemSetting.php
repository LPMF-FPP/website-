<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        $clearCache = static function (): void {
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
