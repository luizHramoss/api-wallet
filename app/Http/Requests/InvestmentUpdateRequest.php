<?php

namespace App\Http\Requests;

use App\Models\Investment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvestmentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:20'],
            'type' => ['sometimes', 'required', Rule::in(Investment::TYPES)],
            'current_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'O tipo deve ser stock, fixed_income, fund, crypto ou other.',
            'current_price.numeric' => 'O preço atual deve ser numérico.',
            'current_price.min' => 'O preço atual não pode ser negativo.',
        ];
    }
}
