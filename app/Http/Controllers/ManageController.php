<?php

namespace App\Http\Controllers;

use App\Http\Resources\BankResource;
use App\Http\Resources\CardResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\PaymentMethodResource;
use App\Models\Bank;
use App\Models\Card;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Services\CatalogUsage;
use Inertia\Inertia;
use Inertia\Response;

class ManageController extends Controller
{
    public function index(CatalogUsage $usage): Response
    {
        return Inertia::render('Manage/Index', [
            'banks' => BankResource::collection($this->annotate($usage, $this->banks(), 'bank'))->resolve(),
            'categories' => CategoryResource::collection($this->annotate($usage, $this->categories(), 'category'))->resolve(),
            'cards' => CardResource::collection($this->annotate($usage, $this->cards(), 'card'))->resolve(),
            'paymentMethods' => PaymentMethodResource::collection($this->annotate($usage, $this->paymentMethods(), 'payment_method'))->resolve(),
            'options' => [
                'colors' => $this->options('colors'),
                'icons' => $this->options('icons'),
                'categoryTypes' => $this->options('category_types'),
                'natures' => $this->options('natures'),
            ],
        ]);
    }

    private function annotate(CatalogUsage $usage, $items, string $kind)
    {
        $summaries = $usage->summaries($kind, $items->pluck('id')->all());

        return $items->each(function ($item) use ($summaries) {
            $row = $summaries[(int) $item->id] ?? ['can_delete' => true, 'usage_summary' => null];
            $item->setAttribute('can_delete', $row['can_delete']);
            $item->setAttribute('usage_summary', $row['usage_summary']);
        });
    }

    private function banks()
    {
        return Bank::query()
            ->withCount([
                'cards as active_cards_count' => fn ($query) => $query->whereNull('archived_at'),
            ])
            ->orderBy('name')
            ->get();
    }

    private function categories()
    {
        return Category::query()
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
