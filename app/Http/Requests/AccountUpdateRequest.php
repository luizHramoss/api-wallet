<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(Account::TYPES)],
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
        ];
    }
}
