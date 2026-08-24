<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * type e account_id não são editáveis por aqui - mudar o tipo/conta de
     * uma transação já realizada é, na prática, uma operação diferente
     * (exclua e crie novamente). Ver TransactionService::update().
     */
    public function rules(): array
    {
        return [
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()?->id),
            ],
            'status' => ['sometimes', Rule::in(Transaction::STATUSES)],
            'amount' => [
                'sometimes',
                'numeric',
                'min:0.01',
                'max:9999999999.99',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['sometimes', 'date', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'Categoria não encontrada.',
            'status.in' => 'O status deve ser planned ou realized.',
            'amount.numeric' => 'O valor deve ser numérico.',
            'amount.min' => 'O valor mínimo permitido é R$ 0,01.',
            'amount.regex' => 'O valor deve ter no máximo 2 casas decimais.',
            'occurred_at.date_format' => 'A data deve estar no formato YYYY-MM-DD.',
        ];
    }
}
