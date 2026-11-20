<?php

namespace App\Http\Requests;

use App\Services\CardInvoiceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UnpayInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('closing_month'))) {
            $this->merge([
                'closing_month' => trim($this->input('closing_month')),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'card_id' => ['bail', 'required', 'integer'],
            'closing_month' => ['bail', 'required', 'date_format:Y-m-d', function (string $attribute, mixed $value, \Closure $fail) {
                if (is_string($value) && ! str_ends_with($value, '-01')) {
                    $fail('O mês do fechamento deve ser o dia 1.');
                }
            }],
            'user_id' => ['missing'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'card_id.required' => 'Escolha um cartão.',
            'card_id.integer' => 'Escolha um cartão.',
            'closing_month.required' => 'Informe o mês do fechamento.',
            'closing_month.date_format' => 'Informe o mês do fechamento.',
            'user_id.missing' => 'O usuário não pode ser informado.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            app(CardInvoiceService::class)->ownedActiveCard((int) $this->input('card_id'));
        });
    }
}
