<?php

namespace App\Http\Requests\Quality;

use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Support\QmhFormAnswersValidator;
use Illuminate\Foundation\Http\FormRequest;

class SaveQmhRevisionContentRequest extends FormRequest
{
    /**
     * @var array<string, string>
     */
    private array $answersErrors = [];

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('answers_json')) {
            return;
        }

        $revision = $this->route('revision');
        if (! $revision instanceof QmhDocumentRevision) {
            return;
        }

        $revision->loadMissing('document');
        if (($revision->document?->doc_type ?? '') !== 'formulir') {
            return;
        }

        $template = null;
        if ((int) ($revision->template_id ?? 0) > 0) {
            $template = QmhTemplate::query()->find((int) $revision->template_id);
        }

        $metadata = is_array($template?->metadata) ? $template->metadata : [];
        $schema = $metadata['form_schema'] ?? null;
        if (! is_array($schema)) {
            return;
        }

        $result = QmhFormAnswersValidator::validateAndNormalize($schema, $this->input('answers_json'));
        $this->answersErrors = $result['errors'];

        $this->merge([
            'answers_json' => $result['normalized'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content_html' => ['nullable', 'string'],
            'content_css' => ['nullable', 'string'],
            'editor_json' => ['nullable', 'array'],
            'answers_json' => ['nullable', 'array'],
            'effective_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $revision = $this->route('revision');
            $isFormulir = false;
            if ($revision instanceof QmhDocumentRevision) {
                $revision->loadMissing('document');
                $isFormulir = (($revision->document?->doc_type ?? '') === 'formulir');
            }

            $contentHtml = $this->input('content_html');
            $hasHtml = is_string($contentHtml) && trim($contentHtml) !== '';
            $hasAnswers = $this->has('answers_json') && is_array($this->input('answers_json'));

            if ($isFormulir) {
                if (! $hasHtml && ! $hasAnswers) {
                    $validator->errors()->add('answers_json', 'Jawaban formulir wajib diisi.');

                    return;
                }

                foreach ($this->answersErrors as $key => $message) {
                    $validator->errors()->add($key, $message);
                }

                return;
            }

            if (! $hasHtml) {
                $validator->errors()->add('content_html', 'Konten wajib diisi.');

                return;
            }
        });
    }
}
