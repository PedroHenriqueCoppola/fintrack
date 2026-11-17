<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Installment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class InstallmentService
{
    public function __construct(
        private PlanGenerator $generator,
        private InstallmentCalculator $calculator,
    ) {}

    /**
     * @return array<int, string>
     */
    public function amounts(string $total, int $count): array
    {
        return $this->calculator->amounts($total, $count);
    }

    public function hasGenerated(Installment $installment): bool
    {
        return $installment->transactions()->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Installment
    {
        $this->calculator->assertSplittable($data['total_amount'], (int) $data['installments_count']);

        return DB::transaction(function () use ($data) {
            $installment = new Installment;
            $this->fill($installment, $data, false);
            $installment->save();
            $this->generator->syncInstallment($installment);

            return $installment;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Installment $installment, array $data): Installment
    {
        $locked = $this->hasGenerated($installment);

        if (! $locked) {
            $this->calculator->assertSplittable(
                (string) ($data['total_amount'] ?? $installment->total_amount),
                (int) ($data['installments_count'] ?? $installment->installments_count),
            );
        }

        return DB::transaction(function () use ($installment, $data, $locked) {
            $this->fill($installment, $data, $locked);
            $installment->save();
            $this->generator->syncInstallment($installment);

            return $installment;
        });
    }

    public function archive(Installment $installment): void
    {
        DB::transaction(function () use ($installment) {
            $installment->archived_at = now();
            $installment->save();
            $this->generator->syncInstallment($installment);
        });
    }

    public function restore(Installment $installment): void
    {
        DB::transaction(function () use ($installment) {
            $installment->archived_at = null;
            $installment->save();
            $this->generator->syncInstallment($installment);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(Installment $installment, array $data, bool $locked): void
    {
        $cardId = $data['card_id'] ?? null;
        $bankId = $data['bank_id'] ?? null;

        if ($cardId) {
            $bankId = Card::query()->findOrFail($cardId)->bank_id;
        }

        $installment->name = trim($data['name']);
        $installment->category_id = $data['category_id'];
        $installment->bank_id = $bankId;
        $installment->payment_method_id = $data['payment_method_id'];
        $installment->card_id = $cardId ?: null;
        $installment->notes = $data['notes'] ?? null;

        if ($locked) {
            return;
        }

        $installment->total_amount = Money::of($data['total_amount']);
        $installment->installments_count = (int) $data['installments_count'];
        $installment->first_date = $data['first_date'];
    }
}
