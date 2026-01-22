<?php

use App\Models\Document;

echo "Checking documents...\n";
$docs = Document::where('document_type', 'ba_penyerahan')->get();
echo 'Total found: '.$docs->count()."\n";
foreach ($docs as $d) {
    echo "ID: {$d->id} | Name: {$d->filename} | Date: {$d->created_at}\n";
}
