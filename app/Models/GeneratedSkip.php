<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedSkip extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'fixed_expense_id',
        'installment_id',
        'installment_number',
        'generated_for',
    ];

    protected function casts(): array
    {
        return [
            'installment_number' => 'integer',
            'generated_for' => 'date',
        ];
    }

    public function fixedExpense(): BelongsTo
    {
        return $this->belongsTo(FixedExpense::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }
}
