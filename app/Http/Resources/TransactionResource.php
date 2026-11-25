<?php

namespace App\Http\Resources;

use App\Services\ReserveBalanceService;
use App\Support\Month;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'reserve_action' => $this->reserve_action,
            'reserve_support' => $this->type === 'reserve'
                ? app(ReserveBalanceService::class)->support(
                    (int) $this->category_id,
                    $this->reserve_action,
                    $this->status,
                    (string) $this->amount,
                )
                : null,
            'status' => $this->status,
            'amount' => $this->amount,
            'date' => $this->date?->toDateString(),
            'month_heading' => $this->date ? Month::heading($this->date->toDateString()) : null,
            'competence_date' => $this->competence_date?->toDateString(),
            'competence_label' => Month::competenceLabel(
                $this->date?->toDateString(),
                $this->competence_date?->toDateString(),
            ),
            'description' => $this->description,
            'notes' => $this->notes,
            'day_total' => $this->day_total,
            'category' => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'icon' => $this->category->icon,
                'color' => $this->category->color,
                'applies_to' => $this->category->applies_to,
                'nature' => $this->category->nature,
                'archived' => $this->category->archived_at !== null,
            ],
            'bank' => $this->bank === null ? null : [
                'id' => $this->bank->id,
                'name' => $this->bank->name,
                'archived' => $this->bank->archived_at !== null,
            ],
            'payment_method' => $this->paymentMethod === null ? null : [
                'id' => $this->paymentMethod->id,
                'name' => $this->paymentMethod->name,
                'requires_card' => (bool) $this->paymentMethod->requires_card,
                'archived' => $this->paymentMethod->archived_at !== null,
            ],
            'card' => $this->card === null ? null : [
                'id' => $this->card->id,
                'name' => $this->card->name,
                'bank_id' => $this->card->bank_id,
                'closing_day' => $this->card->closing_day,
                'archived' => $this->card->archived_at !== null,
            ],
            'generated' => $this->resource->isGenerated(),
            'fixed_expense' => $this->fixed_expense_id === null ? null : [
                'id' => $this->fixed_expense_id,
                'name' => $this->fixedExpense?->name,
            ],
            'installment' => $this->installment_id === null ? null : [
                'id' => $this->installment_id,
                'number' => $this->installment_number,
                'count' => $this->installment?->installments_count,
            ],
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])->values()->all()),
        ];
    }
}
