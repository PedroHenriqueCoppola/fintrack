<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavedFilterRequest;
use App\Models\SavedFilter;
use App\Services\SavedFilterService;
use Illuminate\Http\RedirectResponse;

class SavedFilterController extends Controller
{
    public function store(SavedFilterRequest $request, SavedFilterService $savedFilters): RedirectResponse
    {
        $savedFilters->create($request->validated('name'), $request->storedFilters());

        return back();
    }

    public function destroy(SavedFilter $savedFilter, SavedFilterService $savedFilters): RedirectResponse
    {
        $savedFilters->delete($savedFilter);

        return back();
    }
}
