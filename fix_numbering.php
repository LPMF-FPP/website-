<?php

use App\Models\Document;
use App\Models\Sequence;
use Illuminate\Support\Facades\Storage;

echo "Starting Renumbering Process for BA Penyerahan 2026...\n";

// 1. Get Documents
$documents = Document::where('document_type', 'ba_penyerahan')
    ->whereYear('created_at', 2026)
    ->orderBy('created_at') // Urutkan berdasarkan waktu pembuatan
    ->get();

echo 'Found '.$documents->count()." documents.\n";

$seq = 1; // Mulai dari 1
foreach ($documents as $doc) {
    // Pattern: BA-ST-003-I-2026...
    if (preg_match('/(BA-ST-)(\d+)(-.+)/', $doc->filename, $matches)) {
        $prefix = $matches[1];
        $currentSeqStr = $matches[2];
        $suffix = $matches[3];

        $newSeqStr = str_pad($seq, 3, '0', STR_PAD_LEFT);
        $newFilename = $prefix.$newSeqStr.$suffix;

        if ($doc->filename !== $newFilename) {
            $disk = Storage::disk($doc->storage_disk ?? 'public');
            $newPath = dirname($doc->path).'/'.$newFilename;

            // Cek file fisik
            if ($disk->exists($doc->path)) {
                try {
                    // Cek jika target sudah ada (safety check)
                    if ($disk->exists($newPath)) {
                        echo "WARNING: Target file {$newFilename} already exists! Skipping.\n";
                    } else {
                        // Rename Physical File
                        $disk->move($doc->path, $newPath);

                        // Update DB
                        $oldFilename = $doc->filename;
                        $doc->filename = $newFilename;
                        $doc->path = $newPath;
                        $doc->save();

                        echo "RENAMED: {$oldFilename} -> {$newFilename}\n";
                    }
                } catch (\Exception $e) {
                    echo "ERROR renaming {$doc->filename}: ".$e->getMessage()."\n";
                }
            } else {
                echo "SKIP: Physical file missing for {$doc->filename}\n";
                // Tetap update DB jika file fisik hilang? Sebaiknya jangan, nanti broken link.
            }
        } else {
            echo "OK: {$doc->filename} correct sequence ({$seq})\n";
        }
    } else {
        echo "SKIP: Pattern mismatch for {$doc->filename}\n";
    }
    $seq++;
}

// 2. Reset Sequence Counter
$lastSeq = $seq - 1;
echo "Updating Sequence Counter to {$lastSeq}...\n";

$sequence = Sequence::firstOrNew([
    'scope' => 'ba_penyerahan',
    'bucket' => '2026',
]);
$sequence->current_value = $lastSeq;
$sequence->save();

echo "COMPLETE. Counter is now at {$lastSeq}.\n";
