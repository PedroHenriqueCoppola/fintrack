<?php

namespace App\Services;

use App\Models\SavedFilter;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SavedFilterService
{
    public function list(): Collection
    {
        return SavedFilter::query()->orderBy('name')->orderByDesc('id')->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function create(string $name, array $filters): SavedFilter
    {
        $name = trim($name);

        if (SavedFilter::query()->count() >= 20) {
            throw ValidationException::withMessages([
                'name' => 'Você já tem 20 filtros salvos. Apague um para salvar outro.',
            ]);
        }

        $taken = SavedFilter::query()
            ->whereRaw('unaccent(lower(name)) = unaccent(lower(?))', [$name])
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'name' => 'Já existe um filtro salvo com este nome.',
            ]);
        }

        $saved = new SavedFilter;
        $saved->name = $name;
        $saved->filters = $filters;
        $saved->save();

        return $saved;
    }

    public function delete(SavedFilter $savedFilter): void
    {
        $savedFilter->delete();
    }
}
