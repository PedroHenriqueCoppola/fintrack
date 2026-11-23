<?php

namespace App\Http\Resources;

use App\Support\Month;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deadline = $this->deadline?->format('Y-m');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'target_amount' => $this->target_amount,
            'deadline' => $this->deadline?->toDateString(),
            'deadline_key' => $deadline,
            'deadline_phrase' => $deadline === null ? null : Month::phrase($deadline),
            'notes' => $this->notes,
            'category_id' => $this->category_id,
            'category' => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'icon' => $this->category->icon,
                'color' => $this->category->color,
                'archived' => $this->category->archived_at !== null,
            ],
            'progress' => $this->progress,
            'percent' => $this->percent,
            'remaining' => $this->remaining,
            'reached' => (bool) $this->reached,
            'surplus' => $this->surplus,
            'state' => $this->state,
            'monthly_amount' => $this->monthly_amount,
            'overdue' => (bool) $this->overdue,
            'period_saved' => $this->period_saved,
            'href' => $this->href,
        ];
    }
}
