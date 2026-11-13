<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 10;

    private const DECAY_SECONDS = 3600;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->ensureIsNotRateLimited();

        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email', ''))),
        ]);

        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
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
            'name.required' => 'Informe o nome.',
            'name.string' => 'Informe o nome.',
            'name.max' => 'O nome pode ter no máximo 255 caracteres.',
            'email.required' => 'Informe o e-mail.',
            'email.string' => 'Informe um e-mail válido.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail pode ter no máximo 255 caracteres.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'password.required' => 'Informe a senha.',
            'password.string' => 'Informe a senha.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'password_confirmation.required' => 'Confirme a senha.',
            'password_confirmation.same' => 'A confirmação da senha não confere.',
        ];
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Muitas tentativas de cadastro. Tente novamente em {$seconds} segundos.",
        ]);
    }

    protected function throttleKey(): string
    {
        return 'register|'.$this->ip();
    }
}
