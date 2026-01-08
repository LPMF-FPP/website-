<?php

namespace App\Services;

use App\Enums\InstrumentAssetStatus;
use App\Enums\InstrumentUsageType;
use App\Models\Instrument;
use App\Models\InstrumentAsset;
use App\Models\InstrumentUsageLog;
use App\Models\MethodInstrumentRequirement;
use App\Models\Sample;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

use function settings;

class InstrumentLoggingService
{
    public function getSettings(): array
    {
        return [
            'enabled' => (bool) settings('monitoring_logging.instrument_logging.enabled', false),
        ];
    }

    public function isEnabled(): bool
    {
        return $this->getSettings()['enabled'];
    }

    public function requirementsForMethod(string $methodCode): Collection
    {
        return MethodInstrumentRequirement::forMethod($methodCode)
            ->with(['instrument', 'instrument.assets' => function ($query) {
                $query->available();
            }])
            ->ordered()
            ->get();
    }

    public function requirementsForSampleMethods(Sample $sample): Collection
    {
        $methods = $this->getMethodsFromSample($sample);

        if (empty($methods)) {
            return collect();
        }

        return MethodInstrumentRequirement::whereIn('method_code', $methods)
            ->with(['instrument', 'instrument.assets' => function ($query) {
                $query->available();
            }])
            ->ordered()
            ->get()
            ->groupBy('method_code');
    }

    public function getAvailableAssets(int $instrumentId): Collection
    {
        return InstrumentAsset::forInstrument($instrumentId)
            ->available()
            ->get();
    }

    public function validateSelections(string $methodCode, array $selections): array
    {
        $errors = [];
        $requirements = $this->requirementsForMethod($methodCode);

        foreach ($requirements as $requirement) {
            if (! $requirement->mandatory) {
                continue;
            }

            $instrumentId = $requirement->instrument_id;
            $hasSelection = isset($selections[$instrumentId]) && ! empty($selections[$instrumentId]);

            if (! $hasSelection) {
                $errors[$instrumentId] = "Instrumen {$requirement->instrument->name} wajib dipilih.";
            }
        }

        return $errors;
    }

    public function validateSelectionsForSample(Sample $sample, array $allSelections): array
    {
        $errors = [];
        $methods = $this->getMethodsFromSample($sample);

        foreach ($methods as $method) {
            $methodSelections = $allSelections[$method] ?? [];
            $methodErrors = $this->validateSelections($method, $methodSelections);

            if (! empty($methodErrors)) {
                foreach ($methodErrors as $instrumentId => $error) {
                    $errors["{$method}.{$instrumentId}"] = $error;
                }
            }
        }

        return $errors;
    }

    public function createUsageLogs(
        Sample $sample,
        string $methodCode,
        array $assetSelections,
        User $user,
        ?InstrumentUsageType $usageType = null
    ): Collection {
        $logs = collect();
        $now = Carbon::now();

        foreach ($assetSelections as $instrumentId => $assetId) {
            if (empty($assetId)) {
                continue;
            }

            $log = InstrumentUsageLog::create([
                'test_request_id' => $sample->test_request_id,
                'sample_id' => $sample->id,
                'method_code' => $methodCode,
                'instrument_asset_id' => $assetId,
                'usage_type' => $usageType ?? InstrumentUsageType::RUN,
                'logged_at' => $now,
                'performed_by' => $user->id,
                'notes' => null,
            ]);

            $logs->push($log);
        }

        return $logs;
    }

    public function createBatchUsageLogs(
        Sample $sample,
        array $allSelections,
        User $user
    ): Collection {
        $allLogs = collect();

        foreach ($allSelections as $methodCode => $assetSelections) {
            $logs = $this->createUsageLogs($sample, $methodCode, $assetSelections, $user);
            $allLogs = $allLogs->merge($logs);
        }

        return $allLogs;
    }

    public function hasCompletedRequirements(Sample $sample, string $methodCode): bool
    {
        $requirements = $this->requirementsForMethod($methodCode);
        $mandatoryRequirements = $requirements->where('mandatory', true);

        if ($mandatoryRequirements->isEmpty()) {
            return true;
        }

        $existingLogs = InstrumentUsageLog::forSample($sample->id)
            ->forMethod($methodCode)
            ->get();

        foreach ($mandatoryRequirements as $requirement) {
            $hasLog = $existingLogs->contains(function ($log) use ($requirement) {
                $asset = $log->instrumentAsset;

                return $asset && $asset->instrument_id === $requirement->instrument_id;
            });

            if (! $hasLog) {
                return false;
            }
        }

        return true;
    }

    public function hasCompletedAllRequirementsForSample(Sample $sample): bool
    {
        $methods = $this->getMethodsFromSample($sample);

        if (empty($methods)) {
            return true;
        }

        $instrumentMethods = $this->getMethodsRequiringInstruments($methods);

        foreach ($instrumentMethods as $method) {
            if (! $this->hasCompletedRequirements($sample, $method)) {
                return false;
            }
        }

        return true;
    }

