<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlanningSettingsRequest extends FormRequest
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
            'high_impact_percent' => ['required', 'integer', 'between:1,100'],
            'user_id' => ['missing'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'high_impact_percent.required' => 'Informe a porcentagem.',
            'high_impact_percent.integer' => 'Informe um número inteiro de 1 a 100.',
            'high_impact_percent.between' => 'Informe um número inteiro de 1 a 100.',
            'user_id.missing' => 'O usuário não pode ser informado.',
        ];
    }
}
