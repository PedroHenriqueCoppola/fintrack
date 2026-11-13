<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $editing = $category instanceof Category;
        $expense = $editing
            ? $category->applies_to === 'expense'
            : $this->input('applies_to') === 'expense';

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', Rule::in(array_column(config('fintrack.icons'), 'value'))],
            'color' => ['nullable', 'string', Rule::in(array_column(config('fintrack.colors'), 'value'))],
            'pocket_id' => [
                'nullable',
                'integer',
                Rule::exists('pockets', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id)->whereNull('archived_at');
                }),
            ],
            'initial_month' => ['missing'],
            'user_id' => ['missing'],
            'initial_balance' => $expense
                ? ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999999.99']
                : ['missing'],
        ];

        if ($editing) {
            $rules['applies_to'] = ['missing'];
            $rules['nature'] = ['missing'];

            return $rules;
        }

        $rules['applies_to'] = ['required', 'string', Rule::in(['expense', 'income'])];
        $rules['nature'] = [
            Rule::prohibitedIf(fn () => $this->input('applies_to') !== 'expense'),
            Rule::requiredIf(fn () => $this->input('applies_to') === 'expense'),
            'nullable',
            'string',
            Rule::in(['spending', 'reserve']),
        ];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome.',
            'name.string' => 'Informe o nome.',
            'name.max' => 'O nome pode ter no máximo 255 caracteres.',
            'applies_to.required' => 'Escolha o tipo.',
            'applies_to.string' => 'Escolha o tipo.',
            'applies_to.in' => 'Escolha Despesa ou Receita.',
            'applies_to.missing' => 'O tipo não pode ser alterado.',
            'nature.required' => 'Escolha a natureza.',
            'nature.required_if' => 'Escolha a natureza.',
            'nature.string' => 'Escolha a natureza.',
            'nature.in' => 'Escolha Gasto ou Reserva.',
            'nature.prohibited' => 'Receita não tem natureza.',
            'nature.missing' => 'A natureza não pode ser alterada.',
            'icon.string' => 'Escolha um ícone da lista.',
            'icon.in' => 'Escolha um ícone da lista.',
            'color.string' => 'Escolha uma cor da lista.',
            'color.in' => 'Escolha uma cor da lista.',
            'pocket_id.integer' => 'Escolha uma caixinha ativa.',
            'pocket_id.exists' => 'Escolha uma caixinha ativa.',
            'initial_balance.missing' => 'Receita não tem saldo inicial.',
            'initial_balance.numeric' => 'Informe o saldo inicial no formato 1.234,56.',
            'initial_balance.min' => 'O saldo inicial não pode ser negativo.',
            'initial_balance.decimal' => 'Informe o saldo inicial com no máximo duas casas decimais.',
            'initial_balance.max' => 'O saldo inicial é alto demais.',
            'initial_month.missing' => 'O mês inicial é definido pelo sistema.',
            'user_id.missing' => 'O usuário não pode ser informado.',
        ];
    }
}
