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

    public static function sub(mixed $left, mixed $right): string
    {
        return bcsub(self::of($left), self::of($right), 2);
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
}
