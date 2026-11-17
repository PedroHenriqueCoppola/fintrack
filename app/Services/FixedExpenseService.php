<?php

namespace App\Services;

use App\Models\Card;
use App\Models\FixedExpense;
use App\Support\Money;
use App\Support\Month;
use Illuminate\Support\Facades\DB;

class FixedExpenseService
{
    public function __construct(private PlanGenerator $generator) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): FixedExpense
    {
        return DB::transaction(function () use ($data) {
            $expense = new FixedExpense;
            $this->fill($expense, $data);
            $expense->save();
            $this->generator->syncFixed($expense, Money::of($expense->amount));

            return $expense;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FixedExpense $expense, array $data): FixedExpense
    {
        return DB::transaction(function () use ($expense, $data) {
            $previous = Money::of($expense->amount);
            $this->fill($expense, $data);
            $expense->save();
            $this->generator->syncFixed($expense, $previous);

            return $expense;
        });
    }

    public function archive(FixedExpense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $previous = Money::of($expense->amount);
            $expense->archived_at = now();
            $expense->save();
            $this->generator->syncFixed($expense, $previous);
        });
    }

    public function restore(FixedExpense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $previous = Money::of($expense->amount);
            $expense->archived_at = null;
            $expense->save();
            $this->generator->syncFixed($expense, $previous);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(FixedExpense $expense, array $data): void
    {
        $cardId = $data['card_id'] ?? null;
        $bankId = $data['bank_id'] ?? null;

        if ($cardId) {
            $bankId = Card::query()->findOrFail($cardId)->bank_id;
        }

        $expense->name = trim($data['name']);
        $expense->category_id = $data['category_id'];
        $expense->amount = Money::of($data['amount']);
        $expense->due_day = (int) $data['due_day'];
        $expense->start_month = Month::start($data['start_month']);
        $expense->end_month = empty($data['end_month']) ? null : Month::start($data['end_month']);
        $expense->bank_id = $bankId;
        $expense->payment_method_id = $data['payment_method_id'];
        $expense->card_id = $cardId ?: null;
        $expense->notes = $data['notes'] ?? null;
    }
}
