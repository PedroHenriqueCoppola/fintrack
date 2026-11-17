<?php

namespace App\Services;

use App\Models\Transaction;
use App\Support\Money;

class SpendingService
{
    /**
     * Paid expenses in the month, grouped by category.
     * The month is the competence date, or the transaction date when it is empty.
     * Pending expenses and income are left out. One grouped query.
     *
     * @return array<int, string>
     */
    public function totals(string $start, string $end): array
    {
        $rows = Transaction::query()
            ->where('type', 'expense')
            ->where('status', 'paid')
            ->whereRaw('coalesce(competence_date, date) between ? and ?', [$start, $end])
            ->selectRaw('category_id, sum(amount)::text as spent')
            ->groupBy('category_id')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row->category_id] = Money::of($row->spent);
        }

        return $totals;
    }
}
