<?php

namespace App\Services;

use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\NameNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DefaultDataService
{
    public function __construct(private NameNormalizer $names) {}

    public function createFor(User $user): void
    {
        if ($user->id === null) {
            throw new RuntimeException('The user must be saved before receiving default data.');
        }

        if (Auth::id() !== null && (int) Auth::id() !== (int) $user->id) {
            throw new RuntimeException('Default data can only be created for the authenticated user.');
        }

        DB::transaction(function () use ($user): void {
            $this->createCategories($user);
            $this->createPaymentMethods($user);
        });
    }

    private function createCategories(User $user): void
    {
        $existing = Category::withoutGlobalScope('belongs_to_user')
            ->where('user_id', $user->id)
            ->pluck('name')
            ->map(fn (string $name) => $this->names->key($name))
            ->all();

        $month = Carbon::now('America/Sao_Paulo')->startOfMonth()->toDateString();

        foreach ($this->categories() as $item) {
            if (in_array($this->names->key($item['name']), $existing, true)) {
                continue;
            }

            $category = new Category;
            $category->user_id = $user->id;
            $category->name = $item['name'];
            $category->applies_to = $item['applies_to'];
            $category->nature = $item['nature'];
            $category->icon = $item['icon'];
            $category->color = $this->color($item['color']);
            $category->initial_balance = '0.00';
            $category->initial_month = $item['applies_to'] === 'expense' ? $month : null;
            $category->save();
        }
    }

    private function createPaymentMethods(User $user): void
    {
        $existing = PaymentMethod::withoutGlobalScope('belongs_to_user')
            ->where('user_id', $user->id)
            ->pluck('name')
            ->map(fn (string $name) => $this->names->key($name))
            ->all();

        foreach ($this->paymentMethods() as $item) {
            if (in_array($this->names->key($item['name']), $existing, true)) {
                continue;
            }

            $method = new PaymentMethod;
            $method->user_id = $user->id;
            $method->name = $item['name'];
            $method->requires_card = $item['requires_card'];
            $method->save();
        }
    }

    private function color(string $key): string
    {
        foreach (config('fintrack.colors') as $color) {
            if ($color['key'] === $key) {
                return $color['value'];
            }
        }

        throw new RuntimeException('Missing fintrack color: '.$key);
    }

    /**
     * @return array<int, array{name: string, applies_to: string, nature: ?string, icon: string, color: string}>
     */
    private function categories(): array
    {
        return [
            ['name' => 'Mercado', 'applies_to' => 'expense', 'nature' => 'spending', 'icon' => 'ShoppingCart', 'color' => 'amber'],
            ['name' => 'Moradia', 'applies_to' => 'expense', 'nature' => 'spending', 'icon' => 'House', 'color' => 'indigo'],
            ['name' => 'Transporte', 'applies_to' => 'expense', 'nature' => 'spending', 'icon' => 'Car', 'color' => 'blue'],
            ['name' => 'Saúde', 'applies_to' => 'expense', 'nature' => 'spending', 'icon' => 'HeartPulse', 'color' => 'rose'],
            ['name' => 'Lazer', 'applies_to' => 'expense', 'nature' => 'spending', 'icon' => 'Ticket', 'color' => 'violet'],
            ['name' => 'Outros', 'applies_to' => 'expense', 'nature' => 'spending', 'icon' => 'Ellipsis', 'color' => 'cyan'],
            ['name' => 'Reserva de emergência', 'applies_to' => 'expense', 'nature' => 'reserve', 'icon' => 'Shield', 'color' => 'green'],
            ['name' => 'Investimentos', 'applies_to' => 'expense', 'nature' => 'reserve', 'icon' => 'TrendingUp', 'color' => 'purple'],
            ['name' => 'Salário', 'applies_to' => 'income', 'nature' => null, 'icon' => 'Wallet', 'color' => 'purple'],
            ['name' => 'Outras receitas', 'applies_to' => 'income', 'nature' => null, 'icon' => 'Banknote', 'color' => 'green'],
        ];
    }

    /**
     * @return array<int, array{name: string, requires_card: bool}>
     */
    private function paymentMethods(): array
    {
        return [
            ['name' => 'Pix', 'requires_card' => false],
            ['name' => 'Débito', 'requires_card' => false],
            ['name' => 'Crédito', 'requires_card' => true],
        ];
    }
}
