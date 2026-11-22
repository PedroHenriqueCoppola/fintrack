<?php

namespace App\Support;

class Money
{
    public static function of(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        $string = trim((string) $value);

        if (! is_numeric($string)) {
            return '0.00';
        }

        return bcadd($string, '0', 2);
    }

    public static function add(mixed $left, mixed $right): string
    {
        return bcadd(self::of($left), self::of($right), 2);
    }

    public static function sub(mixed $left, mixed $right): string
    {
        return bcsub(self::of($left), self::of($right), 2);
    }

    /**
     * Divide and keep the cents that fit, dropping the remainder.
     * bcdiv truncates toward zero.
     */
    public static function divFloor(mixed $amount, int $count): string
    {
        if ($count <= 0) {
            return '0.00';
        }

        return bcdiv(self::of($amount), (string) $count, 2);
    }

    /**
     * Divide and round up to the next cent.
     * An exact quotient stays as it is. Zero and negative amounts stay 0.00.
     */
    public static function divCeil(mixed $amount, int $count): string
    {
        $value = self::of($amount);

        if ($count <= 0 || self::compare($value, '0') !== 1) {
            return '0.00';
        }

        $quotient = bcdiv($value, (string) $count, 2);
        $product = bcmul($quotient, (string) $count, 2);

        if (bccomp($product, $value, 2) === 0) {
            return $quotient;
        }

        return bcadd($quotient, '0.01', 2);
    }

    /**
     * "R$ 1.100,00" or "−R$ 900,00". The minus is the real minus sign.
     */
    public static function label(mixed $amount): string
    {
        $value = self::of($amount);
        $negative = self::compare($value, '0') === -1;
        $absolute = $negative ? self::sub('0', $value) : $value;
        [$whole, $cents] = array_pad(explode('.', $absolute), 2, '00');
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) ?? $whole;

        return ($negative ? '−' : '').'R$ '.$grouped.','.$cents;
    }

    public static function cents(mixed $amount): string
    {
        return bcmul(self::of($amount), '100', 0);
    }

    /**
     * Divide and round half up to 2 decimal places.
     * bcmath truncates, so the extra digit plus 0.005 does the rounding.
     */
    public static function divHalfUp(mixed $amount, int $count): string
    {
        if ($count <= 0) {
            return '0.00';
        }

        $quotient = bcdiv(self::of($amount), (string) $count, 3);

        return bcadd($quotient, '0.005', 2);
    }

    public static function compare(mixed $left, mixed $right): int
    {
        return bccomp(self::of($left), self::of($right), 2);
    }

    /**
     * Integer percent, rounded half up once.
     * A total of zero has no percent.
     */
    public static function wholePercent(mixed $part, mixed $total): ?int
    {
        if (self::compare($total, '0') !== 1) {
            return null;
        }

        $scaled = bcmul(bcdiv(self::of($part), self::of($total), 8), '100', 8);

        return (int) self::roundHalfUp($scaled, 0);
    }

    /**
     * Integer percent of planned that was spent.
     * The division is rounded half up to one decimal, then that result
     * is rounded half up to the integer shown on the screen.
     * A planned amount of zero has no percent.
     */
    public static function usedPercent(mixed $spent, mixed $planned): ?int
    {
        if (self::compare($planned, '0') !== 1) {
            return null;
        }

        $ratio = bcdiv(self::of($spent), self::of($planned), 8);
        $scaled = bcmul($ratio, '100', 8);
        $oneDecimal = self::roundHalfUp($scaled, 1);

        return (int) self::roundHalfUp($oneDecimal, 0);
    }

    /**
     * Length of a donut arc. Not money: the caller passes the circumference.
     */
    public static function share(mixed $part, mixed $total, string $circumference): string
    {
        if (self::compare($total, '0') !== 1 || self::compare($part, '0') !== 1) {
            return '0.00';
        }

        $product = bcmul(self::of($part), $circumference, 6);

        return self::roundHalfUp(bcdiv($product, self::of($total), 6), 2);
    }

    /**
     * True when monthly * 100 >= planned * percent.
     * A planned amount of zero is never high impact.
     */
    public static function reachesPercent(mixed $monthly, mixed $planned, int $percent): bool
    {
        if ($percent <= 0 || self::compare($planned, '0') !== 1) {
            return false;
        }

        $left = bcmul(self::of($monthly), '100', 2);
        $right = bcmul(self::of($planned), (string) $percent, 2);

        return bccomp($left, $right, 2) >= 0;
    }

    public static function roundHalfUp(string $value, int $scale): string
    {
        $negative = str_starts_with($value, '-');
        $absolute = $negative ? substr($value, 1) : $value;
        $increment = '0.'.str_repeat('0', $scale).'5';
        $rounded = bcadd($absolute, $increment, $scale);

        return $negative ? '-'.$rounded : $rounded;
    }
}
