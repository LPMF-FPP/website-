<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Gate;

class SettingsPageController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-settings');

        $flat = SystemSetting::query()
            ->get()
            ->mapWithKeys(fn (SystemSetting $row) => [$row->key => $row->value])
            ->toArray();

        $settings = settings_nest($flat);

        $options = [
            'timezones' => ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'],
            'date_formats' => ['DD/MM/YYYY', 'YYYY-MM-DD', 'DD-MM-YYYY'],
            'number_formats' => ['1.234,56', '1,234.56'],
            'languages' => ['id', 'en'],
            'storage_drivers' => ['local', 's3'],
        ];

        $templates = DocumentTemplate::orderBy('name')->get();

        return view('settings.index', [
            'settings' => $settings,
            'options' => $options,
            'templates' => $templates,
        ]);
    }
}
