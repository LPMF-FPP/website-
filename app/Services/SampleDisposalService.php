<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SampleDisposalMethod;
use App\Http\Requests\SampleDisposalRequest;
use App\Models\Sample;
use App\Models\SampleDisposal;
use App\Models\SampleTestProcess;
use App\Models\SystemSetting;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SampleDisposalService
{
    public function __construct(
        private readonly SampleDisposalEligibilityService $eligibility,
        private readonly PermissionService $permissions,
        private readonly SampleDisposalFileService $files,
    ) {}

    public function preview(User $actor, array $ids): array
    {
        $this->authorizeRead($actor, true);
        Validator::make(['ids' => $ids], ['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'min:1', 'distinct']])->validate();
        $ids = array_map('intval', $ids);
        sort($ids);
        $samples = $this->eligibility->query()->whereIn('id', $ids)->orderBy('id')->get();
        if ($samples->count() !== count($ids)) {
            throw ValidationException::withMessages(['sample_ids' => 'Beberapa sampel tidak lagi memenuhi syarat pemusnahan. Pilih lagi dari daftar terbaru.']);
        }
        $snapshot = [
            'version' => 1, 'review_id' => (string) Str::uuid(), 'actor_id' => $actor->id,
            'reviewed_at' => now()->toIso8601String(), 'retention_days' => Sample::disposalRetentionDays(),
            'timezone' => config('app.timezone'),
            'samples' => $samples->map(fn ($sample) => $this->eligibility->projection($sample))->all(),
        ];

        return ['samples' => $samples, 'manifest' => $snapshot, 'review_token' => Crypt::encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR))];
    }

    public function authorizeRead(User $actor, bool $execute = false): void
    {
        if (! $actor->is_active || ! $actor->hasPermission('sample-disposal.view')
            || ($execute && ! $actor->hasPermission('sample-disposal.execute'))
            || (auth()->check() && auth()->id() !== $actor->id)) {
            throw new AuthorizationException('Anda tidak memiliki izin pemusnahan sampel untuk tindakan ini.');
        }
    }

    public function execute(User $actor, array $input): SampleDisposal
    {
        $this->authorizeRead($actor, true);
        $data = Validator::make($input, SampleDisposalRequest::executionRules())->validate();
        try {
            $snapshot = json_decode(Crypt::decryptString($data['review_token']), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw ValidationException::withMessages(['review_token' => 'Preview tidak valid. Buka kembali batch dan tinjau ulang.']);
        }
        if (! is_array($snapshot) || ($snapshot['version'] ?? null) !== 1 || ($snapshot['actor_id'] ?? null) !== $actor->id
            || ! Str::isUuid($snapshot['review_id'] ?? '') || empty($snapshot['samples'])) {
            throw ValidationException::withMessages(['review_token' => 'Preview bukan milik pengguna ini atau tidak valid.']);
        }
        $ids = array_map('intval', $data['sample_ids']);
        sort($ids);
        if ($ids !== array_column($snapshot['samples'], 'sample_id')) {
            throw ValidationException::withMessages(['sample_ids' => 'Pilihan berbeda dari manifest yang ditinjau. Buka preview baru.']);
        }
        $fingerprintData = $data;
        unset($fingerprintData['review_token'], $fingerprintData['review_confirmed']);
        $fingerprintData['sample_ids'] = $ids;
        $fingerprintData['documentation_photos'] = array_map(fn ($file) => [
            'sha256' => hash_file('sha256', $file->getRealPath()), 'name' => $file->getClientOriginalName(),
        ], $data['documentation_photos'] ?? []);
        $fingerprint = hash('sha256', json_encode($fingerprintData, JSON_THROW_ON_ERROR));
        $photos = [];
        try {
            $disposal = DB::transaction(function () use ($actor, $data, $snapshot, $ids, $fingerprint, &$photos): SampleDisposal {
                $userIds = collect($data['witnesses'])->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->push($actor->id)->unique()->sort()->values();
                $users = User::query()->whereIn('id', $userIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $executor = $users->get($actor->id);
                if (! $executor?->is_active || ! $this->permissions->userHasPermissionsForMutation($executor, ['sample-disposal.view', 'sample-disposal.execute'])) {
                    throw new AuthorizationException('Izin eksekusi pemusnahan sampel sudah tidak tersedia.');
                }
                $existing = SampleDisposal::query()->where('review_id', $snapshot['review_id'])->first();
                if ($existing) {
                    if ($existing->created_by !== $actor->id || $existing->manifest_snapshot !== $snapshot || $existing->execution_fingerprint !== $fingerprint) {
                        throw ValidationException::withMessages(['review_token' => 'Preview sudah digunakan untuk konteks lain.']);
                    }

                    return $existing;
                }
                $setting = SystemSetting::query()->where('key', 'inventory.disposal_retention_days')->lockForUpdate()->first();
                $days = max(0, (int) ($setting?->value ?? 90));
                if ($days !== $snapshot['retention_days'] || config('app.timezone') !== $snapshot['timezone']) {
                    throw ValidationException::withMessages(['sample_ids' => 'Aturan retensi berubah. Segarkan dan tinjau ulang manifest.']);
                }
                $requestIds = Sample::query()->whereIn('id', $ids)->pluck('test_request_id')->unique()->sort()->values();
                TestRequest::query()->whereIn('id', $requestIds)->orderBy('id')->lockForUpdate()->get();
                $locked = Sample::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                SampleTestProcess::query()->whereIn('sample_id', $ids)->orderBy('id')->lockForUpdate()->get();
                $samples = $this->eligibility->query()->whereIn('id', $ids)->orderBy('id')->get();
                $current = $samples->map(fn ($sample) => $this->eligibility->projection($sample))->all();
                $stable = static fn ($rows) => array_map(static function ($row) {
                    unset($row['age_days']);

                    return $row;
                }, $rows);
                if ($locked->count() !== count($ids) || $samples->count() !== count($ids) || $stable($current) !== $stable($snapshot['samples'])) {
                    throw ValidationException::withMessages(['sample_ids' => 'Daftar sampel berubah atau sebagian sampel tidak lagi memenuhi syarat. Muat ulang daftar; tidak ada sampel yang dimusnahkan.']);
                }
                $witnesses = $this->witnesses($data['witnesses'], $users);
                if ($photos === []) {
                    foreach ($data['documentation_photos'] ?? [] as $file) {
                        $path = $file->storeAs('disposal-documentation', Str::uuid().'.'.$file->getClientOriginalExtension(), 'local');
                        if (! $path) {
                            throw new RuntimeException('Dokumentasi gagal disimpan.');
                        }
                        $photos[] = ['disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()];
                    }
                }
                $primary = $witnesses[0];
                $number = $executor->nrp ?: $executor->nip;
                $disposal = $this->createRecord([
                    'review_id' => $snapshot['review_id'], 'manifest_snapshot' => $snapshot,
                    'execution_fingerprint' => $fingerprint,
                    'executed_at' => now(), 'method' => SampleDisposalMethod::from($data['method']),
                    'executed_by' => $executor->id, 'created_by' => $executor->id,
                    'executed_by_name' => trim((string) ($data['executor_name'] ?? '')) ?: ($executor->display_name_with_title ?: $executor->name),
                    'executed_by_role' => trim((string) ($data['executor_role'] ?? '')) ?: $executor->rank,
                    'executed_by_identity' => trim((string) ($data['executor_identity'] ?? '')) ?: ($number ? ($executor->nrp ? 'NRP: ' : 'NIP: ').$number : null),
                    'witness_name' => $primary['name'], 'witness_role' => $primary['role'], 'witness_user_id' => $primary['user_id'],
                    'witness_entries' => $witnesses, 'approver_name' => trim($data['approver_name']), 'approver_role' => trim($data['approver_role']),
                    'approver_identity' => trim((string) ($data['approver_identity'] ?? '')) ?: null,
                    'documentation_photos' => $photos ?: null, 'notes' => $data['notes'] ?? null,
                ]);
                foreach ($samples as $sample) {
                    $sample->markAsDisposed($disposal);
                }

                return $disposal;
            }, 3);
        } catch (Throwable $exception) {
            $this->deletePhotos($photos);
            throw $exception;
        }
        $usedPaths = array_column($disposal->documentation_photos ?? [], 'path');
        $this->deletePhotos(array_filter($photos, fn ($photo) => ! in_array($photo['path'], $usedPaths, true)));

        return $disposal;
    }

    private function witnesses(array $rows, \Illuminate\Support\Collection $users): array
    {
        $result = [];
        foreach ($rows as $index => $row) {
            $userId = ! empty($row['user_id']) ? (int) $row['user_id'] : null;
            $user = $userId ? $users->get($userId) : null;
            $name = trim((string) ($row['name'] ?? ''));
            $role = trim((string) ($row['role'] ?? ''));
            if (! $userId && $name === '' && $role === '') {
                continue;
            }
            if ($userId && ! $user?->is_active) {
                throw ValidationException::withMessages(["witnesses.{$index}.user_id" => 'User saksi tidak valid atau tidak aktif.']);
            }
            $name = $name ?: ($user?->display_name_with_title ?: $user?->name);
            $role = $role ?: $user?->rank;
            if (! $name || ! $role) {
                throw ValidationException::withMessages(["witnesses.{$index}.name" => 'Nama dan jabatan saksi wajib diisi.']);
            }
            $number = $user?->nrp ?: $user?->nip;
            $result[] = ['user_id' => $userId, 'name' => $name, 'role' => $role,
                'identity' => trim((string) ($row['identity'] ?? '')) ?: ($number ? ($user?->nrp ? 'NRP: ' : 'NIP: ').$number : null)];
        }
        if ($result === []) {
            throw ValidationException::withMessages(['witnesses' => 'Isi minimal satu saksi yang valid.']);
        }

        return $result;
    }

    private function createRecord(array $attributes): SampleDisposal
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                // Each retry uses a savepoint so a PostgreSQL unique violation does not poison the outer batch transaction.
                return DB::transaction(fn () => SampleDisposal::create(['batch_number' => SampleDisposal::generateBatchNumber(), ...$attributes]));
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? '') !== '23505' || ! str_contains($exception->getMessage(), 'batch_number') || $attempt === 4) {
                    throw $exception;
                }
            }
        }
        throw new RuntimeException('Nomor batch pemusnahan belum dapat dibuat. Coba kembali.');
    }

    private function deletePhotos(array $photos): void
    {
        $this->files->cleanup($photos);
    }
}
