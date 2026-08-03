<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // scope check handled by ValidateJwt:inventory:admin middleware
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'exists:products,sku'],
            'delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
