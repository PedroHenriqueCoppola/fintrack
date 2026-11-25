<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'name',
        'name_key',
    ];

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(Transaction::class);
    }
}
