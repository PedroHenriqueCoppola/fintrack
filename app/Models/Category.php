<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'name',
        'icon',
        'color',
        'pocket_id',
        'initial_balance',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'initial_month' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function pocket(): BelongsTo
    {
        return $this->belongsTo(Pocket::class);
    }
}
