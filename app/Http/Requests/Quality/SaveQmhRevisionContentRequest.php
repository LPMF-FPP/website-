<?php

namespace App\Http\Requests\Quality;

use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Support\QmhFormAnswersValidator;
use App\Support\QmhFormSchemaValidator;
use Illuminate\Foundation\Http\FormRequest;

class SaveQmhRevisionContentRequest extends FormRequest
{
    /**
     * @var array<string, string>
     */
    private array $answersErrors = [];

    /**
     * @var array<int, string>
     */
    private array $schemaErrors = [];

    private bool $schemaJsonDecodeFailed = false;

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $rawSchema = $this->input('form_schema_json');
        if (is_string($rawSchema) && trim($rawSchema) === '') {
            $this->merge([
                'form_schema_json' => null,
            ]);
        }

        if (! $this->has('answers_json') && ! $this->has('form_schema_json')) {
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

        $overrideSchema = $this->input('form_schema_json');
        if (is_string($overrideSchema) && trim($overrideSchema) !== '') {
            try {
                $decoded = json_decode($overrideSchema, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $this->schemaJsonDecodeFailed = true;
                $decoded = null;
            }

            if (is_array($decoded)) {
                $overrideSchema = $decoded;
            } else {
                $this->schemaJsonDecodeFailed = true;
                $overrideSchema = null;
            }
        }

        if (is_array($overrideSchema)) {
            $this->schemaErrors = QmhFormSchemaValidator::errors($overrideSchema);
        }

        $template = null;
        if ((int) ($revision->template_id ?? 0) > 0) {
            $template = QmhTemplate::query()->find((int) $revision->template_id);
        }

        $metadata = is_array($template?->metadata) ? $template->metadata : [];

        $revisionSchema = is_array($revision->form_schema_json ?? null) ? $revision->form_schema_json : null;
        $templateSchema = $metadata['form_schema'] ?? null;
        $resolvedSchema = is_array($overrideSchema)
            ? $overrideSchema
            : ($revisionSchema ?? (is_array($templateSchema) ? $templateSchema : null));

        if (is_array($overrideSchema)) {
            // Only persist schema when client explicitly sends it.
            $this->merge([
                'form_schema_json' => $overrideSchema,
            ]);
        }

        if ($this->has('answers_json') && is_array($resolvedSchema)) {
            $requiredPolicy = $this->resolveRequiredPolicy($revision);

            $result = QmhFormAnswersValidator::validateAndNormalize(
                $resolvedSchema,
                $this->input('answers_json'),
                $requiredPolicy
            );
            $this->answersErrors = $result['errors'];

            $this->merge([
                'answers_json' => $result['normalized'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content_version' => ['required', 'integer', 'min:1'],
            'content_html' => ['nullable', 'string'],
            'content_css' => ['nullable', 'string'],
            'editor_json' => ['nullable', 'array'],
            'answers_json' => ['nullable', 'array'],
            'form_schema_json' => ['nullable', 'array'],
            'dibuat_oleh' => ['nullable', 'integer', 'exists:users,id'],
            'diperiksa_oleh' => ['nullable', 'integer', 'exists:users,id'],
            'disahkan_oleh' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $revision = $this->route('revision');

            // Validate SoD if signatories are provided
            $dibuat = (int) ($this->input('dibuat_oleh') ?: ($revision?->dibuat_oleh ?? 0));
            $diperiksa = (int) ($this->input('diperiksa_oleh') ?: ($revision?->diperiksa_oleh ?? 0));
            $disahkan = (int) ($this->input('disahkan_oleh') ?: ($revision?->disahkan_oleh ?? 0));

            if ($dibuat && $diperiksa && $dibuat === $diperiksa) {
                $validator->errors()->add('diperiksa_oleh', 'Pemeriksa tidak boleh sama dengan Pembuat.');
            }

            if ($dibuat && $disahkan && $dibuat === $disahkan) {
                $validator->errors()->add('disahkan_oleh', 'Pengesah tidak boleh sama dengan Pembuat.');
            }

            if ($diperiksa && $disahkan && $diperiksa === $disahkan) {
                $validator->errors()->add('disahkan_oleh', 'Pengesah tidak boleh sama dengan Pemeriksa.');
            }

            $isFormulir = false;
            if ($revision instanceof QmhDocumentRevision) {
                $revision->loadMissing('document');
                $isFormulir = (($revision->document?->doc_type ?? '') === 'formulir');
            }

            $contentHtml = $this->input('content_html');
            $hasHtml = is_string($contentHtml) && trim($contentHtml) !== '';
            $hasAnswers = $this->has('answers_json') && is_array($this->input('answers_json'));
            $hasSchema = $this->has('form_schema_json') && is_array($this->input('form_schema_json'));

            if ($isFormulir) {
                if (! $hasHtml && ! $hasAnswers && ! $hasSchema) {
                    $validator->errors()->add('answers_json', 'Jawaban formulir wajib diisi.');

                    return;
                }

                foreach ($this->answersErrors as $key => $message) {
                    $validator->errors()->add($key, $message);
                }

                if ($this->schemaJsonDecodeFailed) {
                    $validator->errors()->add('form_schema_json', 'Schema pertanyaan harus berupa JSON valid.');

                    return;
                }

                foreach ($this->schemaErrors as $message) {
                    $validator->errors()->add('form_schema_json', $message);
                }

                return;
            }

            if ($this->has('form_schema_json')) {
                $validator->errors()->add('form_schema_json', 'Schema pertanyaan hanya dapat diubah untuk dokumen Formulir (FR).');
            }

            // Task 6: Allow answers_json without content_html if answers exist (schema-first editing)
            if ($hasAnswers) {
                return;
            }

            if (! $hasHtml) {
                $validator->errors()->add('content_html', 'Konten wajib diisi.');

                return;
            }
        });
    }

    private function resolveRequiredPolicy(QmhDocumentRevision $revision): string
    {
        if (($revision->status ?? null) !== 'draft') {
            return QmhFormAnswersValidator::REQUIRED_POLICY_ENFORCE;
        }

        $route = $this->route();
        if ($route === null) {
            return QmhFormAnswersValidator::REQUIRED_POLICY_ENFORCE;
        }

        $actionName = (string) $route->getActionName();
        if ($actionName !== '' && str_ends_with($actionName, 'QmhRevisionWorkflowController@saveContent')) {
            return QmhFormAnswersValidator::REQUIRED_POLICY_ALLOW_PARTIAL;
        }

        $uri = (string) $route->uri();
        if ($this->isMethod('PUT') && str_ends_with($uri, 'quality/revisions/{revision}/content')) {
            return QmhFormAnswersValidator::REQUIRED_POLICY_ALLOW_PARTIAL;
        }

        return QmhFormAnswersValidator::REQUIRED_POLICY_ENFORCE;
    }
}
