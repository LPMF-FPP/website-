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
        $uvvisEnabled = (bool) settings('monitoring_logging.uvvis_weighing.enabled', false);

        if (! $uvvisEnabled) {
            return false;
        }

        $methods = $this->getMethodsFromSample($sample);

        return in_array('uv_vis', $methods, true);
    }

    public function hasCompletedUvvisWeighing(Sample $sample): bool
    {
        return $sample->uvvis_weighed_grams !== null
            && $sample->uvvis_weighed_by !== null
            && $sample->uvvis_weighed_at !== null;
    }

    public function recordUvvisWeighing(Sample $sample, float $grams, User $user): Sample
    {
        $sample->uvvis_weighed_grams = $grams;
        $sample->uvvis_weighed_by = $user->id;
        $sample->uvvis_weighed_at = Carbon::now();
        $sample->save();

        return $sample;
    }
}
