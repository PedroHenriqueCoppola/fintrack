<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoicePreviewRequest;
use App\Http\Requests\PayInvoiceRequest;
use App\Http\Requests\UnpayInvoiceRequest;
use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Services\CardInvoiceService;
use App\Services\TransactionOptionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(
        Request $request,
        CardInvoiceService $invoices,
        TransactionOptionsService $options,
    ): Response {
        $cards = $invoices->activeCards();

        if ($cards->isEmpty()) {
            if ($request->filled('card')) {
                abort(404);
            }

            return Inertia::render('Invoices/Index', [
                'cards' => [],
                'selectedId' => null,
                'invoice' => null,
                'history' => [],
                'options' => $options->catalog(),
            ]);
        }

        $card = $invoices->selectedCard($cards, $request->query('card'));
        $page = $invoices->present($card, $request->query('invoice'));

        return Inertia::render('Invoices/Index', [
            'cards' => CardResource::collection($cards)->resolve(),
            'selectedId' => $card->id,
            'invoice' => $page['invoice'],
            'history' => $page['history'],
            'options' => $options->catalog(),
        ]);
    }

    public function pay(PayInvoiceRequest $request, CardInvoiceService $invoices): RedirectResponse
    {
        $data = $request->validated();
        $card = $invoices->ownedActiveCard((int) $data['card_id']);
        $invoices->pay($card, $data['closing_month'], $data['paid_on']);

        return redirect()->route('invoices', [
            'card' => $card->id,
            'invoice' => substr($data['closing_month'], 0, 7),
        ]);
    }

    public function unpay(UnpayInvoiceRequest $request, CardInvoiceService $invoices): RedirectResponse
    {
        $data = $request->validated();
        $card = $invoices->ownedActiveCard((int) $data['card_id']);
        $invoices->unpay($card, $data['closing_month']);

        return redirect()->route('invoices', [
            'card' => $card->id,
            'invoice' => substr($data['closing_month'], 0, 7),
        ]);
    }

    public function preview(InvoicePreviewRequest $request, Card $card, CardInvoiceService $invoices): JsonResponse
    {
        if ($card->archived_at !== null) {
            abort(404);
        }

        return response()->json($invoices->preview($card, $request->validated('date')));
    }
}
