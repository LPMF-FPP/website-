<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GoogleDriveOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class GoogleDriveOAuthController extends Controller
{
    public function connect(GoogleDriveOAuthService $googleDriveOAuth): RedirectResponse
    {
        try {
            return redirect()->away($googleDriveOAuth->authorizationUrl());
        } catch (RuntimeException $exception) {
            return redirect()->route('profile.edit')->with('google_drive_error', $exception->getMessage());
        }
    }

    public function callback(Request $request, GoogleDriveOAuthService $googleDriveOAuth): RedirectResponse
    {
        $request->validate([
            'code' => ['nullable', 'string'],
            'state' => ['required', 'string'],
            'error' => ['nullable', 'string'],
        ]);

        if ($request->filled('error')) {
            return redirect()->route('profile.edit')->with('google_drive_error', 'Google Drive authorization was cancelled or denied.');
        }

        $expectedState = session('google_drive_oauth_state');
        $providedState = $request->query('state');

        if (! is_string($expectedState) || $expectedState === '' || ! is_string($providedState) || $providedState === '' || ! hash_equals($expectedState, $providedState)) {
            return redirect()->route('profile.edit')->with('google_drive_error', 'Google Drive OAuth state is invalid. Please try again.');
        }

        if (! $request->filled('code')) {
            return redirect()->route('profile.edit')->with('google_drive_error', 'Google Drive OAuth callback did not include an authorization code.');
        }

        try {
            $googleDriveOAuth->storeCallbackToken($request->user(), (string) $request->query('code'));
        } catch (RuntimeException $exception) {
            return redirect()->route('profile.edit')->with('google_drive_error', $exception->getMessage());
        } finally {
            $request->session()->forget('google_drive_oauth_state');
        }

        return redirect()->route('profile.edit')->with('google_drive_status', 'Google Drive berhasil terhubung.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->user()->googleDriveToken()?->delete();

        return redirect()->route('profile.edit')->with('google_drive_status', 'Google Drive berhasil diputuskan.');
    }
}
