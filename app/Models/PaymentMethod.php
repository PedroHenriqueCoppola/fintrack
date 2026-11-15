<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'name',
        'requires_card',
    ];

    protected function casts(): array
    {
        return [
            'requires_card' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }
}
