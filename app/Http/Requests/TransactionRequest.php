<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:9999999999.99',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'O valor é obrigatório.',
            'amount.numeric'  => 'O valor deve ser numérico.',
            'amount.min'      => 'O valor mínimo permitido é R$ 0,01.',
            'amount.max'      => 'O valor excede o limite permitido.',
            'amount.regex'    => 'O valor deve ter no máximo 2 casas decimais.',
        ];
    }
}
