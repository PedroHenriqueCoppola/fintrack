<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'name',
        'icon',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'initial_month' => 'date',
            'archived_at' => 'datetime',
        ];
    }
}
