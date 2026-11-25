<?php

namespace App\Services;

use App\Models\Card;
use App\Models\CardInvoicePayment;
use App\Models\FixedExpense;
use App\Models\Goal;
use App\Models\Installment;
use App\Models\MonthlyPlanItem;
use App\Models\NetWorthClosingItem;
use App\Models\SavedFilter;
use App\Models\Transaction;
use App\Support\UsageLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogUsage
{
    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{can_delete: bool, usage_summary: ?string}>
     */
    public function summaries(string $kind, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $result = [];

        foreach ($ids as $id) {
            $result[$id] = ['can_delete' => true, 'usage_summary' => null];
        }

        if ($ids === []) {
            return $result;
        }

        $counts = [];

        foreach ($this->sources($kind) as $index => [$model, $column]) {
            $counts[$index] = [];
            $rows = $model::query()
                ->whereIn($column, $ids)
                ->selectRaw($column.' as item_id, count(*) as total')
                ->groupBy($column)
                ->pluck('total', 'item_id');

            foreach ($rows as $itemId => $total) {
                $counts[$index][(int) $itemId] = (int) $total;
            }
        }

        foreach ($ids as $id) {
            $parts = [];

            foreach ($this->sources($kind) as $index => [$model, $column, $one, $many]) {
                $total = $counts[$index][$id] ?? 0;

                if ($total > 0) {
                    $parts[] = UsageLabel::count($total, $one, $many);
                }
            }

            $result[$id] = [
                'can_delete' => $parts === [],
                'usage_summary' => $parts === [] ? null : implode(', ', $parts),
            ];
        }

        return $result;
    }

    public function delete(Model $item, string $kind, string $filterKey): void
    {
        DB::transaction(function () use ($item, $kind, $filterKey): void {
            $locked = $item->newQuery()->whereKey($item->getKey())->lockForUpdate()->first();

            if (! $locked instanceof Model) {
                abort(404);
            }

            $summary = $this->summaries($kind, [(int) $locked->getKey()])[(int) $locked->getKey()];

            if (! $summary['can_delete']) {
                throw ValidationException::withMessages([
                    'delete' => 'Esse cadastro está em uso e só pode ser arquivado.',
                ]);
            }

            $this->forgetSavedFilter($filterKey, (int) $locked->getKey());

            try {
                $locked->delete();
            } catch (QueryException) {
                throw ValidationException::withMessages([
                    'delete' => 'Esse cadastro está em uso e só pode ser arquivado.',
                ]);
            }
        });
    }

    /**
     * @return array<int, array{0: class-string<Model>, 1: string, 2: string, 3: string}>
     */
    private function sources(string $kind): array
    {
        return match ($kind) {
            'bank' => [
                [Transaction::class, 'bank_id', 'transação', 'transações'],
                [Card::class, 'bank_id', 'cartão', 'cartões'],
                [FixedExpense::class, 'bank_id', 'fixo', 'fixos'],
                [Installment::class, 'bank_id', 'parcelado', 'parcelados'],
                [NetWorthClosingItem::class, 'bank_id', 'fechamento', 'fechamentos'],
            ],
            'card' => [
                [Transaction::class, 'card_id', 'transação', 'transações'],
                [FixedExpense::class, 'card_id', 'fixo', 'fixos'],
                [Installment::class, 'card_id', 'parcelado', 'parcelados'],
                [CardInvoicePayment::class, 'card_id', 'pagamento de fatura', 'pagamentos de fatura'],
            ],
            'category' => [
                [Transaction::class, 'category_id', 'transação', 'transações'],
                [FixedExpense::class, 'category_id', 'fixo', 'fixos'],
                [Installment::class, 'category_id', 'parcelado', 'parcelados'],
                [MonthlyPlanItem::class, 'category_id', 'item de plano', 'itens de plano'],
                [Goal::class, 'category_id', 'meta', 'metas'],
            ],
            'payment_method' => [
                [Transaction::class, 'payment_method_id', 'transação', 'transações'],
                [FixedExpense::class, 'payment_method_id', 'fixo', 'fixos'],
                [Installment::class, 'payment_method_id', 'parcelado', 'parcelados'],
            ],
            default => [],
        };
    }

    private function forgetSavedFilter(string $key, int $id): void
    {
        SavedFilter::query()->get()->each(function (SavedFilter $filter) use ($key, $id): void {
            $filters = $filter->filters ?? [];

            if ((int) ($filters[$key] ?? 0) !== $id) {
                return;
            }

            unset($filters[$key]);
            $filter->filters = $filters;
            $filter->save();
        });
    }
}
