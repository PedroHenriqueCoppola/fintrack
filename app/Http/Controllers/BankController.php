<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankRequest;
use App\Models\Bank;
use App\Services\BankService;
use Illuminate\Http\RedirectResponse;

class BankController extends Controller
{
    public function store(BankRequest $request, BankService $banks): RedirectResponse
    {
        $banks->create($request->validated());

        return back();
    }

    public function update(BankRequest $request, Bank $bank, BankService $banks): RedirectResponse
    {
        $banks->update($bank, $request->validated());

        return back();
    }

    public function archive(Bank $bank, BankService $banks): RedirectResponse
    {
        $banks->archive($bank);

        return back();
    }

    public function restore(Bank $bank, BankService $banks): RedirectResponse
    {
        $banks->restore($bank);

        return back();
    }
}
