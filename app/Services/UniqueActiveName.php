<?php

namespace App\Services;

use App\Support\NameNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class UniqueActiveName
{
    public function __construct(private NameNormalizer $names) {}

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function assert(
        string $modelClass,
        string $name,
        string $message,
        ?int $ignoreId = null,
        ?string $scopeColumn = null,
        mixed $scopeValue = null,
    ): void {
        $key = $this->names->key($name);

        $query = $modelClass::query()->whereNull('archived_at');

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($scopeColumn !== null) {
            $query->where($scopeColumn, $scopeValue);
        }

        $taken = $query->pluck('name')->contains(
            fn (string $existing) => $this->names->key($existing) === $key
        );

        if ($taken) {
            throw ValidationException::withMessages([
                'name' => $message,
            ]);
        }
    }
}
