<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetWorthClosing extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'month',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(NetWorthClosingItem::class, 'closing_id');
    }
}
