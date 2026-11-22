<?php

namespace App\Services;

use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Support\Money;
use App\Support\Month;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private PeriodRange $periods,
        private TransactionService $transactions,
        private PlanningService $planning,
        private CardInvoiceService $invoices,
        private NetWorthService $netWorth,
    ) {}

    /**
     * @param  array{period: string, from: ?string, to: ?string, breakdown: string}  $filters
     * @param  array<int, string>|null  $only
     * @return array<string, mixed>
     */
    public function page(array $filters, ?array $only = null): array
    {
        $filters['breakdown'] = in_array($filters['breakdown'], ['category', 'bank', 'card'], true)
            ? $filters['breakdown']
            : 'category';
        $range = $this->periods->resolve($filters['period'], $filters['from'], $filters['to']);
        $payload = [];

        if ($this->wants($only, 'filters', 'period')) {
            $payload['filters'] = [
                'period' => $filters['period'],
                'from' => $filters['period'] === 'custom' ? $filters['from'] : $range['from'],
                'to' => $filters['period'] === 'custom' ? $filters['to'] : $range['to'],
                'breakdown' => $filters['breakdown'],
            ];
            $payload['period'] = [
                'key' => $filters['period'],
                'from' => $range['from'],
                'to' => $range['to'],
                'label' => $range['label'],
                'comparison' => $this->periods->comparisonLabel($filters['period']),
            ];
        }

        if ($this->wants($only, 'indicators')) {
            $payload['indicators'] = $this->indicators($filters, $range);
        }

        if ($this->wants($only, 'planning')) {
            $payload['planning'] = $this->planningBlock($range);
        }

        if ($this->wants($only, 'invoices')) {
            $payload['invoices'] = $this->invoices->dashboardCards();
        }

        if ($this->wants($only, 'bars')) {
            $payload['bars'] = $this->bars($range);
        }

        if ($this->wants($only, 'spending')) {
            $payload['spending'] = $this->spending($filters, $range);
        }

        if ($this->wants($only, 'transactions')) {
            $payload['transactions'] = $this->recent($range, $filters);
        }

        if ($this->wants($only, 'netWorth')) {
            $payload['netWorth'] = $this->netWorth->dashboardSnapshot();
        }

        if ($this->wants($only, 'goals')) {
            $payload['goals'] = [
                'message' => 'Você ainda não tem metas.',
                'shortcut_label' => 'Ir para Metas',
                'shortcut_href' => '/goals',
            ];
        }

        return $payload;
    }

    /**
     * @param  array<int, string>|null  $only
     */
    private function wants(?array $only, string ...$keys): bool
    {
        if ($only === null) {
            return true;
        }

        foreach ($keys as $key) {
            if (in_array($key, $only, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{period: string, from: ?string, to: ?string, breakdown: string}  $filters
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array<string, mixed>
     */
    private function indicators(array $filters, array $range): array
    {
        $current = $this->transactions->periodTotals($range['from'], $range['to']);
        $previousRange = $this->periods->previous($filters['period'], $filters['from'], $filters['to']);
        $previous = $previousRange === null
            ? null
            : $this->transactions->periodTotals($previousRange['from'], $previousRange['to']);
        $earlier = fn (string $key): ?string => $previous === null ? null : $previous[$key];

        return [
            'comparison' => $this->periods->comparisonLabel($filters['period']),
            'balance' => [
                ...$this->indicator($current['balance'], $earlier('balance'), 'balance'),
                'legend' => $this->keptLegend($current['reserved']),
            ],
            'income' => [
                ...$this->indicator($current['income'], $earlier('income'), 'income'),
                'count_label' => $this->entriesLabel($current['income_count']),
            ],
            'expense' => [
                ...$this->indicator($current['expense'], $earlier('expense'), 'expense'),
                'amount' => $this->signedExpense($current['expense']),
                'count_label' => $this->paidLabel($current['expense_count']),
            ],
            'reserved' => $this->indicator($current['reserved'], $earlier('reserved'), 'reserved'),
        ];
    }

    /**
     * @return array{amount: string, variation: array{kind: string, amount: ?string, tone: string, message: ?string}}
     */
    private function indicator(string $current, ?string $previous, string $metric): array
    {
        return [
            'amount' => $current,
            'variation' => $this->variation($current, $previous, $metric),
        ];
    }

    /**
     * @return array{kind: string, amount: ?string, tone: string, message: ?string}
     */
    private function variation(string $current, ?string $previous, string $metric): array
    {
        if ($previous === null) {
            return ['kind' => 'none', 'amount' => null, 'tone' => 'neutral', 'message' => null];
        }

        if (Money::compare($previous, '0') === 0 && Money::compare($current, '0') !== 0) {
            return [
                'kind' => 'none',
                'amount' => null,
                'tone' => 'neutral',
                'message' => 'Sem dados no período anterior',
            ];
        }

        $delta = Money::sub($current, $previous);

        if (Money::compare($delta, '0') === 0) {
            return ['kind' => 'same', 'amount' => '0.00', 'tone' => 'neutral', 'message' => 'Sem alteração'];
        }

        return [
            'kind' => 'amount',
            'amount' => $delta,
            'tone' => $this->tone($metric, $delta),
            'message' => null,
        ];
    }

    private function tone(string $metric, string $delta): string
    {
        $positive = Money::compare($delta, '0') === 1;

        if ($metric === 'balance' || $metric === 'income') {
            return $positive ? 'positive' : 'negative';
        }

        if ($metric === 'expense') {
            return $positive ? 'negative' : 'positive';
        }

        return 'neutral';
    }

    private function signedExpense(string $amount): string
    {
        if (Money::compare($amount, '0') === 0) {
            return '0.00';
        }

        return Money::sub('0', $amount);
    }

    private function entriesLabel(int $count): string
    {
        return $count === 1 ? '1 entrada' : $count.' entradas';
    }

    private function paidLabel(int $count): string
    {
        return $count === 1 ? '1 transação paga' : $count.' transações pagas';
    }

    /**
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array<string, mixed>
     */
    private function planningBlock(array $range): array
    {
        $month = $this->referenceMonth($range);
        $usage = $this->planning->usage($month);

        return [
            ...$usage,
            'href' => '/planning?month='.$month,
        ];
    }

    /**
     * Month the period belongs to. A single month is used as it is, including
     * a future one. A longer period stops at the current month. An open-ended
     * period uses the current month.
     *
     * @param  array{from: ?string, to: ?string}  $range
     */
    private function referenceMonth(array $range): string
    {
        $current = Month::currentKey();

        if ($range['to'] === null) {
            return $current;
        }

        $end = substr($range['to'], 0, 7);
        $start = $range['from'] !== null ? substr($range['from'], 0, 7) : null;

        if ($start !== null && $start === $end) {
            return $end;
        }

        return $end > $current ? $current : $end;
    }

    /**
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array{aria_label: string, points: array<int, array<string, mixed>>}
     */
    private function bars(array $range): array
    {
        $months = $this->chartMonths($range);
        $start = Month::start($months[0]);
        $end = Month::end($months[array_key_last($months)]);
        $found = [];

        $rows = Transaction::query()
            ->where('status', 'paid')
            ->whereIn('type', ['income', 'expense'])
            ->where('date', '>=', $start)
            ->where('date', '<=', $end)
            ->groupBy(DB::raw("to_char(date, 'YYYY-MM')"))
            ->selectRaw("
                to_char(date, 'YYYY-MM') as month_key,
                coalesce(sum(amount) filter (where type = 'income'), 0) as income_total,
                coalesce(sum(amount) filter (where type = 'expense'), 0) as expense_total
            ")
            ->get();

        foreach ($rows as $row) {
            $found[$row->month_key] = [
                'income' => Money::of($row->income_total),
                'expense' => Money::of($row->expense_total),
            ];
        }

        $withYear = substr($months[0], 0, 4) !== substr($months[array_key_last($months)], 0, 4);
        $points = [];

        foreach ($months as $month) {
            $income = $found[$month]['income'] ?? '0.00';
            $expense = $found[$month]['expense'] ?? '0.00';
            $short = Month::short($month);
            $points[] = [
                'key' => $month,
                'label' => $withYear ? $short.'/'.substr($month, 2, 2) : $short,
                'caption' => Month::phrase($month),
                'income' => $income,
                'expense' => $expense,
                'income_cents' => (int) Money::cents($income),
                'expense_cents' => (int) Money::cents($expense),
            ];
        }

        $first = $points[0]['caption'];
        $last = $points[array_key_last($points)]['caption'];
        $span = $first === $last ? $first : $first.' a '.$last;
        $peak = 0;

        foreach ($points as $point) {
            $peak = max($peak, $point['income_cents'], $point['expense_cents']);
        }

        return [
            'aria_label' => 'Receitas e despesas de '.$span.'.',
            'axis_max_cents' => $peak === 0 ? 100 : null,
            'points' => $points,
        ];
    }

    /**
     * Months drawn on the bars. Fewer than three months in the period
     * expands to the six months ending when the period ends, never after
     * the current month. More than twelve keeps the last twelve.
     *
     * @param  array{from: ?string, to: ?string}  $range
     * @return array<int, string>
     */
    private function chartMonths(array $range): array
    {
        $current = Month::currentKey();

        if ($range['from'] === null || $range['to'] === null) {
            return $this->monthWindow(Month::shift($current, -11) ?? $current, $current);
        }

        $start = substr($range['from'], 0, 7);
        $end = substr($range['to'], 0, 7);
        $count = $this->monthCount($start, $end);

        if ($count < 3) {
            $end = $end > $current ? $current : $end;
            $start = Month::shift($end, -5) ?? $end;
        } elseif ($count > 12) {
            $start = Month::shift($end, -11) ?? $start;
        }

        return $this->monthWindow($start, $end);
    }

    private function monthCount(string $start, string $end): int
    {
        $startIndex = ((int) substr($start, 0, 4) * 12) + (int) substr($start, 5, 2);
        $endIndex = ((int) substr($end, 0, 4) * 12) + (int) substr($end, 5, 2);

        return $endIndex - $startIndex + 1;
    }

    /**
     * @return array<int, string>
     */
    private function monthWindow(string $start, string $end): array
    {
        $months = [];
        $cursor = $start;
        $guard = 0;

        while ($cursor !== null && $cursor <= $end && $guard < 18) {
            $months[] = $cursor;

            if ($cursor === $end) {
                break;
            }

            $cursor = Month::shift($cursor, 1);
            $guard++;
        }

        return $months === [] ? [$end] : $months;
    }

    /**
     * @param  array{period: string, from: ?string, to: ?string, breakdown: string}  $filters
     * @param  array{from: ?string, to: ?string}  $range
     * @return array<string, mixed>
     */
    private function spending(array $filters, array $range): array
    {
        $breakdown = $filters['breakdown'];
        $query = Transaction::query()
            ->where('transactions.type', 'expense')
            ->where('transactions.status', 'paid');

        if ($range['from'] !== null) {
            $query->where('transactions.date', '>=', $range['from']);
        }

        if ($range['to'] !== null) {
            $query->where('transactions.date', '<=', $range['to']);
        }

        if ($breakdown === 'bank') {
            $query->join('banks', 'banks.id', '=', 'transactions.bank_id')
                ->groupBy('banks.id', 'banks.name', 'banks.color')
                ->selectRaw('banks.id as group_id, banks.name as group_name, banks.color as group_color, sum(transactions.amount) as total')
                ->orderBy('banks.name');
        } elseif ($breakdown === 'card') {
            $query->whereNotNull('transactions.card_id')
                ->join('cards', 'cards.id', '=', 'transactions.card_id')
                ->groupBy('cards.id', 'cards.name', 'cards.color')
                ->selectRaw('cards.id as group_id, cards.name as group_name, cards.color as group_color, sum(transactions.amount) as total')
                ->orderBy('cards.name');
        } else {
            $query->join('categories', 'categories.id', '=', 'transactions.category_id')
                ->groupBy('categories.id', 'categories.name', 'categories.color')
                ->selectRaw('categories.id as group_id, categories.name as group_name, categories.color as group_color, sum(transactions.amount) as total')
                ->orderBy('categories.name');
        }

        $groups = [];

        foreach ($query->get() as $row) {
            $groups[] = [
                'key' => (string) $row->group_id,
                'name' => (string) $row->group_name,
                'color' => $row->group_color,
                'amount' => Money::of($row->total),
            ];
        }

        usort($groups, function (array $left, array $right): int {
            $byAmount = Money::compare($right['amount'], $left['amount']);

            return $byAmount !== 0 ? $byAmount : strnatcasecmp($left['name'], $right['name']);
        });

        $total = '0.00';

        foreach ($groups as $group) {
            $total = Money::add($total, $group['amount']);
        }

        $top = array_slice($groups, 0, 5);
        $rest = array_slice($groups, 5);
        $slices = [];

        foreach ($top as $group) {
            $slices[] = [
                ...$group,
                'percent' => Money::wholePercent($group['amount'], $total),
                'cents' => (int) Money::cents($group['amount']),
            ];
        }

        if ($rest !== []) {
            $other = '0.00';

            foreach ($rest as $group) {
                $other = Money::add($other, $group['amount']);
            }

            $slices[] = [
                'key' => 'other',
                'name' => 'Outras',
                'color' => null,
                'amount' => $other,
                'percent' => Money::wholePercent($other, $total),
                'cents' => (int) Money::cents($other),
            ];
        }

        return [
            'breakdown' => $breakdown,
            'card_only' => $breakdown === 'card',
            'empty' => $slices === [],
            'total' => $total,
            'aria_label' => $this->spendingLabel($breakdown, $slices),
            'slices' => $slices,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $slices
     */
    private function spendingLabel(string $breakdown, array $slices): string
    {
        $by = match ($breakdown) {
            'bank' => 'banco',
            'card' => 'cartão',
            default => 'categoria',
        };

        if ($slices === []) {
            return 'Nenhuma despesa neste período.';
        }

        $parts = array_map(function (array $slice): string {
            $percent = $slice['percent'] === null ? '' : ' ('.$slice['percent'].'%)';

            return $slice['name'].' '.$this->reais($slice['amount']).$percent;
        }, $slices);

        return 'Gastos do período por '.$by.': '.implode(', ', $parts).'.';
    }

    /**
     * @param  array{from: ?string, to: ?string}  $range
     * @param  array{period: string, from: ?string, to: ?string}  $filters
     * @return array{href: string, items: array<int, array<string, mixed>>}
     */
    private function recent(array $range, array $filters): array
    {
        $query = Transaction::query()
            ->with([
                'category:id,name,icon,color,applies_to,nature,archived_at',
                'bank:id,name,archived_at',
                'paymentMethod:id,name,requires_card,archived_at',
                'card:id,name,closing_day,bank_id,archived_at',
                'fixedExpense:id,name',
                'installment:id,name,installments_count',
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(6);

        if ($range['from'] !== null) {
            $query->where('date', '>=', $range['from']);
        }

        if ($range['to'] !== null) {
            $query->where('date', '<=', $range['to']);
        }

        $items = TransactionResource::collection($query->get())->resolve();

        foreach ($items as $index => $item) {
            $amount = (string) $item['amount'];
            $items[$index]['signed_amount'] = $item['type'] === 'expense'
                ? $this->signedExpense($amount)
                : $amount;
        }

        return [
            'href' => '/analysis?'.http_build_query(array_filter([
                'period' => $filters['period'],
                'from' => $filters['period'] === 'custom' ? $filters['from'] : null,
                'to' => $filters['period'] === 'custom' ? $filters['to'] : null,
            ])),
            'items' => $items,
        ];
    }

    /**
     * Caption under the period balance when something was set aside.
     * Null when nothing was reserved, so the card stays without the line.
     */
    private function keptLegend(string $reserved): ?string
    {
        if (Money::compare($reserved, '0') !== 1) {
            return null;
        }

        return $this->reais($reserved).' deste saldo foi guardado';
    }

    private function reais(string $amount): string
    {
        $negative = str_starts_with($amount, '-');
        $absolute = Money::of($negative ? substr($amount, 1) : $amount);
        [$whole, $cents] = array_pad(explode('.', $absolute), 2, '00');
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) ?? $whole;

        return ($negative ? '−' : '').'R$ '.$grouped.','.$cents;
    }
}
