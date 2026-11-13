<?php

namespace App\Http\Controllers;

use App\Http\Requests\PocketRequest;
use App\Models\Pocket;
use App\Services\PocketService;
use Illuminate\Http\RedirectResponse;

class PocketController extends Controller
{
    public function store(PocketRequest $request, PocketService $pockets): RedirectResponse
    {
        $pockets->create($request->validated());

        return back();
    }

    public function update(PocketRequest $request, Pocket $pocket, PocketService $pockets): RedirectResponse
    {
        $pockets->update($pocket, $request->validated());

        return back();
    }

    public function archive(Pocket $pocket, PocketService $pockets): RedirectResponse
    {
        $pockets->archive($pocket);

        return back();
    }

    public function restore(Pocket $pocket, PocketService $pockets): RedirectResponse
    {
        $pockets->restore($pocket);

        return back();
    }
}
