<?php

namespace App\Http\Requests;

use App\Services\CardInvoiceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PayInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['closing_month', 'paid_on'] as $key) {
            if (is_string($this->input($key))) {
                $merge[$key] = trim($this->input($key));
            }
        }

        $this->merge($merge);
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
            'paid_on' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
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
            'paid_on.required' => 'Informe a data do pagamento.',
            'paid_on.date_format' => 'Informe uma data válida.',
            'paid_on.before_or_equal' => 'A data não pode ser posterior a hoje.',
            'user_id.missing' => 'O usuário não pode ser informado.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $invoices = app(CardInvoiceService::class);
            $card = $invoices->ownedActiveCard((int) $this->input('card_id'));
            $errors = $invoices->paymentErrors(
                $card,
                (string) $this->input('closing_month'),
                (string) $this->input('paid_on'),
            );

            foreach ($errors as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        });
    }
}
