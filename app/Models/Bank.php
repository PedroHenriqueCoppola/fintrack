<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'name',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function pockets(): HasMany
    {
        return $this->hasMany(Pocket::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
