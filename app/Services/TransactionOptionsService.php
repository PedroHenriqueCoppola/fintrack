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
use App\Models\Tag;
use App\Models\Transaction;
use App\Support\Money;

class TransactionOptionsService
{
    public function __construct(private ReserveBalanceService $reserves) {}

    /**
     * Active catalog lists for the transaction modal and the analysis filters.
     * reserve_balances is the paid balance of each reserve category, one query.
     *
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        $balances = Transaction::query()
            ->where('type', 'reserve')
            ->where('status', 'paid')
            ->groupBy('category_id')
            ->selectRaw("
                category_id,
                coalesce(sum(amount) filter (where reserve_action = 'deposit'), 0)
                - coalesce(sum(amount) filter (where reserve_action = 'withdraw'), 0) as balance
            ");
        $categories = Category::query()
            ->leftJoinSub($balances, 'reserve_balance_rows', function ($join): void {
                $join->on('reserve_balance_rows.category_id', '=', 'categories.id');
            })
            ->where(function ($query): void {
                $query->whereNull('categories.archived_at')
                    ->orWhere('categories.nature', 'reserve');
            })
            ->orderBy('categories.name')
            ->select('categories.*')
            ->selectRaw('reserve_balance_rows.balance as reserve_balance')
            ->get();
        $this->reserves->remember($categories
            ->filter(fn (Category $category): bool => $category->nature === 'reserve' && $category->reserve_balance !== null)
            ->mapWithKeys(fn (Category $category): array => [
                (int) $category->id => Money::of($category->reserve_balance),
            ])
            ->all());
        $categories = $categories
            ->filter(fn (Category $category): bool => $category->archived_at === null)
            ->values();

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
                Bank::query()->account()->whereNull('archived_at')->orderBy('name')->get()
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
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name'])->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])->values()->all(),
            'reserve_balances' => $this->reserves->balances(),
        ];
    }
}
