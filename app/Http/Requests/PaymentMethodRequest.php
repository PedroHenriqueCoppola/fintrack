<?php

namespace App\Http\Requests;

use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;

class PaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => PlainText::collapse($this->input('name'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new PlainText],
            'requires_card' => ['required', 'boolean'],
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
            'requires_card.required' => 'Informe se a forma de pagamento exige cartão.',
            'requires_card.boolean' => 'Informe se a forma de pagamento exige cartão.',
            'user_id.missing' => 'O usuário não pode ser informado.',
        ];
    }
}
