<?php

namespace App\Services;

use App\Support\Month;
use Illuminate\Support\Carbon;

class PeriodRange
{
    /**
     * @return array{from: ?string, to: ?string, label: string}
     */
    public function resolve(string $period, ?string $from, ?string $to): array
    {
        $today = Carbon::now('America/Sao_Paulo');

        $range = match ($period) {
            'previous_month' => $this->month($today->copy()->startOfMonth()->subMonth()),
            'quarter' => $this->bounds($today->copy()->startOfQuarter(), $today->copy()->endOfQuarter()),
            'semester' => $this->semester($today),
            'year' => $this->bounds($today->copy()->startOfYear(), $today->copy()->endOfYear()),
            'all' => ['from' => null, 'to' => null],
            'custom' => ['from' => $from, 'to' => $to],
            default => $this->month($today),
        };

        $range['label'] = $this->label($period, $range['from'], $range['to']);

        return $range;
    }

    /**
     * The window of the same length immediately before the resolved period.
     * "Todo o período" has no comparison.
     *
     * @return array{from: string, to: string}|null
     */
    public function previous(string $period, ?string $from, ?string $to): ?array
    {
        if ($period === 'all') {
            return null;
        }

        $current = $this->resolve($period, $from, $to);

        if ($current['from'] === null || $current['to'] === null) {
            return null;
        }

        $start = Carbon::parse($current['from'], 'America/Sao_Paulo')->startOfDay();
        $end = Carbon::parse($current['to'], 'America/Sao_Paulo')->startOfDay();

        return match ($period) {
            'quarter' => $this->bounds(
                $start->copy()->subMonthsNoOverflow(3)->startOfQuarter(),
                $start->copy()->subMonthsNoOverflow(3)->endOfQuarter(),
            ),
            'semester' => $this->previousSemester($start),
            'year' => $this->bounds(
                $start->copy()->subYear()->startOfYear(),
                $start->copy()->subYear()->endOfYear(),
            ),
            'custom' => $this->previousCustom($start, $end),
            default => $this->month($start->copy()->subMonth()),
        };
    }

    /**
     * Months drawn on a chart for this period.
     * Fewer than three months expands to the six months ending when the
     * period ends, never after the current month. More than $maxMonths keeps
     * the last $maxMonths. An open period is the last twelve months, unless
     * $openFromFirst names an earlier month: then it starts there, with at
     * least six months, and still stops at $maxMonths.
     *
     * @param  array{from: ?string, to: ?string}  $range
     * @return array<int, string>
     */
    public function chartMonths(array $range, int $maxMonths = 12, ?string $openFromFirst = null): array
    {
        $current = Month::currentKey();
        $limit = max($maxMonths, 18);

        if ($range['from'] === null || $range['to'] === null) {
            if ($openFromFirst === null || $openFromFirst === '') {
                return $this->monthWindow(Month::shift($current, -11) ?? $current, $current, $limit);
            }

            $end = $current;
            $start = $openFromFirst > $end ? $end : $openFromFirst;
            $minimum = Month::shift($end, -5) ?? $end;

            if ($start > $minimum) {
                $start = $minimum;
            }

            if ($this->inclusiveMonths($start, $end) > $maxMonths) {
                $start = Month::shift($end, -($maxMonths - 1)) ?? $start;
            }

            return $this->monthWindow($start, $end, $limit);
        }

        $start = substr($range['from'], 0, 7);
        $end = substr($range['to'], 0, 7);
        $count = $this->inclusiveMonths($start, $end);

        if ($count < 3) {
            $end = $end > $current ? $current : $end;
            $start = Month::shift($end, -5) ?? $end;
        } elseif ($count > $maxMonths) {
            $start = Month::shift($end, -($maxMonths - 1)) ?? $start;
        }

        return $this->monthWindow($start, $end, $limit);
    }

    /**
     * How many months the closed interval contains, counting both ends.
     */
    public function inclusiveMonths(string $start, string $end): int
    {
        $startIndex = ((int) substr($start, 0, 4) * 12) + (int) substr($start, 5, 2);
        $endIndex = ((int) substr($end, 0, 4) * 12) + (int) substr($end, 5, 2);

        return $endIndex - $startIndex + 1;
    }

    /**
     * Phrase shown next to an indicator variation. Null when there is no comparison.
     */
    public function comparisonLabel(string $period): ?string
    {
        return match ($period) {
            'current_month', 'previous_month' => 'vs mês anterior',
            'quarter' => 'vs trimestre anterior',
            'semester' => 'vs semestre anterior',
            'year' => 'vs ano anterior',
            'custom' => 'vs período anterior',
            default => null,
        };
    }

    /**
     * @return array{from: string, to: string}
     */
    private function month(Carbon $day): array
    {
        return $this->bounds($day->copy()->startOfMonth(), $day->copy()->endOfMonth());
    }

    /**
     * @return array{from: string, to: string}
     */
    private function semester(Carbon $today): array
    {
        $start = $today->copy()->month($today->month <= 6 ? 1 : 7)->startOfMonth();

        return $this->bounds($start, $start->copy()->addMonths(5)->endOfMonth());
    }

    /**
     * @return array{from: string, to: string}
     */
    private function previousSemester(Carbon $start): array
    {
        if ($start->month <= 6) {
            $from = $start->copy()->subYear()->month(7)->startOfMonth();
        } else {
            $from = $start->copy()->month(1)->startOfMonth();
        }

        return $this->bounds($from, $from->copy()->addMonths(5)->endOfMonth());
    }

    /**
     * Same number of days, ending the day before $from.
     *
     * @return array{from: string, to: string}
     */
    private function previousCustom(Carbon $from, Carbon $to): array
    {
        $span = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay());
        $previousTo = $from->copy()->subDay();

        return $this->bounds($previousTo->copy()->subDays($span), $previousTo);
    }

    /**
     * @return array<int, string>
     */
    private function monthWindow(string $start, string $end, int $limit = 18): array
    {
        $months = [];
        $cursor = $start;
        $guard = 0;

        while ($cursor !== null && $cursor <= $end && $guard < $limit) {
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
     * @return array{from: string, to: string}
     */
    private function bounds(Carbon $from, Carbon $to): array
    {
        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }

    private function label(string $period, ?string $from, ?string $to): string
    {
        if ($period === 'all' || $from === null) {
            return 'Todo o período';
        }

        $start = Carbon::parse($from, 'America/Sao_Paulo');

        return match ($period) {
            'quarter' => $start->quarter.'º trimestre de '.$start->year,
            'semester' => ($start->month <= 6 ? '1' : '2').'º semestre de '.$start->year,
            'year' => (string) $start->year,
            'custom' => $this->short($from).' a '.$this->short($to),
            default => $this->monthName($start->month).' '.$start->year,
        };
    }

    private function short(?string $date): string
    {
        if ($date === null) {
            return '';
        }

        return Carbon::parse($date)->format('d/m/Y');
    }

    private function monthName(int $month): string
    {
        return [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ][$month];
    }
}
