<?php

namespace App\Services;

use App\Models\Card;
use App\Models\FixedExpense;
use App\Models\GeneratedSkip;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function __construct(
        private PeriodRange $periods,
        private PlanGenerator $generator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Transaction
    {
        $transaction = new Transaction;
        $this->fill($transaction, $data);
        $transaction->save();

        return $transaction;
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

        $this->fill($transaction, $data);
        $transaction->save();

        if ($fixed instanceof FixedExpense && $previous !== null) {
            $fixed->amount = Money::of($transaction->amount);
            $fixed->save();
            $from = $transaction->generated_for?->toDateString() ?? $transaction->date->toDateString();
            $this->generator->applyAmountForward($fixed, $previous, $from);
        }

        return $transaction;
    }

    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            if ($transaction->status === 'pending' && $transaction->isGenerated()) {
                $this->rememberSkip($transaction);
            }

            $transaction->delete();
        });
    }

    /**
     * A generated pending the user deleted must not be created again.
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

        if ($date > $today) {
            if ($transaction->competence_date === null) {
                $transaction->competence_date = $date;
            }

            $transaction->date = $today;
        }

        $transaction->status = 'paid';
        $transaction->save();

        return $transaction;
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
            ])
            ->paginate(50, ['*'], 'page', $filters['page']);
    }

    /**
     * Totals for the whole filter, not for the loaded page.
     *
     * @param  array<string, mixed>  $filters
     * @return array{count: int, income: string, expense: string, balance: string, average_expense: string}
     */
    public function summary(array $filters): array
    {
        $row = $this->filtered($filters)
            ->selectRaw("
                count(*) as aggregate_count,
                coalesce(sum(amount) filter (where status = 'paid' and type = 'income'), 0) as income_total,
                coalesce(sum(amount) filter (where status = 'paid' and type = 'expense'), 0) as expense_total,
                count(*) filter (where status = 'paid' and type = 'expense') as paid_expense_count
            ")
            ->first();

        $income = Money::of($row->income_total ?? '0');
        $expense = Money::of($row->expense_total ?? '0');

        return [
            'count' => (int) ($row->aggregate_count ?? 0),
            'income' => $income,
            'expense' => $expense,
            'balance' => Money::sub($income, $expense),
            'average_expense' => Money::divHalfUp($expense, (int) ($row->paid_expense_count ?? 0)),
        ];
    }

    /**
     * Net paid total of each date, over the whole filter.
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
            ->selectRaw("date, sum(case when type = 'income' then amount else -amount end) as day_total")
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

        return $query;
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
}
