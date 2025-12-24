<?php

namespace Database\Factories;

use App\Enums\DocumentFormat;
use App\Enums\DocumentRenderEngine;
use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentTemplateFactory extends Factory
{
    protected $model = DocumentTemplate::class;

    public function definition(): array
    {
        $type = fake()->randomElement(DocumentType::cases());
        $format = fake()->randomElement($type->supportedFormats());
        $code = strtoupper("{$type->value}_{$format->value}_" . fake()->unique()->lexify('???'));
        
        return [
            'code' => $code,
            'type' => $type,
            'format' => $format,
            'name' => fake()->words(3, true),
            'storage_path' => "templates/{$type->value}/{$format->value}/" . Str::slug(fake()->words(2, true)) . ".{$format->value}",
            'content_html' => '<div class="doc-template"><h1>{{ $title ?? "Dokumen Contoh" }}</h1></div>',
            'content_css' => 'body { font-family: Arial, sans-serif; }',
            'editor_project' => json_encode([
                'pages' => [],
                'styles' => [],
            ]),
            'is_active' => false,
            'version' => fake()->unique()->numberBetween(1, 10000),
            'checksum' => md5(fake()->text()),
            'meta' => [
                'disk' => 'local',
                'uploaded_at' => now()->toIso8601String(),
            ],
            'render_engine' => DocumentRenderEngine::DOMPDF,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
