<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SampleDisposal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SampleDisposalFileService
{
    public function cleanup(array $photos): void
    {
        foreach ($photos as $photo) {
            try {
                $id = $this->register('cleanup', $photo['disk'] ?? 'public', $photo['path']);
                $this->process($id);
            } catch (Throwable $exception) {
                report(new RuntimeException('Cleanup bukti pemusnahan memerlukan rekonsiliasi: '.get_class($exception)));
            }
        }
    }

    public function legacyPaths(): array
    {
        $paths = [];
        SampleDisposal::query()->select(['id', 'documentation_photos'])->chunkById(100, function ($records) use (&$paths): void {
            foreach ($records as $record) {
                foreach ($record->documentation_photos ?? [] as $photo) {
                    if (is_array($photo) && ($photo['disk'] ?? 'public') === 'public' && is_string($photo['path'] ?? null)) {
                        $paths[$photo['path']] = $photo['path'];
                    }
                }
            }
        });

        return array_values($paths);
    }

    public function privatize(string $path): bool
    {
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('Privatisasi bukti memerlukan transaksi independen agar mapping tersimpan sebelum sumber dihapus.');
        }

        return $this->process($this->register('privatize', 'public', $path));
    }

    public function retry(): array
    {
        $results = ['completed' => 0, 'failed' => 0];
        $ids = DB::table('sample_disposal_file_operations')->whereIn('state', ['pending', 'copied'])->orderBy('id')->pluck('id');
        foreach ($ids as $id) {
            $results[$this->process((int) $id) ? 'completed' : 'failed']++;
        }

        return $results;
    }

    public function privateDestination(string $path): ?string
    {
        return DB::table('sample_disposal_file_operations')
            ->where('operation_key', $this->key('privatize', 'public', $path))
            ->whereIn('state', ['copied', 'complete'])->value('destination_path');
    }

    private function register(string $kind, string $disk, string $path): int
    {
        $this->assertSafePath($disk, $path);
        $key = $this->key($kind, $disk, $path);
        DB::table('sample_disposal_file_operations')->insertOrIgnore([
            'operation_key' => $key, 'kind' => $kind, 'disk' => $disk, 'path' => $path,
            'state' => 'pending', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('sample_disposal_file_operations')->where('operation_key', $key)->value('id');
    }

    private function process(int $id): bool
    {
        $copied = false;
        $result = DB::transaction(function () use ($id, &$copied): bool {
            $operation = DB::table('sample_disposal_file_operations')->where('id', $id)->lockForUpdate()->first();
            if (! $operation) {
                throw new RuntimeException('Operasi file tidak ditemukan.');
            }
            if ($operation->state === 'protected' || ($operation->state === 'complete' && $operation->kind === 'cleanup')) {
                return true;
            }
            $changes = ['attempts' => $operation->attempts + 1, 'updated_at' => now(), 'last_error' => null];
            try {
                $this->assertSafePath($operation->disk, $operation->path);
                if ($operation->kind === 'cleanup') {
                    if ($this->isReferenced($operation->disk, $operation->path)) {
                        $changes['state'] = 'protected';
                    } else {
                        $this->remove($operation->disk, $operation->path);
                        $changes['state'] = 'complete';
                    }
                } else {
                    $destination = $operation->destination_path ?: 'disposal-documentation/legacy-'.$operation->operation_key.'.'.pathinfo($operation->path, PATHINFO_EXTENSION);
                    $this->assertSafePath('local', $destination);
                    $sha256 = $operation->sha256;
                    if (! in_array($operation->state, ['copied', 'complete'], true)) {
                        $sha256 = $this->digest('public', $operation->path);
                        if (Storage::disk('local')->exists($destination)) {
                            if ($this->digest('local', $destination) !== $sha256) {
                                throw new RuntimeException('destination_mismatch');
                            }
                        } else {
                            $stream = Storage::disk('public')->readStream($operation->path);
                            if (! is_resource($stream)) {
                                throw new RuntimeException('source_unreadable');
                            }
                            try {
                                if (! Storage::disk('local')->put($destination, $stream)) {
                                    throw new RuntimeException('copy_failed');
                                }
                            } finally {
                                fclose($stream);
                            }
                        }
                    }
                    if (! $sha256 || $this->digest('local', $destination) !== $sha256) {
                        throw new RuntimeException('private_copy_invalid');
                    }
                    $changes += ['destination_path' => $destination, 'sha256' => $sha256, 'state' => 'copied'];
                    if (! in_array($operation->state, ['copied', 'complete'], true)) {
                        $copied = true;
                    } else {
                        if (Storage::disk('public')->exists($operation->path) && $this->digest('public', $operation->path) !== $sha256) {
                            throw new RuntimeException('source_changed');
                        }
                        $this->remove('public', $operation->path);
                        $changes['state'] = 'complete';
                    }
                }
                $changes['completed_at'] = in_array($changes['state'], ['complete', 'protected'], true) ? now() : null;
            } catch (Throwable $exception) {
                $knownErrors = ['source_unreadable', 'copy_failed', 'destination_mismatch', 'private_copy_invalid', 'source_changed', 'delete_failed', 'file_unreadable', 'unsafe_evidence_path', 'symlink_evidence_path', 'unsafe_evidence_root'];
                $changes['last_error'] = in_array($exception->getMessage(), $knownErrors, true) ? $exception->getMessage() : get_class($exception);
                if ($operation->kind === 'privatize' && $operation->state === 'complete') {
                    $changes['state'] = 'copied';
                    $changes['completed_at'] = null;
                }
                DB::table('sample_disposal_file_operations')->where('id', $id)->update($changes);
                report(new RuntimeException('Operasi file bukti pemusnahan gagal; ID operasi '.$id.'; '.get_class($exception)));

                return false;
            }
            DB::table('sample_disposal_file_operations')->where('id', $id)->update($changes);

            return true;
        });

        // Commit the private-file mapping before the second phase removes its public source.
        return $result && $copied ? $this->process($id) : $result;
    }

    private function isReferenced(string $disk, string $path): bool
    {
        if (SampleDisposal::query()->whereJsonContains('documentation_photos', [['path' => $path]])->get(['documentation_photos'])
            ->contains(fn ($record) => collect($record->documentation_photos)->contains(fn ($photo) => ($photo['path'] ?? null) === $path && ($photo['disk'] ?? 'public') === $disk))) {
            return true;
        }

        return $disk === 'local' && DB::table('sample_disposal_file_operations')->where('kind', 'privatize')
            ->where('destination_path', $path)->whereIn('state', ['copied', 'complete'])->exists();
    }

    private function remove(string $disk, string $path): void
    {
        if (Storage::disk($disk)->exists($path) && ! Storage::disk($disk)->delete($path)) {
            throw new RuntimeException('delete_failed');
        }
    }

    private function digest(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException('file_unreadable');
        }
        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    private function assertSafePath(string $disk, string $path): void
    {
        if (! in_array($disk, ['local', 'public'], true) || config('filesystems.disks.'.$disk.'.driver') !== 'local'
            || preg_match('#\Adisposal-documentation/[a-zA-Z0-9][a-zA-Z0-9._/-]*\z#', $path) !== 1
            || collect(explode('/', $path))->contains(fn ($part) => in_array($part, ['', '.', '..'], true))) {
            throw new RuntimeException('unsafe_evidence_path');
        }
        $absolute = Storage::disk($disk)->path($path);
        $ancestor = $absolute;
        $configuredRoot = rtrim(Storage::disk($disk)->path(''), DIRECTORY_SEPARATOR);
        while ($ancestor !== $configuredRoot && dirname($ancestor) !== $ancestor) {
            if (is_link($ancestor)) {
                throw new RuntimeException('symlink_evidence_path');
            }
            $ancestor = dirname($ancestor);
        }
        $root = realpath(Storage::disk($disk)->path(''));
        $existing = file_exists($absolute) ? realpath($absolute) : realpath(dirname($absolute));
        if ($root === false || ($existing !== false && $existing !== $root && ! str_starts_with($existing, $root.DIRECTORY_SEPARATOR)) || is_link($absolute)) {
            throw new RuntimeException('unsafe_evidence_root');
        }
    }

    private function key(string $kind, string $disk, string $path): string
    {
        return hash('sha256', $kind.'|'.$disk.'|'.$path);
    }
}
