<?php

declare(strict_types=1);

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class RetryGowaUpdate extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('gowa-update.retry') === true;
    }

    public function rules(): array
    {
        return ['confirmation' => ['accepted']];
    }
}
