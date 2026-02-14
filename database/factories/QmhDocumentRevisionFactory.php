<?php

namespace Database\Factories;

use App\Models\QmhDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QmhDocumentRevisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_id' => QmhDocument::factory(),
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => User::factory(),
            'created_at' => now(),
        ];
    }
}
