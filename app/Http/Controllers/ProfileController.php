<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\GoogleDriveOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, GoogleDriveOAuthService $googleDriveOAuth): View
    {
        $user = $request->user();

        return view('profile.edit', [
            'googleDriveConnectionStatus' => $this->googleDriveConnectionStatus($user, $googleDriveOAuth),
            'user' => $user,
        ]);
    }

    private function googleDriveConnectionStatus($user, GoogleDriveOAuthService $googleDriveOAuth): array
    {
        if (! $user?->googleDriveToken) {
            return [
                'connected' => false,
                'status' => 'missing',
                'message' => 'Google Drive belum terhubung.',
            ];
        }

        try {
            $googleDriveOAuth->accessTokenFor($user);

            return [
                'connected' => true,
                'status' => 'ok',
                'message' => 'Google Drive terhubung dan token masih dapat diperbarui.',
            ];
        } catch (Throwable $exception) {
            return [
                'connected' => true,
                'status' => 'invalid',
                'message' => $this->googleDriveTokenFailureMessage($exception->getMessage()),
            ];
        }
    }

    private function googleDriveTokenFailureMessage(string $reason): string
    {
        if (str_contains(strtolower($reason), 'expired or revoked')) {
            return 'Token Google Drive sudah tidak valid atau dicabut oleh Google. Putuskan Google Drive, lalu hubungkan kembali agar sinkronisasi dokumen dapat berjalan lagi.';
        }

        return 'Token Google Drive tidak dapat diverifikasi: '.$reason;
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
