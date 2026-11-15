<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'bank_id',
        'name',
        'closing_day',
        'due_day',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'closing_day' => 'integer',
            'due_day' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