    public function getMethodsRequiringInstruments(array $methods): array
    {
        return MethodInstrumentRequirement::whereIn('method_code', $methods)
            ->mandatory()
            ->distinct('method_code')
            ->pluck('method_code')
            ->toArray();
    }

    public function getUsageLogsForSample(Sample $sample): Collection
    {
        return InstrumentUsageLog::forSample($sample->id)
            ->with(['instrumentAsset.instrument', 'performer'])
            ->orderBy('logged_at')
            ->get();
    }

    public function getUsageLogsForMonth(int $year, int $month, ?int $assetId = null): Collection
    {
        $query = InstrumentUsageLog::forMonth($year, $month)
            ->with(['sample', 'testRequest', 'instrumentAsset.instrument', 'performer']);

        if ($assetId !== null) {
            $query->where('instrument_asset_id', $assetId);
        }

        return $query->orderBy('logged_at')->get();
    }

    public function getMissingRequirements(Sample $sample): array
    {
        $missing = [];
        $methods = $this->getMethodsFromSample($sample);

        foreach ($methods as $methodCode) {
            $requirements = $this->requirementsForMethod($methodCode);
            $mandatoryRequirements = $requirements->where('mandatory', true);

            if ($mandatoryRequirements->isEmpty()) {
                continue;
            }

            $existingLogs = InstrumentUsageLog::forSample($sample->id)
                ->forMethod($methodCode)
                ->get();

            foreach ($mandatoryRequirements as $requirement) {
                $hasLog = $existingLogs->contains(function ($log) use ($requirement) {
                    $asset = $log->instrumentAsset;

                    return $asset && $asset->instrument_id === $requirement->instrument_id;
                });

                if (! $hasLog) {
                    $missing[] = [
                        'method_code' => $methodCode,
                        'instrument_id' => $requirement->instrument_id,
                        'instrument_name' => $requirement->instrument->name ?? 'Unknown',
                    ];
                }
            }
        }

        return $missing;
    }

    protected function getMethodsFromSample(Sample $sample): array
    {
        $testMethods = $sample->test_methods;

        if (empty($testMethods)) {
            return [];
        }

        if (is_string($testMethods)) {
            $decoded = json_decode($testMethods, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($testMethods) ? $testMethods : [];
    }

    public function requiresUvvisWeighing(Sample $sample): bool
    {
        return $this->requiresWeighing($sample);
    }

    public function hasCompletedUvvisWeighing(Sample $sample): bool
    {
        return $this->hasCompletedWeighing($sample);
    }

    public function recordUvvisWeighing(Sample $sample, float $grams, User $user): Sample
    {
        return $this->recordWeighing($sample, 1, $grams, 'g', $user);
    }

    public function requiresWeighing(Sample $sample): bool
    {
        $methods = $this->getMethodsFromSample($sample);

        if (empty($methods)) {
            return false;
        }

        return MethodInstrumentRequirement::whereIn('method_code', $methods)
            ->whereHas('instrument', fn ($q) => $q->where('code', 'ANALYTICAL_BALANCE'))
            ->where('mandatory', true)
            ->where('usage_type', InstrumentUsageType::PREP->value)
            ->exists();
    }

    public function hasCompletedWeighing(Sample $sample): bool
    {
        return $sample->weighed_mass_value !== null
            && $sample->weighed_mass_unit !== null
            && $sample->weighed_by !== null
            && $sample->weighed_at !== null
            && $sample->weighed_items_count !== null
            && $sample->weighed_items_count >= 1;
    }

    public function recordWeighing(
        Sample $sample,
        int $itemsCount,
        float $massValue,
        string $massUnit,
        User $user
    ): Sample {
        $sample->weighed_items_count = $itemsCount;
        $sample->weighed_mass_value = $massValue;
        $sample->weighed_mass_unit = $massUnit;
        $sample->weighed_by = $user->id;
        $sample->weighed_at = Carbon::now();
        $sample->save();

        return $sample;
    }

    public function getWeighingDataForSample(Sample $sample): array
    {
        $requiresWeighing = $this->requiresWeighing($sample);
        $hasWeighing = $this->hasCompletedWeighing($sample);

        return [
            'requires_weighing' => $requiresWeighing,
            'has_weighing' => $hasWeighing,
            'weighing_data' => $hasWeighing ? [
                'items_count' => $sample->weighed_items_count,
                'mass_value' => $sample->weighed_mass_value,
                'mass_unit' => $sample->weighed_mass_unit?->value ?? $sample->weighed_mass_unit,
                'mass_display' => $sample->weighed_mass_value . ' ' . ($sample->weighed_mass_unit?->symbol() ?? $sample->weighed_mass_unit ?? ''),
                'weighed_by' => $sample->weighedByUser?->name ?? '-',
                'weighed_at' => $sample->weighed_at?->format('d/m/Y H:i'),
            ] : null,
        ];
    }
}
