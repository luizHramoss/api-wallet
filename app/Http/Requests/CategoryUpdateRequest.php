<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(Category::TYPES)],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()?->id),
                function ($attribute, $value, $fail) {
                    if ((int) $value === (int) $this->route('category')) {
                        $fail('Uma categoria não pode ser subcategoria de si mesma.');
                    }
                },
            ],
            'icon' => ['nullable', 'string', 'max:10'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome da categoria é obrigatório.',
            'type.in' => 'O tipo deve ser income ou expense.',
            'parent_id.exists' => 'Categoria pai não encontrada.',
        ];
    }
}
