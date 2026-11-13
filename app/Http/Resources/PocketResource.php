<?php

namespace App\Http\Resources;

use App\Support\UsageLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PocketResource extends JsonResource
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
            'type' => $this->type,
            'color' => $this->color,
            'archived' => $this->archived_at !== null,
            'usage' => UsageLabel::count(
                (int) $this->active_categories_count,
                'categoria',
                'categorias',
            ),
        ];
    }
}
