<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Support\TemplatePreviewData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BladeTemplateEditorController extends Controller
{
    /**
     * Allowed templates that can be edited via web interface
     * Security: Only these specific templates are editable
     */
    private const EDITABLE_TEMPLATES = [
        // PDF Documents
        'berita-acara-penerimaan' => 'resources/views/pdf/berita-acara-penerimaan.blade.php',
        'ba-penyerahan' => 'resources/views/pdf/ba-penyerahan.blade.php',
        'laporan-hasil-uji' => 'resources/views/pdf/laporan-hasil-uji.blade.php',
        'form-preparation' => 'resources/views/pdf/form-preparation.blade.php',
        // Labels - Evidence (Barang Bukti)
        'label-barang-bukti-sheet' => 'resources/views/labels/evidence-sheet.blade.php',
        'label-barang-bukti-single' => 'resources/views/labels/evidence-single.blade.php',
        // Labels - Remaining (Sisa Barang Bukti)
        'label-sisa-bukti-sheet' => 'resources/views/labels/remaining-sheet.blade.php',
        'label-sisa-bukti-single' => 'resources/views/labels/remaining-single.blade.php',
        // Inventory - Stock Card (Kartu Stok)
        'kartu-stok' => 'resources/views/inventory/pdf/stock-card.blade.php',
    ];

    /**
     * Get list of editable templates
     */
    public function index(): JsonResponse
    {
        $templates = [];
        
        foreach (self::EDITABLE_TEMPLATES as $key => $path) {
            $fullPath = base_path($path);
            
            if (File::exists($fullPath)) {
                $templates[] = [
                    'key' => $key,
                    'name' => ucwords(str_replace('-', ' ', $key)),
                    'path' => $path,
                    'size' => File::size($fullPath),
                    'modified_at' => Carbon::createFromTimestamp(File::lastModified($fullPath))->toIso8601String(),
                    'editable' => File::isWritable($fullPath),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    /**
     * Get template content
     */
    public function show(string $templateKey): JsonResponse
    {
        if (!isset(self::EDITABLE_TEMPLATES[$templateKey])) {
            return response()->json([
                'success' => false,
                'message' => 'Template tidak ditemukan atau tidak diizinkan untuk diedit.',
            ], 404);
        }

        $path = self::EDITABLE_TEMPLATES[$templateKey];
        $fullPath = base_path($path);

        if (!File::exists($fullPath)) {
            return response()->json([
                'success' => false,
                'message' => 'File template tidak ditemukan.',
            ], 404);
        }

        $content = File::get($fullPath);

        return response()->json([
            'success' => true,
            'template' => [
                'key' => $templateKey,
                'name' => ucwords(str_replace('-', ' ', $templateKey)),
                'path' => $path,
                'content' => $content,
                'size' => strlen($content),
                'modified_at' => Carbon::createFromTimestamp(File::lastModified($fullPath))->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update template content
     */
    public function update(Request $request, string $templateKey): JsonResponse
    {
        // Validate template key
        if (!isset(self::EDITABLE_TEMPLATES[$templateKey])) {
            return response()->json([
                'success' => false,
                'message' => 'Template tidak ditemukan atau tidak diizinkan untuk diedit.',
            ], 404);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
            'create_backup' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = self::EDITABLE_TEMPLATES[$templateKey];
        $fullPath = base_path($path);
        $content = $request->input('content');

        // Security validation
        $securityCheck = $this->validateTemplateContent($content);
        if (!$securityCheck['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Template mengandung kode yang tidak diizinkan.',
                'errors' => $securityCheck['errors'],
            ], 400);
        }

        try {
            // Create backup if requested
            if ($request->boolean('create_backup', true)) {
                $this->createBackup($templateKey, $fullPath);
            }

            // Write new content
            File::put($fullPath, $content);

            // Clear view cache
            \Artisan::call('view:clear');

            return response()->json([
                'success' => true,
                'message' => 'Template berhasil disimpan.',
                'template' => [
                    'key' => $templateKey,
                    'size' => strlen($content),
                    'modified_at' => Carbon::now()->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get template backups
     */
    public function backups(string $templateKey): JsonResponse
    {
        if (!isset(self::EDITABLE_TEMPLATES[$templateKey])) {
            return response()->json([
                'success' => false,
                'message' => 'Template tidak ditemukan.',
            ], 404);
        }

        $backupPath = "template-backups/{$templateKey}";
        $disk = Storage::disk('local');

        if (!$disk->exists($backupPath)) {
            return response()->json([
                'success' => true,
                'backups' => [],
            ]);
        }

        $files = $disk->files($backupPath);
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'path' => $file,
                'size' => $disk->size($file),
                'created_at' => Carbon::createFromTimestamp($disk->lastModified($file))->toIso8601String(),
            ];
        }

        // Sort by created_at descending
        usort($backups, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return response()->json([
            'success' => true,
            'backups' => $backups,
        ]);
    }

    /**
     * Restore from backup
     */
    public function restore(Request $request, string $templateKey): JsonResponse
    {
        if (!isset(self::EDITABLE_TEMPLATES[$templateKey])) {
            return response()->json([
                'success' => false,
                'message' => 'Template tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'backup_file' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $backupFile = $request->input('backup_file');
        $disk = Storage::disk('local');

        if (!$disk->exists($backupFile)) {
            return response()->json([
                'success' => false,
                'message' => 'Backup tidak ditemukan.',
            ], 404);
        }

        try {
            $content = $disk->get($backupFile);
            $path = self::EDITABLE_TEMPLATES[$templateKey];
            $fullPath = base_path($path);

            // Create backup of current version before restore
            $this->createBackup($templateKey, $fullPath, 'before-restore');

            File::put($fullPath, $content);
            \Artisan::call('view:clear');

            return response()->json([
                'success' => true,
                'message' => 'Template berhasil dipulihkan dari backup.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview template with sample data
     */
    public function preview(Request $request, string $templateKey): JsonResponse|Response
    {
        if (!isset(self::EDITABLE_TEMPLATES[$templateKey])) {
            return response()->json([
                'success' => false,
                'message' => 'Template tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:400000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'error' => 'Konten template harus diisi.',
                'errors' => $validator->errors(),
                'slug' => $templateKey,
            ], 422);
        }

        $content = $request->input('content');

        // Security validation
        $securityCheck = $this->validateTemplateContent($content);
        if (!$securityCheck['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Template mengandung kode yang tidak diizinkan.',
                'error' => implode(', ', $securityCheck['errors']),
                'errors' => $securityCheck['errors'],
                'slug' => $templateKey,
            ], 422);
        }

        $tempFile = null;
        $tempDir = storage_path('framework/views');

        try {
            if (!File::isDirectory($tempDir)) {
                File::makeDirectory($tempDir, 0755, true);
            }
            $tempFile = $tempDir . '/tmp_preview_' . uniqid() . '_' . time() . '.blade.php';

            file_put_contents($tempFile, $content);

            $viewData = TemplatePreviewData::forKey($templateKey);
            $html = view()->file($tempFile, $viewData)->render();

            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Preview render gagal.',
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ], 422);
        } finally {
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
            try {
                \Illuminate\Support\Facades\View::flushState();
                \Artisan::call('view:clear');
            } catch (\Throwable $e) {
                // ignore cache clear errors
            }
        }
    }

    /**
     * Build comprehensive preview data for template
     */
    private function buildPreviewDataFor(string $templateKey): array
    {
        $now = now();
        
        return match ($templateKey) {
            'berita-acara-penerimaan' => $this->getBeritaAcaraPenerimaanData($now),
            'ba-penyerahan' => $this->getBaPenyerahanData($now),
            'laporan-hasil-uji' => $this->getLaporanHasilUjiData($now),
            'form-preparation' => $this->getFormPreparationData($now),
            'label-barang-bukti-sheet' => $this->getLabelEvidenceSheetData($now),
            'label-barang-bukti-single' => $this->getLabelEvidenceSingleData($now),
            'label-sisa-bukti-sheet' => $this->getLabelRemainingSheetData($now),
            'label-sisa-bukti-single' => $this->getLabelRemainingSingleData($now),
            'kartu-stok' => $this->getKartuStokData($now),
            default => [],
        };
    }

    /**
     * Get sample data for Berita Acara Penerimaan template
     */
    private function getBeritaAcaraPenerimaanData(\Illuminate\Support\Carbon $now): array
    {
        return [
            'request' => (object) [
                'request_number' => 'REQ-2025-0001',
                'receipt_number' => 'RESI-2025-0001',
                'case_number' => 'B/001/I/2025/Reskrim',
                'to_office' => 'Kepala Sub Satker Farmapol Pusdokkes Polri',
                'received_at' => $now->copy()->subDays(1),
                'investigator' => (object) [
                    'rank' => 'IPDA',
                    'name' => 'Budi Santoso',
                    'nrp' => '12345678',
                    'jurisdiction' => 'Polres Metro Jakarta Selatan',
                ],
                'samples' => collect([
                    (object) [
                        'sample_name' => 'Pil Ekstasi Warna Biru',
                        'test_methods' => json_encode(['gc_ms', 'uv_vis']),
                        'active_substance' => 'MDMA',
                    ],
                    (object) [
                        'sample_name' => 'Bubuk Putih Kristal',
                        'test_methods' => json_encode(['gc_ms']),
                        'active_substance' => 'Metamfetamina',
                    ],
                ]),
            ],
            'generatedAt' => $now,
        ];
    }

    /**
     * Get sample data for BA Penyerahan template
     */
    private function getBaPenyerahanData(\Illuminate\Support\Carbon $now): array
    {
        return [
            'request' => (object) [
                'request_number' => 'REQ-2025-0001',
                'receipt_number' => 'RESI-2025-0001',
                'ba_number' => 'BA-001/LPMF/I/2025',
                'suspect_name' => 'Tersangka ABC',
                'unit' => 'Polres Metro Jakarta Selatan',
                'investigator' => (object) [
                    'rank' => 'IPDA',
                    'name' => 'Budi Santoso',
                    'nrp' => '12345678',
                    'jurisdiction' => 'Polres Metro Jakarta Selatan',
                ],
                'samples' => collect([
                    (object) [
                        'sample_code' => 'W-001-2025',
                        'sample_name' => 'Pil Ekstasi',
                        'package_quantity' => 100,
                        'quantity' => 10,
                        'packaging_type' => 'butir',
                        'test_methods' => json_encode(['gc_ms', 'uv_vis']),
                    ],
                    (object) [
                        'sample_code' => 'W-002-2025',
                        'sample_name' => 'Bubuk Putih',
                        'package_quantity' => 50,
                        'quantity' => 5,
                        'packaging_type' => 'gram',
                        'test_methods' => json_encode(['gc_ms']),
                    ],
                ]),
            ],
            'generatedAt' => $now,
        ];
    }

    /**
     * Get sample data for Laporan Hasil Uji template
     */
    private function getLaporanHasilUjiData(\Illuminate\Support\Carbon $now): array
    {
        return [
            'process' => (object) [
                'method' => 'gc_ms',
                'metadata' => [
                    'instrument' => 'GC-MS (Gas Chromatography–Mass Spectrometry)',
                    'test_result' => 'positive',
                    'detected_substance' => 'MDMA',
                    'report_number' => 'FLHU-001',
                ],
                'sample' => (object) [
                    'sample_name' => 'Pil Ekstasi Warna Biru',
                    'sample_code' => 'W-001-2025',
                    'batch_no' => 'BATCH-001',
                    'exp_date' => $now->copy()->addYears(2),
                    'package_quantity' => 100,
                    'packaging_type' => 'butir',
                    'active_substance' => 'MDMA',
                    'testRequest' => (object) [
                        'request_number' => 'REQ-2025-0001',
                        'received_at' => $now->copy()->subDays(7),
                        'investigator' => (object) [
                            'rank' => 'IPDA',
                            'name' => 'Budi Santoso',
                            'jurisdiction' => 'Polres Metro Jakarta Selatan',
                        ],
                    ],
                ],
            ],
            'noLHU' => 'FLHU-001/LPMF/I/2025',
            'generatedAt' => $now,
        ];
    }

    /**
     * Get sample data for Form Preparation template
     */
    private function getFormPreparationData(\Illuminate\Support\Carbon $now): array
    {
        return [
            'process' => (object) [
                'analyst_name' => 'Dr. Ahmad Fauzi, S.Si., Apt.',
                'sample' => (object) [
                    'sample_name' => 'Pil Ekstasi Warna Biru',
                    'sample_code' => 'W-001-2025',
                    'id' => 1,
                    'testRequest' => (object) [
                        'request_number' => 'REQ-2025-0001',
                    ],
                ],
            ],
            'generatedAt' => $now,
        ];
    }

    /**
     * Get sample data for Label Evidence Sheet (Barang Bukti - Multiple) template
     */
    private function getLabelEvidenceSheetData(\Illuminate\Support\Carbon $now): array
    {
        $labels = collect([
            [
                'resi' => 'RESI-2025-0001',
                'kode_sampel' => 'BB-2025-001',
                'tanggal_terima' => $now->copy()->subDays(3)->format('d/m/Y'),
                'penyidik' => 'IPDA Budi Santoso',
                'satuan_kerja' => 'Polres Metro Jakarta Selatan',
                'satuan' => 'Tablet',
                'jenis' => 'Narkotika',
                'qr' => $this->qrPngDataUri('BB-2025-001'),
                'qr_text' => 'BB-2025-001',
            ],
            [
                'resi' => 'RESI-2025-0001',
                'kode_sampel' => 'BB-2025-002',
                'tanggal_terima' => $now->copy()->subDays(3)->format('d/m/Y'),
                'penyidik' => 'IPDA Budi Santoso',
                'satuan_kerja' => 'Polres Metro Jakarta Selatan',
                'satuan' => 'Tablet',
                'jenis' => 'Psikotropika',
                'qr' => $this->qrPngDataUri('BB-2025-002'),
                'qr_text' => 'BB-2025-002',
            ],
        ]);

        return [
            'labels' => $labels,
            'printDate' => $now->format('d/m/Y H:i'),
        ];
    }

    /**
     * Get sample data for Label Evidence Single (Barang Bukti - Single) template
     */
    private function getLabelEvidenceSingleData(\Illuminate\Support\Carbon $now): array
    {
        $label = [
            'resi' => 'RESI-2025-0001',
            'kode_sampel' => 'BB-2025-001',
            'tanggal_terima' => $now->copy()->subDays(3)->format('d/m/Y'),
            'penyidik' => 'IPDA Budi Santoso',
            'satuan_kerja' => 'Polres Metro Jakarta Selatan',
            'satuan' => 'Tablet',
            'jenis' => 'Narkotika',
            'qr' => $this->qrPngDataUri('BB-2025-001'),
            'qr_text' => 'BB-2025-001',
        ];

        return [
            'label' => $label,
            'printDate' => $now->format('d/m/Y H:i'),
        ];
    }

    /**
     * Get sample data for Label Remaining Sheet (Sisa Barang Bukti - Multiple) template
     */
    private function getLabelRemainingSheetData(\Illuminate\Support\Carbon $now): array
    {
        // Create mock evidenceUnit for nested reference
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

        $remainingUnits = collect([
            (object) [
                'remaining_code' => 'SISA-BB-2025-001',
                'qty_with_uom' => '1.5 gram',
                'seal_status_delivered' => 'Tersegel',
                'delivered_at_formatted' => $now->copy()->subDays(1)->format('d/m/Y'),
                'handover_doc_no' => 'BA-SERAH-001/LPMF/2025',
                'qr_content' => 'SISA-BB-2025-001',
                'qr_png' => $this->qrPngDataUri('SISA-BB-2025-001'),
                'evidenceUnit' => $evidenceUnit1,
            ],
            (object) [
                'remaining_code' => 'SISA-BB-2025-002',
                'qty_with_uom' => '50 butir',
                'seal_status_delivered' => 'Tersegel',
                'delivered_at_formatted' => $now->copy()->subDays(1)->format('d/m/Y'),
                'handover_doc_no' => 'BA-SERAH-001/LPMF/2025',
                'qr_content' => 'SISA-BB-2025-002',
                'qr_png' => $this->qrPngDataUri('SISA-BB-2025-002'),
                'evidenceUnit' => $evidenceUnit2,
            ],
        ]);

        return [
            'remainingUnits' => $remainingUnits,
            'printDate' => $now->format('d/m/Y H:i'),
        ];
    }

    /**
     * Get sample data for Label Remaining Single (Sisa Barang Bukti - Single) template
     */
    private function getLabelRemainingSingleData(\Illuminate\Support\Carbon $now): array
    {
        // Create mock evidenceUnit for nested reference
        $evidenceUnit = (object) [
            'receipt_code' => 'RESI-2025-0001',
            'sample_code' => 'BB-2025-001',
            'received_at_formatted' => $now->copy()->subDays(3)->format('d/m/Y'),
            'investigator_name' => 'IPDA Budi Santoso',
            'investigator_unit' => 'Polres Metro Jakarta Selatan',
        ];

        $remainingUnit = (object) [
            'remaining_code' => 'SISA-BB-2025-001',
            'qty_with_uom' => '1.5 gram',
            'seal_status_delivered' => 'Tersegel',
            'delivered_at_formatted' => $now->copy()->subDays(1)->format('d/m/Y'),
            'handover_doc_no' => 'BA-SERAH-001/LPMF/2025',
            'qr_content' => 'SISA-BB-2025-001',
            'qr_png' => $this->qrPngDataUri('SISA-BB-2025-001'),
            'evidenceUnit' => $evidenceUnit,
        ];

        return [
            'remainingUnit' => $remainingUnit,
            'printDate' => $now->format('d/m/Y H:i'),
        ];
    }

    /**
     * Get sample data for Kartu Stok (Stock Card) template
     */
    private function getKartuStokData(\Illuminate\Support\Carbon $now): array
    {
        // Create mock item object
        $item = (object) [
            'id' => 1,
            'name' => 'Marquis Reagent',
            'item_type' => 'REAGENT',
            'item_type_label' => 'Reagent',
            'brand' => 'Sigma-Aldrich',
            'manufacturer' => 'Merck KGaA',
            'uom' => 'mL',
        ];

        // Create mock lot
        $lot = (object) [
            'lot_no' => 'MRQ-2024-001',
        ];

        // Create mock location
        $location = (object) [
            'name' => 'Lemari Reagent Lab 1',
        ];

        // Create mock movements for stock card
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

        // Create mock user
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

    /**
     * Generate a QR PNG data URI for preview templates.
     */
    private function qrPngDataUri(string $text): string
    {
        try {
            $png = QrCode::format('png')
                ->size(180)
                ->margin(0)
                ->errorCorrection('M')
                ->generate($text);

            return 'data:image/png;base64,' . base64_encode($png);
        } catch (\Throwable $e) {
            $svg = QrCode::format('svg')
                ->size(180)
                ->margin(0)
                ->errorCorrection('M')
                ->generate($text);

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        }
    }

    /**
     * Get sample data for template preview
     * @deprecated Use buildPreviewDataFor instead
     */
    private function getSampleData(string $templateKey): array
    {
        return $this->buildPreviewDataFor($templateKey);
    }

    /**
     * Create backup of template
     */
    private function createBackup(string $templateKey, string $fullPath, string $suffix = ''): void
    {
        if (!File::exists($fullPath)) {
            return;
        }

        $disk = Storage::disk('local');
        $backupPath = "template-backups/{$templateKey}";
        $timestamp = now()->format('Y-m-d_His');
        $suffixPart = $suffix ? "_{$suffix}" : '';
        $filename = "{$timestamp}{$suffixPart}.blade.php";

        $disk->put(
            "{$backupPath}/{$filename}",
            File::get($fullPath)
        );

        // Keep only last 20 backups
        $this->cleanupOldBackups($templateKey);
    }

    /**
     * Clean up old backups, keep only last 20
     */
    private function cleanupOldBackups(string $templateKey, int $keepLast = 20): void
    {
        $disk = Storage::disk('local');
        $backupPath = "template-backups/{$templateKey}";

        if (!$disk->exists($backupPath)) {
            return;
        }

        $files = $disk->files($backupPath);

        if (count($files) <= $keepLast) {
            return;
        }

        // Sort by modification time
        usort($files, function($a, $b) use ($disk) {
            return $disk->lastModified($b) - $disk->lastModified($a);
        });

        // Delete old files
        $filesToDelete = array_slice($files, $keepLast);
        foreach ($filesToDelete as $file) {
            $disk->delete($file);
        }
    }

    /**
     * Validate template content for security
     */
    private function validateTemplateContent(string $content): array
    {
        $errors = [];

        // Check for dangerous PHP functions
        $dangerousFunctions = [
            'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen',
            'eval', 'assert', 'create_function', 'file_put_contents',
            'unlink', 'rmdir', 'chmod', 'chown', 'curl_exec', 'curl_multi_exec',
        ];

        foreach ($dangerousFunctions as $func) {
            if (preg_match('/\b' . preg_quote($func, '/') . '\s*\(/i', $content)) {
                $errors[] = "Fungsi PHP berbahaya terdeteksi: {$func}()";
            }
        }

        if (stripos($content, '<?php') !== false || stripos($content, '<?=') !== false) {
            $errors[] = 'Tag PHP tidak diizinkan dalam template.';
        }

        if (preg_match('/file_get_contents\s*\(\s*[\'"]https?:\/\//i', $content)) {
            $errors[] = 'Akses HTTP via file_get_contents tidak diizinkan.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
