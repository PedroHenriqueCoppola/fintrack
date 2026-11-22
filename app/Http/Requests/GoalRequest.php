<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Goal;
use App\Support\Month;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('name'))) {
            $merge['name'] = trim($this->input('name'));
        }

        if (is_string($this->input('notes'))) {
            $notes = trim($this->input('notes'));
            $merge['notes'] = $notes === '' ? null : $notes;
        } elseif ($this->input('notes') === '') {
            $merge['notes'] = null;
        }

        $deadline = $this->input('deadline');

        if ($deadline === '' || $deadline === null) {
            $merge['deadline'] = null;
        } elseif (is_string($deadline) && preg_match('/^(0[1-9]|1[0-2])\/(\d{4})$/', trim($deadline), $match)) {
            $merge['deadline'] = $match[2].'-'.$match[1];
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'target_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'category_id' => ['required', 'integer'],
            'deadline' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'notes' => ['nullable', 'string', 'max:255'],
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
            'name.max' => 'O nome pode ter no máximo 60 caracteres.',
            'target_amount.required' => 'Informe o valor alvo.',
            'target_amount.numeric' => 'Informe o valor alvo.',
            'target_amount.gt' => 'Informe um valor maior que zero.',
            'target_amount.decimal' => 'Informe o valor com no máximo duas casas decimais.',
            'target_amount.max' => 'O valor é alto demais.',
            'category_id.required' => 'Escolha a categoria.',
            'category_id.integer' => 'Escolha a categoria.',
            'deadline.regex' => 'Informe um mês válido.',
            'notes.max' => 'A observação pode ter no máximo 255 caracteres.',
            'user_id.missing' => 'O usuário não pode ser informado.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateCategory($validator);
            $this->validateDeadline($validator);
        });
    }

    private function validateCategory(Validator $validator): void
    {
        $goal = $this->currentGoal();
        $category = Category::query()->find($this->integer('category_id'));

        if (! $category instanceof Category || $category->applies_to !== 'expense' || $category->nature !== 'reserve') {
            $validator->errors()->add('category_id', 'Escolha uma categoria de Reserva.');

            return;
        }

        $keeping = $goal instanceof Goal && (int) $goal->category_id === (int) $category->id;

        if ($category->archived_at !== null && ! $keeping) {
            $validator->errors()->add('category_id', 'Escolha uma categoria de Reserva ativa.');

            return;
        }

        $taken = Goal::query()
            ->where('category_id', $category->id)
            ->whereNull('archived_at')
            ->when($goal, fn ($query) => $query->whereKeyNot($goal->id))
            ->exists();

        if ($taken) {
            $validator->errors()->add(
                'category_id',
                'Já existe uma meta para essa categoria. Arquive a outra para criar esta.',
            );
        }
    }

    private function validateDeadline(Validator $validator): void
    {
        $key = $this->input('deadline');

        if (! is_string($key) || $key === '') {
            return;
        }

        if (Month::startOrNull($key) === null) {
            $validator->errors()->add('deadline', 'Informe um mês entre 01/2000 e 12/2100.');

            return;
        }

        if ($key >= Month::currentKey()) {
            return;
        }

        $existing = $this->currentGoal()?->deadline?->format('Y-m');

        if ($existing !== $key) {
            $validator->errors()->add('deadline', 'O prazo não pode ser anterior ao mês atual.');
        }
    }

    private function currentGoal(): ?Goal
    {
        $goal = $this->route('goal');

        return $goal instanceof Goal ? $goal : null;
    }
}
