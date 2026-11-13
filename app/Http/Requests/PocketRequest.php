<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PocketRequest extends FormRequest
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
            'type' => ['nullable', 'string', Rule::in(array_column(config('fintrack.pocket_types'), 'value'))],
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
            'type.string' => 'Escolha um tipo da lista.',
            'type.in' => 'Escolha um tipo da lista.',
            'color.string' => 'Escolha uma cor da lista.',
            'color.in' => 'Escolha uma cor da lista.',
            'user_id.missing' => 'O usuário não pode ser informado.',
        ];
    }
}
