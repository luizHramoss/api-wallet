<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Account::TYPES)],
            'balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome da conta é obrigatório.',
            'name.max' => 'O nome da conta deve ter no máximo 255 caracteres.',
            'type.required' => 'O tipo da conta é obrigatório.',
            'type.in' => 'O tipo deve ser checking, savings, cash ou investment.',
            'balance.numeric' => 'O saldo inicial deve ser numérico.',
            'balance.min' => 'O saldo inicial não pode ser negativo.',
        ];
    }
}
