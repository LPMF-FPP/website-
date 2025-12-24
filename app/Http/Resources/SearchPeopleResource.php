<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchPeopleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->type ?? 'investigator';

        if ($type === 'test_request') {
            return [
                'id' => (int) ($this->id ?? 0),
                'type' => 'test_request',
                'name' => (string) ($this->name ?? ''),
                'request_number' => (string) ($this->request_number ?? ''),
                'subtitle' => (string) ($this->subtitle ?? ''),
                'investigator' => (string) ($this->investigator ?? ''),
                'created_at' => (string) ($this->created_at ?? ''),
                'role_label' => 'Permintaan Pengujian',
            ];
        }

        // Investigator type
        return [
            'id' => (int) ($this->id ?? 0),
            'type' => 'investigator',
            'name' => (string) ($this->name ?? ''),
            'rank' => (string) ($this->rank ?? ''),
            'subtitle' => (string) ($this->subtitle ?? ''),
            'created_at' => (string) ($this->created_at ?? ''),
            'role_label' => 'Penyidik',
            'test_requests' => array_map(
                fn ($tr) => [
                    'id' => (int) ($tr['id'] ?? 0),
                    'request_number' => (string) ($tr['request_number'] ?? ''),
                    'suspect_name' => (string) ($tr['suspect_name'] ?? ''),
                ],
                is_array($this->test_requests ?? null) ? $this->test_requests : []
            ),
        ];
    }
}
