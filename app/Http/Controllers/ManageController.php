<?php

namespace App\Http\Controllers;

use App\Http\Resources\BankResource;
use App\Http\Resources\CardResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\PocketResource;
use App\Models\Bank;
use App\Models\Card;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Pocket;
use Inertia\Inertia;
use Inertia\Response;

class ManageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Manage/Index', [
            'banks' => BankResource::collection($this->banks())->resolve(),
            'pockets' => PocketResource::collection($this->pockets())->resolve(),
            'categories' => CategoryResource::collection($this->categories())->resolve(),
            'cards' => CardResource::collection($this->cards())->resolve(),
            'paymentMethods' => PaymentMethodResource::collection($this->paymentMethods())->resolve(),
            'options' => [
                'colors' => $this->options('colors'),
                'icons' => $this->options('icons'),
                'pocketTypes' => $this->options('pocket_types'),
                'categoryTypes' => $this->options('category_types'),
                'natures' => $this->options('natures'),
            ],
        ]);
    }

    private function banks()
    {
        return Bank::query()
            ->withCount([
                'pockets as active_pockets_count' => fn ($query) => $query->whereNull('archived_at'),
                'cards as active_cards_count' => fn ($query) => $query->whereNull('archived_at'),
            ])
            ->orderBy('name')
            ->get();
    }

    private function pockets()
    {
        return Pocket::query()
            ->with('bank:id,user_id,name')
            ->withCount([
                'categories as active_categories_count' => fn ($query) => $query->whereNull('archived_at'),
            ])
            ->orderBy('name')
            ->get();
    }

    private function categories()
    {
        return Category::query()
            ->with('pocket:id,user_id,name')
            ->orderBy('name')
            ->get();
    }

    private function cards()
    {
        return Card::query()
            ->with('bank:id,user_id,name')
            ->orderBy('name')
            ->get();
    }

    private function paymentMethods()
    {
        return PaymentMethod::query()
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function options(string $name): array
    {
        return array_map(
            fn (array $option) => [
                'value' => $option['value'],
                'label' => $option['label'],
            ],
            config('fintrack.'.$name),
        );
    }
}
