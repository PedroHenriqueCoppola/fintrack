<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardInvoicePayment extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'card_id',
        'closing_month',
        'paid_on',
    ];

    protected function casts(): array
    {
        return [
            'closing_month' => 'date',
            'paid_on' => 'date',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
