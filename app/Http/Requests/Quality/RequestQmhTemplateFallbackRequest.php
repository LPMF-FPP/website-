<?php

namespace App\Http\Requests\Quality;

use App\Support\QmhFrLayoutProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestQmhTemplateFallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'layout_profile' => ['nullable', Rule::in(QmhFrLayoutProfile::allowedProfiles())],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
