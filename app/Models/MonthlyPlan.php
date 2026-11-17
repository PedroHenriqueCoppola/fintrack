<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyPlan extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'month',
        'expected_income',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'expected_income' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(MonthlyPlanItem::class);
    }
}
