<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Carbon;

class CategoryService
{
    public function __construct(private UniqueActiveName $names) {}

    public function create(array $data): Category
    {
        $name = trim($data['name']);
        $this->names->assert(Category::class, $name, 'Já existe uma categoria ativa com este nome.');

        $category = new Category;
        $category->name = $name;
        $category->applies_to = $data['applies_to'];
        $category->icon = $data['icon'] ?? null;
        $category->color = $data['color'] ?? null;
        $category->pocket_id = $data['pocket_id'] ?? null;

        if ($data['applies_to'] === 'expense') {
            $category->nature = $data['nature'];
            $category->initial_balance = $this->balance($data['initial_balance'] ?? null);
            $category->initial_month = Carbon::now('America/Sao_Paulo')->startOfMonth()->toDateString();
        } else {
            $category->nature = null;
            $category->initial_balance = '0.00';
            $category->initial_month = null;
        }

        $category->save();

        return $category;
    }

    public function update(Category $category, array $data): Category
    {
        $name = trim($data['name']);

        if ($category->archived_at === null) {
            $this->names->assert(Category::class, $name, 'Já existe uma categoria ativa com este nome.', $category->id);
        }

        $category->name = $name;
        $category->icon = $data['icon'] ?? null;
        $category->color = $data['color'] ?? null;
        $category->pocket_id = $data['pocket_id'] ?? null;

        if ($category->applies_to === 'expense' && array_key_exists('initial_balance', $data)) {
            $category->initial_balance = $this->balance($data['initial_balance']);
        }

        $category->save();

        return $category;
    }

    public function archive(Category $category): void
    {
        $category->archived_at = now();
        $category->save();
    }

    public function restore(Category $category): void
    {
        $this->names->assert(Category::class, $category->name, 'Já existe uma categoria ativa com este nome.', $category->id);

        $category->archived_at = null;
        $category->save();
    }

    private function balance(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return bcadd((string) $value, '0', 2);
    }
}
