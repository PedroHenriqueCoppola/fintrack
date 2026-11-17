<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InstallmentPreviewRequest extends FormRequest
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
            'total_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'installments_count' => ['required', 'integer', 'between:2,120'],
            'user_id' => ['missing'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'total_amount.required' => 'Informe o valor total.',
            'total_amount.numeric' => 'Informe o valor total.',
            'total_amount.gt' => 'Informe um valor maior que zero.',
            'total_amount.decimal' => 'Informe o valor total com no máximo duas casas decimais.',
            'total_amount.max' => 'O valor total é alto demais.',
            'installments_count.required' => 'Informe o número de parcelas.',
            'installments_count.integer' => 'Informe um número de 2 a 120.',
            'installments_count.between' => 'Informe um número de 2 a 120.',
            'user_id.missing' => 'O usuário não pode ser informado.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $total = (string) $this->input('total_amount');
            $count = (int) $this->input('installments_count');

            if ((int) Money::cents($total) < $count) {
                $validator->errors()->add('total_amount', 'O valor total é baixo demais para esse número de parcelas.');
            }
        });
    }
}
