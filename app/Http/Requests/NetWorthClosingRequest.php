<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesClosingMonth;
use App\Models\Bank;
use App\Models\NetWorthClosing;
use App\Services\NetWorthService;
use App\Support\Month;
use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;

class NetWorthClosingRequest extends FormRequest
{
    use ValidatesClosingMonth;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('notes'))) {
            return;
        }

        $notes = PlainText::collapse($this->input('notes'));
        $this->merge(['notes' => $notes === '' ? null : $notes]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'notes' => ['nullable', 'string', 'max:500', new PlainText],
            'items' => ['required', 'array'],
            'items.*.bank_id' => ['required', 'integer', 'min:1'],
            'items.*.balance' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'user_id' => ['missing'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'month.required' => 'Informe um mês válido.',
            'month.regex' => 'Informe um mês válido.',
            'notes.max' => 'A observação pode ter no máximo 500 caracteres.',
            'items.required' => 'Informe o saldo de todos os bancos.',
            'items.array' => 'Informe o saldo de todos os bancos.',
            'items.*.bank_id.required' => 'Escolha um banco.',
            'items.*.bank_id.integer' => 'Escolha um banco.',
            'items.*.bank_id.min' => 'Escolha um banco.',
            'items.*.balance.required' => 'Informe o saldo.',
            'items.*.balance.numeric' => 'Informe um saldo válido.',
            'items.*.balance.decimal' => 'Informe o saldo com no máximo duas casas decimais.',
            'items.*.balance.min' => 'O saldo não pode ser negativo.',
            'items.*.balance.max' => 'O saldo é alto demais.',
            'user_id.missing' => 'O usuário não pode ser informado.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $month = $this->input('month');
            $monthError = $this->closingMonthError($month);

            if ($monthError !== null) {
                if (! $validator->errors()->has('month')) {
                    $validator->errors()->add('month', $monthError);
                }

                return;
            }

            $start = Month::start($month);
            $current = $this->route('closing');
            $currentId = $current instanceof NetWorthClosing ? $current->id : null;
            $existingId = NetWorthClosing::query()->where('month', $start)->value('id');

            if ($existingId !== null && (int) $existingId !== $currentId) {
                $validator->errors()->add('month', 'Já existe um fechamento de '.Month::phrase($month).'. Edite-o.');

                return;
            }

            $this->validateItems($validator, $month);
        });
    }

    /**
     * @return array{month: string, notes: ?string, items: array<int, array{bank_id: int, balance: string}>}
     */
    public function closing(): array
    {
        $data = $this->validated();

        return [
            'month' => $data['month'],
            'notes' => $data['notes'] ?? null,
            'items' => array_map(fn (array $item) => [
                'bank_id' => (int) $item['bank_id'],
                'balance' => (string) $item['balance'],
            ], $data['items']),
        ];
    }

    private function validateItems($validator, string $month): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        $expected = array_map(
            fn (array $bank) => (int) $bank['bank_id'],
            app(NetWorthService::class)->form($month)['banks'],
        );
        $seen = [];

        foreach ($items as $index => $item) {
            if (! is_array($item) || ! is_numeric($item['bank_id'] ?? null)) {
                continue;
            }

            $bankId = (int) $item['bank_id'];

            if (isset($seen[$bankId])) {
                $validator->errors()->add("items.$index.bank_id", 'Não repita o mesmo banco.');

                continue;
            }

            $seen[$bankId] = true;

            if (! Bank::query()->whereKey($bankId)->exists()) {
                $validator->errors()->add("items.$index.bank_id", 'Escolha um banco.');
            } elseif (! in_array($bankId, $expected, true)) {
                $validator->errors()->add("items.$index.bank_id", 'Este banco não entra neste fechamento.');
            }
        }

        foreach ($expected as $bankId) {
            if (! isset($seen[$bankId])) {
                $validator->errors()->add('items', 'Informe o saldo de todos os bancos.');

                break;
            }
        }
    }
}
