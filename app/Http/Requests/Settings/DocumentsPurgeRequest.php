<?php

namespace App\Http\Requests\Settings;

class DocumentsPurgeRequest extends DocumentsPurgePreviewRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'confirm_text' => ['required', 'in:HAPUS DOKUMEN'],
            'current_password' => ['required', 'current_password:web'],
        ]);
    }
}
