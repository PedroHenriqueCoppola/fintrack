<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class Month
{
    public const MIN = '2000-01';

    public const MAX = '2100-12';

    /**
     * @var array<int, string>
     */
    private const NAMES = [
        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
    ];

    public static function currentKey(): string
    {
        return now('America/Sao_Paulo')->format('Y-m');
    }

    public static function currentStart(): string
    {
        return self::start(self::currentKey());
    }

    /**
     * First day of a valid YYYY-MM, or null.
     */
    public static function startOrNull(string $value): ?string
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $value, $match)) {
            return null;
        }

        $year = (int) $match[1];
        $month = (int) $match[2];

        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            return null;
        }

        return sprintf('%04d-%02d-01', $year, $month);
    }

    public static function start(string $key): string
    {
        $start = self::startOrNull($key);

        if ($start === null) {
            throw ValidationException::withMessages([
                'month' => 'Informe um mês entre 01/2000 e 12/2100.',
            ]);
        }

        return $start;
    }

    public static function end(string $key): string
    {
        $start = self::start($key);
        $date = Carbon::createFromFormat('Y-m-d', $start);

        return $date->endOfMonth()->toDateString();
    }

    public static function key(string $start): string
    {
        return substr($start, 0, 7);
    }

    public static function label(string $key): string
    {
        $start = self::start($key);
        $month = (int) substr($start, 5, 2);
        $year = substr($start, 0, 4);

        return self::NAMES[$month - 1].' '.$year;
    }

    public static function shift(string $key, int $delta): ?string
    {
        $start = self::startOrNull($key);

        if ($start === null) {
            return null;
        }

        $year = (int) substr($start, 0, 4);
        $month = (int) substr($start, 5, 2);
        $index = $year * 12 + ($month - 1) + $delta;
        $nextYear = intdiv($index, 12);
        $nextMonth = ($index % 12) + 1;
        $next = sprintf('%04d-%02d', $nextYear, $nextMonth);

        return self::startOrNull($next) === null ? null : $next;
    }

    public static function clampDay(string $key, int $day): string
    {
        $start = self::start($key);
        $year = (int) substr($start, 0, 4);
        $month = (int) substr($start, 5, 2);
        $last = (int) Carbon::createFromFormat('Y-m-d', $start)->endOfMonth()->format('d');
        $day = max(1, min($day, $last));

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
