<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfilePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'string', 'same:password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Informe a senha atual.',
            'password.required' => 'Informe a senha.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'password_confirmation.required' => 'Confirme a senha.',
            'password_confirmation.same' => 'A confirmação da senha não confere.',
        ];
    }
}
