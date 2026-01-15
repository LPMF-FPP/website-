<?php

namespace App\Services;

use App\Models\Document;
use App\Models\NumberingChangeLog;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\Sequence;
use App\Models\TestRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NumberingRepairService
{
    protected array $scopeConfig = [
        'ba' => [
            'model' => TestRequest::class,
            'column' => 'request_number',
            'label' => 'BA Penerimaan',
        ],
        'sample_code' => [
            'model' => Sample::class,
            'column' => 'sample_code',
            'label' => 'Kode Sampel',
        ],
        'lhu' => [
            'model' => SampleTestProcess::class,
            'column' => 'metadata->lhu_number',
            'json_path' => 'lhu_number',
            'label' => 'Laporan Hasil Uji',
        ],
        'ba_penyerahan' => [
            'model' => Document::class,
            'column' => 'filename',
            'document_type' => 'ba_penyerahan',
            'label' => 'BA Penyerahan',
        ],
        'tracking' => [
            'model' => TestRequest::class,
            'column' => 'receipt_number',
            'label' => 'Nomor Resi',
        ],
    ];

    public function __construct(
        protected NumberingService $numberingService
    ) {}

    /**
     * Get all available scopes
     */
    public function getScopes(): array
    {
        return array_keys($this->scopeConfig);
    }

    /**
     * Get scope configuration
     */
    public function getScopeConfig(string $scope): ?array
    {
        return $this->scopeConfig[$scope] ?? null;
    }

    /**
     * Get current bucket for a scope based on reset period
     */
    public function getCurrentBucket(string $scope): string
    {
        $config = $this->numberingService->currentSnapshot($scope);
        $reset = settings("numbering.$scope.reset") ?? 'never';
        $now = CarbonImmutable::now();

        return match ($reset) {
            'yearly' => $now->format('Y'),
            'monthly' => $now->format('Y-m'),
            'daily' => $now->format('Y-m-d'),
            default => 'default',
        };
    }
}
