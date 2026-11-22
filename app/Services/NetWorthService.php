<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\NetWorthClosing;
use App\Models\NetWorthClosingItem;
use App\Support\Money;
use App\Support\Month;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly net worth is the sum of the balances typed for each bank.
 * Nothing derived is stored: the total, the variation and the current
 * net worth are calculated here.
 *
 * The Dashboard (module 8) reads current() and series().
 */
class NetWorthService
{
    /** @var Collection<int, Bank>|null */
    private ?Collection $banks = null;

    public function __construct(private PeriodRange $periods) {}

    /**
     * Closing with the greatest month strictly before the one given.
     * Skipped months stay skipped: October's previous can be August.
     */
    public function previousClosing(string $month): ?NetWorthClosing
    {
        $start = strlen($month) === 7 ? Month::start($month) : $month;

        return NetWorthClosing::query()
            ->with('items')
            ->where('month', '<', $start)
            ->orderByDesc('month')
            ->first();
    }

    /**
     * Latest closing of all time: total and variation against the one before it.
     * Variation is null when this is the first closing.
     *
     * @return array{month: string, total: string, variation: ?string}|null
     */
    public function current(): ?array
    {
        $latest = NetWorthClosing::query()->with('items')->orderByDesc('month')->first();

        if ($latest === null) {
            return null;
        }

        $total = $this->sum($latest);
        $previous = $this->previousClosing($latest->month->toDateString());

        return [
            'month' => Month::key($latest->month->toDateString()),
            'total' => $total,
            'variation' => $previous === null ? null : Money::sub($total, $this->sum($previous)),
        ];
    }

    /**
     * Latest closing and the six most recent ones, oldest first.
     * The variation uses the closing immediately before the latest,
     * which is included in those six whenever more than one exists.
     *
     * @return array<string, mixed>
     */
    public function dashboardSnapshot(): array
    {
        $closings = NetWorthClosing::query()
            ->with('items')
            ->orderByDesc('month')
            ->limit(6)
            ->get()
            ->sortBy(fn (NetWorthClosing $closing) => $closing->month->toDateString())
            ->values();

        if ($closings->isEmpty()) {
            return [
                'empty' => true,
                'total' => null,
                'month' => null,
                'variation' => null,
                'variation_tone' => null,
                'previous_name' => null,
                'chart' => ['aria_label' => null, 'points' => []],
            ];
        }

        $rows = $closings->map(fn (NetWorthClosing $closing) => [
            'month' => Month::key($closing->month->toDateString()),
            'total' => $this->sum($closing),
        ])->all();
        $latest = $rows[array_key_last($rows)];
        $previous = count($rows) > 1 ? $rows[count($rows) - 2] : null;
        $variation = $previous === null ? null : Money::sub($latest['total'], $previous['total']);

        return [
            'empty' => false,
            'total' => $latest['total'],
            'month' => $latest['month'],
            'variation' => $variation,
            'variation_tone' => $this->variationTone($variation),
            'previous_name' => $previous === null ? null : Month::name($previous['month'], true),
            'chart' => $this->chart($rows),
        ];
    }

    /**
     * One already-summed total per closing, oldest first.
     * Null bounds mean the whole history. The Dashboard reuses this.
     *
     * @return array<int, array{month: string, total: string}>
     */
    public function series(?string $from, ?string $to): array
    {
        return $this->between($from, $to)
            ->map(fn (NetWorthClosing $closing) => [
                'month' => Month::key($closing->month->toDateString()),
                'total' => $this->sum($closing),
            ])
            ->all();
    }

