<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where('user_id', $userId),
            ],
            'to_account_id' => [
                'required_if:type,transfer',
                'integer',
                Rule::exists('accounts', 'id')->where('user_id', $userId),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $userId),
            ],
            'type' => ['required', Rule::in(Transaction::TYPES)],
            'status' => ['nullable', Rule::in(Transaction::STATUSES)],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:9999999999.99',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'A conta é obrigatória.',
            'account_id.exists' => 'Conta não encontrada.',
            'to_account_id.required_if' => 'A conta de destino é obrigatória em transferências.',
            'to_account_id.exists' => 'Conta de destino não encontrada.',
            'category_id.exists' => 'Categoria não encontrada.',
            'type.required' => 'O tipo é obrigatório.',
            'type.in' => 'O tipo deve ser income, expense ou transfer.',
            'status.in' => 'O status deve ser planned ou realized.',
            'amount.required' => 'O valor é obrigatório.',
            'amount.numeric' => 'O valor deve ser numérico.',
            'amount.min' => 'O valor mínimo permitido é R$ 0,01.',
            'amount.regex' => 'O valor deve ter no máximo 2 casas decimais.',
            'occurred_at.date_format' => 'A data deve estar no formato YYYY-MM-DD.',
        ];
    }
}
