<?php

namespace App\Services;

use App\Http\Resources\BankResource;
use App\Http\Resources\CardResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\PaymentMethodResource;
use App\Models\Bank;
use App\Models\Card;
use App\Models\Category;
use App\Models\PaymentMethod;

class TransactionOptionsService
{
    /**
     * Active catalog lists for the transaction modal and the analysis filters.
     *
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        $categories = Category::query()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();

        return [
            'categories' => [
                'expense' => CategoryResource::collection(
                    $categories->where('applies_to', 'expense')->where('nature', 'spending')->values()
                )->resolve(),
                'income' => CategoryResource::collection(
                    $categories->where('applies_to', 'income')->values()
                )->resolve(),
                'reserve' => CategoryResource::collection(
                    $categories->where('applies_to', 'expense')->where('nature', 'reserve')->values()
                )->resolve(),
            ],
            'banks' => BankResource::collection(
                Bank::query()->whereNull('archived_at')->orderBy('name')->get()
            )->resolve(),
            'cards' => CardResource::collection(
                Card::query()
                    ->with('bank:id,user_id,name')
                    ->whereNull('archived_at')
                    ->orderBy('name')
                    ->get()
            )->resolve(),
            'paymentMethods' => PaymentMethodResource::collection(
                PaymentMethod::query()->whereNull('archived_at')->orderBy('name')->get()
            )->resolve(),
        ];
    }
}
