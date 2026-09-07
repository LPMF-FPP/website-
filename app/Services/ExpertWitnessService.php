<?php

namespace App\Services;

use App\Models\Document;
use App\Models\ExpertWitnessRequest;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExpertWitnessService
{
    public function __construct(private readonly DatabaseManager $database) {}

    public function syncFarmapolRequests(): void
    {
        TestRequest::query()
            ->where('has_expert_witness_request', true)
            ->whereDoesntHave('expertWitnessRequest')
            ->each(function (TestRequest $testRequest): void {
                DB::transaction(function () use ($testRequest): void {
                    $lockedRequest = TestRequest::query()
                        ->lockForUpdate()
                        ->with('investigator')
                        ->find($testRequest->id);

                    if (! $lockedRequest || ! $lockedRequest->has_expert_witness_request || $lockedRequest->expertWitnessRequest()->exists()) {
                        return;
                    }

                    $investigator = $lockedRequest->investigator;
                    $request = ExpertWitnessRequest::create([
                        'source' => ExpertWitnessRequest::SOURCE_FARMAPOL,
                        'test_request_id' => $lockedRequest->id,
                        'investigator_id' => $lockedRequest->investigator_id,
                        'submitted_by' => $lockedRequest->user_id ?? User::query()->value('id'),
                        'letter_number' => $lockedRequest->expert_witness_letter_number ?: '-',
                        'letter_date' => $lockedRequest->expert_witness_letter_date ?? $lockedRequest->created_at->toDateString(),
                        'investigator_name' => $investigator?->name ?: '-',
                        'investigator_institution' => $investigator?->jurisdiction ?: ($investigator?->institution ?: '-'),
                        'investigator_phone' => $investigator?->phone ?: '-',
                        'notes' => $investigator ? null : 'Data penyidik pada permintaan Farmapol belum lengkap.',
                        'submitted_at' => $lockedRequest->created_at,
                    ]);

                    $this->seedMilestones($request);
                });
            });
    }

    public function createExternal(array $data, UploadedFile $file, User $user): ExpertWitnessRequest
    {
        $token = $data['submission_token'] ?? null;
        abort_unless(is_string($token) && \Illuminate\Support\Str::isUuid($token), 422);

        $path = null;

        try {
            return Cache::lock('expert-witness-submit:'.$user->id.':'.$token, 30)->block(5, function () use ($data, $file, $user, $token, &$path): ExpertWitnessRequest {
                $existing = ExpertWitnessRequest::query()
                    ->where('submission_token', $token)
                    ->first();

                if ($existing) {
                    return $existing;
                }

                return $this->database->transaction(function () use ($data, $file, $user, $token, &$path): ExpertWitnessRequest {
                    $request = ExpertWitnessRequest::create([
                        'source' => ExpertWitnessRequest::SOURCE_EXTERNAL,
                        'submitted_by' => $user->id,
                        'submission_token' => $token,
                        'letter_number' => $data['letter_number'],
                        'letter_date' => $data['letter_date'],
                        'investigator_name' => $data['investigator_name'],
                        'investigator_institution' => $data['investigator_institution'],
                        'investigator_phone' => $data['investigator_phone'],
                        'notes' => $data['notes'] ?? null,
                        'submitted_at' => now(),
                    ]);

                    $this->seedMilestones($request);

                    $path = $file->store('expert-witness/requests/'.$request->id, 'local');
                    $request->documents()->create([
                        'document_type' => 'submission_letter',
                        'disk' => 'local',
                        'path' => $path,
                        'original_filename' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                    ]);

                    return $request;
                });
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }

    public function seedMilestones(ExpertWitnessRequest $request): void
    {
        foreach (ExpertWitnessRequest::MILESTONES as $code => $label) {
            $request->milestones()->firstOrCreate(['code' => $code]);
        }
    }

    public function updateMilestone(ExpertWitnessRequest $request, string $code, bool $completed, User $user): void
    {
        abort_unless(array_key_exists($code, ExpertWitnessRequest::MILESTONES), 422);

        $codes = array_keys(ExpertWitnessRequest::MILESTONES);
        $currentIndex = array_search($code, $codes, true);
        $milestones = $request->milestones()->get()->keyBy('code');

        if ($completed && collect(array_slice($codes, 0, $currentIndex))->contains(fn (string $previousCode): bool => ! $milestones->get($previousCode)?->completed_at)) {
            throw ValidationException::withMessages(['milestone' => 'Selesaikan tahap sebelumnya terlebih dahulu.']);
        }

        if (! $completed && collect(array_slice($codes, $currentIndex + 1))->contains(fn (string $nextCode): bool => $milestones->get($nextCode)?->completed_at)) {
            throw ValidationException::withMessages(['milestone' => 'Buka kembali tahap berikutnya terlebih dahulu.']);
        }

        if ($code === 'sprin_approved' && $completed && (! $request->sprin_number || ! $request->sprin_date)) {
            throw ValidationException::withMessages([
                'sprin' => 'Nomor dan tanggal sprin harus diisi sebelum tahap ini diselesaikan.',
            ]);
        }

        $milestone = $request->milestones()->where('code', $code)->firstOrFail();
        $milestone->update([
            'completed_at' => $completed ? now() : null,
            'completed_by' => $completed ? $user->id : null,
        ]);

        $allComplete = $request->milestones()->whereNull('completed_at')->doesntExist();
        $request->update(['completed_at' => $allComplete ? now() : null]);
    }

    public function farmapolReferences(ExpertWitnessRequest $request): array
    {
        if ($request->source !== ExpertWitnessRequest::SOURCE_FARMAPOL || ! $request->test_request_id) {
            return [];
        }

        $samples = Sample::query()
            ->where('test_request_id', $request->test_request_id)
            ->with('testResult', 'testProcesses')
            ->get();

        $officialResultDocuments = Document::query()
            ->where('test_request_id', $request->test_request_id)
            ->whereIn('sample_id', $samples->pluck('id'))
            ->whereIn('document_type', ['laporan_hasil_uji', 'laporan_hasil_uji_html', 'lhu', 'test_results'])
            ->latest()
            ->get()
            ->unique('sample_id')
            ->keyBy('sample_id');

        return $samples
            ->map(function (Sample $sample) use ($officialResultDocuments): array {
                $interpretation = $sample->testProcesses
                    ->filter(fn ($process): bool => $process->stage?->value === 'interpretation')
                    ->filter(fn ($process): bool => $process->completed_at !== null)
                    ->sortByDesc('completed_at')
                    ->first();
                $lhuDocument = $officialResultDocuments->get($sample->id);
                $metadata = is_array($interpretation?->metadata) ? $interpretation->metadata : [];
                $lhuNumber = $metadata['lhu_number'] ?? $metadata['report_number'] ?? null;
                $available = (bool) ($lhuDocument && $interpretation && $lhuNumber);
                $result = $sample->testResult?->test_conclusion ?: $sample->testResult?->result_status;

                if ($available && ! $result) {
                    $formatResult = static function (array $data, ?string $fallbackSubstance = null): ?string {
                        $resultLabel = match ($data['test_result'] ?? null) {
                            'positive' => 'Positif',
                            'negative' => 'Negatif',
                            default => null,
                        };
                        $detectedSubstance = $data['detected_substance']
                            ?? $data['detection']
                            ?? $data['hasil']
                            ?? $fallbackSubstance;

                        return $resultLabel && $detectedSubstance
                            ? "{$resultLabel}: {$detectedSubstance}"
                            : ($resultLabel ?: $detectedSubstance);
                    };

                    $results = [$formatResult($metadata, $sample->active_substance)];
                    foreach ($metadata['multi_interpretations'] ?? [] as $additionalInterpretation) {
                        if (is_array($additionalInterpretation)) {
                            $results[] = $formatResult($additionalInterpretation, $sample->active_substance);
                        }
                    }
                    $result = collect($results)->filter()->join('; ') ?: null;
                }

                return [
                    'lhu_number' => $lhuNumber,
                    'sample_code' => $sample->sample_code,
                    'description' => $sample->short_description ?: $sample->sample_description,
                    'result' => $available ? $result : null,
                    'available' => $available,
                    'lhu_document_id' => $lhuDocument?->id,
                ];
            })
            ->groupBy(fn (array $reference): string => $reference['lhu_number'] ?: 'LHU belum tersedia')
            ->toArray();
    }
}
