<?php

namespace App\Http\Requests;

use App\Models\InvestmentMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvestmentMovementStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(InvestmentMovement::TYPES)],
            'quantity' => ['required_unless:type,dividend', 'numeric', 'min:0.00000001'],
            'price' => ['required_unless:type,dividend', 'numeric', 'min:0.0001'],
            'amount' => ['required_if:type,dividend', 'numeric', 'min:0.01'],
            'occurred_at' => ['nullable', 'date', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'O tipo é obrigatório.',
            'type.in' => 'O tipo deve ser buy, sell ou dividend.',
            'quantity.required_unless' => 'A quantidade é obrigatória em compras e vendas.',
            'price.required_unless' => 'O preço é obrigatório em compras e vendas.',
            'amount.required_if' => 'O valor recebido é obrigatório em dividendos.',
            'occurred_at.date_format' => 'A data deve estar no formato YYYY-MM-DD.',
        ];
    }
}
