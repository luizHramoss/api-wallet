<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\RecurringBill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecurringBillUpdateRequest extends FormRequest
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
                'sometimes',
                'integer',
                Rule::exists('accounts', 'id')->where('user_id', $userId),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $userId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(Category::TYPES)],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'day_of_month' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'start_date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::in(RecurringBill::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.exists' => 'Conta não encontrada.',
            'category_id.exists' => 'Categoria não encontrada.',
            'type.in' => 'O tipo deve ser income ou expense.',
            'amount.min' => 'O valor mínimo permitido é R$ 0,01.',
            'day_of_month.min' => 'O dia do mês deve ser entre 1 e 31.',
            'day_of_month.max' => 'O dia do mês deve ser entre 1 e 31.',
            'end_date.after_or_equal' => 'A data de término não pode ser anterior à data de início.',
        ];
    }
}
