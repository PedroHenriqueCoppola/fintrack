<?php

namespace App\Http\Resources;

use App\Support\UsageLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'kind' => $this->kind ?? 'account',
            'archived' => $this->archived_at !== null,
            'can_delete' => (bool) ($this->can_delete ?? false),
            'usage_summary' => $this->usage_summary,
            'usage' => (int) $this->active_cards_count > 0
                ? UsageLabel::count((int) $this->active_cards_count, 'cartão', 'cartões')
                : null,
        ];
    }
}
