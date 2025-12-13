<?php

use App\Models\TestRequest;

// API endpoint untuk generator Berita Acara
Route::get('/requests/{requestNumber}', function($requestNumber) {
    $request = TestRequest::with(['investigator', 'samples', 'user'])
        ->where('request_number', $requestNumber)
        ->first();

    if (!$request) {
        return response()->json(['error' => 'Request not found'], 404);
    }

    // Format test methods untuk display
    $formatTestMethods = function($methods) {
        if (is_string($methods)) {
            $methods = json_decode($methods, true) ?? [];
        }
        $map = [
            'uv_vis' => 'Identifikasi Spektrofotometri UV-VIS',
            'gc_ms' => 'Identifikasi GC-MS',
            'lc_ms' => 'Identifikasi LC-MS'
        ];
        return collect($methods)->map(fn($m) => $map[$m] ?? $m)->join('; ');
    };

    return response()->json([
        'request_id' => $request->id,
        'request_no' => $request->request_number,
        'surat_permintaan_no' => $request->case_number ?? '',
        'received_date' => $request->received_at ? $request->received_at->format('d F Y') : now()->format('d F Y'),
        'customer_rank_name' => $request->investigator->rank . ' ' . $request->investigator->name,
        'customer_no' => $request->investigator->nrp ?? '',
        'unit' => $request->investigator->jurisdiction ?? '',
        'addressed_to' => $request->to_office ?? 'Kepala Sub Satker Farmapol Pusdokkes Polri',
        'tests_summary' => $request->samples->map(fn($s) => $formatTestMethods($s->test_methods))->unique()->join('; '),
        'sample_count' => $request->samples->count(),
        'samples' => $request->samples->map(function($sample) use ($formatTestMethods) {
            return [
                'name' => $sample->sample_name,
                'tests' => $formatTestMethods($sample->test_methods),
                'active' => $sample->active_substance ?? ''
            ];
        }),
        'submitted_by' => $request->investigator->rank . ' ' . $request->investigator->name,
        'received_by' => 'Petugas Administrasi (dokumen) & Petugas Laboratorium (sampel)',
        'source_printed_at' => $request->submitted_at ? $request->submitted_at->format('d F Y H:i:s') : '',
    ]);
});

// API endpoint untuk generator Laporan Hasil Uji
Route::get('/sample-processes/{processId}', function($processId) {
    // Refresh dari database untuk memastikan data terbaru
    $process = \App\Models\SampleTestProcess::with(['sample.testRequest.investigator', 'analyst'])
        ->findOrFail($processId);
    
    // Force reload dari database
    $process->refresh();
    $process->load(['sample.testRequest.investigator', 'analyst']);
    
    $sample = $process->sample;
    $testRequest = $sample?->testRequest;
    $investigator = $testRequest?->investigator;
    $metadata = $process->metadata ?? [];
    
    // Format test methods
    $formatTestMethods = function($methods) {
        if (is_string($methods)) {
            $methods = json_decode($methods, true) ?? [];
        }
        $map = [
            'uv_vis' => 'Identifikasi UV-VIS',
            'gc_ms' => 'Identifikasi GC-MS',
            'lc_ms' => 'Identifikasi LC-MS'
        ];
        return collect($methods)->map(fn($m) => $map[$m] ?? $m)->join(', ');
    };
    
    // Get test result
    $resultRaw = $metadata['test_result'] ?? null;
    $resultLabel = match ($resultRaw) {
        'positive' => 'Positif',
        'negative' => 'Negatif',
        default => 'Belum ditentukan',
    };
    
    $testResultPrefix = match($resultRaw) {
        'positive' => '(+)',
        'negative' => '(-)',
        default => '',
    };
    
    $detected = $metadata['detected_substance'] ?? $metadata['detection'] ?? $metadata['hasil'] ?? ($sample?->active_substance ?: 'Tidak ada hasil terdeteksi');
    $testResultText = trim($testResultPrefix . ' ' . $detected);
    
    // Format quantity
    $quantityDisplay = '-';
    if ($sample?->quantity) {
        $quantityDisplay = rtrim(rtrim(number_format($sample->quantity, 2, ',', '.'), '0'), ',') . ' ' . ($sample->quantity_unit ?? '');
    }
    
    return response()->json([
        'process_id' => $process->id,
        'report_number' => $metadata['report_number'] ?? sprintf('FLHU%03d', $process->id),
        'customer_unit' => $investigator?->jurisdiction ?? $investigator?->name ?? '-',
        'customer_name' => trim(($investigator?->rank ? $investigator->rank . ' ' : '') . ($investigator?->name ?? '')),
        'customer_address' => $testRequest?->delivery_address ?? '-',
        'request_number' => $testRequest?->request_number ?? '-',
        'case_number' => $testRequest?->case_number ?? '-',
        'sample_name' => $sample?->sample_name ?? '-',
        'sample_code' => $sample?->sample_code ?? '-',
        'quantity_display' => $quantityDisplay,
        'batch_number' => $sample?->batch_number ?? '-',
        'expiry_date' => $sample?->expiry_date ? $sample->expiry_date->format('d F Y') : '-',
        // Tanggal penerimaan = tanggal formulir pengujian diisi (submitted_at)
        'received_date' => $testRequest?->submitted_at ? $testRequest->submitted_at->format('d F Y') : ($testRequest?->received_at ? $testRequest->received_at->format('d F Y') : '-'),
        'test_date' => $sample?->test_date ? $sample->test_date->format('d F Y') : '-',
        'tests_summary' => $formatTestMethods($sample?->test_methods ?? []),
        'test_result_text' => $testResultText,
        'test_result_label' => $resultLabel,
        'test_result_raw' => $resultRaw,
        'detected_substance' => $detected,
        'instrument_label' => $metadata['instrument'] ?? $metadata['instrument_pengujian'] ?? $sample?->test_type ?? '-',
        'report_date' => now()->format('d F Y'),
    ]);
});
