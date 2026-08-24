<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['income', 'expense', 'transfer'])],
            'status' => ['nullable', Rule::in(['planned', 'realized'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('user_id', $this->user()?->id),
            ],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            // 'boolean' rejeita a string "true"/"false" (só aceita
            // true/false/1/0/"1"/"0") - query strings sempre chegam como
            // string, então aceitamos ambas as formas explicitamente.
            'is_recurring' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'O tipo deve ser income, expense ou transfer.',
            'status.in' => 'O status deve ser planned ou realized.',
            'category_id.exists' => 'Categoria não encontrada.',
            'is_recurring.boolean' => 'O filtro is_recurring deve ser verdadeiro ou falso.',
            'date_from.date' => 'Data inicial inválida.',
            'date_from.date_format' => 'A data inicial deve estar no formato YYYY-MM-DD.',
            'date_to.date' => 'Data final inválida.',
            'date_to.date_format' => 'A data final deve estar no formato YYYY-MM-DD.',
            'date_to.after_or_equal' => 'A data final não pode ser menor que a data inicial.',
            'per_page.integer' => 'O campo per_page deve ser um número inteiro.',
            'per_page.min' => 'O campo per_page deve ser no mínimo 1.',
            'per_page.max' => 'O campo per_page deve ser no máximo 100.',
        ];
    }
}
