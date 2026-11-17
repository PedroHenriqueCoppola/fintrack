<?php

namespace App\Services;

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
