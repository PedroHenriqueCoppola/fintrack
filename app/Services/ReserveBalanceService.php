<?php

namespace App\Services;

use App\Models\Transaction;
use App\Support\Money;

class ReserveBalanceService
{
    /**
     * Paid deposits minus paid withdrawals. Callers add their own filters.
     */
    public static function netSql(string $amount = 'amount', string $action = 'reserve_action', string $when = ''): string
    {
        $deposit = $when === '' ? "{$action} = 'deposit'" : "{$when} and {$action} = 'deposit'";
        $withdraw = $when === '' ? "{$action} = 'withdraw'" : "{$when} and {$action} = 'withdraw'";

        return "coalesce(sum({$amount}) filter (where {$deposit}), 0) - coalesce(sum({$amount}) filter (where {$withdraw}), 0)";
    }
    /**
     * Paid reserve balance of every category, one grouped query.
     * A category with no paid reserve is absent. The balance is the whole
     * history: a paid transaction cannot be in the future.
     *
     * @var array<int, string>|null
     */
    private ?array $balances = null;

    /**
     * Reuse balances already read by another query in this request.
     *
     * @param  array<int, string>  $balances
     */
    public function remember(array $balances): void
    {
        $this->balances ??= $balances;
    }

    /**
     * @return array<int, string>
     */
    public function balances(): array
    {
        if ($this->balances !== null) {
            return $this->balances;
        }

        $rows = Transaction::query()
            ->where('type', 'reserve')
            ->where('status', 'paid')
            ->groupBy('category_id')
            ->selectRaw('category_id, '.self::netSql().' as balance')
            ->get();

        $balances = [];

        foreach ($rows as $row) {
            $balances[(int) $row->category_id] = Money::of($row->balance);
        }

        $this->balances = $balances;

        return $balances;
    }

    /**
     * Balance shown beside the category while editing.
     * A paid withdraw is added back, so the hint ignores the row being edited.
     */
    public function support(int $categoryId, ?string $action, ?string $status, ?string $amount): string
    {
        $balance = $this->balances()[$categoryId] ?? '0.00';

        if ($status === 'paid' && $action === 'withdraw' && $amount !== null) {
            return Money::add($balance, $amount);
        }

        return $balance;
    }

    /**
     * First date whose end-of-day balance is negative, and how much is missing.
     * Deposits and withdrawals of the same date are netted together: the check
     * is the balance at the end of each date. One grouped query.
     *
     * @return array{date: string, missing: string}|null
     */
    public function firstShortfall(int $categoryId): ?array
    {
        $rows = Transaction::query()
            ->where('category_id', $categoryId)
            ->where('type', 'reserve')
            ->where('status', 'paid')
            ->whereIn('reserve_action', ['deposit', 'withdraw'])
            ->groupBy('date')
            ->orderBy('date')
            ->selectRaw("
                date,
                coalesce(sum(amount) filter (where reserve_action = 'deposit'), 0) as deposited,
                coalesce(sum(amount) filter (where reserve_action = 'withdraw'), 0) as withdrawn
            ")
            ->get();

        $balance = '0.00';

        foreach ($rows as $row) {
            $balance = Money::sub(Money::add($balance, $row->deposited), $row->withdrawn);

            if (Money::compare($balance, '0') === -1) {
                $date = $row->date instanceof \DateTimeInterface
                    ? $row->date->format('Y-m-d')
                    : substr((string) $row->getAttributes()['date'], 0, 10);

                return [
                    'date' => $date,
                    'missing' => Money::sub('0', $balance),
                ];
            }
        }

        return null;
    }
}
