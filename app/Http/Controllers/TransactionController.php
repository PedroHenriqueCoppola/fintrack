<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function store(TransactionRequest $request, TransactionService $transactions): RedirectResponse
    {
        $transactions->create($request->validated());

        return $this->redirectToList();
    }

    public function update(TransactionRequest $request, Transaction $transaction, TransactionService $transactions): RedirectResponse
    {
        $transactions->update($transaction, $request->validated());

        return $this->redirectToList();
    }

    public function destroy(Transaction $transaction, TransactionService $transactions): RedirectResponse
    {
        $transactions->delete($transaction);

        return $this->redirectToList();
    }

    public function pay(Transaction $transaction, TransactionService $transactions): RedirectResponse
    {
        $transactions->pay($transaction);

        return $this->redirectToList();
    }

    private function redirectToList(): RedirectResponse
    {
        $previous = url()->previous();
        $target = Request::create($previous);

        if ($target->getSchemeAndHttpHost() !== request()->getSchemeAndHttpHost()) {
            return redirect()->back();
        }

        $query = $target->query();
        unset($query['page']);
        $path = $target->getPathInfo() ?: '/';

        return redirect()->to($query === [] ? $path : $path.'?'.http_build_query($query));
    }
}
