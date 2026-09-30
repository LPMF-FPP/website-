<?php

declare(strict_types=1);

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

final class RequestGowaPreparation extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('gowa-update.request') === true;
    }

    public function rules(): array
    {
        return [
            'action_uuid' => ['required', 'uuid'],
        ];
    }
}
