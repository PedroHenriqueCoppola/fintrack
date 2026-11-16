<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class SavedFilter extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'name',
        'filters',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
        ];
    }
}
