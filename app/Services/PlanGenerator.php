<?php

namespace App\Services;

use App\Models\FixedExpense;
use App\Models\GeneratedSkip;
use App\Models\Installment;
use App\Models\MonthlyPlan;
use App\Models\Transaction;
use App\Support\Money;
use App\Support\Month;
use Illuminate\Database\UniqueConstraintViolationException;

class PlanGenerator
{
    public function __construct(private InstallmentCalculator $installments) {}

    /**
     * Create the missing pending transactions of one month.
     * An existing transaction is left as it is, paid or pending.
     * A month the user removed stays removed (generated_skips).
     * This does not create a monthly plan.
     */
    public function generateMonth(string $monthStart): void
    {
        $key = Month::key($monthStart);

        FixedExpense::query()
            ->whereNull('archived_at')
            ->whereDate('start_month', '<=', $monthStart)
            ->where(function ($query) use ($monthStart) {
                $query->whereNull('end_month')
                    ->orWhereDate('end_month', '>=', $monthStart);
            })
            ->orderBy('id')
            ->each(function (FixedExpense $expense) use ($monthStart, $key) {
                $this->createFixedIfMissing($expense, $monthStart, $key);
            });

        Installment::query()
            ->whereNull('archived_at')
            ->orderBy('id')
            ->each(function (Installment $installment) use ($monthStart, $key) {
                $number = $this->installments->numberInMonth($installment, $key);

                if ($number === null) {
                    return;
                }

                $this->createInstallmentIfMissing($installment, $number, $monthStart, $key);
            });
    }

    /**
     * After a fixed expense changes: drop pendings that no longer belong,
     * create the ones missing in confirmed months, and refresh pendings.
     * A pending whose amount was edited by hand keeps that amount.
     * A paid transaction is never changed or deleted.
     */
    public function syncFixed(FixedExpense $expense, string $previousAmount): void
    {
        $this->pruneFixed($expense);

        foreach ($this->monthsToSync() as $monthStart) {
            if (! $this->fixedApplies($expense, $monthStart)) {
                continue;
            }

            $key = Month::key($monthStart);
            $this->createFixedIfMissing($expense, $monthStart, $key);
            $this->refreshFixedPending($expense, $monthStart, $previousAmount);
        }
    }

    public function syncInstallment(Installment $installment): void
    {
        $this->pruneInstallment($installment);

        if ($installment->archived_at !== null) {
            return;
        }

        foreach ($this->monthsToSync() as $monthStart) {
            $key = Month::key($monthStart);
            $number = $this->installments->numberInMonth($installment, $key);

            if ($number === null) {
                continue;
            }

            $this->createInstallmentIfMissing($installment, $number, $monthStart, $key);
            $this->refreshInstallmentPending($installment, $number);
        }
    }

    /**
     * Pendings of later months that still have the old default follow the new one.
     */
    public function applyAmountForward(FixedExpense $expense, string $previousAmount, string $afterMonth): void
    {
        Transaction::query()
            ->where('fixed_expense_id', $expense->id)
            ->where('status', 'pending')
            ->whereDate('generated_for', '>', $afterMonth)
            ->where('amount', Money::of($previousAmount))
            ->update(['amount' => Money::of($expense->amount)]);
    }

    public function fixedApplies(FixedExpense $expense, string $monthStart): bool
    {
        if ($expense->archived_at !== null) {
            return false;
        }

        if ($expense->start_month->toDateString() > $monthStart) {
            return false;
        }

        return $expense->end_month === null || $expense->end_month->toDateString() >= $monthStart;
    }

