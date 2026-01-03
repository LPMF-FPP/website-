<?php

namespace App\Support;

use App\Models\EvidenceUnit;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\RemainingUnit;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TemplatePreviewData
{
    public static function forSlug(string $slug): array
    {
        return self::forKey($slug);
    }

    public static function forSlugDeterministic(string $slug): array
    {
        return self::forKey($slug, self::fixedNow(), true);
    }

    public static function forKey(string $key, ?Carbon $now = null, bool $forceDummy = false): array
    {
        $now = $now ?: now();

        $base = self::baseData($now, $forceDummy);
        $specific = match ($key) {
            'berita-acara-penerimaan' => self::beritaAcaraPenerimaan($now, $forceDummy),
            'ba-penyerahan', 'berita-acara-penyerahan' => self::baPenyerahan($now, $forceDummy),
            'laporan-hasil-uji' => self::laporanHasilUji($now, $forceDummy),
            'form-preparation' => self::formPreparation($now, $forceDummy),
            'label-barang-bukti-sheet' => self::labelEvidenceSheet($now, $forceDummy),
            'label-barang-bukti-single' => self::labelEvidenceSingle($now, $forceDummy),
            'label-sisa-bukti-sheet', 'label-sisa-sampel-sheet' => self::labelRemainingSheet($now, $forceDummy),
            'label-sisa-bukti-single', 'label-sisa-sampel-single' => self::labelRemainingSingle($now, $forceDummy),
            'kartu-stok' => self::kartuStok($now, $forceDummy),
            default => [],
        };

        return array_merge($base, $specific);
    }

    private static function fixedNow(): Carbon
    {
        return Carbon::create(2025, 1, 15, 10, 0, 0, 'Asia/Jakarta');
    }

    private static function baseData(Carbon $now, bool $forceDummy): array
    {
        $request = self::resolveRequest($now, $forceDummy);

        return [
            'request' => $request,
            'generatedAt' => $now,
            'printDate' => $now->format('d/m/Y H:i'),
            'meta' => self::resolveMeta($request),
        ];
    }

    private static function beritaAcaraPenerimaan(Carbon $now, bool $forceDummy): array
    {
        $request = self::resolveRequest($now, $forceDummy);

        return [
            'request' => $request,
            'generatedAt' => $now,
        ];
    }

    private static function baPenyerahan(Carbon $now, bool $forceDummy): array
    {
        $request = self::resolveRequest($now, $forceDummy);
        $meta = self::resolveMeta($request);

        return [
            'request' => $request,
            'generatedAt' => $now,
            'meta' => $meta,
        ];
    }

    private static function laporanHasilUji(Carbon $now, bool $forceDummy): array
    {
        if ($forceDummy) {
            return [
                'process' => self::dummyProcess($now),
                'noLHU' => 'FLHU-001/LPMF/I/2025',
                'generatedAt' => $now,
            ];
        }

        $process = SampleTestProcess::with(['sample.testRequest.investigator'])->latest('id')->first();

        if ($process) {
            $process->loadMissing(['sample.testRequest.investigator']);
            $process->metadata = self::normalizeMeta($process->metadata ?? []);

            if (! $process->sample) {
                $process->sample = self::dummySample($now);
            }

            if (! $process->sample->testRequest) {
                $request = self::resolveRequest($now, $forceDummy);
                if (method_exists($process->sample, 'setRelation')) {
                    $process->sample->setRelation('testRequest', $request);
                } else {
                    $process->sample->testRequest = $request;
                }
            }

            if (! isset($process->method)) {
                $process->method = $process->metadata['method']
                    ?? $process->metadata['test_method']
                    ?? $process->test_method
                    ?? 'gc_ms';
            }

            $noLHU = $process->metadata['report_number']
                ?? $process->metadata['lab_report_no']
                ?? $process->metadata['lhu_number']
                ?? 'FLHU-001/LPMF/I/2025';

            return [
                'process' => $process,
                'noLHU' => $noLHU,
                'generatedAt' => $now,
            ];
        }

        return [
            'process' => self::dummyProcess($now),
            'noLHU' => 'FLHU-001/LPMF/I/2025',
            'generatedAt' => $now,
        ];
    }

    private static function formPreparation(Carbon $now, bool $forceDummy): array
    {
        if ($forceDummy) {
            $dummyProcess = self::dummyProcess($now);
            $dummyProcess->analyst_name = $dummyProcess->analyst_name ?? 'Dr. Ahmad Fauzi, S.Si., Apt.';

            return [
                'process' => $dummyProcess,
                'generatedAt' => $now,
            ];
        }

        $process = SampleTestProcess::with(['sample.testRequest'])->latest('id')->first();

        if ($process) {
            $process->loadMissing(['sample.testRequest', 'analyst']);
            if (! $process->sample) {
                $process->sample = self::dummySample($now);
            }
            if (! $process->sample->testRequest) {
                $request = self::resolveRequest($now, $forceDummy);
                if (method_exists($process->sample, 'setRelation')) {
                    $process->sample->setRelation('testRequest', $request);
                } else {
                    $process->sample->testRequest = $request;
                }
            }
            $process->analyst_name = $process->analyst?->name ?? $process->analyst_name ?? '__________________________';

            return [
                'process' => $process,
                'generatedAt' => $now,
            ];
        }

        $dummyProcess = self::dummyProcess($now);
        $dummyProcess->analyst_name = $dummyProcess->analyst_name ?? 'Dr. Ahmad Fauzi, S.Si., Apt.';

        return [
            'process' => $dummyProcess,
            'generatedAt' => $now,
        ];
    }

    private static function labelEvidenceSheet(Carbon $now, bool $forceDummy): array
    {
        $units = $forceDummy ? collect() : EvidenceUnit::with('sample')->latest('id')->take(10)->get();

        if ($units->isEmpty()) {
            $labels = self::dummyEvidenceLabels($now);
        } else {
            $labels = $units->map(fn (EvidenceUnit $unit) => self::buildEvidenceLabelPayload($unit))->values();
        }

        return [
            'labels' => $labels instanceof Collection ? $labels : collect($labels),
            'printDate' => $now->format('d/m/Y H:i'),
        ];
    }

    private static function labelEvidenceSingle(Carbon $now, bool $forceDummy): array
    {
        $data = self::labelEvidenceSheet($now, $forceDummy);
        $label = $data['labels']->first() ?? self::dummyEvidenceLabels($now)->first();

        return [
            'label' => $label,
            'printDate' => $data['printDate'],
        ];
    }

    private static function labelRemainingSheet(Carbon $now, bool $forceDummy): array
    {
        $units = $forceDummy ? collect() : RemainingUnit::with('evidenceUnit')->latest('id')->take(10)->get();

        if ($units->isEmpty()) {
            $remainingUnits = self::dummyRemainingUnits($now);
        } else {
            $units->each(function (RemainingUnit $unit) {
                $unit->qr_png = self::qrPngDataUri($unit->qr_content);
            });
            $remainingUnits = $units;
        }

        return [
            'remainingUnits' => $remainingUnits instanceof Collection ? $remainingUnits : collect($remainingUnits),
            'printDate' => $now->format('d/m/Y H:i'),
        ];
    }

    private static function labelRemainingSingle(Carbon $now, bool $forceDummy): array
    {
        $data = self::labelRemainingSheet($now, $forceDummy);
        $remainingUnit = $data['remainingUnits']->first() ?? self::dummyRemainingUnits($now)->first();

        return [
            'remainingUnit' => $remainingUnit,
            'printDate' => $data['printDate'],
        ];
    }

    private static function kartuStok(Carbon $now, bool $forceDummy): array
    {
        if ($forceDummy) {
            return self::dummyKartuStok($now);
        }

        $movements = InventoryMovement::with(['item', 'lot', 'fromLocation', 'toLocation', 'performedByUser'])
            ->latest('performed_at')
            ->take(10)
            ->get()
            ->sortBy('performed_at')
            ->values();

        if ($movements->isEmpty()) {
            return self::dummyKartuStok($now);
        }

        $item = $movements->first()->item ?: InventoryItem::latest('id')->first();
        $lot = $movements->first()->lot ?: InventoryLot::latest('id')->first();
        if (! $lot) {
            $lot = (object) ['lot_no' => 'Semua'];
        }

        $location = $movements->first()->toLocation
            ?: $movements->first()->fromLocation
            ?: InventoryLocation::latest('id')->first();
        if (! $location) {
            $location = (object) ['name' => 'Semua'];
        }

        $running = 0.0;
        $stockCard = [];

        foreach ($movements as $movement) {
            $change = (float) ($movement->signed_qty ?? $movement->qty ?? 0);
            $running += $change;
            $stockCard[] = [
                'movement' => $movement,
                'change' => $change,
                'running_balance' => $running,
            ];
        }

        $generatedBy = $movements->first()->performedByUser ?: (object) ['name' => 'System'];

        return [
            'stockCard' => $stockCard,
            'item' => $item ?: (object) ['name' => 'Item', 'item_type' => 'UNKNOWN', 'item_type_label' => 'Item', 'uom' => '-'],
            'lot' => $lot,
            'location' => $location,
            'filters' => [
                'item_id' => $item?->id,
                'lot_id' => $lot?->id,
                'location_id' => $location?->id,
                'date_from' => optional($movements->first()->performed_at)->format('Y-m-d'),
                'date_to' => optional($movements->last()->performed_at)->format('Y-m-d'),
            ],
            'generatedAt' => $now,
            'generatedBy' => $generatedBy,
        ];
    }

    private static function resolveRequest(Carbon $now, bool $forceDummy): object
    {
        if ($forceDummy) {
            return self::dummyRequest($now);
        }

        $request = TestRequest::with(['investigator', 'samples'])->latest('id')->first();

        if ($request) {
            $request->loadMissing(['investigator', 'samples']);
            if (! $request->relationLoaded('samples')) {
                $request->setRelation('samples', collect());
            }
            if (! $request->investigator) {
                $fallbackInvestigator = self::dummyRequest($now)->investigator;
                if (method_exists($request, 'setRelation')) {
                    $request->setRelation('investigator', $fallbackInvestigator);
                } else {
                    $request->investigator = $fallbackInvestigator;
                }
            }

            return $request;
        }

        return self::dummyRequest($now);
    }

    private static function resolveMeta(object $request): array
    {
        $metaRaw = $request->metadata ?? [];

        return self::normalizeMeta($metaRaw);
    }

    private static function normalizeMeta(mixed $metaRaw): array
    {
        if (is_string($metaRaw)) {
            $decoded = json_decode($metaRaw, true);

            return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
        }

        if ($metaRaw instanceof Collection) {
            return $metaRaw->toArray();
        }

        if (is_array($metaRaw)) {
            return $metaRaw;
        }

        return (array) $metaRaw;
    }

    private static function dummyRequest(Carbon $now): object
    {
        $investigator = (object) [
            'rank' => 'IPDA',
            'name' => 'Budi Santoso',
            'nrp' => '12345678',
            'jurisdiction' => 'Polres Metro Jakarta Selatan',
        ];

        $samples = collect([
            (object) [
                'short_description' => 'Pil Ekstasi Warna Biru',
                'sample_code' => 'W-001-2025',
                'test_methods' => json_encode(['gc_ms', 'uv_vis']),
                'active_substance' => 'MDMA',
                'package_quantity' => 100,
                'quantity' => 10,
                'unit' => 'butir',
            ],
            (object) [
                'short_description' => 'Bubuk Putih Kristal',
                'sample_code' => 'W-002-2025',
                'test_methods' => json_encode(['gc_ms']),
                'active_substance' => 'Metamfetamina',
                'package_quantity' => 50,
                'quantity' => 5,
                'unit' => 'gram',
            ],
        ]);

        return (object) [
            'request_number' => 'REQ-2025-0001',
            'receipt_number' => 'RESI-2025-0001',
            'case_number' => 'B/001/I/2025/Reskrim',
            'to_office' => 'Kepala Sub Satker Farmapol Pusdokkes Polri',
            'received_at' => $now->copy()->subDays(1),
            'investigator_id' => 1,
            'suspect_name' => 'Tersangka ABC',
            'unit' => 'Polres Metro Jakarta Selatan',
            'investigator' => $investigator,
            'samples' => $samples,
            'metadata' => [
                'ba_penyerahan_number' => 'BA-001/LPMF/I/2025',
                'report_number' => 'LHU-LPMF-001',
            ],
        ];
    }

    private static function dummySample(Carbon $now): object
    {
        $request = self::dummyRequest($now);

        return (object) [
            'short_description' => 'Pil Ekstasi Warna Biru',
            'sample_code' => 'W-001-2025',
            'batch_no' => 'BATCH-001',
            'exp_date' => $now->copy()->addYears(2),
            'package_quantity' => 100,
            'unit' => 'butir',
            'active_substance' => 'MDMA',
            'testRequest' => $request,
        ];
    }

    private static function dummyProcess(Carbon $now): object
    {
        $sample = self::dummySample($now);

        return (object) [
            'method' => 'gc_ms',
            'metadata' => [
                'instrument' => 'GC-MS (Gas Chromatography–Mass Spectrometry)',
                'test_result' => 'positive',
                'detected_substance' => 'MDMA',
                'report_number' => 'FLHU-001',
            ],
            'sample' => $sample,
            'analyst_name' => 'Dr. Ahmad Fauzi, S.Si., Apt.',
        ];
    }

    private static function dummyEvidenceLabels(Carbon $now): Collection
    {
        return collect([
            [
                'resi' => 'RESI-2025-0001',
                'kode_sampel' => 'BB-2025-001',
                'tanggal_terima' => $now->copy()->subDays(3)->format('d/m/Y'),
                'penyidik' => 'IPDA Budi Santoso',
                'satuan_kerja' => 'Polres Metro Jakarta Selatan',
                'satuan' => 'Tablet',
                'jenis' => 'Narkotika',
                'qr' => self::qrPngDataUri('BB-2025-001'),
                'qr_text' => 'BB-2025-001',
            ],
            [
                'resi' => 'RESI-2025-0001',
                'kode_sampel' => 'BB-2025-002',
                'tanggal_terima' => $now->copy()->subDays(3)->format('d/m/Y'),
                'penyidik' => 'IPDA Budi Santoso',
                'satuan_kerja' => 'Polres Metro Jakarta Selatan',
                'satuan' => 'Tablet',
                'jenis' => 'Narkotika',
                'qr' => self::qrPngDataUri('BB-2025-002'),
                'qr_text' => 'BB-2025-002',
            ],
        ]);
    }

    private static function dummyRemainingUnits(Carbon $now): Collection
    {
        $evidenceUnit1 = (object) [
            'receipt_code' => 'RESI-2025-0001',
            'sample_code' => 'BB-2025-001',
            'received_at_formatted' => $now->copy()->subDays(3)->format('d/m/Y'),
            'investigator_name' => 'IPDA Budi Santoso',
            'investigator_unit' => 'Polres Metro Jakarta Selatan',
        ];

        $evidenceUnit2 = (object) [
            'receipt_code' => 'RESI-2025-0001',
            'sample_code' => 'BB-2025-002',
            'received_at_formatted' => $now->copy()->subDays(3)->format('d/m/Y'),
            'investigator_name' => 'IPDA Budi Santoso',
            'investigator_unit' => 'Polres Metro Jakarta Selatan',
        ];

        return collect([
            (object) [
                'remaining_code' => 'SISA-BB-2025-001',
                'qty_with_uom' => '1.5 gram',
                'seal_status_delivered' => 'Tersegel',
                'delivered_at_formatted' => $now->copy()->subDays(1)->format('d/m/Y'),
                'handover_doc_no' => 'BA-SERAH-001/LPMF/2025',
                'qr_content' => 'SISA-BB-2025-001',
                'qr_png' => self::qrPngDataUri('SISA-BB-2025-001'),
                'evidenceUnit' => $evidenceUnit1,
            ],
            (object) [
                'remaining_code' => 'SISA-BB-2025-002',
                'qty_with_uom' => '50 butir',
                'seal_status_delivered' => 'Tersegel',
                'delivered_at_formatted' => $now->copy()->subDays(1)->format('d/m/Y'),
                'handover_doc_no' => 'BA-SERAH-001/LPMF/2025',
                'qr_content' => 'SISA-BB-2025-002',
                'qr_png' => self::qrPngDataUri('SISA-BB-2025-002'),
                'evidenceUnit' => $evidenceUnit2,
            ],
        ]);
    }

    private static function dummyKartuStok(Carbon $now): array
    {
        $item = (object) [
            'id' => 1,
            'name' => 'Marquis Reagent',
            'item_type' => 'REAGENT',
            'item_type_label' => 'Reagent',
            'brand' => 'Sigma-Aldrich',
            'manufacturer' => 'Merck KGaA',
            'uom' => 'mL',
        ];

        $lot = (object) [
            'lot_no' => 'MRQ-2024-001',
        ];

        $location = (object) [
            'name' => 'Lemari Reagent Lab 1',
        ];

        $stockCard = [
            [
                'movement' => (object) [
                    'movement_type' => 'RECEIPT',
                    'movement_type_label' => 'Penerimaan',
                    'performed_at' => $now->copy()->subDays(30),
                    'lot' => $lot,
                    'fromLocation' => null,
                    'toLocation' => $location,
                    'notes' => 'PO dari Merck',
                    'reason_code' => 'NEW_STOCK',
                ],
                'change' => 100,
                'running_balance' => 100,
            ],
            [
                'movement' => (object) [
                    'movement_type' => 'ISSUE',
                    'movement_type_label' => 'Pengeluaran',
                    'performed_at' => $now->copy()->subDays(20),
                    'lot' => $lot,
                    'fromLocation' => $location,
                    'toLocation' => null,
                    'notes' => 'Pengujian sampel BB-001',
                    'reason_code' => 'TESTING',
                ],
                'change' => -5,
                'running_balance' => 95,
            ],
            [
                'movement' => (object) [
                    'movement_type' => 'ISSUE',
                    'movement_type_label' => 'Pengeluaran',
                    'performed_at' => $now->copy()->subDays(10),
                    'lot' => $lot,
                    'fromLocation' => $location,
                    'toLocation' => null,
                    'notes' => 'Pengujian sampel BB-002',
                    'reason_code' => 'TESTING',
                ],
                'change' => -3,
                'running_balance' => 92,
            ],
            [
                'movement' => (object) [
                    'movement_type' => 'ADJUST',
                    'movement_type_label' => 'Penyesuaian',
                    'performed_at' => $now->copy()->subDays(5),
                    'lot' => $lot,
                    'fromLocation' => null,
                    'toLocation' => $location,
                    'notes' => 'Stock opname Desember',
                    'reason_code' => 'OPNAME',
                ],
                'change' => -2,
                'running_balance' => 90,
            ],
        ];

        $generatedBy = (object) [
            'name' => 'Admin LPMF',
        ];

        return [
            'stockCard' => $stockCard,
            'item' => $item,
            'lot' => $lot,
            'location' => $location,
            'filters' => [
                'item_id' => 1,
                'lot_id' => 1,
                'location_id' => 1,
                'date_from' => $now->copy()->subDays(30)->format('Y-m-d'),
                'date_to' => $now->format('Y-m-d'),
            ],
            'generatedAt' => $now,
            'generatedBy' => $generatedBy,
        ];
    }

    private static function buildEvidenceLabelPayload(EvidenceUnit $unit): array
    {
        $sample = $unit->sample;
        $receivedAt = $unit->received_at ?? $sample?->received_at ?? $unit->created_at;
        $receivedAtFormatted = $receivedAt
            ? Carbon::parse($receivedAt)->translatedFormat('d M Y H:i')
            : '-';

        $satuan = $sample?->quantity_unit ?? $sample?->unit ?? '-';
        $qrContent = $unit->qr_content;

        return [
            'id' => $unit->id,
            'resi' => $unit->receipt_code ?? '-',
            'kode_sampel' => $unit->sample_code ?? '-',
            'tanggal_terima' => $receivedAtFormatted,
            'penyidik' => $unit->investigator_name ?? '-',
            'satuan_kerja' => $unit->investigator_unit ?? '-',
            'satuan' => $satuan,
            'jenis' => $unit->sample_type ?? '-',
            'deskripsi' => $unit->sample_desc ?? '-',
            'segel' => $unit->seal_status_received ?? null,
            'kondisi' => $unit->condition_received ?? '-',
            'qr' => self::qrPngDataUri($qrContent),
            'qr_text' => $qrContent,
        ];
    }

    private static function qrPngDataUri(?string $text): string
    {
        if (! $text) {
            return '';
        }

        try {
            $png = QrCode::format('png')
                ->size(180)
                ->margin(0)
                ->errorCorrection('M')
                ->generate($text);

            return 'data:image/png;base64,'.base64_encode($png);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
