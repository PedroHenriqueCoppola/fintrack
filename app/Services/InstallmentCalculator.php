<?php

namespace App\Services;

use App\Models\Installment;
use App\Support\Money;
use App\Support\Month;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class InstallmentCalculator
{
    /**
     * Each parcel is the total divided by the count, rounded down to cents.
     * The remainder goes to the last parcel, so the parcels sum to the total.
     *
     * @return array<int, string>
     */
    public function amounts(string $total, int $count): array
    {
        $total = Money::of($total);
        $base = Money::divFloor($total, $count);
        $amounts = [];
        $used = '0.00';

        for ($number = 1; $number <= $count; $number++) {
            if ($number === $count) {
                $amounts[$number] = Money::sub($total, $used);
                break;
            }

            $amounts[$number] = $base;
            $used = Money::add($used, $base);
        }

        return $amounts;
    }

    public function amountForNumber(string $total, int $count, int $number): string
    {
        $base = Money::divFloor($total, $count);

        if ($number < $count) {
            return $base;
        }

        $used = bcmul($base, (string) ($count - 1), 2);

        return Money::sub($total, $used);
    }

    public function assertSplittable(string $total, int $count): void
    {
        if ((int) Money::cents($total) < $count) {
            throw ValidationException::withMessages([
                'total_amount' => 'O valor total é baixo demais para esse número de parcelas.',
            ]);
        }
    }

    /**
     * Parcel n falls in the month of the first date plus n − 1 months.
     */
    public function numberInMonth(Installment $installment, string $monthKey): ?int
    {
        $first = $installment->first_date->copy()->startOfMonth();
        $target = Carbon::createFromFormat('Y-m-d', Month::start($monthKey))->startOfMonth();

        if ($target->lt($first)) {
            return null;
        }

        $number = (($target->year - $first->year) * 12) + ($target->month - $first->month) + 1;

        if ($number < 1 || $number > $installment->installments_count) {
            return null;
        }

        return $number;
    }
}