    /**
     * Banks and prefilled balances for the closing form of one month.
     *
     * @return array<string, mixed>
     */
    public function form(string $monthKey): array
    {
        $start = Month::start($monthKey);
        $existing = NetWorthClosing::query()->with('items')->where('month', $start)->first();
        $previous = $this->previousClosing($monthKey);
        $banks = $this->orderedBanks();
        $previousItems = $previous?->items->keyBy('bank_id') ?? collect();
        $existingItems = $existing?->items->keyBy('bank_id') ?? collect();

        $selected = $banks->filter(function (Bank $bank) use ($existing, $existingItems, $previousItems) {
            if ($bank->archived_at === null) {
                return true;
            }

            if ($existing !== null) {
                return $existingItems->has($bank->id);
            }

            $reference = $previousItems->get($bank->id);

            return $reference !== null && Money::compare($reference->balance, '0') === 1;
        })->values();

        $rows = $selected->map(function (Bank $bank) use ($existing, $existingItems, $previous, $previousItems) {
            if ($existing !== null && $existingItems->has($bank->id)) {
                $balance = Money::of($existingItems[$bank->id]->balance);
            } elseif ($existing !== null) {
                $balance = '0.00';
            } else {
                $balance = $previousItems->has($bank->id)
                    ? Money::of($previousItems[$bank->id]->balance)
                    : '0.00';
            }

            return [
                'bank_id' => $bank->id,
                'name' => $bank->name,
                'color' => $bank->color,
                'balance' => $balance,
                'reference_balance' => $previous === null
                    ? null
                    : ($previousItems->has($bank->id) ? Money::of($previousItems[$bank->id]->balance) : '0.00'),
            ];
        })->all();

        $referenceKey = $previous === null ? null : Month::key($previous->month->toDateString());

        return [
            'month' => $monthKey,
            'month_phrase' => Month::phrase($monthKey),
            'existing_id' => $existing?->id,
            'notes' => $existing->notes ?? '',
            'reference' => $previous === null ? null : [
                'month' => $referenceKey,
                'name' => Month::name($referenceKey),
                'name_lower' => Month::name($referenceKey, true),
                'total' => $this->sum($previous),
            ],
            'banks' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): NetWorthClosing
    {
        $monthKey = $data['month'];
        $blueprint = $this->form($monthKey);

        if ($blueprint['existing_id'] !== null) {
            throw ValidationException::withMessages([
                'month' => 'Já existe um fechamento de '.Month::phrase($monthKey).'. Edite-o.',
            ]);
        }

        $this->assertItems($blueprint['banks'], $data['items']);

        try {
            return DB::transaction(function () use ($data, $monthKey) {
                $closing = new NetWorthClosing;
                $closing->month = Month::start($monthKey);
                $closing->notes = $this->notes($data['notes'] ?? null);
                $closing->save();
                $this->writeItems($closing, $data['items']);

                return $closing;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'month' => 'Já existe um fechamento de '.Month::phrase($monthKey).'. Edite-o.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(NetWorthClosing $closing, array $data): void
    {
        $monthKey = $data['month'];
        $blueprint = $this->form($monthKey);

        if ($blueprint['existing_id'] !== null && $blueprint['existing_id'] !== $closing->id) {
            throw ValidationException::withMessages([
                'month' => 'Já existe um fechamento de '.Month::phrase($monthKey).'. Edite-o.',
            ]);
        }

        $this->assertItems($blueprint['banks'], $data['items']);

        try {
            DB::transaction(function () use ($closing, $data, $monthKey) {
                $closing->month = Month::start($monthKey);
                $closing->notes = $this->notes($data['notes'] ?? null);
                $closing->save();
                $closing->items()->delete();
                $this->writeItems($closing, $data['items']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'month' => 'Já existe um fechamento de '.Month::phrase($monthKey).'. Edite-o.',
            ]);
        }
    }

    public function delete(NetWorthClosing $closing): void
    {
        $closing->delete();
    }

    /**
     * Page payload. Closings in the period are one eager load. The closing
     * before the first of that period is a second load, so the first row
     * still has a variation. The latest closing of all time is loaded only
     * when it falls outside the period (the indicators do not follow the filter).
     *
     * @return array<string, mixed>
     */
    public function present(string $period, ?string $from, ?string $to): array
    {
        $range = $this->periods->resolve($period, $from, $to);
        $closings = $this->between($range['from'], $range['to']);
        $before = $closings->isEmpty()
            ? null
            : $this->previousClosing($closings->first()->month->toDateString());

        $rows = [];
        $previous = $before;

        foreach ($closings as $closing) {
            $rows[] = $this->row($closing, $previous);
            $previous = $closing;
        }

        $latest = $this->latestRow($closings, $rows, $range);
        $currentKey = Month::currentKey();
        $current = collect($rows)->firstWhere('month', $currentKey);

        if ($current === null && $latest !== null && $latest['month'] === $currentKey) {
            $current = $latest;
        }

        if ($current === null) {
            $currentId = NetWorthClosing::query()->where('month', Month::currentStart())->value('id');
        } else {
            $currentId = $current['id'];
        }

        return [
            'period' => [
                'key' => $period,
                'from' => $range['from'],
                'to' => $range['to'],
                'label' => $range['label'],
            ],
            'has_banks' => $this->orderedBanks()->isNotEmpty(),
            'has_closings' => $latest !== null,
            'current_month' => $currentKey,
            'current_month_name' => Month::name($currentKey, true),
            'current_closing_id' => $currentId,
            'suggested_month' => $this->suggestedMonth($currentKey),
            'indicators' => [
                'total' => $latest['total'] ?? null,
                'variation' => $latest['variation'] ?? null,
                'variation_kind' => $latest['variation_kind'] ?? 'none',
                'last_label' => $latest === null ? null : Month::label($latest['month']),
                'records_label' => $this->recordsLabel(count($rows)),
            ],
            'by_bank' => $latest === null ? null : [
                'title' => 'Por banco, em '.Month::name($latest['month'], true),
                'banks' => $latest['banks'],
            ],
            'chart' => $this->chart($rows),
            'records' => array_reverse($rows),
        ];
    }

    /**
     * @return Collection<int, NetWorthClosing>
     */
    private function between(?string $from, ?string $to): Collection
    {
        return NetWorthClosing::query()
            ->with(['items.bank'])
            ->when($from !== null, fn ($query) => $query->where('month', '>=', $from)->where('month', '<=', $to))
            ->orderBy('month')
            ->get();
    }

    /**
     * @param  Collection<int, NetWorthClosing>  $closings
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array<string, mixed>|null
     */
    private function latestRow(Collection $closings, array $rows, array $range): ?array
    {
        if ($range['from'] === null) {
            return $rows === [] ? null : $rows[array_key_last($rows)];
        }

        $after = NetWorthClosing::query()
            ->with(['items.bank'])
            ->where('month', '>', $range['to'])
            ->orderByDesc('month')
            ->first();

        if ($after !== null) {
            return $this->row($after, $this->previousClosing($after->month->toDateString()));
        }

        if ($rows !== []) {
            return $rows[array_key_last($rows)];
        }

        $before = NetWorthClosing::query()
            ->with(['items.bank'])
            ->where('month', '<', $range['from'])
            ->orderByDesc('month')
            ->first();

        if ($before === null) {
            return null;
        }

        return $this->row($before, $this->previousClosing($before->month->toDateString()));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(NetWorthClosing $closing, ?NetWorthClosing $previous): array
    {
        $key = Month::key($closing->month->toDateString());
        $total = $this->sum($closing);
        $previousItems = $previous?->items->keyBy('bank_id');
        $totalVariation = null;
        $totalKind = 'none';

        if ($previous !== null) {
            $totalVariation = Money::sub($total, $this->sum($previous));
            $totalKind = Money::compare($totalVariation, '0') === 0 ? 'same' : 'amount';
        }

        $banks = $this->sortedItems($closing)->map(function (NetWorthClosingItem $item) use ($previous, $previousItems) {
            $balance = Money::of($item->balance);
            $variation = null;
            $kind = 'none';

            if ($previous !== null && ! $previousItems->has($item->bank_id)) {
                $kind = 'new';
                $variation = $balance;
            } elseif ($previous !== null) {
                $variation = Money::sub($balance, $previousItems[$item->bank_id]->balance);
                $kind = Money::compare($variation, '0') === 0 ? 'same' : 'amount';
            }

            return [
                'id' => $item->bank_id,
                'name' => $item->bank->name,
                'color' => $item->bank->color,
                'balance' => $balance,
                'variation' => $variation,
                'variation_kind' => $kind,
            ];
        })->all();

        return [
            'id' => $closing->id,
            'month' => $key,
            'label' => Month::label($key),
            'total' => $total,
            'variation' => $totalVariation,
            'variation_kind' => $totalKind,
            'notes' => $closing->notes,
            'banks' => $banks,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{aria_label: ?string, points: array<int, array<string, mixed>>}
     */
    private function chart(array $rows): array
    {
        if ($rows === []) {
            return ['aria_label' => null, 'points' => []];
        }

        $first = $rows[0]['month'];
        $last = $rows[array_key_last($rows)]['month'];
        $withYear = count($rows) > 12 || substr($first, 0, 4) !== substr($last, 0, 4);

        $points = array_map(function (array $row) use ($withYear) {
            $short = Month::short($row['month']);

            return [
                'label' => $withYear ? $short.'/'.substr($row['month'], 2, 2) : $short,
                'caption' => Month::name($row['month']).' de '.substr($row['month'], 0, 4),
                'value' => $row['total'],
                'cents' => (int) Money::cents($row['total']),
            ];
        }, $rows);

        $fromTotal = $rows[0]['total'];
        $toTotal = $rows[array_key_last($rows)]['total'];

        return [
            'aria_label' => 'Evolução do patrimônio de '.$this->span($first, $last).', de '.$this->reais($fromTotal).' a '.$this->reais($toTotal),
            'points' => $points,
        ];
    }

    private function span(string $fromKey, string $toKey): string
    {
        $fromYear = substr($fromKey, 0, 4);
        $toYear = substr($toKey, 0, 4);

        if ($fromKey === $toKey) {
            return Month::name($fromKey, true).' de '.$fromYear;
        }

        if ($fromYear === $toYear) {
            return Month::name($fromKey, true).' a '.Month::name($toKey, true).' de '.$toYear;
        }

        return Month::name($fromKey, true).' de '.$fromYear.' a '.Month::name($toKey, true).' de '.$toYear;
    }

    /**
     * Month the register button opens. The current month when it is still open.
     * Otherwise the latest gap between the first closing and the current month,
     * or the current month itself when that span has no gap.
     */
    private function suggestedMonth(string $current): string
    {
        $closed = NetWorthClosing::query()
            ->orderBy('month')
            ->pluck('month')
            ->map(function ($month) {
                if ($month instanceof \DateTimeInterface) {
                    return $month->format('Y-m');
                }

                return substr((string) $month, 0, 7);
            })
            ->all();

        if (! in_array($current, $closed, true)) {
            return $current;
        }

        $cursor = $closed[0];
        $gap = null;

        while ($cursor !== null && $cursor < $current) {
            if (! in_array($cursor, $closed, true)) {
                $gap = $cursor;
            }

            $cursor = Month::shift($cursor, 1);
        }

        return $gap ?? $current;
    }

    private function recordsLabel(int $count): string
    {
        return $count === 1 ? '1 mês' : $count.' meses';
    }

    private function sum(NetWorthClosing $closing): string
    {
        $total = '0.00';

        foreach ($closing->items as $item) {
            $total = Money::add($total, $item->balance);
        }

        return $total;
    }

    /**
     * @return Collection<int, NetWorthClosingItem>
     */
    private function sortedItems(NetWorthClosing $closing): Collection
    {
        $order = array_flip($this->orderedBanks()->pluck('id')->all());

        return $closing->items
            ->sortBy(fn (NetWorthClosingItem $item) => $order[$item->bank_id] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Same order as Manage: name, then id.
     *
     * @return Collection<int, Bank>
     */
    private function orderedBanks(): Collection
    {
        return $this->banks ??= Bank::query()->orderBy('name')->orderBy('id')->get();
    }

    /**
     * @param  array<int, array<string, mixed>>  $expected
     * @param  array<int, array<string, mixed>>  $items
     */
    private function assertItems(array $expected, array $items): void
    {
        $expectedIds = array_map(fn (array $bank) => (int) $bank['bank_id'], $expected);
        $sent = array_map(fn (array $item) => (int) $item['bank_id'], $items);
        sort($expectedIds);
        $sorted = $sent;
        sort($sorted);

        if ($expectedIds !== $sorted || count($sent) !== count(array_unique($sent))) {
            throw ValidationException::withMessages([
                'items' => 'Informe o saldo de todos os bancos.',
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function writeItems(NetWorthClosing $closing, array $items): void
    {
        foreach ($items as $item) {
            $row = new NetWorthClosingItem;
            $row->closing_id = $closing->id;
            $row->bank_id = (int) $item['bank_id'];
            $row->balance = Money::of($item['balance']);
            $row->save();
        }
    }

    private function notes(mixed $notes): ?string
    {
        if (! is_string($notes)) {
            return null;
        }

        $notes = trim($notes);

        return $notes === '' ? null : $notes;
    }

    private function variationTone(?string $variation): ?string
    {
        if ($variation === null || Money::compare($variation, '0') === 0) {
            return $variation === null ? null : 'neutral';
        }

        return Money::compare($variation, '0') === 1 ? 'positive' : 'negative';
    }

    private function reais(string $amount): string
    {
        $normalized = Money::of($amount);
        [$whole, $cents] = explode('.', $normalized);
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole);

        return 'R$ '.$grouped.','.$cents;
    }
}
