<?php

namespace App\Http\Controllers;

use App\Http\Requests\GoalRequest;
use App\Http\Requests\GoalsRequest;
use App\Models\Goal;
use App\Services\GoalService;
use App\Services\TransactionOptionsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GoalController extends Controller
{
    public function index(GoalsRequest $request, GoalService $goals): Response
    {
        return Inertia::render('Goals/Index', $goals->index($request->filters()));
    }

    public function show(
        GoalsRequest $request,
        Goal $goal,
        GoalService $goals,
        TransactionOptionsService $options,
    ): Response {
        return Inertia::render('Goals/Show', [
            ...$goals->show($goal, $request->filters()),
            'options' => $options->catalog(),
        ]);
    }

    public function store(GoalRequest $request, GoalService $goals): RedirectResponse
    {
        $goals->create($request->validated());

        return back();
    }

    public function update(GoalRequest $request, Goal $goal, GoalService $goals): RedirectResponse
    {
        $goals->update($goal, $request->validated());

        return back();
    }

    public function archive(Goal $goal, GoalService $goals): RedirectResponse
    {
        $goals->archive($goal);

        return back();
    }

    public function restore(Goal $goal, GoalService $goals): RedirectResponse
    {
        $goals->restore($goal);

        return back();
    }
}
