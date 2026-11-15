<?php

namespace App\Support;

class UsageLabel
{
    public static function count(int $count, string $one, string $many): string
    {
        return $count === 1 ? '1 '.$one : $count.' '.$many;
    }
}
