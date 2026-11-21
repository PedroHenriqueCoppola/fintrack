<?php

namespace App\Http\Controllers;

use App\Http\Requests\NetWorthClosingRequest;
use App\Http\Requests\NetWorthFormRequest;
use App\Http\Requests\NetWorthPeriodRequest;
use App\Models\NetWorthClosing;
use App\Services\NetWorthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NetWorthController extends Controller
{
    public function index(NetWorthPeriodRequest $request, NetWorthService $netWorth): Response
    {
        $filters = $request->filters();

        return Inertia::render('NetWorth/Index', $netWorth->present(
            $filters['period'],
            $filters['from'],
            $filters['to'],
        ));
    }

    public function form(NetWorthFormRequest $request, NetWorthService $netWorth): JsonResponse
    {
        return response()->json($netWorth->form($request->month()));
    }

    public function store(NetWorthClosingRequest $request, NetWorthService $netWorth): RedirectResponse
    {
        $netWorth->create($request->closing());

        return redirect()->back();
    }

    public function update(
        NetWorthClosingRequest $request,
        NetWorthClosing $closing,
        NetWorthService $netWorth,
    ): RedirectResponse {
        $netWorth->update($closing, $request->closing());

        return redirect()->back();
    }

    public function destroy(NetWorthClosing $closing, NetWorthService $netWorth): RedirectResponse
    {
        $netWorth->delete($closing);

        return redirect()->back();
    }
}
