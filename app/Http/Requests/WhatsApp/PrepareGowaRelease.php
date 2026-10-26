<?php

declare(strict_types=1);

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

final class PrepareGowaRelease extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('gowa-update.request') === true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:64', 'regex:/^v\d+\.\d+\.\d+$/D'],
            'confirmation' => ['accepted'],
        ];
    }
}
