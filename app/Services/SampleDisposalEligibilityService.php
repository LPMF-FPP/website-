<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SampleDisposalStatus;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class SampleDisposalEligibilityService
{
    public function qualifying(Builder|Relation $processes, ?int $days = null): Builder|Relation
    {
        return $processes->where('stage', 'interpretation')->whereNotNull('completed_at')
            ->where('completed_at', '<=', now()->subDays($days ?? Sample::disposalRetentionDays()))
            ->whereNotNull('metadata->lhu_number');
    }

    public function scope(Builder $samples): Builder
    {
        return $samples->whereNull('disposal_id')->whereNull('disposed_at')
            ->where(fn ($q) => $q->whereNull('disposal_status')->orWhere('disposal_status', '!=', SampleDisposalStatus::DISPOSED->value))
            ->whereHas('testProcesses', fn ($processes) => $this->qualifying($processes));
    }

    public function query(): Builder
    {
        return $this->scope(Sample::query())->with([
            'testRequest.investigator',
            'disposalInterpretations' => fn ($processes) => $processes->orderByDesc('completed_at')->orderByDesc('id'),
        ]);
    }

    public function anchor(Sample $sample): ?SampleTestProcess
    {
        return $sample->relationLoaded('disposalInterpretations')
            ? $sample->disposalInterpretations->first()
            : $sample->disposalInterpretations()->orderByDesc('completed_at')->orderByDesc('id')->first();
    }

    public function projection(Sample $sample): array
    {
        $anchor = $this->anchor($sample);
        $date = $anchor?->completed_at;
        $request = $sample->testRequest;

        return [
            'sample_id' => $sample->id, 'sample_code' => $sample->sample_code,
            'description' => $sample->short_description ?? $sample->sample_form,
            'request_number' => $request?->request_number,
            'case_number' => $request?->case_number, 'case_date' => $request?->case_date ? CarbonImmutable::parse($request->case_date)->format('Y-m-d') : null,
            'suspect_name' => $request?->suspect_name, 'investigator_name' => $request?->investigator?->name,
            'anchor_id' => $anchor?->id, 'lhu_number' => $anchor?->metadata['lhu_number'] ?? null,
            'completed_at' => $date?->format('Y-m-d H:i:s.u'),
            'age_days' => $date ? max(0, (int) floor($date->diffInDays(now(), false))) : null,
            'sample_updated_at' => $sample->updated_at?->format('Y-m-d H:i:s.u'),
        ];
    }

    public function monthKey(Sample $sample): ?string
    {
        return $this->anchor($sample)?->completed_at?->format('Y-m');
    }

    public function monthlySummaries(): \Illuminate\Support\Collection
    {
        return $this->scope(Sample::query()->select('samples.id'))
            ->withMax('disposalInterpretations as disposal_anchor_completed_at', 'completed_at')->get()
            ->groupBy(fn ($sample) => CarbonImmutable::parse($sample->disposal_anchor_completed_at)->format('Y-m'))
            ->map(function ($samples, $key): array {
                return ['key' => $key, 'label' => CarbonImmutable::parse($key.'-01')->translatedFormat('F Y'),
                    'count' => $samples->count(), 'sample_ids' => $samples->pluck('id')->all()];
            })->sortKeysDesc()->values();
    }
}
