<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsToPlanning;
use App\Http\Requests\FixedExpenseRequest;
use App\Models\FixedExpense;
use App\Services\FixedExpenseService;
use Illuminate\Http\RedirectResponse;

class FixedExpenseController extends Controller
{
    use RedirectsToPlanning;

    public function store(FixedExpenseRequest $request, FixedExpenseService $expenses): RedirectResponse
    {
        $expenses->create($request->validated());

        return $this->redirectToPlanning();
    }

    public function update(FixedExpenseRequest $request, FixedExpense $fixedExpense, FixedExpenseService $expenses): RedirectResponse
    {
        $expenses->update($fixedExpense, $request->validated());

        return $this->redirectToPlanning();
    }

    public function archive(FixedExpense $fixedExpense, FixedExpenseService $expenses): RedirectResponse
    {
        $expenses->archive($fixedExpense);

        return $this->redirectToPlanning();
    }

    public function restore(FixedExpense $fixedExpense, FixedExpenseService $expenses): RedirectResponse
    {
        $expenses->restore($fixedExpense);

        return $this->redirectToPlanning();
    }
}
