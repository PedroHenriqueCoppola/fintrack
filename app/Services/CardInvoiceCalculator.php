<?php

namespace App\Services;

use DateTimeImmutable;

/**
 * Credit-card cycle, with no database access.
 *
 * A purchase on date D belongs to the invoice whose closing date is the
 * first closing strictly after D. A purchase on the closing day itself
 * belongs to the next invoice. The invoice is named by the closing month.
 */
class CardInvoiceCalculator
{
    public function __construct(
        private int $closingDay,
        private ?int $dueDay = null,
        private ?string $asOf = null,
    ) {}

    public function today(): string
    {
        return $this->asOf ?? now('America/Sao_Paulo')->toDateString();
    }

    public function closingDateFor(int $year, int $month): string
    {
        return $this->clamped($year, $month, $this->closingDay);
    }

    /**
     * YYYY-MM of the invoice that contains this purchase date.
     */
    public function invoiceKeyFor(string $date): string
    {
        $year = (int) substr($date, 0, 4);
        $month = (int) substr($date, 5, 2);
        $closing = $this->closingDateFor($year, $month);

        if ($date < $closing) {
            return sprintf('%04d-%02d', $year, $month);
        }

        return $this->shift($year, $month, 1);
    }

    /**
     * Cycle of the invoice that closes in $key (YYYY-MM).
     * The previous closing day is included. The closing day of $key is not.
     *
     * @return array{start: string, end: string, closing: string}
     */
    public function periodFor(string $key): array
    {
        $year = (int) substr($key, 0, 4);
        $month = (int) substr($key, 5, 2);
        $closing = $this->closingDateFor($year, $month);
        $previous = $this->shift($year, $month, -1);
        $start = $this->closingDateFor((int) substr($previous, 0, 4), (int) substr($previous, 5, 2));
        $end = (new DateTimeImmutable($closing))->modify('-1 day')->format('Y-m-d');

        return [
            'start' => $start,
            'end' => $end,
            'closing' => $closing,
        ];
    }

    /**
     * First due day strictly after the closing date, or null when the card has none.
     */
    public function dueDateFor(string $key): ?string
    {
        if ($this->dueDay === null) {
            return null;
        }

        $year = (int) substr($key, 0, 4);
        $month = (int) substr($key, 5, 2);
        $closing = $this->closingDateFor($year, $month);
        $candidate = $this->clamped($year, $month, $this->dueDay);

        if ($candidate > $closing) {
            return $candidate;
        }

        $next = $this->shift($year, $month, 1);

        return $this->clamped((int) substr($next, 0, 4), (int) substr($next, 5, 2), $this->dueDay);
    }

    /**
     * paid, closed (today is on or after the closing date) or open.
     */
    public function situation(string $key, bool $paid): string
    {
        if ($paid) {
            return 'paid';
        }

        $closing = $this->periodFor($key)['closing'];

        return $this->today() >= $closing ? 'closed' : 'open';
    }

    public function openKey(): string
    {
        return $this->invoiceKeyFor($this->today());
    }

    private function clamped(int $year, int $month, int $day): string
    {
        $last = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))
            ->modify('last day of this month')
            ->format('d');

        return sprintf('%04d-%02d-%02d', $year, $month, min(max($day, 1), $last));
    }

    private function shift(int $year, int $month, int $delta): string
    {
        $index = ($year * 12) + ($month - 1) + $delta;
        $nextYear = intdiv($index, 12);
        $nextMonth = ($index % 12) + 1;

        return sprintf('%04d-%02d', $nextYear, $nextMonth);
    }
}
