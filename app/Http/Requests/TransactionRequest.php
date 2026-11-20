<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Transaction;
use App\Support\CatalogGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['competence_date', 'notes', 'card_id', 'bank_id'] as $key) {
            if ($this->input($key) === '') {
                $merge[$key] = null;
            }
        }

        if (is_string($this->input('description'))) {
            $merge['description'] = trim($this->input('description'));
        }

        if (is_string($this->input('notes'))) {
            $notes = trim($this->input('notes'));
            $merge['notes'] = $notes === '' ? null : $notes;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['income', 'expense'])],
            'status' => ['required', 'string', Rule::in(['paid', 'pending'])],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'date' => ['bail', 'required', 'date_format:Y-m-d'],
            'competence_date' => ['bail', 'nullable', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'category_id' => ['required', 'integer'],
            'bank_id' => ['nullable', 'integer'],
            'payment_method_id' => ['required', 'integer'],
            'card_id' => ['nullable', 'integer'],
            'apply_amount_forward' => ['sometimes', 'boolean'],
            'user_id' => ['missing'],
            'fixed_expense_id' => ['missing'],
            'installment_id' => ['missing'],
            'installment_number' => ['missing'],
            'generated_for' => ['missing'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Escolha Despesa ou Receita.',
            'type.in' => 'Escolha Despesa ou Receita.',
            'status.required' => 'Escolha Paga ou Pendente.',
            'status.in' => 'Escolha Paga ou Pendente.',
            'amount.required' => 'Informe o valor.',
            'amount.numeric' => 'Informe o valor.',
            'amount.gt' => 'Informe um valor maior que zero.',
            'amount.decimal' => 'Informe o valor com no máximo duas casas decimais.',
            'amount.max' => 'O valor é alto demais.',
            'date.required' => 'Informe a data.',
            'date.date_format' => 'Informe uma data válida.',
            'competence_date.date_format' => 'Informe uma data válida.',
            'description.required' => 'Informe a descrição.',
            'description.max' => 'A descrição pode ter no máximo 120 caracteres.',
            'notes.max' => 'A observação pode ter no máximo 500 caracteres.',
            'category_id.required' => 'Escolha a categoria.',
            'category_id.integer' => 'Escolha a categoria.',
            'bank_id.integer' => 'Escolha o banco.',
            'payment_method_id.required' => 'Escolha a forma de pagamento.',
            'payment_method_id.integer' => 'Escolha a forma de pagamento.',
            'card_id.integer' => 'Escolha o cartão.',
            'apply_amount_forward.boolean' => 'A opção de valor daqui em diante é inválida.',
            'user_id.missing' => 'O usuário não pode ser informado.',
            'fixed_expense_id.missing' => 'Esse campo é definido pelo sistema.',
            'installment_id.missing' => 'Esse campo é definido pelo sistema.',
            'installment_number.missing' => 'Esse campo é definido pelo sistema.',
            'generated_for.missing' => 'Esse campo é definido pelo sistema.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateDate($validator);
            $this->validateRelations($validator);
        });
    }

    private function validateDate(Validator $validator): void
    {
        $date = $this->input('date');

        if (! is_string($date)) {
            return;
        }

        $today = now('America/Sao_Paulo')->toDateString();

        if ($this->input('status') === 'paid' && $date > $today) {
            $validator->errors()->add('date', 'Transação paga não pode ter data futura.');

            return;
        }

        if ($date > $this->latestDate($today)) {
            $validator->errors()->add('date', 'A data não pode ser mais de 5 anos à frente.');
        }
    }

    private function latestDate(string $today): string
    {
        $current = new \DateTimeImmutable($today);
        $year = (int) $current->format('Y') + 5;
        $month = (int) $current->format('n');
        $day = (int) $current->format('j');
        $last = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');

        return sprintf('%04d-%02d-%02d', $year, $month, min($day, $last));
    }

    private function validateRelations(Validator $validator): void
    {
        $existing = $this->route('transaction');
        $current = $existing instanceof Transaction ? $existing : null;
        $type = $this->input('type');

        $category = Category::query()->find($this->integer('category_id'));

        if (! $category instanceof Category || ! $this->usable($category, $current?->category_id)) {
            $validator->errors()->add('category_id', 'Escolha uma categoria ativa.');
        } elseif ($category->applies_to !== $type) {
            $validator->errors()->add(
                'category_id',
                $type === 'income' ? 'Escolha uma categoria de receita.' : 'Escolha uma categoria de despesa.',
            );
        } elseif ($type === 'expense' && $category->nature !== 'spending') {
            $validator->errors()->add('category_id', 'Categorias de Reserva não aceitam gastos.');
        }

        CatalogGuard::payment(
            $validator,
            $this->input('payment_method_id'),
            $this->input('bank_id'),
            $this->input('card_id'),
            $current?->payment_method_id,
            $current?->bank_id,
            $current?->card_id,
        );

        if ($this->boolean('apply_amount_forward') && ! ($current instanceof Transaction && $current->fixed_expense_id)) {
            $validator->errors()->add('apply_amount_forward', 'Essa opção vale só para uma despesa gerada por gasto fixo.');
        }
    }

    private function usable(object $model, mixed $currentId): bool
    {
        if ($model->archived_at === null) {
            return true;
        }

        return $currentId !== null && (int) $currentId === (int) $model->getKey();
    }
}
