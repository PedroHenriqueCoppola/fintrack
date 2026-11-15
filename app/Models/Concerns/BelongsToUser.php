<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Keeps a user's records out of every other user's queries.
 *
 * During an authenticated request the scope forces user_id, so a value sent
 * in the request is never stored.
 * A web request with no authenticated user matches nothing.
 * In console (tinker, artisan, jobs) there is no user and the scope does not
 * filter. The caller must set user_id.
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope('belongs_to_user', function (Builder $query): void {
            $userId = Auth::id();

            if ($userId === null) {
                if (! app()->runningInConsole()) {
                    $query->whereRaw('1 = 0');
                }

                return;
            }

            $query->where($query->getModel()->qualifyColumn('user_id'), $userId);
        });

        static::creating(function (Model $model): void {
            $userId = Auth::id();

            if ($userId !== null) {
                $model->user_id = $userId;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
