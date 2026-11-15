<?php

namespace App\Http\Controllers;

use App\Http\Requests\CardRequest;
use App\Models\Card;
use App\Services\CardService;
use Illuminate\Http\RedirectResponse;

class CardController extends Controller
{
    public function store(CardRequest $request, CardService $cards): RedirectResponse
    {
        $cards->create($request->validated());

        return back();
    }

    public function update(CardRequest $request, Card $card, CardService $cards): RedirectResponse
    {
        $cards->update($card, $request->validated());

        return back();
    }

    public function archive(Card $card, CardService $cards): RedirectResponse
    {
        $cards->archive($card);

        return back();
    }

    public function restore(Card $card, CardService $cards): RedirectResponse
    {
        $cards->restore($card);

        return back();
    }
}