    /**
     * Notice for a single full month after the current one, when that plan
     * is not confirmed and at least one fixed expense or installment falls in it.
     *
     * @return array{message: string, month: string}|null
     */
    public function futureForecastNotice(?string $from, ?string $to): ?array
    {
        $key = $this->fullMonthAfterCurrent($from, $to);

        if ($key === null) {
            return null;
        }

        $start = Month::start($key);
        $confirmed = MonthlyPlan::query()
            ->whereDate('month', $start)
            ->where('status', 'confirmed')
            ->exists();

        if ($confirmed) {
            return null;
        }

        $count = $this->scheduledCount($start, $key);

        if ($count === 0) {
            return null;
        }

        $word = $count === 1 ? 'fixo' : 'fixos';

        return [
            'message' => "Há {$count} {$word} e parcelas previstos para este mês.",
            'month' => $key,
        ];
    }

    private function createFixedIfMissing(FixedExpense $expense, string $monthStart, string $key): void
    {
        $exists = Transaction::query()
            ->where('fixed_expense_id', $expense->id)
            ->whereDate('generated_for', $monthStart)
            ->exists();

        if ($exists || $this->fixedWasSkipped($expense, $monthStart)) {
            return;
        }

        $transaction = $this->baseTransaction($expense, $monthStart);
        $transaction->amount = Money::of($expense->amount);
        $transaction->date = Month::clampDay($key, (int) $expense->due_day);
        $transaction->description = $expense->name;
        $transaction->fixed_expense_id = $expense->id;
        $this->saveIgnoringDuplicate($transaction);
    }

    private function refreshFixedPending(FixedExpense $expense, string $monthStart, string $previousAmount): void
    {
        if ($monthStart < Month::currentStart()) {
            return;
        }

        $existing = Transaction::query()
            ->where('fixed_expense_id', $expense->id)
            ->whereDate('generated_for', $monthStart)
            ->first();

        if (! $existing instanceof Transaction || $existing->status === 'paid') {
            return;
        }

        $this->copyTemplate($existing, $expense);
        $existing->description = $expense->name;

        if (Money::compare($existing->amount, $previousAmount) === 0) {
            $existing->amount = Money::of($expense->amount);
        }

        $existing->save();
    }

    private function createInstallmentIfMissing(Installment $installment, int $number, string $monthStart, string $key): void
    {
        $exists = Transaction::query()
            ->where('installment_id', $installment->id)
            ->where('installment_number', $number)
            ->exists();

        if ($exists || $this->installmentWasSkipped($installment, $number)) {
            return;
        }

        $day = (int) $installment->first_date->format('d');
        $transaction = $this->baseTransaction($installment, $monthStart);
        $transaction->amount = $this->installments->amountForNumber(
            (string) $installment->total_amount,
            (int) $installment->installments_count,
            $number,
        );
        $transaction->date = Month::clampDay($key, $day);
        $transaction->description = $this->description($installment, $number);
        $transaction->installment_id = $installment->id;
        $transaction->installment_number = $number;
        $this->saveIgnoringDuplicate($transaction);
    }

    private function refreshInstallmentPending(Installment $installment, int $number): void
    {
        $monthStart = $installment->first_date->copy()->startOfMonth()->addMonths($number - 1)->toDateString();

        if ($monthStart < Month::currentStart()) {
            return;
        }

        $existing = Transaction::query()
            ->where('installment_id', $installment->id)
            ->where('installment_number', $number)
            ->first();

        if (! $existing instanceof Transaction || $existing->status === 'paid') {
            return;
        }

        $this->copyTemplate($existing, $installment);
        $existing->description = $this->description($installment, $number);
        $existing->save();
    }

    private function pruneFixed(FixedExpense $expense): void
    {
        if ($expense->end_month !== null) {
            $this->deletePendingFixedAfter($expense, $expense->end_month->toDateString());
        }

        if ($expense->archived_at !== null) {
            $this->deletePendingFixedAfter($expense, Month::currentStart());
        }
    }

    private function pruneInstallment(Installment $installment): void
    {
        if ($installment->archived_at === null) {
            return;
        }

        Transaction::query()
            ->where('installment_id', $installment->id)
            ->where('status', 'pending')
            ->whereDate('generated_for', '>', Month::currentStart())
            ->delete();
    }

