<?php

namespace App\Http\Requests;

use App\Models\Bank;
use App\Models\Card;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Tag;
use App\Support\AnalysisFilters;
use App\Support\CatalogGuard;
use App\Rules\PlainText;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavedFilterRequest extends FormRequest
{
    /** @var array<string, mixed> */
    private array $storedFilters = [];

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
            'name' => ['required', 'string', 'max:40', new PlainText],
            'include_period' => ['required', 'boolean'],
            'filters' => ['present', 'array'],
            'filters.type' => ['nullable', 'string', Rule::in(['income', 'expense', 'reserve'])],
            'filters.status' => ['nullable', 'string', Rule::in(['paid', 'pending'])],
            'filters.category_id' => ['nullable', 'integer', 'min:1'],
            'filters.bank_id' => ['nullable', 'integer', 'min:1'],
            'filters.card_id' => ['nullable', 'integer', 'min:1'],
            'filters.payment_method_id' => ['nullable', 'integer', 'min:1'],
            'filters.tags' => ['nullable', 'array', 'max:20'],
            'filters.tags.*' => ['integer', 'min:1'],
            'filters.min_amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'filters.max_amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'filters.q' => ['nullable', 'string', 'max:500'],
            'filters.sort' => ['nullable', 'string', Rule::in(AnalysisFilters::SORTS)],
            'filters.period' => ['nullable', 'string', Rule::in(AnalysisFilters::PERIODS)],
            'filters.from' => ['nullable', 'date_format:Y-m-d'],
            'filters.to' => ['nullable', 'date_format:Y-m-d'],
            'user_id' => ['missing'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do filtro.',
            'name.max' => 'O nome pode ter no máximo 40 caracteres.',
            'include_period.required' => 'Informe se o período entra no filtro.',
            'include_period.boolean' => 'Informe se o período entra no filtro.',
            'filters.present' => 'Informe os filtros.',
            'filters.array' => 'Informe os filtros.',
            'filters.type.in' => 'Escolha Receita, Despesa ou Reserva.',
            'filters.status.in' => 'Escolha Paga ou Pendente.',
            'filters.category_id.integer' => 'Escolha uma categoria.',
            'filters.bank_id.integer' => 'Escolha um banco.',
            'filters.card_id.integer' => 'Escolha um cartão.',
            'filters.payment_method_id.integer' => 'Escolha uma forma de pagamento.',
            'filters.min_amount.gt' => 'O valor mínimo precisa ser maior que zero.',
            'filters.max_amount.gt' => 'O valor máximo precisa ser maior que zero.',
            'filters.sort.in' => 'Escolha uma ordenação da lista.',
            'filters.period.in' => 'Escolha um período da lista.',
            'filters.from.date_format' => 'Informe uma data inicial válida.',
            'filters.to.date_format' => 'Informe uma data final válida.',
            'filters.q.max' => 'A busca pode ter no máximo 500 caracteres.',
            'user_id.missing' => 'O usuário não pode ser informado.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $filters = $this->input('filters', []);
            $includePeriod = $this->boolean('include_period');
            $allowed = AnalysisFilters::KEYS;

            if ($includePeriod) {
                $allowed = [...$allowed, ...AnalysisFilters::PERIOD_KEYS];
            }

            $unknown = array_diff(array_keys($filters), $allowed);

            if ($unknown !== []) {
                $validator->errors()->add('filters', 'Há um filtro que não pode ser salvo.');

                return;
            }

            if ($includePeriod && ($filters['period'] ?? null) === 'custom' && (empty($filters['from']) || empty($filters['to']))) {
                $validator->errors()->add('filters', 'Informe o período personalizado.');

                return;
            }

            if ($includePeriod && ($filters['period'] ?? null) === 'custom' && $filters['from'] > $filters['to']) {
                $validator->errors()->add('filters', 'A data inicial não pode ser maior que a final.');

                return;
            }

            $min = $filters['min_amount'] ?? null;
            $max = $filters['max_amount'] ?? null;

            if ($min !== null && $max !== null && Money::compare($min, $max) === 1) {
                $validator->errors()->add('filters.max_amount', 'O valor máximo não pode ser menor que o mínimo.');

                return;
            }

            $this->owned($validator, Category::class, $filters['category_id'] ?? null, 'filters.category_id', 'Escolha uma categoria.');
            $this->owned($validator, Bank::class, $filters['bank_id'] ?? null, 'filters.bank_id', 'Escolha um banco.');
            $this->owned($validator, Card::class, $filters['card_id'] ?? null, 'filters.card_id', 'Escolha um cartão.');
            CatalogGuard::cardBelongsToBank($validator, $filters['bank_id'] ?? null, $filters['card_id'] ?? null, 'filters.card_id');
            CatalogGuard::accountBank($validator, $filters['bank_id'] ?? null, 'filters.bank_id');
            $this->owned($validator, PaymentMethod::class, $filters['payment_method_id'] ?? null, 'filters.payment_method_id', 'Escolha uma forma de pagamento.');
            foreach ((array) ($filters['tags'] ?? []) as $tagId) {
                $this->owned($validator, Tag::class, $tagId, 'filters.tags', 'Escolha uma tag.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $clean = $this->clean($filters, $includePeriod);

            if ($clean === []) {
                $validator->errors()->add('filters', 'Não há filtros para salvar.');

                return;
            }

            $this->storedFilters = $clean;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function storedFilters(): array
    {
        return $this->storedFilters;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function clean(array $filters, bool $includePeriod): array
    {
        $clean = [];

        foreach (['type', 'status', 'q'] as $key) {
            if (! empty($filters[$key])) {
                $clean[$key] = $filters[$key];
            }
        }

        foreach (['category_id', 'bank_id', 'card_id', 'payment_method_id'] as $key) {
            if (! empty($filters[$key])) {
                $clean[$key] = (int) $filters[$key];
            }
        }

        $tags = array_values(array_unique(array_map('intval', array_filter((array) ($filters['tags'] ?? [])))));

        if ($tags !== []) {
            $clean['tags'] = $tags;
        }

        foreach (['min_amount', 'max_amount'] as $key) {
            if (! empty($filters[$key])) {
                $clean[$key] = Money::of($filters[$key]);
            }
        }

        if (! empty($filters['sort']) && $filters['sort'] !== 'date_desc') {
            $clean['sort'] = $filters['sort'];
        }

        if ($includePeriod && ! empty($filters['period'])) {
            $clean['period'] = $filters['period'];

            if ($filters['period'] === 'custom') {
                $clean['from'] = $filters['from'];
                $clean['to'] = $filters['to'];
            }
        }

        return $clean;
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
