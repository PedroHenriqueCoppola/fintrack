<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsToPlanning;
use App\Http\Requests\InstallmentRequest;
use App\Models\Installment;
use App\Services\InstallmentService;
use Illuminate\Http\RedirectResponse;

class InstallmentController extends Controller
{
    use RedirectsToPlanning;

    public function store(InstallmentRequest $request, InstallmentService $installments): RedirectResponse
    {
        $installments->create($request->validated());

        return $this->redirectToPlanning();
    }

    public function update(InstallmentRequest $request, Installment $installment, InstallmentService $installments): RedirectResponse
    {
        $installments->update($installment, $request->validated());

        return $this->redirectToPlanning();
    }

    public function archive(Installment $installment, InstallmentService $installments): RedirectResponse
    {
        $installments->archive($installment);

        return $this->redirectToPlanning();
    }

    public function restore(Installment $installment, InstallmentService $installments): RedirectResponse
    {
        $installments->restore($installment);

        return $this->redirectToPlanning();
    }
}
