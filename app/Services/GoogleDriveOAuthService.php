<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserGoogleDriveToken;
use Google\Client;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleDriveOAuthService
{
    public function authorizationUrl(): string
    {
        $state = Str::random(40);
        session(['google_drive_oauth_state' => $state]);

        $client = $this->oauthClient();
        $client->setState($state);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);

        return $client->createAuthUrl();
    }

    public function storeCallbackToken(User $user, string $code): UserGoogleDriveToken
    {
        $token = $this->oauthClient()->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            $description = is_string($token['error_description'] ?? null) ? $token['error_description'] : $token['error'];

            throw new RuntimeException('Google Drive OAuth failed: '.$description);
        }

        if (! is_string($token['access_token'] ?? null) || $token['access_token'] === '') {
            throw new RuntimeException('Google Drive OAuth did not return an access token.');
        }

        $existingRefreshToken = $user->googleDriveToken?->refresh_token;
        $refreshToken = is_string($token['refresh_token'] ?? null) ? $token['refresh_token'] : $existingRefreshToken;

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new RuntimeException('Google Drive OAuth did not return a refresh token. Revoke app access in Google Account, then connect again.');
        }

        return UserGoogleDriveToken::updateOrCreate(
            ['user_id' => $user->id],
            [
                'access_token' => $token['access_token'],
                'refresh_token' => $refreshToken,
                'expires_at' => now()->addSeconds(max(1, (int) ($token['expires_in'] ?? 3600)))->subMinute(),
                'scopes' => isset($token['scope']) && is_string($token['scope']) ? explode(' ', $token['scope']) : null,
            ]
        );
    }

    public function accessTokenFor(User $user): string
    {
        $driveToken = $user->googleDriveToken;
        if (! $driveToken || ! $driveToken->refresh_token) {
            throw new RuntimeException('Akun Google Drive belum terhubung. Hubungkan Google Drive dari halaman profil.');
        }

        if ($driveToken->access_token && $driveToken->expires_at?->isFuture()) {
            return $driveToken->access_token;
        }

        $token = $this->oauthClient()->fetchAccessTokenWithRefreshToken($driveToken->refresh_token);

        if (isset($token['error'])) {
            $description = is_string($token['error_description'] ?? null) ? $token['error_description'] : $token['error'];

            throw new RuntimeException('Google Drive token refresh failed: '.$description);
        }

        if (! is_string($token['access_token'] ?? null) || $token['access_token'] === '') {
            throw new RuntimeException('Google Drive token refresh did not return an access token.');
        }

        $driveToken->forceFill([
            'access_token' => $token['access_token'],
            'expires_at' => now()->addSeconds(max(1, (int) ($token['expires_in'] ?? 3600)))->subMinute(),
            'scopes' => isset($token['scope']) && is_string($token['scope']) ? explode(' ', $token['scope']) : $driveToken->scopes,
        ])->save();

        return $token['access_token'];
    }

    private function oauthClient(): Client
    {
        $clientId = $this->configString('google-drive.auth_client_id', 'GOOGLE_DRIVE_AUTH_CLIENT_ID');
        $clientSecret = $this->configString('google-drive.auth_client_secret', 'GOOGLE_DRIVE_AUTH_CLIENT_SECRET');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Set GOOGLE_DRIVE_AUTH_CLIENT_ID and GOOGLE_DRIVE_AUTH_CLIENT_SECRET before connecting Google Drive.');
        }

        $client = new Client;
        $client->setApplicationName((string) config('google-drive.application_name', 'LPMF LIMS'));
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($this->redirectUri());
        $client->setScopes([(string) config('google-drive.scope', 'https://www.googleapis.com/auth/drive.file')]);

        return $client;
    }

    private function redirectUri(): string
    {
        $redirectUri = $this->configString('google-drive.auth_redirect_uri', 'GOOGLE_DRIVE_AUTH_REDIRECT_URI');

        return $redirectUri !== '' ? $redirectUri : route('google-drive.callback');
    }

    private function configString(string $configKey, string $envKey): string
    {
        $value = config($configKey);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $value = env($envKey);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $this->envFileValue($envKey);
    }

    private function envFileValue(string $key): string
    {
        $envPath = base_path('.env');
        if (! is_readable($envPath)) {
            return '';
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return '';
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_starts_with($line, $key.'=')) {
                continue;
            }

            return trim(substr($line, strlen($key) + 1), " \t\n\r\0\x0B\"'");
        }

        return '';
    }
}
