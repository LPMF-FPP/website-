<?php

declare(strict_types=1);

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class RequestGowaUpdate extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('gowa-update.request') === true;
    }

    public function rules(): array
    {
        return [
            'release_id' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'action_uuid' => ['required', 'uuid'],
            'confirmation' => ['accepted'],
        ];
    }
}
