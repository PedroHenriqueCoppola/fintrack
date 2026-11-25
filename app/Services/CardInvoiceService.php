<?php

namespace App\Services;

use App\Http\Resources\TransactionResource;
use App\Models\Card;
use App\Models\CardInvoicePayment;
use App\Models\Transaction;
use App\Support\Money;
use App\Support\Month;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CardInvoiceService
{
    /**
     * @var array<int, string>
     */
    private const MONTHS = [
        1 => 'janeiro',
        2 => 'fevereiro',
        3 => 'março',
        4 => 'abril',
        5 => 'maio',
        6 => 'junho',
        7 => 'julho',
        8 => 'agosto',
        9 => 'setembro',
        10 => 'outubro',
        11 => 'novembro',
        12 => 'dezembro',
    ];

    /**
     * Active cards in the same order as Gerenciar (name, then id).
     *
     * @return Collection<int, Card>
     */
    public function activeCards(): Collection
    {
        return Card::query()
            ->with('bank:id,name')
            ->whereNull('archived_at')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Open invoice of every active card, plus the closed unpaid invoice
     * immediately before it when that cycle has at least one expense.
     * Expenses of every card are read in one grouped query.
     *
     * @return array{cards: array<int, array<string, mixed>>}
     */
    public function dashboardCards(): array
    {
        $cards = Card::query()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        if ($cards->isEmpty()) {
            return ['cards' => []];
        }

        $cycles = [];

        foreach ($cards as $card) {
            $calculator = $this->calculator($card);
            $open = $calculator->openKey();
            $openPeriod = $calculator->periodFor($open);
            $previous = $this->previousKey($open);
            $previousPeriod = $calculator->periodFor($previous);
            $cycles[$card->id] = [
                'open' => $open,
                'start' => $openPeriod['start'],
                'closing' => $openPeriod['closing'],
                'previous' => $previous,
                'previous_start' => $previousPeriod['start'],
                'previous_closing' => $previousPeriod['closing'],
            ];
        }

        $totals = $this->dashboardTotals($cycles);
        $paid = $this->paidKeys($cards->pluck('id')->all());
        $rows = [];

        foreach ($cards as $card) {
            $cycle = $cycles[$card->id];
            $bucket = $totals[$card->id] ?? ['posted' => '0.00', 'forecast' => '0.00', 'previous_count' => 0];
            $posted = $bucket['posted'];
            $forecast = $bucket['forecast'];
            $closed = null;

            if ($bucket['previous_count'] > 0 && ! isset($paid[$card->id][$cycle['previous']])) {
                $closed = [
                    'month' => $cycle['previous'],
                    'label' => 'Fatura de '.Month::name($cycle['previous'], true).' fechada e não paga',
                    'situation' => 'closed',
                    'situation_label' => 'Fechada',
                ];
            }

            $rows[] = [
                'id' => $card->id,
                'name' => $card->name,
                'color' => $card->color,
                'month' => $cycle['open'],
                'month_label' => Month::label($cycle['open']),
                'closes_on' => $this->short($cycle['closing']),
                'situation' => 'open',
                'situation_label' => 'Aberta',
                'posted' => $posted,
                'forecast' => $forecast,
                'estimated' => Money::add($posted, $forecast),
                'closed' => $closed,
            ];
        }

        return ['cards' => $rows];
    }

    public function selectedCard(Collection $cards, mixed $requested): Card
    {
        if ($requested === null || $requested === '') {
            $first = $cards->first();

            if ($first instanceof Card) {
                return $first;
            }

            abort(404);
        }

        if (is_array($requested) || ! ctype_digit((string) $requested)) {
            abort(404);
        }

        $card = $cards->firstWhere('id', (int) $requested);

        if (! $card instanceof Card) {
            abort(404);
        }

        return $card;
    }

    /**
     * A missing card is a validation error. Another user's card, or an
     * archived one, is a 404.
     */
    public function ownedActiveCard(int $id): Card
    {
        $card = Card::withoutGlobalScopes()->find($id);

        if (! $card instanceof Card) {
            throw ValidationException::withMessages([
                'card_id' => 'Escolha um cartão.',
            ]);
        }

        if ((int) $card->user_id !== (int) Auth::id() || $card->archived_at !== null) {
            abort(404);
        }

        return $card;
    }

    /**
     * @return array{label: string, closing_date: string, due_date: ?string}
     */
    public function preview(Card $card, string $date): array
    {
        $calculator = $this->calculator($card);
        $key = $calculator->invoiceKeyFor($date);
        $closing = $calculator->periodFor($key)['closing'];
        $month = self::MONTHS[(int) substr($key, 5, 2)];
        $year = substr($key, 0, 4);
        $name = $year === substr($this->today(), 0, 4) ? $month : $month.' de '.$year;

        return [
            'label' => 'Esta compra entra na fatura de '.$name.' (fecha em '.$this->short($closing).').',
            'closing_date' => $closing,
            'due_date' => $calculator->dueDateFor($key),
        ];
    }

    /**
     * @return array{invoice: array<string, mixed>, history: array<int, array<string, mixed>>}
     */
    public function present(Card $card, mixed $requested): array
    {
        $facts = $this->facts($card);
        $selected = $this->selectedKey(is_string($requested) ? $requested : null, $facts['range'], $facts['open']);
        [$previous, $next] = $this->neighbors($selected, $facts['range']);
        $calculator = $facts['calculator'];
        $period = $calculator->periodFor($selected);
        $paidOn = $facts['paidOn'][$selected] ?? null;
        $situation = $calculator->situation($selected, $paidOn !== null);
        $posted = $facts['buckets'][$selected]['posted'] ?? '0.00';
        $forecast = $facts['buckets'][$selected]['forecast'] ?? '0.00';
        $lines = $this->lines($card, $period['start'], $period['closing']);
        $postedLines = $lines->filter(fn (Transaction $transaction) => $transaction->status === 'paid')->values();
        $forecastLines = $lines->filter(fn (Transaction $transaction) => $transaction->status !== 'paid')->values();

        $history = [];

        foreach (array_reverse($facts['range']) as $key) {
            if ($key >= $selected) {
                continue;
            }

            $history[] = $this->historyItem($calculator, $key, $facts);
        }

        return [
            'invoice' => [
                'key' => $selected,
                'title' => 'Fatura de '.$this->lowerMonth($selected),
                'nav_label' => $this->navLabel($selected),
                'situation' => $situation,
                'situation_label' => $this->situationLabel($situation, $paidOn),
                'paid_on' => $paidOn,
                'cycle_label' => $this->cycleLabel($calculator, $selected),
                'closing_date' => $period['closing'],
                'posted' => $posted,
                'forecast' => $forecast,
                'estimated' => Money::add($posted, $forecast),
                'closes_in_label' => $this->closesInLabel($situation, $facts['today'], $period['closing']),
                'previous_key' => $previous,
                'next_key' => $next,
                'groups' => [
                    [
                        'key' => 'posted',
                        'label' => 'Lançado',
                        'total' => $posted,
                        'transactions' => $this->presentLines($postedLines),
                    ],
                    [
                        'key' => 'forecast',
                        'label' => 'Previsto',
                        'total' => $forecast,
                        'transactions' => $this->presentLines($forecastLines),
                    ],
                ],
            ],
            'history' => $history,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function paymentErrors(Card $card, string $closingMonth, string $paidOn): array
    {
        $key = substr($closingMonth, 0, 7);
        $calculator = $this->calculator($card);
        $closing = $calculator->periodFor($key)['closing'];
        $today = $this->today();
        $errors = [];

        if ($today < $closing) {
            $errors['closing_month'] = 'A fatura ainda está aberta.';
        } elseif ($paidOn < $closing) {
            $errors['paid_on'] = 'A data não pode ser anterior ao fechamento.';
        }

        $already = CardInvoicePayment::query()
            ->where('card_id', $card->id)
            ->whereDate('closing_month', $closingMonth)
            ->exists();

        if ($already) {
            $errors['closing_month'] = 'Esta fatura já está paga.';
        }

        if (! isset($errors['closing_month']) && ! $this->keyInRange($card, $key)) {
            $errors['closing_month'] = 'Não há fatura nesse mês.';
        }

        return $errors;
    }

    public function pay(Card $card, string $closingMonth, string $paidOn): void
    {
        $errors = $this->paymentErrors($card, $closingMonth, $paidOn);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        try {
            $payment = new CardInvoicePayment;
            $payment->card_id = $card->id;
            $payment->closing_month = $closingMonth;
            $payment->paid_on = $paidOn;
            $payment->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'closing_month' => 'Esta fatura já está paga.',
            ]);
        }
    }

    public function unpay(Card $card, string $closingMonth): void
    {
        $payment = CardInvoicePayment::query()
            ->where('card_id', $card->id)
            ->whereDate('closing_month', $closingMonth)
            ->first();

        if (! $payment instanceof CardInvoicePayment) {
            throw ValidationException::withMessages([
                'closing_month' => 'Esta fatura não está paga.',
            ]);
        }

        $payment->delete();
    }

    /**
     * One row per invoice whose closing date falls in the period.
     * Invoices with no purchase and no payment are left out.
     * Three queries for the whole list: cards, purchases and payments.
     *
     * @return array<int, array{card: string, invoice: string, closing: string, due: ?string, posted: string, forecast: string, total: string, situation: string}>
     */
    public function reportInvoices(?int $cardId, ?string $from, ?string $to): array
    {
        $cards = Card::query()
            ->when($cardId !== null, fn ($query) => $query->whereKey($cardId))
            ->orderBy('name')
            ->get();

        if ($cards->isEmpty()) {
            return [];
        }

        $ids = $cards->modelKeys();
        $transactions = Transaction::query()
            ->where('type', 'expense')
            ->whereIn('card_id', $ids);

        if ($from !== null) {
            $transactions->whereDate('date', '>=', Carbon::parse($from)->subMonths(2)->toDateString());
        }

        if ($to !== null) {
            $transactions->whereDate('date', '<=', $to);
        }

        $transactions = $transactions->get(['card_id', 'date', 'amount', 'status']);
        $payments = CardInvoicePayment::query()
            ->whereIn('card_id', $ids);

        if ($from !== null) {
            $payments->whereDate('closing_month', '>=', Carbon::parse($from)->subMonth()->startOfMonth()->toDateString());
        }

        if ($to !== null) {
            $payments->whereDate('closing_month', '<=', $to);
        }

        $payments = $payments->get(['card_id', 'closing_month', 'paid_on']);
        $purchases = [];
        $paid = [];

        foreach ($transactions as $transaction) {
            $purchases[$transaction->card_id][] = $transaction;
        }

        foreach ($payments as $payment) {
            $paid[$payment->card_id][substr($this->day($payment->closing_month), 0, 7)] = true;
        }

        $rows = [];

        foreach ($cards as $card) {
            $calculator = $this->calculator($card);
            $buckets = [];

            foreach ($purchases[$card->id] ?? [] as $transaction) {
                $key = $calculator->invoiceKeyFor($this->day($transaction->date));

                if (! isset($buckets[$key])) {
                    $buckets[$key] = ['posted' => '0.00', 'forecast' => '0.00'];
                }

                $field = $transaction->status === 'paid' ? 'posted' : 'forecast';
                $buckets[$key][$field] = Money::add($buckets[$key][$field], $transaction->amount);
            }

            $keys = array_unique(array_merge(array_keys($buckets), array_keys($paid[$card->id] ?? [])));

            foreach ($keys as $key) {
                $hasPurchase = isset($buckets[$key]);
                $hasPayment = isset($paid[$card->id][$key]);

                if (! $hasPurchase && ! $hasPayment) {
                    continue;
                }

                $closing = $calculator->periodFor($key)['closing'];

                if (($from !== null && $closing < $from) || ($to !== null && $closing > $to)) {
                    continue;
                }

                $posted = $buckets[$key]['posted'] ?? '0.00';
                $forecast = $buckets[$key]['forecast'] ?? '0.00';
                $situation = $calculator->situation($key, $hasPayment);
                $rows[] = [
                    'card' => $card->name,
                    'invoice' => Month::label($key),
                    'closing' => $closing,
                    'due' => $calculator->dueDateFor($key),
                    'posted' => $posted,
                    'forecast' => $forecast,
                    'total' => Money::add($posted, $forecast),
                    'situation' => match ($situation) {
                        'paid' => 'Paga',
                        'closed' => 'Fechada',
                        default => 'Aberta',
                    },
                ];
            }
        }

        usort($rows, function (array $left, array $right): int {
            $byDate = strcmp($right['closing'], $left['closing']);

            return $byDate !== 0 ? $byDate : strnatcasecmp($left['card'], $right['card']);
        });

        return $rows;
    }

    /**
     * @param  array<int, array{open: string, start: string, closing: string, previous: string, previous_start: string, previous_closing: string}>  $cycles
     * @return array<int, array{posted: string, forecast: string, previous_count: int}>
     */
    private function dashboardTotals(array $cycles): array
    {
        $openStart = $this->cardDateCase($cycles, 'start');
        $openClosing = $this->cardDateCase($cycles, 'closing');
        $previousStart = $this->cardDateCase($cycles, 'previous_start');
        $previousClosing = $this->cardDateCase($cycles, 'previous_closing');
        $bindings = array_merge(
            $openStart['bindings'],
            $openClosing['bindings'],
            $openStart['bindings'],
            $openClosing['bindings'],
            $previousStart['bindings'],
            $previousClosing['bindings'],
        );

        $rows = Transaction::query()
            ->where('transactions.type', 'expense')
            ->where(function ($outer) use ($cycles) {
                foreach ($cycles as $cardId => $cycle) {
                    $outer->orWhere(function ($inner) use ($cardId, $cycle) {
                        $inner->where('transactions.card_id', $cardId)
                            ->where('transactions.date', '>=', $cycle['previous_start'])
                            ->where('transactions.date', '<', $cycle['closing']);
                    });
                }
            })
            ->groupBy('transactions.card_id')
            ->selectRaw("
                transactions.card_id as card_id,
                coalesce(sum(transactions.amount) filter (
                    where transactions.status = 'paid'
                      and transactions.date >= ({$openStart['sql']})
                      and transactions.date < ({$openClosing['sql']})
                ), 0) as posted,
                coalesce(sum(transactions.amount) filter (
                    where transactions.status <> 'paid'
                      and transactions.date >= ({$openStart['sql']})
                      and transactions.date < ({$openClosing['sql']})
                ), 0) as forecast,
                count(*) filter (
                    where transactions.date >= ({$previousStart['sql']})
                      and transactions.date < ({$previousClosing['sql']})
                ) as previous_count
            ", $bindings)
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row->card_id] = [
                'posted' => Money::of($row->posted),
                'forecast' => Money::of($row->forecast),
                'previous_count' => (int) $row->previous_count,
            ];
        }

        return $totals;
    }

    /**
     * @param  array<int, array<string, string>>  $cycles
     * @return array{sql: string, bindings: array<int, int|string>}
     */
    private function cardDateCase(array $cycles, string $field): array
    {
        $sql = 'case transactions.card_id';
        $bindings = [];

        foreach ($cycles as $cardId => $cycle) {
            $sql .= ' when ? then ?::date';
            $bindings[] = $cardId;
            $bindings[] = $cycle[$field];
        }

        return [
            'sql' => $sql.' end',
            'bindings' => $bindings,
        ];
    }

    /**
     * @param  array<int, int>  $cardIds
     * @return array<int, array<string, true>>
     */
    private function paidKeys(array $cardIds): array
    {
        $payments = CardInvoicePayment::query()
            ->whereIn('card_id', $cardIds)
            ->get(['card_id', 'closing_month']);
        $paid = [];

        foreach ($payments as $payment) {
            $paid[(int) $payment->card_id][substr($this->day($payment->closing_month), 0, 7)] = true;
        }

        return $paid;
    }

    private function previousKey(string $key): string
    {
        $year = (int) substr($key, 0, 4);
        $month = (int) substr($key, 5, 2) - 1;

        if ($month < 1) {
            $month = 12;
            $year--;
        }

        return sprintf('%04d-%02d', $year, $month);
    }

    private function calculator(Card $card): CardInvoiceCalculator
    {
        return new CardInvoiceCalculator(
            (int) $card->closing_day,
            $card->due_day === null ? null : (int) $card->due_day,
            $this->today(),
        );
    }

    /**
     * One pass over the card's expenses and one pass over its payments.
     *
     * @return array{
     *     calculator: CardInvoiceCalculator,
     *     today: string,
     *     open: string,
     *     buckets: array<string, array{posted: string, forecast: string}>,
     *     paidOn: array<string, string>,
     *     range: array<int, string>
     * }
     */
    private function facts(Card $card): array
    {
        $calculator = $this->calculator($card);
        $today = $this->today();
        $open = $calculator->openKey();
        $rows = Transaction::query()
            ->where('card_id', $card->id)
            ->where('type', 'expense')
            ->get(['date', 'amount', 'status']);
        $payments = CardInvoicePayment::query()
            ->where('card_id', $card->id)
            ->get(['closing_month', 'paid_on']);

        $paidOn = [];

        foreach ($payments as $payment) {
            $paidOn[substr($this->day($payment->closing_month), 0, 7)] = $this->day($payment->paid_on);
        }

        $buckets = [];

        foreach ($rows as $row) {
            $key = $calculator->invoiceKeyFor($this->day($row->date));

            if (! isset($buckets[$key])) {
                $buckets[$key] = ['posted' => '0.00', 'forecast' => '0.00'];
            }

            $field = $row->status === 'paid' ? 'posted' : 'forecast';
            $buckets[$key][$field] = Money::add($buckets[$key][$field], $row->amount);
        }

        return [
            'calculator' => $calculator,
            'today' => $today,
            'open' => $open,
            'buckets' => $buckets,
            'paidOn' => $paidOn,
            'range' => $this->range(array_keys($buckets), $open),
        ];
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    private function range(array $keys, string $open): array
    {
        if ($keys === []) {
            return [$open];
        }

        sort($keys);
        $end = $keys[count($keys) - 1];

        if ($open > $end) {
            $end = $open;
        }

        $range = [];
        $cursor = $keys[0];
        $guard = 0;

        while ($cursor <= $end && $guard < 600) {
            $range[] = $cursor;

            if ($cursor === $end) {
                break;
            }

            $cursor = $this->nextKey($cursor);
            $guard++;
        }

        return $range;
    }

    /**
     * @param  array<int, string>  $range
     */
    private function selectedKey(?string $requested, array $range, string $open): string
    {
        if ($requested === null || ! preg_match('/^\d{4}-\d{2}$/', $requested)) {
            return $open;
        }

        $month = (int) substr($requested, 5, 2);

        if ($month < 1 || $month > 12) {
            return $open;
        }

        if (! in_array($requested, $range, true)) {
            return $open;
        }

        return $requested;
    }

    /**
     * @param  array<int, string>  $range
     * @return array{0: ?string, 1: ?string}
     */
    private function neighbors(string $selected, array $range): array
    {
        $index = array_search($selected, $range, true);

        if ($index === false) {
            $previous = null;
            $next = null;

            foreach ($range as $key) {
                if ($key < $selected) {
                    $previous = $key;
                }

                if ($next === null && $key > $selected) {
                    $next = $key;
                }
            }

            return [$previous, $next];
        }

        return [
            $range[$index - 1] ?? null,
            $range[$index + 1] ?? null,
        ];
    }

    private function keyInRange(Card $card, string $key): bool
    {
        $facts = $this->facts($card);

        return in_array($key, $facts['range'], true);
    }

    /**
     * @return Collection<int, Transaction>
     */
    private function lines(Card $card, string $start, string $closing): Collection
    {
        return Transaction::query()
            ->where('card_id', $card->id)
            ->where('type', 'expense')
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<', $closing)
            ->with([
                'category:id,name,icon,color,applies_to,nature,archived_at',
                'bank:id,name,archived_at',
                'paymentMethod:id,name,requires_card,archived_at',
                'card:id,name,closing_day,bank_id,archived_at',
                'fixedExpense:id,name',
                'installment:id,name,installments_count',
                'tags:id,name',
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, Transaction>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function presentLines(Collection $lines): array
    {
        return $lines->map(function (Transaction $transaction) {
            $data = (new TransactionResource($transaction))->resolve();
            $data['display_name'] = $this->displayName($transaction);

            return $data;
        })->all();
    }

    private function displayName(Transaction $transaction): string
    {
        $description = (string) $transaction->description;

        if ($transaction->fixed_expense_id !== null && ! str_contains(mb_strtolower($description), 'fixo')) {
            return $description.' (fixo)';
        }

        if ($transaction->installment_id !== null) {
            $count = $transaction->installment?->installments_count;
            $mark = $count === null
                ? (string) $transaction->installment_number
                : $transaction->installment_number.'/'.$count;

            if (! str_contains($description, (string) $mark)) {
                return $description.' ('.$mark.')';
            }
        }

        return $description;
    }

    /**
     * @param  array{paidOn: array<string, string>, buckets: array<string, array{posted: string, forecast: string}>}  $facts
     * @return array<string, mixed>
     */
    private function historyItem(CardInvoiceCalculator $calculator, string $key, array $facts): array
    {
        $paidOn = $facts['paidOn'][$key] ?? null;
        $situation = $calculator->situation($key, $paidOn !== null);
        $posted = $facts['buckets'][$key]['posted'] ?? '0.00';
        $forecast = $facts['buckets'][$key]['forecast'] ?? '0.00';

        return [
            'key' => $key,
            'label' => $this->monthTitle($key),
            'situation' => $situation,
            'situation_label' => $this->situationLabel($situation, $paidOn),
            'total' => Money::add($posted, $forecast),
        ];
    }

    private function cycleLabel(CardInvoiceCalculator $calculator, string $key): string
    {
        $period = $calculator->periodFor($key);
        $label = 'Compras de '.$this->short($period['start']).' a '.$this->short($period['end'])
            .' · Fecha em '.$this->short($period['closing']);
        $due = $calculator->dueDateFor($key);

        if ($due !== null) {
            $label .= ' · Vence em '.$this->short($due);
        }

        return $label;
    }

    private function closesInLabel(string $situation, string $today, string $closing): ?string
    {
        if ($situation !== 'open') {
            return null;
        }

        $days = (int) (new \DateTimeImmutable($today))->diff(new \DateTimeImmutable($closing))->format('%r%a');

        if ($days <= 0) {
            return null;
        }

        return $days === 1 ? 'Fecha em 1 dia' : 'Fecha em '.$days.' dias';
    }

    private function situationLabel(string $situation, ?string $paidOn): string
    {
        if ($situation === 'paid') {
            return $paidOn === null ? 'Paga' : 'Paga em '.$this->short($paidOn);
        }

        return $situation === 'closed' ? 'Fechada' : 'Aberta';
    }

    private function monthTitle(string $key): string
    {
        $name = self::MONTHS[(int) substr($key, 5, 2)];

        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8').' '.substr($key, 0, 4);
    }

    private function lowerMonth(string $key): string
    {
        return self::MONTHS[(int) substr($key, 5, 2)].' '.substr($key, 0, 4);
    }

    private function navLabel(string $key): string
    {
        return $this->monthTitle($key);
    }

    private function short(string $date): string
    {
        return substr($date, 8, 2).'/'.substr($date, 5, 2);
    }

    private function nextKey(string $key): string
    {
        $year = (int) substr($key, 0, 4);
        $month = (int) substr($key, 5, 2) + 1;

        if ($month > 12) {
            $month = 1;
            $year++;
        }

        return sprintf('%04d-%02d', $year, $month);
    }

    private function day(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }

    private function today(): string
    {
        return now('America/Sao_Paulo')->toDateString();
    }
}
