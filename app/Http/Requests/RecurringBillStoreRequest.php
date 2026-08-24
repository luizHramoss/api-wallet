<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\RecurringBill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecurringBillStoreRequest extends FormRequest
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
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $userId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Category::TYPES)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'day_of_month' => ['required', 'integer', 'min:1', 'max:31'],
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(RecurringBill::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'A conta é obrigatória.',
            'account_id.exists' => 'Conta não encontrada.',
            'category_id.exists' => 'Categoria não encontrada.',
            'name.required' => 'O nome é obrigatório.',
            'type.required' => 'O tipo é obrigatório.',
            'type.in' => 'O tipo deve ser income ou expense.',
            'amount.required' => 'O valor é obrigatório.',
            'amount.min' => 'O valor mínimo permitido é R$ 0,01.',
            'day_of_month.required' => 'O dia do mês é obrigatório.',
            'day_of_month.min' => 'O dia do mês deve ser entre 1 e 31.',
            'day_of_month.max' => 'O dia do mês deve ser entre 1 e 31.',
            'start_date.required' => 'A data de início é obrigatória.',
            'end_date.after_or_equal' => 'A data de término não pode ser anterior à data de início.',
        ];
    }
}
