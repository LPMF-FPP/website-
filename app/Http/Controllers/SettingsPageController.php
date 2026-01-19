<?php

namespace App\Http\Controllers;

use App\Services\Settings\SettingsResponseBuilder;
use Illuminate\Support\Facades\Gate;

class SettingsPageController extends Controller
{
    public function __construct(
        protected SettingsResponseBuilder $builder
    ) {}

    public function index()
    {
        Gate::authorize('manage-settings');

        $settings = $this->builder->build();

        $options = $this->builder->getOptions();

        $templates = $settings['templates']['list'] ?? [];

        return view('settings.index', [
            'settings' => $settings,
            'options' => $options,
            'templates' => $templates,
        ]);
    }
}
