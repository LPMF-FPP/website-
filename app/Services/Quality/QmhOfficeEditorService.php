<?php

namespace App\Services\Quality;

use App\Models\QmhDocumentRevision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class QmhOfficeEditorService
{
    /**
     * @return array{revision_id: int, token: string, editor_url: string, config: array<string, mixed>}
     */
    public function createSession(QmhDocumentRevision $revision, User $actor): array
    {
        $revision->loadMissing(['document', 'lock']);

        if ($revision->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Sesi Office hanya tersedia untuk revisi draft.',
            ]);
        }

        $lock = $revision->lock;
        if ($lock === null || ! $lock->isActive() || (int) $lock->locked_by !== (int) $actor->id) {
            throw new AuthorizationException('Sesi Office hanya dapat dibuka oleh pemilik lock aktif.');
        }

        $editorUrl = trim((string) config('quality.office.editor_url', ''));
        if ($editorUrl === '') {
            throw new ServiceUnavailableHttpException(null, 'Server Office belum dikonfigurasi. Hubungi administrator.');
        }

        $token = $this->encodeToken([
            'iss' => 'qmh-office',
            'revision_id' => $revision->id,
            'user_id' => $actor->id,
            'iat' => time(),
            'exp' => time() + max(60, (int) config('quality.office.token_ttl_seconds', 3600)),
        ]);

        $config = [
            'document' => [
                'fileType' => 'docx',
                'key' => sprintf('qmh-%d-v%d', $revision->id, (int) $revision->source_docx_version),
                'title' => sprintf('%s-%s.docx', $revision->document?->doc_code ?? 'QMH', $revision->version_label),
            ],
            'editorConfig' => [
                'callbackUrl' => url(sprintf('/api/quality/revisions/%d/office-callback', $revision->id)),
                'mode' => 'edit',
                'user' => [
                    'id' => (string) $actor->id,
                    'name' => $actor->name,
                ],
                'customization' => [
                    'forcesave' => true,
                ],
            ],
        ];

        return [
            'revision_id' => (int) $revision->id,
            'token' => $token,
            'editor_url' => $editorUrl,
            'config' => $config,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{saved: bool, status: int|string|null, source_docx_version: int}
     */
    public function handleCallback(QmhDocumentRevision $revision, array $payload, ?string $callbackHost): array
    {
        $this->assertHostAllowed($callbackHost);

        $token = Arr::get($payload, 'token');
        if (! is_string($token) || trim($token) === '') {
            throw new UnauthorizedHttpException('Bearer', 'Token callback Office wajib diisi.');
        }

        $claims = $this->decodeToken($token);
        if ((int) ($claims['revision_id'] ?? 0) !== (int) $revision->id) {
            throw new UnauthorizedHttpException('Bearer', 'Token callback tidak cocok dengan revision target.');
        }

        $status = Arr::get($payload, 'status');
        $shouldSave = in_array((int) $status, [2, 6], true);

        if (! $shouldSave) {
            return [
                'saved' => false,
                'status' => $status,
                'source_docx_version' => (int) $revision->source_docx_version,
            ];
        }

        return DB::transaction(function () use ($revision, $payload, $status) {
            $revision->refresh();

            $revision->source_docx_version = max(1, (int) $revision->source_docx_version) + 1;
            $revision->last_autosaved_at = now();

            if (is_string(Arr::get($payload, 'content_html'))) {
                $revision->content_html = Arr::get($payload, 'content_html');
            }

            if (is_array(Arr::get($payload, 'editor_json'))) {
                $revision->editor_json = Arr::get($payload, 'editor_json');
            }

            $revision->save();

            return [
                'saved' => true,
                'status' => $status,
                'source_docx_version' => (int) $revision->source_docx_version,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function encodeToken(array $claims): string
    {
        $header = $this->base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode((string) json_encode($claims));
        $signature = hash_hmac('sha256', $header.'.'.$payload, $this->jwtSecret(), true);

        return $header.'.'.$payload.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new UnauthorizedHttpException('Bearer', 'Format token callback tidak valid.');
        }

        [$header, $payload, $signature] = $parts;
        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $header.'.'.$payload, $this->jwtSecret(), true));

        if (! hash_equals($expectedSignature, $signature)) {
            throw new UnauthorizedHttpException('Bearer', 'Signature callback Office tidak valid.');
        }

        $claims = json_decode($this->base64UrlDecode($payload), true);
        if (! is_array($claims)) {
            throw new UnauthorizedHttpException('Bearer', 'Payload token callback tidak valid.');
        }

        if ((int) ($claims['exp'] ?? 0) < time()) {
            throw new UnauthorizedHttpException('Bearer', 'Token callback Office sudah kedaluwarsa.');
        }

        return $claims;
    }

    private function assertHostAllowed(?string $callbackHost): void
    {
        $allowedHosts = config('quality.office.callback_hosts', []);
        if (! is_array($allowedHosts) || $allowedHosts === []) {
            return;
        }

        if ($callbackHost === null || $callbackHost === '' || ! in_array($callbackHost, $allowedHosts, true)) {
            throw new AccessDeniedHttpException('Host callback Office tidak diizinkan.');
        }
    }

    private function jwtSecret(): string
    {
        return (string) config('quality.office.jwt_secret', 'qmh-office-secret');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padded = str_pad(strtr($value, '-_', '+/'), strlen($value) % 4 === 0 ? strlen($value) : strlen($value) + 4 - strlen($value) % 4, '=', STR_PAD_RIGHT);

        $decoded = base64_decode($padded, true);
        if ($decoded === false) {
            throw new UnauthorizedHttpException('Bearer', 'Payload token callback gagal didekode.');
        }

        return $decoded;
    }
}
