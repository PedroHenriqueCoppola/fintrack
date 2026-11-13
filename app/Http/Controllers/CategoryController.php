<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;

class CategoryController extends Controller
{
    public function store(CategoryRequest $request, CategoryService $categories): RedirectResponse
    {
        $categories->create($request->validated());

        return back();
    }

    public function update(CategoryRequest $request, Category $category, CategoryService $categories): RedirectResponse
    {
        $categories->update($category, $request->validated());

        return back();
    }

    public function archive(Category $category, CategoryService $categories): RedirectResponse
    {
        $categories->archive($category);

        return back();
    }

    public function restore(Category $category, CategoryService $categories): RedirectResponse
    {
        $categories->restore($category);

        return back();
    }
}
