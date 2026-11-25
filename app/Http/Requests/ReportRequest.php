<?php

namespace App\Http\Requests;

use App\Models\Bank;
use App\Models\Card;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Tag;
use App\Support\AnalysisFilters;
use App\Support\CatalogGuard;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $mode = $this->filledQuery('mode') ?? 'presets';
        $clean = [
            'period' => $this->filledQuery('period') ?? 'current_month',
            'from' => $this->filledQuery('from'),
            'to' => $this->filledQuery('to'),
            'mode' => $mode,
        ];

        if ($mode === 'presets') {
            $clean['preset'] = $this->filledQuery('preset') ?? 'spending';
        } elseif ($mode === 'builder') {
            $dataset = $this->filledQuery('dataset') ?? 'transactions';
            $clean['dataset'] = $dataset;

            if ($dataset === 'transactions') {
                $clean['type'] = $this->filledQuery('type');
                $clean['category_id'] = $this->filledQuery('category_id');
                $clean['bank_id'] = $this->filledQuery('bank_id');
                $clean['card_id'] = $this->filledQuery('card_id');
                $clean['payment_method_id'] = $this->filledQuery('payment_method_id');
                $clean['status'] = $this->filledQuery('status') ?? 'paid';
                $clean['group_by'] = $this->filledQuery('group_by') ?? 'none';
                $clean['tags'] = $this->integerList('tags');
            } elseif ($dataset === 'invoices') {
                $clean['card_id'] = $this->filledQuery('card_id');
            } elseif ($dataset === 'goals') {
                $clean['goal_status'] = $this->filledQuery('goal_status') ?? 'all';
            }
        }

        if ($this->routeIs('reports.download')) {
            $clean['format'] = $this->filledQuery('format');
        }

        $this->query->replace($clean);
        $this->replace($clean);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'period' => ['required', 'string', Rule::in(AnalysisFilters::PERIODS)],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'mode' => ['required', 'string', Rule::in(['presets', 'builder'])],
            'preset' => ['exclude_unless:mode,presets', 'required', 'string', Rule::in(['spending', 'summary', 'statement', 'monthly'])],
            'dataset' => ['exclude_unless:mode,builder', 'required', 'string', Rule::in(['transactions', 'invoices', 'net_worth', 'goals'])],
            'type' => ['nullable', 'string', Rule::in(['income', 'expense', 'reserve'])],
            'status' => ['nullable', 'string', Rule::in(['paid', 'pending', 'all'])],
            'group_by' => ['nullable', 'string', Rule::in(['none', 'day', 'week', 'month', 'category', 'bank'])],
            'goal_status' => ['nullable', 'string', Rule::in(['all', 'active', 'completed', 'archived'])],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'bank_id' => ['nullable', 'integer', 'min:1'],
            'card_id' => ['nullable', 'integer', 'min:1'],
            'payment_method_id' => ['nullable', 'integer', 'min:1'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['integer', 'min:1'],
        ];

        if ($this->routeIs('reports.download')) {
            $rules['format'] = ['required', 'string', Rule::in(['pdf', 'xlsx'])];
        }

        return $rules;
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
            'mode.in' => 'Escolha modelos prontos ou montar o seu.',
            'preset.in' => 'Escolha um modelo da lista.',
            'preset.required' => 'Escolha um modelo da lista.',
            'dataset.in' => 'Escolha o que incluir no relatório.',
            'dataset.required' => 'Escolha o que incluir no relatório.',
            'type.in' => 'Escolha Receita, Despesa ou Reserva.',
            'status.in' => 'Escolha Paga, Pendente ou Todas.',
            'group_by.in' => 'Escolha um agrupamento da lista.',
            'goal_status.in' => 'Escolha Todas, Ativas, Concluídas ou Arquivadas.',
            'category_id.integer' => 'Escolha uma categoria.',
            'bank_id.integer' => 'Escolha um banco.',
            'card_id.integer' => 'Escolha um cartão.',
            'payment_method_id.integer' => 'Escolha uma forma de pagamento.',
            'tags.array' => 'Escolha uma tag.',
            'tags.*.integer' => 'Escolha uma tag.',
            'format.required' => 'Escolha PDF ou Excel.',
            'format.in' => 'Escolha PDF ou Excel.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->owned($validator, Category::class, $this->input('category_id'), 'category_id', 'Escolha uma categoria.');
            $this->owned($validator, Bank::class, $this->input('bank_id'), 'bank_id', 'Escolha um banco.');
            $this->owned($validator, Card::class, $this->input('card_id'), 'card_id', 'Escolha um cartão.');
            CatalogGuard::cardBelongsToBank($validator, $this->input('bank_id'), $this->input('card_id'));
            CatalogGuard::accountBank($validator, $this->input('bank_id'));
            $this->owned($validator, PaymentMethod::class, $this->input('payment_method_id'), 'payment_method_id', 'Escolha uma forma de pagamento.');
            foreach ((array) $this->input('tags', []) as $tagId) {
                $this->owned($validator, Tag::class, $tagId, 'tags', 'Escolha uma tag.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $data = $this->validated();
        $mode = $data['mode'];
        $period = $data['period'];
        $dataset = $mode === 'builder' ? ($data['dataset'] ?? 'transactions') : 'transactions';

        return [
            'period' => $period,
            'from' => $period === 'custom' ? ($data['from'] ?? null) : null,
            'to' => $period === 'custom' ? ($data['to'] ?? null) : null,
            'mode' => $mode,
            'preset' => $mode === 'presets' ? ($data['preset'] ?? 'spending') : 'spending',
            'dataset' => $dataset,
            'type' => $dataset === 'transactions' ? ($data['type'] ?? null) : null,
            'category_id' => isset($data['category_id']) ? (int) $data['category_id'] : null,
            'bank_id' => isset($data['bank_id']) ? (int) $data['bank_id'] : null,
            'card_id' => isset($data['card_id']) ? (int) $data['card_id'] : null,
            'payment_method_id' => isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : null,
            'tag_ids' => $dataset === 'transactions'
                ? array_values(array_unique(array_map('intval', $data['tags'] ?? [])))
                : [],
            'status' => $dataset === 'transactions' ? ($data['status'] ?? 'paid') : 'paid',
            'group_by' => $dataset === 'transactions' ? ($data['group_by'] ?? 'none') : 'none',
            'goal_status' => $dataset === 'goals' ? ($data['goal_status'] ?? 'all') : 'all',
        ];
    }

    public function downloadFormat(): string
    {
        return (string) $this->validated('format');
    }

    /**
     * @return array<string, string>
     */
    public function redirectQuery(): array
    {
        $data = $this->definition();
        $query = [];

        if ($data['period'] !== 'current_month') {
            $query['period'] = $data['period'];
        }

        if ($data['period'] === 'custom') {
            $query['period'] = 'custom';
            $query['from'] = (string) $data['from'];
            $query['to'] = (string) $data['to'];
        }

        if ($data['mode'] === 'builder') {
            $query['mode'] = 'builder';

            if ($data['dataset'] !== 'transactions') {
                $query['dataset'] = $data['dataset'];
            }

            if ($data['dataset'] === 'transactions') {
                $this->put($query, 'type', $data['type']);
                $this->put($query, 'category_id', $data['category_id']);
                $this->put($query, 'bank_id', $data['bank_id']);
                $this->put($query, 'card_id', $data['card_id']);
                $this->put($query, 'payment_method_id', $data['payment_method_id']);

                if ($data['status'] !== 'paid') {
                    $query['status'] = $data['status'];
                }

                if ($data['group_by'] !== 'none') {
                    $query['group_by'] = $data['group_by'];
                }

                if ($data['tag_ids'] !== []) {
                    $query['tags'] = $data['tag_ids'];
                }
            } elseif ($data['dataset'] === 'invoices') {
                $this->put($query, 'card_id', $data['card_id']);
            } elseif ($data['dataset'] === 'goals' && $data['goal_status'] !== 'all') {
                $query['goal_status'] = $data['goal_status'];
            }
        } elseif ($data['preset'] !== 'spending') {
            $query['preset'] = $data['preset'];
        }

        return $query;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('reports')->withErrors($validator)
        );
    }

    private function filledQuery(string $key): ?string
    {
        $value = $this->query($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, int>
     */
    private function integerList(string $key): array
    {
        $value = $this->query($key, []);

        if (! is_array($value)) {
            $value = $value === null || $value === '' ? [] : [$value];
        }

        return array_values(array_unique(array_filter(array_map('intval', $value))));
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

    /**
     * @param  array<string, string>  $query
     */
    private function put(array &$query, string $key, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $query[$key] = (string) $value;
        }
    }
}
