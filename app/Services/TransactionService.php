<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Category;
use App\Models\FixedExpense;
use App\Models\GeneratedSkip;
use App\Models\Transaction;
use App\Services\TagService;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(
        private PeriodRange $periods,
        private PlanGenerator $generator,
        private ReserveBalanceService $reserves,
        private TagService $tags,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $transaction = new Transaction;
            $this->fill($transaction, $data);
            $transaction->save();
            $this->tags->sync($transaction, $data['tags'] ?? []);
            $this->guardReserve(null, $transaction);

            return $transaction;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Transaction $transaction, array $data): Transaction
    {
        $apply = (bool) ($data['apply_amount_forward'] ?? false);
        $fixed = null;
        $previous = null;

        if ($apply && $transaction->fixed_expense_id) {
            $fixed = FixedExpense::query()->find($transaction->fixed_expense_id);
            $previous = $fixed instanceof FixedExpense ? Money::of($fixed->amount) : null;
        }

        return DB::transaction(function () use ($transaction, $data, $apply, $fixed, $previous) {
            $before = $this->reserveSnapshot($transaction);
            $this->fill($transaction, $data);
            $transaction->save();
            $this->tags->sync($transaction, $data['tags'] ?? []);
            $this->guardReserve($before, $transaction);

            if ($fixed instanceof FixedExpense && $previous !== null && $apply) {
                $fixed->amount = Money::of($transaction->amount);
                $fixed->save();
                $from = $transaction->generated_for?->toDateString() ?? $transaction->date->toDateString();
                $this->generator->applyAmountForward($fixed, $previous, $from);
            }

            return $transaction;
        });
    }

    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $before = $this->reserveSnapshot($transaction);

            if ($transaction->isGenerated()) {
                $this->rememberSkip($transaction);
            }

            $transaction->delete();
            $this->guardReserve($before, null);
        });
    }

    /**
     * A generated row the user deleted, paid or pending, must not be created again.
     */
    private function rememberSkip(Transaction $transaction): void
    {
        $skip = new GeneratedSkip;
        $skip->fixed_expense_id = $transaction->fixed_expense_id;
        $skip->installment_id = $transaction->installment_id;
        $skip->installment_number = $transaction->installment_number;
        $skip->generated_for = $transaction->generated_for?->toDateString();

        try {
            $skip->save();
        } catch (UniqueConstraintViolationException) {
            // This month was already skipped.
        }
    }

    public function pay(Transaction $transaction): Transaction
    {
        if ($transaction->status === 'paid') {
            return $transaction;
        }

        $today = now('America/Sao_Paulo')->toDateString();
        $date = $transaction->date->toDateString();
        $movesToToday = $date > $today || ($date < $today && $transaction->card_id === null);

        return DB::transaction(function () use ($transaction, $movesToToday, $date, $today) {
            $before = $this->reserveSnapshot($transaction);

            if ($movesToToday) {
                if ($transaction->competence_date === null) {
                    $transaction->competence_date = $date;
                }

                $transaction->date = $today;
            }

            $transaction->status = 'paid';
            $transaction->save();
            $this->guardReserve($before, $transaction);

            return $transaction;
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{key: string, from: ?string, to: ?string, label: string}
     */
    public function period(array $filters): array
    {
        $range = $this->periods->resolve(
            $filters['period'],
            $filters['from'],
            $filters['to'],
        );

        return [
            'key' => $filters['period'],
            'from' => $range['from'],
            'to' => $range['to'],
            'label' => $range['label'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = $this->filtered($filters);
        $this->sort($query, $filters['sort']);

        return $query
            ->with([
                'category:id,name,icon,color,applies_to,nature,archived_at',
                'bank:id,name,archived_at',
                'paymentMethod:id,name,requires_card,archived_at',
                'card:id,name,closing_day,bank_id,archived_at',
                'fixedExpense:id,name',
                'installment:id,name,installments_count',
                'tags:id,name',
            ])
            ->paginate(50, ['*'], 'page', $filters['page']);
    }

    /**
     * Totals for the whole filter, not for the loaded page.
     * Receitas are paid income. Despesas are paid expenses. Reservado is
     * paid deposits minus paid withdrawals and stays out of the balance.
     * Balance is income minus expenses.
     *
     * @param  array<string, mixed>  $filters
     * @return array{count: int, income: string, expense: string, reserved: string, balance: string, average_expense: string}
     */
    public function summary(array $filters): array
    {
        $paid = $this->paidBreakdown($this->filtered($filters));

        return [
            'count' => $paid['count'],
            'income' => $paid['income'],
            'expense' => $paid['expense'],
            'reserved' => $paid['reserved'],
            'balance' => $paid['balance'],
            'average_expense' => Money::divHalfUp($paid['expense'], $paid['expense_count']),
        ];
    }

    /**
     * Paid income, spending and reserve for one date window.
     * Null bounds mean every date. One grouped query.
     *
     * @return array{count: int, income: string, expense: string, reserved: string, balance: string, income_count: int, expense_count: int}
     */
    public function periodTotals(?string $from, ?string $to): array
    {
        $query = Transaction::query();

        if ($from !== null) {
            $query->where('date', '>=', $from);
        }

        if ($to !== null) {
            $query->where('date', '<=', $to);
        }

        return $this->paidBreakdown($query);
    }

    /**
     * Net paid total of each date, over the whole filter.
     * Income minus paid expenses of that day. Reserve does not change it.
     * A date with only pending transactions is omitted.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, mixed>  $dates
     * @return array<string, string>
     */
    public function dayTotals(array $filters, array $dates): array
    {
        if ($dates === [] || str_starts_with((string) $filters['sort'], 'amount')) {
            return [];
        }

        $normalized = array_map(function ($date) {
            if ($date instanceof \DateTimeInterface) {
                return $date->format('Y-m-d');
            }

            return substr((string) $date, 0, 10);
        }, $dates);

        $rows = $this->filtered($filters)
            ->where('status', 'paid')
            ->whereIn('date', $normalized)
            ->selectRaw("date, sum(case when type = 'income' then amount when type = 'expense' then -amount else 0 end) as day_total")
            ->groupBy('date')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $date = $row->date instanceof \DateTimeInterface
                ? $row->date->format('Y-m-d')
                : substr((string) $row->getAttributes()['date'], 0, 10);
            $totals[$date] = Money::of($row->day_total);
        }

        return $totals;
    }

    /**
     * Same filter as the Análise, for reports. Status "all" means every status.
     *
     * @param  array<string, mixed>  $filters
     */
    public function filteredForReport(array $filters): Builder
    {
        return $this->filtered($this->reportFilter($filters));
    }

    /**
     * Paid income, expense, reserve and balance for the filter.
     * The status filter is ignored: the sums are always the paid rows,
     * matching paidBreakdown. One query. Used by Análise and the Dashboard.
     *
     * @param  array<string, mixed>  $filters
     * @return array{count: int, income: string, expense: string, reserved: string, balance: string, average_expense: string}
     */
    public function paidSummary(array $filters): array
    {
        $filters['status'] = null;

        return $this->summary($this->reportFilter($filters));
    }

    /**
     * Income, expense, reserve and balance of the rows the report filter
     * already selected, including Paga, Pendente or Todas. One query.
     *
     * @param  array<string, mixed>  $filters
     * @return array{count: int, income: string, expense: string, reserved: string, balance: string}
     */
    public function reportSummary(array $filters): array
    {
        $row = $this->filteredForReport($filters)->selectRaw($this->totalsSelectSql(false))->first();
        $income = Money::of($row->income_total ?? '0');
        $expense = Money::of($row->spending_total ?? '0');
        $reserved = Money::of($row->reserved_total ?? '0');

        return [
            'count' => (int) ($row->aggregate_count ?? 0),
            'income' => $income,
            'expense' => $expense,
            'reserved' => $reserved,
            'balance' => Money::sub($income, $expense),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function reportCount(array $filters): int
    {
        return $this->filteredForReport($filters)->count();
    }

    /**
     * Transactions of the filter, newest first. $limit null loads every row.
     * The caller counts once, before this load.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Transaction>
     */
    public function reportLines(array $filters, ?int $limit): Collection
    {
        $query = $this->filteredForReport($filters);
        $this->sort($query, 'date_desc');
        $query->with([
            'category:id,name',
            'bank:id,name',
            'paymentMethod:id,name',
            'card:id,name',
            'tags:id,name',
        ]);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Totals grouped like paidBreakdown. One query.
     * The report filter decides the status; the sums do not force Paga.
     * Time groups come back oldest first. Category and bank come back
     * by expense descending, then name.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array{key: string, name: string, income: string, expense: string, reserved: string, balance: string, count: int}>
     */
    public function groupedPaidTotals(array $filters, string $groupBy): array
    {
        $query = $this->filteredForReport($filters);
        $time = in_array($groupBy, ['day', 'week', 'month'], true);

        if ($groupBy === 'category') {
            $query->join('categories', 'categories.id', '=', 'transactions.category_id')
                ->groupByRaw('transactions.category_id::text, categories.name')
                ->selectRaw('transactions.category_id::text as group_key, categories.name as group_name, '.$this->totalsSelectSql(false));
        } elseif ($groupBy === 'bank') {
            $query->join('banks', 'banks.id', '=', 'transactions.bank_id')
                ->groupByRaw('transactions.bank_id::text, banks.name')
                ->selectRaw('transactions.bank_id::text as group_key, banks.name as group_name, '.$this->totalsSelectSql(false));
        } elseif ($groupBy === 'week') {
            $bucket = "to_char(date_trunc('week', transactions.date::timestamp), 'YYYY-MM-DD')";
            $query->groupByRaw($bucket)
                ->selectRaw($bucket." as group_key, '' as group_name, ".$this->totalsSelectSql(false))
                ->orderByRaw($bucket);
        } elseif ($groupBy === 'month') {
            $bucket = "to_char(transactions.date, 'YYYY-MM')";
            $query->groupByRaw($bucket)
                ->selectRaw($bucket." as group_key, '' as group_name, ".$this->totalsSelectSql(false))
                ->orderByRaw($bucket);
        } else {
            $bucket = "to_char(transactions.date, 'YYYY-MM-DD')";
            $query->groupByRaw($bucket)
                ->selectRaw($bucket." as group_key, '' as group_name, ".$this->totalsSelectSql(false))
                ->orderByRaw($bucket);
        }

        $groups = [];

        foreach ($query->get() as $row) {
            $income = Money::of($row->income_total ?? '0');
            $expense = Money::of($row->spending_total ?? '0');
            $groups[] = [
                'key' => (string) $row->group_key,
                'name' => (string) ($row->group_name ?? ''),
                'income' => $income,
                'expense' => $expense,
                'reserved' => Money::of($row->reserved_total ?? '0'),
                'balance' => Money::sub($income, $expense),
                'count' => (int) ($row->aggregate_count ?? 0),
            ];
        }

        if ($time) {
            return $groups;
        }

        usort($groups, function (array $left, array $right): int {
            $byExpense = Money::compare($right['expense'], $left['expense']);

            return $byExpense !== 0 ? $byExpense : strnatcasecmp($left['name'], $right['name']);
        });

        return $groups;
    }

    /**
     * First and last transaction date of the user, or nulls when there are none.
     *
     * @return array{from: ?string, to: ?string}
     */
    public function dateSpan(): array
    {
        $row = Transaction::query()
            ->selectRaw('min(date) as first_date, max(date) as last_date')
            ->first();

        return [
            'from' => $this->spanDate($row?->first_date),
            'to' => $this->spanDate($row?->last_date),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function reportFilter(array $filters): array
    {
        $status = $filters['status'] ?? null;

        return [
            'period' => $filters['period'],
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'type' => $filters['type'] ?? null,
            'status' => $status === 'all' ? null : $status,
            'category_id' => $filters['category_id'] ?? null,
            'bank_id' => $filters['bank_id'] ?? null,
            'card_id' => $filters['card_id'] ?? null,
            'payment_method_id' => $filters['payment_method_id'] ?? null,
            'tag_ids' => $filters['tag_ids'] ?? [],
            'min_amount' => null,
            'max_amount' => null,
            'q' => null,
        ];
    }

    private function paidSelectSql(): string
    {
        return $this->totalsSelectSql(true);
    }

    /**
     * Shared totals. $paidOnly keeps Análise and the Dashboard on paid rows.
     * Reports pass false and let the filter decide the status.
     */
    private function totalsSelectSql(bool $paidOnly): string
    {
        $status = $paidOnly ? "transactions.status = 'paid' and " : '';

        return "
            count(*) as aggregate_count,
            coalesce(sum(transactions.amount) filter (where {$status}transactions.type = 'income'), 0) as income_total,
            coalesce(sum(transactions.amount) filter (where {$status}transactions.type = 'expense'), 0) as spending_total,
            coalesce(sum(transactions.amount) filter (where {$status}transactions.type = 'reserve' and transactions.reserve_action = 'deposit'), 0)
            - coalesce(sum(transactions.amount) filter (where {$status}transactions.type = 'reserve' and transactions.reserve_action = 'withdraw'), 0) as reserved_total,
            count(*) filter (where {$status}transactions.type = 'income') as income_count,
            count(*) filter (where {$status}transactions.type = 'expense') as spending_count
        ";
    }

    private function spanDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filtered(array $filters): Builder
    {
        $query = Transaction::query();
        $range = $this->periods->resolve($filters['period'], $filters['from'], $filters['to']);

        if ($range['from'] !== null) {
            $query->where('date', '>=', $range['from']);
        }

        if ($range['to'] !== null) {
            $query->where('date', '<=', $range['to']);
        }

        foreach (['type', 'status', 'category_id', 'bank_id', 'card_id', 'payment_method_id'] as $column) {
            if ($filters[$column] !== null && $filters[$column] !== '') {
                $query->where($column, $filters[$column]);
            }
        }

        if ($filters['min_amount'] !== null && $filters['min_amount'] !== '') {
            $query->where('amount', '>=', Money::of($filters['min_amount']));
        }

        if ($filters['max_amount'] !== null && $filters['max_amount'] !== '') {
            $query->where('amount', '<=', Money::of($filters['max_amount']));
        }

        if ($filters['q'] !== null && $filters['q'] !== '') {
            $pattern = '%'.addcslashes($filters['q'], '\\%_').'%';
            $query->where(function (Builder $inner) use ($pattern) {
                $inner->whereRaw("unaccent(description) ilike unaccent(?) escape '\\'", [$pattern])
                    ->orWhereRaw("unaccent(coalesce(notes, '')) ilike unaccent(?) escape '\\'", [$pattern]);
            });
        }

        $tagIds = array_values(array_unique(array_map('intval', $filters['tag_ids'] ?? [])));

        if ($tagIds !== []) {
            $query->whereHas('tags', function (Builder $tags) use ($tagIds): void {
                $tags->whereIn('tags.id', $tagIds);
            });
        }

        return $query;
    }

    /**
     * @return array{count: int, income: string, expense: string, reserved: string, balance: string, income_count: int, expense_count: int}
     */
    private function paidBreakdown(Builder $query): array
    {
        $row = (clone $query)->selectRaw($this->paidSelectSql())->first();

        $income = Money::of($row->income_total ?? '0');
        $expense = Money::of($row->spending_total ?? '0');
        $reserved = Money::of($row->reserved_total ?? '0');

        return [
            'count' => (int) ($row->aggregate_count ?? 0),
            'income' => $income,
            'expense' => $expense,
            'reserved' => $reserved,
            'balance' => Money::sub($income, $expense),
            'income_count' => (int) ($row->income_count ?? 0),
            'expense_count' => (int) ($row->spending_count ?? 0),
        ];
    }

    private function sort(Builder $query, string $sort): void
    {
        match ($sort) {
            'date_asc' => $query->orderBy('date')->orderByDesc('id'),
            'amount_desc' => $query->orderByDesc('amount')->orderByDesc('id'),
            'amount_asc' => $query->orderBy('amount')->orderByDesc('id'),
            default => $query->orderByDesc('date')->orderByDesc('id'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(Transaction $transaction, array $data): void
    {
        $cardId = $data['card_id'] ?? null;
        $bankId = $data['bank_id'] ?? null;

        if ($cardId) {
            $bankId = Card::query()->findOrFail($cardId)->bank_id;
        }

        $transaction->type = $data['type'];
        $transaction->reserve_action = $data['type'] === 'reserve' ? $data['reserve_action'] : null;
        $transaction->status = $data['status'];
        $transaction->amount = Money::of($data['amount']);
        $transaction->date = $data['date'];
        $transaction->competence_date = $data['competence_date'] ?? null;
        $transaction->description = $data['description'];
        $transaction->notes = $data['notes'] ?? null;
        $transaction->category_id = $data['category_id'];
        $transaction->bank_id = $bankId;
        $transaction->payment_method_id = $data['payment_method_id'];
        $transaction->card_id = $cardId;
    }

    /**
     * @return array{type: string, status: string, reserve_action: ?string, category_id: ?int, amount: string, date: ?string}
     */
    private function reserveSnapshot(Transaction $transaction): array
    {
        return [
            'type' => (string) $transaction->type,
            'status' => (string) $transaction->status,
            'reserve_action' => $transaction->reserve_action,
            'category_id' => $transaction->category_id === null ? null : (int) $transaction->category_id,
            'amount' => Money::of($transaction->amount),
            'date' => $transaction->date?->toDateString(),
        ];
    }

    /**
     * After the row is written, both the previous and the new category must
     * stay non-negative on every date. A failure rolls the transaction back.
     *
     * @param  array{type: string, status: string, reserve_action: ?string, category_id: ?int, amount: string, date: ?string}|null  $before
     */
    private function guardReserve(?array $before, ?Transaction $after): void
    {
        $categories = [];

        foreach ([$before, $after] as $state) {
            $categoryId = $this->paidReserveCategory($state);

            if ($categoryId !== null) {
                $categories[] = $categoryId;
            }
        }

        $afterId = $after === null ? null : $this->paidReserveCategory($after);

        usort($categories, function (int $left, int $right) use ($afterId): int {
            return ($left === $afterId ? 0 : 1) <=> ($right === $afterId ? 0 : 1);
        });

        foreach (array_values(array_unique($categories)) as $categoryId) {
            $shortfall = $this->reserves->firstShortfall($categoryId);

            if ($shortfall === null) {
                continue;
            }

            $withdraw = $this->paidReserveCategory($after) === $categoryId
                && $after instanceof Transaction
                && $after->reserve_action === 'withdraw';

            throw ValidationException::withMessages([
                $this->reserveField($before, $after, $categoryId) => $this->reserveMessage(
                    $categoryId,
                    $shortfall,
                    $withdraw,
                ),
            ]);
        }
    }

    /**
     * @param  array{type: string, status: string, reserve_action: ?string, category_id: ?int, amount: string, date: ?string}|Transaction|null  $state
     */
    private function paidReserveCategory(array|Transaction|null $state): ?int
    {
        if ($state === null) {
            return null;
        }

        $type = $state instanceof Transaction ? $state->type : $state['type'];
        $status = $state instanceof Transaction ? $state->status : $state['status'];
        $categoryId = $state instanceof Transaction ? $state->category_id : $state['category_id'];

        if ($type !== 'reserve' || $status !== 'paid' || $categoryId === null) {
            return null;
        }

        return (int) $categoryId;
    }

    /**
     * @param  array{type: string, status: string, reserve_action: ?string, category_id: ?int, amount: string, date: ?string}|null  $before
     */
    private function reserveField(?array $before, ?Transaction $after, int $categoryId): string
    {
        if ($after === null) {
            return 'delete';
        }

        if ($before === null) {
            return 'amount';
        }

        if ((int) $before['category_id'] !== (int) $after->category_id && (int) $before['category_id'] === $categoryId) {
            return 'category_id';
        }

        if ((int) $before['category_id'] !== (int) $after->category_id) {
            return 'category_id';
        }

        if ($before['type'] !== $after->type) {
            return 'type';
        }

        if ($before['reserve_action'] !== $after->reserve_action) {
            return 'reserve_action';
        }

        if ($before['status'] !== $after->status) {
            return 'status';
        }

        $afterDate = $after->date instanceof \DateTimeInterface
            ? $after->date->format('Y-m-d')
            : substr((string) $after->date, 0, 10);

        if ($before['date'] !== $afterDate) {
            return 'date';
        }

        return 'amount';
    }

    /**
     * @param  array{date: string, missing: string}  $shortfall
     */
    private function reserveMessage(int $categoryId, array $shortfall, bool $withdraw): string
    {
        $when = substr($shortfall['date'], 8, 2).'/'.substr($shortfall['date'], 5, 2).'/'.substr($shortfall['date'], 0, 4);
        $missing = Money::label($shortfall['missing']);

        if ($withdraw) {
            $category = Category::query()->find($categoryId);
            $name = $category instanceof Category ? $category->name : 'Reserva';

            return "Esse resgate passa do que está guardado em {$name} até {$when}: faltam {$missing}.";
        }

        return "Não é possível: resgates feitos depois dependem desse valor (faltariam {$missing} em {$when}).";
    }
}
