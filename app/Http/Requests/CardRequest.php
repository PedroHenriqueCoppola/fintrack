<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CardRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'bank_id' => [
                'required',
                'integer',
                Rule::exists('banks', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id)->whereNull('archived_at');
                }),
            ],
            'closing_day' => ['required', 'integer', 'between:1,31'],
            'due_day' => ['nullable', 'integer', 'between:1,31'],
            'color' => ['nullable', 'string', Rule::in(array_column(config('fintrack.colors'), 'value'))],
            'user_id' => ['missing'],
        ];
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
            'bank_id.required' => 'Escolha o banco.',
            'bank_id.integer' => 'Escolha um banco ativo.',
            'bank_id.exists' => 'Escolha um banco ativo.',
            'closing_day.required' => 'Informe o dia de fechamento.',
            'closing_day.integer' => 'O dia de fechamento deve estar entre 1 e 31.',
            'closing_day.between' => 'O dia de fechamento deve estar entre 1 e 31.',
            'due_day.integer' => 'O dia de vencimento deve estar entre 1 e 31.',
            'due_day.between' => 'O dia de vencimento deve estar entre 1 e 31.',
            'color.string' => 'Escolha uma cor da lista.',
            'color.in' => 'Escolha uma cor da lista.',
            'user_id.missing' => 'O usuário não pode ser informado.',
        ];
    }
}
