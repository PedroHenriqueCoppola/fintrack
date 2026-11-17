<?php

namespace App\Http\Requests;

use App\Models\Bank;
use App\Models\Card;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Support\AnalysisFilters;
use App\Support\Money;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class AnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'period' => $this->query('period') ?: 'current_month',
            'sort' => $this->query('sort') ?: 'date_desc',
            'page' => $this->query('page') ?: 1,
        ];

        foreach ([
            'type', 'status', 'category_id', 'bank_id', 'card_id', 'payment_method_id',
            'min_amount', 'max_amount', 'q', 'from', 'to',
        ] as $key) {
            if ($this->query($key) === '') {
                $merge[$key] = null;
            }
        }

        if (is_string($this->query('q'))) {
            $query = trim($this->query('q'));
            $merge['q'] = $query === '' ? null : $query;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'period' => ['required', 'string', Rule::in(AnalysisFilters::PERIODS)],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['nullable', 'string', Rule::in(['income', 'expense'])],
            'status' => ['nullable', 'string', Rule::in(['paid', 'pending'])],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'bank_id' => ['nullable', 'integer', 'min:1'],
            'card_id' => ['nullable', 'integer', 'min:1'],
            'payment_method_id' => ['nullable', 'integer', 'min:1'],
            'min_amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'max_amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'q' => ['nullable', 'string', 'max:500'],
            'sort' => ['required', 'string', Rule::in(AnalysisFilters::SORTS)],
            'page' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period.in' => 'Escolha um período da lista.',
            'from.required_if' => 'Informe a data inicial.',
            'from.date_format' => 'Informe uma data inicial válida.',
            'to.required_if' => 'Informe a data final.',
            'to.date_format' => 'Informe uma data final válida.',
            'to.after_or_equal' => 'A data inicial não pode ser maior que a final.',
            'type.in' => 'Escolha Receita ou Despesa.',
            'status.in' => 'Escolha Paga ou Pendente.',
            'category_id.integer' => 'Escolha uma categoria.',
            'bank_id.integer' => 'Escolha um banco.',
            'card_id.integer' => 'Escolha um cartão.',
            'payment_method_id.integer' => 'Escolha uma forma de pagamento.',
            'min_amount.numeric' => 'Informe um valor mínimo válido.',
            'min_amount.gt' => 'O valor mínimo precisa ser maior que zero.',
            'min_amount.decimal' => 'Informe o valor mínimo com no máximo duas casas decimais.',
            'max_amount.numeric' => 'Informe um valor máximo válido.',
            'max_amount.gt' => 'O valor máximo precisa ser maior que zero.',
            'max_amount.decimal' => 'Informe o valor máximo com no máximo duas casas decimais.',
            'q.max' => 'A busca pode ter no máximo 500 caracteres.',
            'sort.in' => 'Escolha uma ordenação da lista.',
            'page.integer' => 'A página é inválida.',
            'page.min' => 'A página é inválida.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $min = $this->input('min_amount');
            $max = $this->input('max_amount');

            if ($min !== null && $max !== null && Money::compare($min, $max) === 1) {
                $validator->errors()->add('max_amount', 'O valor máximo não pode ser menor que o mínimo.');
            }

            $this->owned($validator, Category::class, $this->input('category_id'), 'category_id', 'Escolha uma categoria.');
            $this->owned($validator, Bank::class, $this->input('bank_id'), 'bank_id', 'Escolha um banco.');
            $this->owned($validator, Card::class, $this->input('card_id'), 'card_id', 'Escolha um cartão.');
            $this->owned($validator, PaymentMethod::class, $this->input('payment_method_id'), 'payment_method_id', 'Escolha uma forma de pagamento.');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $data = $this->validated();
        $period = $data['period'];

        return [
            'period' => $period,
            'from' => $period === 'custom' ? $data['from'] : null,
            'to' => $period === 'custom' ? $data['to'] : null,
            'type' => $data['type'] ?? null,
            'status' => $data['status'] ?? null,
            'category_id' => isset($data['category_id']) ? (int) $data['category_id'] : null,
            'bank_id' => isset($data['bank_id']) ? (int) $data['bank_id'] : null,
            'card_id' => isset($data['card_id']) ? (int) $data['card_id'] : null,
            'payment_method_id' => isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : null,
            'min_amount' => isset($data['min_amount']) ? Money::of($data['min_amount']) : null,
            'max_amount' => isset($data['max_amount']) ? Money::of($data['max_amount']) : null,
            'q' => $data['q'] ?? null,
            'sort' => $data['sort'],
            'page' => (int) $data['page'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('analysis')->withErrors($validator)
        );
    }

    /**
     * @param  class-string  $model
     */
    private function owned(Validator $validator, string $model, mixed $id, string $field, string $message): void
    {
        if ($id === null || $id === '') {
            return;
        }

        if (! $model::query()->whereKey($id)->exists()) {
            $validator->errors()->add($field, $message);
        }
    }
}
