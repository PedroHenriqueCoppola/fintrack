<?php

namespace App\Services\Reports;

use App\Support\Money;

/**
 * The only place that turns a raw report value into pt-BR text and a tone.
 * Preview and PDF print the text. Excel stores the raw value.
 */
class ReportFormatter
{
    /**
     * @param  array<string, mixed>  $column
     * @return array{raw: mixed, text: string, tone: string}
     */
    public function present(mixed $value, array $column): array
    {
        if ($value === null || $value === '') {
            return ['raw' => null, 'text' => '—', 'tone' => 'muted'];
        }

        return match ($column['type']) {
            'money' => $this->money((string) $value, (bool) ($column['signed'] ?? false)),
            'percent' => $this->percent((string) $value, (bool) ($column['signed'] ?? false)),
            'date' => ['raw' => $value, 'text' => $this->date((string) $value), 'tone' => 'neutral'],
            'integer' => $this->integer($value),
            default => ['raw' => $value, 'text' => (string) $value, 'tone' => 'neutral'],
        };
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
     * @return array{raw: string, text: string, tone: string}
     */
    private function money(string $amount, bool $signed): array
    {
        $amount = Money::of($amount);
        $compare = Money::compare($amount, '0');

        if ($compare === 0) {
            return ['raw' => $amount, 'text' => Money::label($amount), 'tone' => 'muted'];
        }

        if ($compare < 0) {
            return ['raw' => $amount, 'text' => Money::label($amount), 'tone' => 'negative'];
        }

        $label = Money::label($amount);

        return [
            'raw' => $amount,
            'text' => $signed ? '+'.$label : $label,
            'tone' => $signed ? 'positive' : 'neutral',
        ];
    }

    /**
     * @return array{raw: string, text: string, tone: string}
     */
    private function percent(string $value, bool $signed): array
    {
        $scaled = bcmul($value, '100', 8);
        $rounded = Money::roundHalfUp($scaled, 1);
        $compare = Money::compare($rounded, '0');
        $negative = str_starts_with($rounded, '-');
        $absolute = $negative ? substr($rounded, 1) : $rounded;
        $text = str_replace('.', ',', $absolute).'%';

        if ($compare === 0) {
            return ['raw' => $value, 'text' => $text, 'tone' => 'muted'];
        }

        if ($compare < 0) {
            return ['raw' => $value, 'text' => '−'.$text, 'tone' => 'negative'];
        }

        return [
            'raw' => $value,
            'text' => $signed ? '+'.$text : $text,
            'tone' => $signed ? 'positive' : 'neutral',
        ];
    }

    /**
     * @return array{raw: int, text: string, tone: string}
     */
    private function integer(mixed $value): array
    {
        $number = (int) $value;

        return [
            'raw' => $number,
            'text' => (string) $number,
            'tone' => $number === 0 ? 'muted' : 'neutral',
        ];
    }

    private function date(string $value): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $match)) {
            return $value;
        }

        return $match[3].'/'.$match[2].'/'.$match[1];
    }
}
