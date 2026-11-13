<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use Illuminate\Http\RedirectResponse;

class PaymentMethodController extends Controller
{
    public function store(PaymentMethodRequest $request, PaymentMethodService $paymentMethods): RedirectResponse
    {
        $paymentMethods->create($request->validated());

        return back();
    }

    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod, PaymentMethodService $paymentMethods): RedirectResponse
    {
        $paymentMethods->update($paymentMethod, $request->validated());

        return back();
    }

    public function archive(PaymentMethod $paymentMethod, PaymentMethodService $paymentMethods): RedirectResponse
    {
        $paymentMethods->archive($paymentMethod);

        return back();
    }

    public function restore(PaymentMethod $paymentMethod, PaymentMethodService $paymentMethods): RedirectResponse
    {
        $paymentMethods->restore($paymentMethod);

        return back();
    }
}
