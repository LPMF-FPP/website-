<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BladeTemplateEditorController extends Controller
{
    /**
     * Allowed templates that can be edited via web interface
     * Security: Only these specific templates are editable
     */
    private const EDITABLE_TEMPLATES = [
        'berita-acara-penerimaan' => 'resources/views/pdf/berita-acara-penerimaan.blade.php',
        'ba-penyerahan' => 'resources/views/pdf/ba-penyerahan.blade.php',
        'laporan-hasil-uji' => 'resources/views/pdf/laporan-hasil-uji.blade.php',
        'form-preparation' => 'resources/views/pdf/form-preparation.blade.php',
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
    public function preview(Request $request, string $templateKey): JsonResponse
    {
        if (!isset(self::EDITABLE_TEMPLATES[$templateKey])) {
            return response()->json([
                'success' => false,
                'message' => 'Template tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
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

        $tempPath = null;
        $disk = Storage::disk('local');

        try {
            // Get sample data for the template
            $sampleData = $this->buildPreviewDataFor($templateKey);

            // Create temporary view file with unique name in resources/views
            $tempPath = 'temp-preview-' . uniqid() . '-' . time();
            $tempFile = resource_path("views/{$tempPath}.blade.php");
            
            \Log::info('Creating preview for template', [
                'template' => $templateKey,
                'temp_file' => $tempFile,
                'content_length' => strlen($content),
            ]);

            file_put_contents($tempFile, $content);

            // Render the view
            $viewPath = $tempPath;
            
            try {
                $html = view($viewPath, $sampleData)->render();
            } catch (\Throwable $renderError) {
                // Log render error details
                \Log::error('Blade template render error', [
                    'template' => $templateKey,
                    'error' => $renderError->getMessage(),
                    'file' => $renderError->getFile(),
                    'line' => $renderError->getLine(),
                    'trace' => $renderError->getTraceAsString(),
                ]);

                // Clean up temp file
                if ($tempFile && file_exists($tempFile)) {
                    unlink($tempFile);
                }

                // Clear view cache
                \Artisan::call('view:clear');

                // Return user-friendly error
                return response()->json([
                    'success' => false,
                    'message' => 'Template memiliki error syntax atau runtime.',
                    'error' => $renderError->getMessage(),
                    'slug' => $templateKey,
                    'line' => $renderError->getLine(),
                    'file' => basename($renderError->getFile()),
                    'hint' => 'Periksa sintaks Blade dan pastikan semua variabel yang digunakan tersedia.',
                ], 422);
            }

            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            // Clear view cache to remove compiled temp file
            \Artisan::call('view:clear');

            \Log::info('Preview generated successfully', [
                'template' => $templateKey,
                'html_length' => strlen($html),
            ]);

            return response()->json([
                'success' => true,
                'html' => $html,
            ]);

        } catch (\Throwable $e) {
            // Clean up temp file if it exists
            if (isset($tempFile) && file_exists($tempFile)) {
                try {
                    unlink($tempFile);
                } catch (\Exception $cleanupError) {
                    // Ignore cleanup errors
                }
            }

            // Log the error
            \Log::error('Preview generation failed', [
                'template' => $templateKey,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return proper error response
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat preview.',
                'error' => $e->getMessage(),
                'slug' => $templateKey,
                'hint' => 'Periksa log aplikasi untuk detail lengkap.',
            ], 422);
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
            'eval', 'assert', 'create_function', 'file_put_contents', 'file_get_contents',
            'unlink', 'rmdir', 'chmod', 'chown', 'curl_exec', 'curl_multi_exec',
        ];

        foreach ($dangerousFunctions as $func) {
            if (preg_match('/\b' . preg_quote($func, '/') . '\s*\(/i', $content)) {
                $errors[] = "Fungsi PHP berbahaya terdeteksi: {$func}()";
            }
        }

        // Check for dangerous Blade directives
        $dangerousDirectives = ['@php', '@endphp'];
        foreach ($dangerousDirectives as $directive) {
            // Allow @php at start of file for variable declarations only
            if ($directive === '@php' && preg_match('/@php\s*$/m', $content)) {
                continue; // This is typically safe for declarations
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
