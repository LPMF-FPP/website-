<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $supported = ['id','en'];
        if (!in_array($locale, $supported, true)) {
            $locale = config('app.locale');
        }
        session(['app_locale' => $locale]);
        return back();
    }
}
