<?php

namespace App\Http\Requests\Quality;

use App\Models\QmhDocument;
use App\Models\QmhTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQmhDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'doc_code' => ['required', 'string', 'max:100', 'unique:qmh_documents,doc_code'],
            'title' => ['required', 'string', 'max:255'],
            'clause' => ['required', 'integer', Rule::in([4, 5, 6, 7, 8])],
            'doc_type' => ['required', Rule::in(['sop', 'ik', 'fr', 'formulir'])],
            'template_id' => [
                'required',
                'integer',
                Rule::exists('qmh_templates', 'id')->where(function ($query) {
                    $query
                        ->where('doc_type', $this->normalizedTemplateDocType())
                        ->where('is_active', true);
                }),
            ],
            'parent_sop_id' => ['nullable', 'integer', 'exists:qmh_documents,id'],
            'paired_ik_id' => ['nullable', 'integer', 'exists:qmh_documents,id'],
            'change_summary' => ['nullable', 'string'],
            'effective_date' => ['nullable', 'date'],
            'editor_json' => ['nullable', 'array'],
            'answers_json' => ['nullable', 'array'],
            'content_html' => ['nullable', 'string'],
            'content_css' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $docType = $this->normalizedTemplateDocType();
            $parentSopId = $this->input('parent_sop_id');
            $pairedIkId = $this->input('paired_ik_id');

            if ($docType === 'sop') {
                if ($parentSopId !== null) {
                    $validator->errors()->add('parent_sop_id', 'SOP tidak boleh memiliki parent SOP.');
                }
                if ($pairedIkId !== null) {
                    $validator->errors()->add('paired_ik_id', 'SOP tidak boleh dipasangkan dengan IK.');
                }

                return;
            }

            if ($docType === 'ik') {
                if ($parentSopId === null) {
                    $validator->errors()->add('parent_sop_id', 'IK wajib memiliki parent SOP.');
                }

                if ($pairedIkId !== null) {
                    $validator->errors()->add('paired_ik_id', 'IK tidak boleh memiliki pasangan IK.');
                }
            }

            if ($docType === 'fr' && $parentSopId === null) {
                $validator->errors()->add('parent_sop_id', 'FR wajib memiliki parent SOP.');
            }

            $parentSop = null;
            if ($parentSopId !== null) {
                $parentSop = QmhDocument::query()->find((int) $parentSopId);
                if ($parentSop === null || $parentSop->doc_type !== 'sop') {
                    $validator->errors()->add('parent_sop_id', 'Parent dokumen harus berupa SOP.');
                }

                if ($parentSop !== null && (int) $parentSop->clause !== (int) $this->input('clause')) {
                    $validator->errors()->add('parent_sop_id', 'Parent SOP harus berada pada klausul yang sama.');
                }
            }

            if ($pairedIkId !== null) {
                if ($docType !== 'fr') {
                    $validator->errors()->add('paired_ik_id', 'Hanya FR yang boleh dipasangkan ke IK.');

                    return;
                }

                $pairedIk = QmhDocument::query()->find((int) $pairedIkId);
                if ($pairedIk === null || $pairedIk->doc_type !== 'ik') {
                    $validator->errors()->add('paired_ik_id', 'Pasangan wajib berupa dokumen IK.');

                    return;
                }

                if ($parentSop !== null && (int) $pairedIk->parent_sop_id !== (int) $parentSop->id) {
                    $validator->errors()->add('paired_ik_id', 'FR dan IK pasangan wajib berada pada parent SOP yang sama.');
                }
            }
        });
    }

    public function template(): ?QmhTemplate
    {
        $templateId = $this->integer('template_id');
        if ($templateId <= 0) {
            return null;
        }

        return QmhTemplate::query()->find($templateId);
    }

    private function normalizedTemplateDocType(): string
    {
        return match ($this->input('doc_type')) {
            'formulir', 'fr' => 'fr',
            default => (string) $this->input('doc_type'),
        };
    }
}
