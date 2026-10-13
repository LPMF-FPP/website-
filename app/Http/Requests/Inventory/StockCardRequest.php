<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StockCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventori.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'item_id' => ['nullable', 'integer', 'min:1'],
            'lot_id' => ['nullable', 'integer', 'min:1'],
            'location_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => array_merge(['nullable', 'date_format:Y-m-d'], $this->filled('date_from') ? ['after_or_equal:date_from'] : []),
            'search' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.date_format' => 'Tanggal awal harus dalam format tahun-bulan-hari.',
            'date_to.date_format' => 'Tanggal akhir harus dalam format tahun-bulan-hari.',
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh mendahului tanggal awal.',
            '*.integer' => 'Pilihan filter harus berupa identitas yang valid.',
        ];
    }

    public function filters(): array
    {
        return array_filter($this->safe()->except('page'), fn ($value) => $value !== null && $value !== '');
    }
}
