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
            'archived' => $this->archived_at !== null,
            'usage' => UsageLabel::pair(
                (int) $this->active_pockets_count,
                'caixinha',
                'caixinhas',
                (int) $this->active_cards_count,
                'cartão',
                'cartões',
            ),
        ];
    }
}