    private function deletePendingFixedAfter(FixedExpense $expense, string $monthStart): void
    {
        Transaction::query()
            ->where('fixed_expense_id', $expense->id)
            ->where('status', 'pending')
            ->whereDate('generated_for', '>', $monthStart)
            ->delete();
    }

    /**
     * The current month is always generated. Later months only when that plan
     * is confirmed. Past months are never generated from a catalog change.
     *
     * @return array<int, string>
     */
    private function monthsToSync(): array
    {
        $current = Month::currentStart();
        $months = [$current];

        foreach ($this->confirmedStarts() as $monthStart) {
            if ($monthStart > $current) {
                $months[] = $monthStart;
            }
        }

        return $months;
    }

    private function fixedWasSkipped(FixedExpense $expense, string $monthStart): bool
    {
        return GeneratedSkip::query()
            ->where('fixed_expense_id', $expense->id)
            ->whereDate('generated_for', $monthStart)
            ->exists();
    }

    private function installmentWasSkipped(Installment $installment, int $number): bool
    {
        return GeneratedSkip::query()
            ->where('installment_id', $installment->id)
            ->where('installment_number', $number)
            ->exists();
    }

    private function fullMonthAfterCurrent(?string $from, ?string $to): ?string
    {
        if ($from === null || $to === null || ! preg_match('/^(\d{4})-(\d{2})-01$/', $from, $match)) {
            return null;
        }

        $key = $match[1].'-'.$match[2];

        if (Month::startOrNull($key) === null || Month::end($key) !== $to || $key <= Month::currentKey()) {
            return null;
        }

        return $key;
    }

    private function scheduledCount(string $monthStart, string $monthKey): int
    {
        $fixed = FixedExpense::query()
            ->whereNull('archived_at')
            ->whereDate('start_month', '<=', $monthStart)
            ->where(function ($query) use ($monthStart) {
                $query->whereNull('end_month')
                    ->orWhereDate('end_month', '>=', $monthStart);
            })
            ->count();

        $year = (int) substr($monthKey, 0, 4);
        $month = (int) substr($monthKey, 5, 2);
        $installments = Installment::query()
            ->whereNull('archived_at')
            ->whereRaw(
                '((CAST(? AS int) - CAST(extract(year from first_date) AS int)) * 12 + (CAST(? AS int) - CAST(extract(month from first_date) AS int)) + 1) between 1 and installments_count',
                [$year, $month],
            )
            ->count();

        return $fixed + $installments;
    }

    /**
     * @return array<int, string>
     */
    private function confirmedStarts(): array
    {
        return MonthlyPlan::query()
            ->where('status', 'confirmed')
            ->orderBy('month')
            ->pluck('month')
            ->map(function ($month) {
                if ($month instanceof \DateTimeInterface) {
                    return $month->format('Y-m-d');
                }

                return substr((string) $month, 0, 10);
            })
            ->all();
    }

    private function baseTransaction(FixedExpense|Installment $source, string $monthStart): Transaction
    {
        $transaction = new Transaction;
        $transaction->type = 'expense';
        $transaction->status = 'pending';
        $transaction->generated_for = $monthStart;
        $this->copyTemplate($transaction, $source);

        return $transaction;
    }

    private function copyTemplate(Transaction $transaction, FixedExpense|Installment $source): void
    {
        $transaction->category_id = $source->category_id;
        $transaction->bank_id = $source->bank_id;
        $transaction->payment_method_id = $source->payment_method_id;
        $transaction->card_id = $source->card_id;
        $transaction->notes = $source->notes;
    }

    private function description(Installment $installment, int $number): string
    {
        $suffix = sprintf(' (%d/%d)', $number, $installment->installments_count);
        $room = 120 - mb_strlen($suffix);

        return mb_substr($installment->name, 0, max(1, $room)).$suffix;
    }

    private function saveIgnoringDuplicate(Transaction $transaction): void
    {
        try {
            $transaction->save();
        } catch (UniqueConstraintViolationException) {
            // The unique index is what makes a second confirm a no-op.
        }
    }
}
