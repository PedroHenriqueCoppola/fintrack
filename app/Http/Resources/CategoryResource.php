<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'applies_to' => $this->applies_to,
            'nature' => $this->nature,
            'icon' => $this->icon,
            'color' => $this->color,
            'pocket_id' => $this->pocket_id,
            'pocket_name' => $this->pocket?->name,
            'initial_balance' => $this->initial_balance,
            'initial_month' => $this->initial_month?->toDateString(),
            'archived' => $this->archived_at !== null,
            'usage' => null,
        ];
    }
}
