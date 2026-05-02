<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GoogleDriveOAuthService;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Throwable;

class GoogleDriveSmokeCommand extends Command
{
    protected $signature = 'lims:google-drive-smoke {--name= : Custom filename for the smoke-test upload} {--delete : Delete the uploaded smoke-test file after verification} {--user= : Upload using this user OAuth token instead of service account credentials}';

    protected $description = 'Upload a small smoke-test file to Google Drive using dev credentials.';

    public function handle(GoogleDriveService $googleDrive, GoogleDriveOAuthService $googleDriveOAuth): int
    {
        $name = $this->option('name') ?: 'lims-google-drive-smoke-'.now()->format('Ymd-His').'.txt';
        try {
            $user = $this->oauthUser();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $accessToken = null;

        if ($user) {
            try {
                $accessToken = $googleDriveOAuth->accessTokenFor($user);
            } catch (Throwable $exception) {
                $this->error('Google Drive OAuth token failed: '.$exception->getMessage());

                return self::FAILURE;
            }
        }

        if (! $user && ! config('google-drive.folder_id')) {
            $this->warn('GOOGLE_DRIVE_FOLDER_ID is empty; the file will be created in the service account default Drive location.');
        }

        try {
            $contents = 'LPMF LIMS Google Drive smoke test generated at '.now()->toIso8601String().PHP_EOL;
            $file = $accessToken
                ? $googleDrive->uploadWithAccessToken($accessToken, (string) $name, $contents)
                : $googleDrive->upload((string) $name, $contents);
        } catch (Throwable $exception) {
            $this->error('Google Drive smoke test failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Google Drive upload succeeded.');
        $this->line('File ID: '.$file['id']);
        $this->line('Name: '.($file['name'] ?? (string) $name));

        if (isset($file['webViewLink'])) {
            $this->line('View URL: '.$file['webViewLink']);
        }

        if ($this->option('delete')) {
            try {
                if ($accessToken) {
                    $googleDrive->deleteWithAccessToken($accessToken, $file['id']);
                } else {
                    $googleDrive->delete($file['id']);
                }
                $this->info('Smoke-test file deleted.');
            } catch (Throwable $exception) {
                $this->warn('Upload succeeded, but cleanup failed: '.$exception->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function oauthUser(): ?User
    {
        $userId = $this->option('user');
        if (! $userId) {
            return null;
        }

        $user = User::find($userId);
        if (! $user) {
            throw new \RuntimeException('User ID '.$userId.' tidak ditemukan untuk Google Drive OAuth smoke test.');
        }

        return $user;
    }
}
