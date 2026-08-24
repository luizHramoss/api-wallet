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
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'O tipo deve ser income, expense ou transfer.',
            'status.in' => 'O status deve ser planned ou realized.',
            'category_id.exists' => 'Categoria não encontrada.',
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
