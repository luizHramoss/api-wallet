<?php

namespace App\Http\Requests;

use App\Models\Investment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvestmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where('user_id', $this->user()?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:20'],
            'type' => ['required', Rule::in(Investment::TYPES)],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'A conta é obrigatória.',
            'account_id.exists' => 'Conta não encontrada.',
            'name.required' => 'O nome do ativo é obrigatório.',
            'type.required' => 'O tipo é obrigatório.',
            'type.in' => 'O tipo deve ser stock, fixed_income, fund, crypto ou other.',
        ];
    }
}
