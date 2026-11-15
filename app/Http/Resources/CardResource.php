<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'bank_id' => $this->bank_id,
            'bank_name' => $this->bank?->name,
            'closing_day' => $this->closing_day,
            'due_day' => $this->due_day,
            'color' => $this->color,
            'archived' => $this->archived_at !== null,
            'usage' => null,
        ];
    }
}
