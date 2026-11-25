<?php

namespace App\Services\Reports;

use App\Support\Money;

/**
 * pt-BR labels for the PDF. Excel keeps real numbers and the preview
 * formats in the browser. Neither path recalculates a total.
 */
class ReportFormatter
{
    /**
     * @param  array<string, mixed>  $column
     */
    public function cell(mixed $value, array $column): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($column['type']) {
            'money' => $this->money($value, $column),
            'percent' => $this->percent($value, (bool) ($column['signed'] ?? false)),
            'date' => $this->date((string) $value),
            'integer' => (string) (int) $value,
            default => (string) $value,
        };
    }

    /**
     * @param  array<string, mixed>  $column
     */
    public function isNegative(mixed $value, array $column): bool
    {
        if ($column['type'] !== 'money' || $value === null || $value === '') {
            return false;
        }

        if (($column['tone'] ?? null) === 'negative') {
            return Money::compare($value, '0') !== 0;
        }

        return Money::compare($value, '0') === -1;
    }

    public function barWidth(mixed $value): string
    {
        $raw = trim((string) $value);

        if ($raw === '' || ! is_numeric($raw) || bccomp($raw, '0', 8) !== 1) {
            return '0%';
        }

        $scaled = bcmul($raw, '100', 2);

        if (bccomp($scaled, '100', 2) === 1) {
            $scaled = '100.00';
        }

        return $scaled.'%';
    }

    public function generatedAt(string $value): string
    {
        [$date, $time] = array_pad(explode(' ', $value, 2), 2, '');

        return trim($this->date($date).' '.$time);
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private function money(mixed $value, array $column): string
    {
        $amount = (string) $value;

        if (($column['tone'] ?? null) === 'negative' && Money::compare($amount, '0') === 1) {
            $amount = Money::sub('0', $amount);
        }

        if (($column['signed'] ?? false) && Money::compare($amount, '0') === 1) {
            return '+'.Money::label($amount);
        }

        return Money::label($amount);
    }

    private function percent(mixed $value, bool $signed): string
    {
        $scaled = bcmul((string) $value, '100', 8);
        $rounded = Money::roundHalfUp($scaled, 1);
        $negative = str_starts_with($rounded, '-');
        $absolute = $negative ? substr($rounded, 1) : $rounded;
        $text = str_replace('.', ',', $absolute).'%';

        if ($negative && Money::compare($rounded, '0') !== 0) {
            return '−'.$text;
        }

        if ($signed && Money::compare($rounded, '0') === 1) {
            return '+'.$text;
        }

        return $text;
    }

    private function date(string $value): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $match)) {
            return $value;
        }

        return $match[3].'/'.$match[2].'/'.$match[1];
    }
}
