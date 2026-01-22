<?php

use App\Models\Document;

echo "Checking ALL documents (incl deleted)...\n";
$docs = Document::withTrashed()->where('document_type', 'ba_penyerahan')->get();
echo 'Total found: '.$docs->count()."\n";
foreach ($docs as $d) {
    $status = $d->deleted_at ? '[DELETED]' : '[ACTIVE]';
    echo "ID: {$d->id} {$status} | Name: {$d->filename}\n";
}
