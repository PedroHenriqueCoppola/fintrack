<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetWorthClosingItem extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'closing_id',
        'bank_id',
        'balance',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    public function closing(): BelongsTo
    {
        return $this->belongsTo(NetWorthClosing::class, 'closing_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
