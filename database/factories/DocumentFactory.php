<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Investigator;
use App\Models\TestRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $filename = Str::slug($this->faker->sentence(3)) . '.pdf';
        $path = 'investigators/' . $this->faker->uuid . '/' . $filename;

        return [
            'investigator_id' => Investigator::factory(),
            'test_request_id' => TestRequest::factory(),
            'document_type' => 'request_letter',
            'source' => 'upload',
            'storage_disk' => 'public',
            'filename' => $filename,
            'original_filename' => $filename,
            'file_path' => $path,
            'path' => $path,
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'extra' => [],
        ];
    }

    public function generated(): static
    {
        return $this->state(fn () => ['source' => 'generated']);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Document $document) {
            if (!$document->investigator_id && $document->test_request_id) {
                $document->investigator_id = $document->testRequest?->investigator_id;
                $document->save();
            }
        });
    }
}
