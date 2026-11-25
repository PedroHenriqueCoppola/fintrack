<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPaymentTemplate;
use App\Models\Installment;
use App\Services\InstallmentService;
use App\Rules\PlainText;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InstallmentRequest extends FormRequest
{
    use ValidatesPaymentTemplate;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTemplateFields();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $installment = $this->route('installment');
        $locked = $installment instanceof Installment
            && app(InstallmentService::class)->hasGenerated($installment);

        $rules = [
            'name' => ['required', 'string', 'max:120', new PlainText],
            'category_id' => ['required', 'integer'],
            'bank_id' => ['nullable', 'integer'],
            'payment_method_id' => ['required', 'integer'],
            'card_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500', new PlainText],
            'user_id' => ['missing'],
        ];

        if ($locked) {
            $rules['total_amount'] = ['missing'];
            $rules['installments_count'] = ['missing'];
            $rules['first_date'] = ['missing'];

            return $rules;
        }

        $rules['total_amount'] = ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'];
        $rules['installments_count'] = ['required', 'integer', 'between:2,120'];
        $rules['first_date'] = ['required', 'date_format:Y-m-d'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome.',
            'name.max' => 'O nome pode ter no máximo 120 caracteres.',
            'category_id.required' => 'Escolha a categoria.',
            'category_id.integer' => 'Escolha a categoria.',
            'total_amount.required' => 'Informe o valor total.',
            'total_amount.numeric' => 'Informe o valor total.',
            'total_amount.gt' => 'Informe um valor maior que zero.',
            'total_amount.decimal' => 'Informe o valor total com no máximo duas casas decimais.',
            'total_amount.max' => 'O valor total é alto demais.',
            'total_amount.missing' => 'Para mudar o valor ou as parcelas, arquive e crie outro.',
            'installments_count.required' => 'Informe o número de parcelas.',
            'installments_count.integer' => 'Informe um número de 2 a 120.',
            'installments_count.between' => 'Informe um número de 2 a 120.',
            'installments_count.missing' => 'Para mudar o valor ou as parcelas, arquive e crie outro.',
            'first_date.required' => 'Informe a data da primeira parcela.',
            'first_date.date_format' => 'Informe uma data válida.',
            'first_date.missing' => 'Para mudar o valor ou as parcelas, arquive e crie outro.',
            'bank_id.integer' => 'Escolha o banco.',
            'payment_method_id.required' => 'Escolha a forma de pagamento.',
            'payment_method_id.integer' => 'Escolha a forma de pagamento.',
            'card_id.integer' => 'Escolha o cartão.',
            'notes.max' => 'A observação pode ter no máximo 500 caracteres.',
            'user_id.missing' => 'O usuário não pode ser informado.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $current = $this->route('installment');
            $current = $current instanceof Installment ? $current : null;

            $this->validateSpendingCategory($validator, $current?->category_id);
            $this->validatePayment(
                $validator,
                $current?->payment_method_id,
                $current?->bank_id,
                $current?->card_id,
            );

            if ($current instanceof Installment && app(InstallmentService::class)->hasGenerated($current)) {
                return;
            }

            $total = (string) $this->input('total_amount');
            $count = (int) $this->input('installments_count');

            if ($count >= 2 && $count <= 120 && (int) Money::cents($total) < $count) {
                $validator->errors()->add('total_amount', 'O valor total é baixo demais para esse número de parcelas.');
            }
        });
    }
}
