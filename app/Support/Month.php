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
    private const SHORT = [
        1 => 'jan',
        2 => 'fev',
        3 => 'mar',
        4 => 'abr',
        5 => 'mai',
        6 => 'jun',
        7 => 'jul',
        8 => 'ago',
        9 => 'set',
        10 => 'out',
        11 => 'nov',
        12 => 'dez',
    ];

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

    /**
     * "dez/2026" when the competence month differs from the transaction month.
     */
    public static function competenceLabel(?string $date, ?string $competence): ?string
    {
        if ($date === null || $competence === null || strlen($date) < 7 || strlen($competence) < 7) {
            return null;
        }

        $dateKey = substr($date, 0, 7);
        $competenceKey = substr($competence, 0, 7);

        if ($dateKey === $competenceKey) {
            return null;
        }

        $month = (int) substr($competenceKey, 5, 2);
        $year = substr($competenceKey, 0, 4);

        if (! isset(self::SHORT[$month]) || strlen($year) !== 4) {
            return null;
        }

        return self::SHORT[$month].'/'.$year;
    }

    public static function heading(string $date): string
    {
        $year = (int) substr($date, 0, 4);
        $month = (int) substr($date, 5, 2);
        $name = self::NAMES[$month - 1] ?? '';
        $currentYear = (int) now('America/Sao_Paulo')->format('Y');

        return $year === $currentYear ? $name : $name.' de '.$year;
    }

    public static function label(string $key): string
    {
        $start = self::start($key);
        $month = (int) substr($start, 5, 2);
        $year = substr($start, 0, 4);

        return self::NAMES[$month - 1].' '.$year;
    }

    public static function name(string $key, bool $lower = false): string
    {
        $start = self::start($key);
        $name = self::NAMES[((int) substr($start, 5, 2)) - 1];

        return $lower ? mb_strtolower($name, 'UTF-8') : $name;
    }

    public static function short(string $key): string
    {
        $start = self::start($key);

        return self::SHORT[(int) substr($start, 5, 2)];
    }

    /**
     * "novembro de 2026".
     */
    public static function phrase(string $key): string
    {
        return self::name($key, true).' de '.substr(self::start($key), 0, 4);
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
