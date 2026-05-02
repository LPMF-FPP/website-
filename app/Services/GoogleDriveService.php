<?php

declare(strict_types=1);

namespace App\Services;

use Google\Client;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveService
{
    public const FOLDER_MIME_TYPE = 'application/vnd.google-apps.folder';

    /**
     * @return array{id:string,name?:string,mimeType?:string,size?:string,webViewLink?:string,webContentLink?:string}
     */
    public function upload(string $name, string $contents, string $mimeType = 'text/plain', ?string $folderId = null): array
    {
        return $this->uploadWithAccessToken($this->serviceAccountAccessToken(), $name, $contents, $mimeType, $folderId);
    }

    /**
     * @return array{id:string,name?:string,mimeType?:string,size?:string,webViewLink?:string,webContentLink?:string}
     */
    public function uploadWithAccessToken(string $accessToken, string $name, string $contents, string $mimeType = 'text/plain', ?string $folderId = null): array
    {
        $metadata = [
            'name' => $name,
            'mimeType' => $mimeType,
        ];

        $parentFolderId = $folderId ?: config('google-drive.folder_id');
        if (is_string($parentFolderId) && $parentFolderId !== '') {
            $metadata['parents'] = [$parentFolderId];
        }

        $boundary = 'lims-drive-'.bin2hex(random_bytes(16));
        $body = $this->multipartRelatedBody($boundary, $metadata, $contents, $mimeType);

        $response = Http::withToken($accessToken)
            ->withQueryParameters([
                'uploadType' => 'multipart',
                'supportsAllDrives' => (bool) config('google-drive.supports_all_drives', false),
                'fields' => 'id,name,mimeType,size,webViewLink,webContentLink',
            ])
            ->withHeaders(['Content-Type' => 'multipart/related; boundary='.$boundary])
            ->withBody($body, 'multipart/related; boundary='.$boundary)
            ->post($this->apiBaseUrl().'/upload/drive/v3/files');

        $this->throwForDriveError($response);

        /** @var array{id:string,name?:string,mimeType?:string,size?:string,webViewLink?:string,webContentLink?:string} $file */
        $file = $response->json();

        return $file;
    }

    public function delete(string $fileId): void
    {
        $this->deleteWithAccessToken($this->serviceAccountAccessToken(), $fileId);
    }

    public function deleteWithAccessToken(string $accessToken, string $fileId): void
    {
        $response = Http::withToken($accessToken)
            ->withQueryParameters([
                'supportsAllDrives' => (bool) config('google-drive.supports_all_drives', false),
            ])
            ->delete($this->apiBaseUrl().'/drive/v3/files/'.rawurlencode($fileId));

        $this->throwForDriveError($response);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{id:string,name?:string,mimeType?:string}
     */
    public function createFolderWithAccessToken(string $accessToken, array $metadata): array
    {
        $metadata['mimeType'] = self::FOLDER_MIME_TYPE;

        $response = Http::withToken($accessToken)
            ->withQueryParameters([
                'supportsAllDrives' => (bool) config('google-drive.supports_all_drives', false),
                'fields' => 'id,name,mimeType',
            ])
            ->post($this->apiBaseUrl().'/drive/v3/files', $metadata);

        $this->throwForDriveError($response);

        /** @var array{id:string,name?:string,mimeType?:string} $folder */
        $folder = $response->json();

        return $folder;
    }

    /**
     * @return array<int, array{id:string,name?:string,mimeType?:string}>
     */
    public function findFoldersWithAccessToken(string $accessToken, string $name, ?string $parentId = null): array
    {
        $query = "mimeType = '".self::FOLDER_MIME_TYPE."' and trashed = false and name = '".$this->escapeQueryString($name)."'";

        if ($parentId) {
            $query .= " and '".$this->escapeQueryString($parentId)."' in parents";
        }

        $response = Http::withToken($accessToken)
            ->get($this->apiBaseUrl().'/drive/v3/files', [
                'q' => $query,
                'spaces' => 'drive',
                'supportsAllDrives' => (bool) config('google-drive.supports_all_drives', false),
                'includeItemsFromAllDrives' => (bool) config('google-drive.supports_all_drives', false),
                'fields' => 'files(id,name,mimeType)',
                'pageSize' => 10,
            ]);

        $this->throwForDriveError($response);

        /** @var array<int, array{id:string,name?:string,mimeType?:string}> $folders */
        $folders = $response->json('files') ?? [];

        return $folders;
    }

    private function serviceAccountAccessToken(): string
    {
        $token = $this->client()->fetchAccessTokenWithAssertion();

        if (isset($token['error'])) {
            $description = is_string($token['error_description'] ?? null) ? $token['error_description'] : $token['error'];

            throw new RuntimeException('Google Drive authentication failed: '.$description);
        }

        if (! is_string($token['access_token'] ?? null) || $token['access_token'] === '') {
            throw new RuntimeException('Google Drive authentication did not return an access token.');
        }

        return $token['access_token'];
    }

    private function client(): Client
    {
        $client = new Client;
        $client->setApplicationName((string) config('google-drive.application_name', 'LPMF LIMS'));
        $client->setScopes([(string) config('google-drive.scope', 'https://www.googleapis.com/auth/drive.file')]);

        $credentialsPath = config('google-drive.credentials_path');
        if (is_string($credentialsPath) && $credentialsPath !== '') {
            if (! is_file($credentialsPath)) {
                throw new RuntimeException('Google Drive credentials file was not found. Check GOOGLE_DRIVE_CREDENTIALS_PATH.');
            }

            $client->setAuthConfig($credentialsPath);
        } else {
            $applicationCredentials = getenv('GOOGLE_APPLICATION_CREDENTIALS');
            if (! is_string($applicationCredentials) || $applicationCredentials === '') {
                throw new RuntimeException('Set GOOGLE_DRIVE_CREDENTIALS_PATH or GOOGLE_APPLICATION_CREDENTIALS before using Google Drive.');
            }

            $client->useApplicationDefaultCredentials();
        }

        $impersonateUser = config('google-drive.impersonate_user');
        if (is_string($impersonateUser) && $impersonateUser !== '') {
            $client->setSubject($impersonateUser);
        }

        return $client;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function multipartRelatedBody(string $boundary, array $metadata, string $contents, string $mimeType): string
    {
        $metadataJson = json_encode($metadata, JSON_THROW_ON_ERROR);

        return implode("\r\n", [
            '--'.$boundary,
            'Content-Type: application/json; charset=UTF-8',
            '',
            $metadataJson,
            '--'.$boundary,
            'Content-Type: '.$mimeType,
            '',
            $contents,
            '--'.$boundary.'--',
            '',
        ]);
    }

    private function apiBaseUrl(): string
    {
        return rtrim((string) config('google-drive.api_base_url', 'https://www.googleapis.com'), '/');
    }

    private function throwForDriveError(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('error.message');
        if (! is_string($message) || $message === '') {
            $message = $response->body();
        }

        throw new RuntimeException('Google Drive REST API request failed with HTTP '.$response->status().': '.$message);
    }

    private function escapeQueryString(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
