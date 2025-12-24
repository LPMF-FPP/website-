<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class SearchDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $id = (string) ($this->id ?? '');
        $source = (string) ($this->source ?? 'database');

        return [
            'id' => $id,
            'type' => 'document',
            'document_type' => (string) ($this->document_type ?? ''),
            'document_type_label' => (string) ($this->document_type_label ?? ''),
            'name' => (string) ($this->name ?? ''),
            'request_number' => (string) ($this->request_number ?? ''),
            'suspect_name' => (string) ($this->suspect_name ?? ''),
            'investigator_name' => (string) ($this->investigator_name ?? ''),
            'source' => $source,
            'created_at' => (string) ($this->created_at ?? ''),
            'download_url' => $this->download_url ?? null,
            'preview_url' => $this->preview_url ?? null,
        ];
    }
}
