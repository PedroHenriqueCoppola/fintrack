<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPaymentTemplate;
use App\Models\FixedExpense;
use App\Rules\PlainText;
use App\Support\Month;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FixedExpenseRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:120', new PlainText],
            'category_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'start_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'end_month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'bank_id' => ['nullable', 'integer'],
            'payment_method_id' => ['required', 'integer'],
            'card_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500', new PlainText],
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
            'name.max' => 'O nome pode ter no máximo 120 caracteres.',
            'category_id.required' => 'Escolha a categoria.',
            'category_id.integer' => 'Escolha a categoria.',
            'amount.required' => 'Informe o valor.',
            'amount.numeric' => 'Informe o valor.',
            'amount.gt' => 'Informe um valor maior que zero.',
            'amount.decimal' => 'Informe o valor com no máximo duas casas decimais.',
            'amount.max' => 'O valor é alto demais.',
            'due_day.required' => 'Informe o dia do vencimento.',
            'due_day.integer' => 'Informe um dia de 1 a 31.',
            'due_day.between' => 'Informe um dia de 1 a 31.',
            'start_month.required' => 'Informe o mês de início.',
            'start_month.regex' => 'Informe um mês entre 01/2000 e 12/2100.',
            'end_month.regex' => 'Informe um mês entre 01/2000 e 12/2100.',
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

            $current = $this->route('fixedExpense');
            $current = $current instanceof FixedExpense ? $current : null;

            $this->validateSpendingCategory($validator, $current?->category_id);
            $this->validatePayment(
                $validator,
                $current?->payment_method_id,
                $current?->bank_id,
                $current?->card_id,
            );

            $start = Month::startOrNull((string) $this->input('start_month'));
            $end = $this->input('end_month') ? Month::startOrNull((string) $this->input('end_month')) : null;

            if ($start === null) {
                $validator->errors()->add('start_month', 'Informe um mês entre 01/2000 e 12/2100.');
            }

            if ($this->input('end_month') && $end === null) {
                $validator->errors()->add('end_month', 'Informe um mês entre 01/2000 e 12/2100.');
            }

            if ($start !== null && $end !== null && $end < $start) {
                $validator->errors()->add('end_month', 'O mês de fim não pode ser anterior ao mês de início.');
            }
        });
    }
}
