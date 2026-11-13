<?php

namespace App\Support;

class UsageLabel
{
    public static function count(int $count, string $one, string $many): string
    {
        return $count === 1 ? '1 '.$one : $count.' '.$many;
    }

    public static function pair(
        int $first,
        string $oneFirst,
        string $manyFirst,
        int $second,
        string $oneSecond,
        string $manySecond,
    ): string {
        return self::count($first, $oneFirst, $manyFirst)
            .' e '
            .self::count($second, $oneSecond, $manySecond);
    }
}
