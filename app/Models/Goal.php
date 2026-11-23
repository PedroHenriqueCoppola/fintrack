<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'name',
        'target_amount',
        'baseline_amount',
        'frozen_amount',
        'frozen_at',
        'deadline',
        'category_id',
        'notes',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'baseline_amount' => 'decimal:2',
            'frozen_amount' => 'decimal:2',
            'frozen_at' => 'datetime',
            'deadline' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
