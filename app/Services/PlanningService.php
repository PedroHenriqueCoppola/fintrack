<?php

namespace App\Services;

use App\Models\Category;
use App\Models\FixedExpense;
use App\Models\Installment;
use App\Models\MonthlyPlan;
use App\Models\MonthlyPlanItem;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use App\Support\Month;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlanningService
{
    private const RING = '276.46';

    public function __construct(
        private SpendingService $spending,
        private PlanGenerator $generator,
        private InstallmentCalculator $calculator,
    ) {}

    /**
     * @param  array{category_id: ?int, min_amount: ?string, max_amount: ?string, sort: string}  $filters
     * @return array<string, mixed>
     */
    public function page(User $user, string $monthKey, string $tab, array $filters): array
    {
        $start = Month::start($monthKey);
        $end = Month::end($monthKey);
        $plan = $this->planFor($start);
        $source = $plan ?? $this->previousPlan($start);
        $amounts = $this->amountMap($source);
        $income = Money::of($source?->expected_income ?? '0');
        $spent = $this->spending->totals($start, $end);
        $categories = Category::query()
            ->where('applies_to', 'expense')
            ->orderByRaw("case when nature = 'reserve' then 1 else 0 end")
            ->orderByRaw('unaccent(name)')
            ->get();

        $spendingLines = [];
        $reserveLines = [];

        foreach ($categories as $category) {
            $known = array_key_exists($category->id, $amounts);
            $planned = $amounts[$category->id] ?? '0.00';
            $categorySpent = $spent[$category->id] ?? '0.00';
            $hasSpent = Money::compare($categorySpent, '0') === 1;

            if ($category->archived_at !== null && ! $known && ! $hasSpent) {
                continue;
            }

            $line = $this->line($category, $planned, $categorySpent);

            if ($category->nature === 'reserve') {
                $reserveLines[] = $line;
            } else {
                $spendingLines[] = $line;
            }
        }

        $catalog = $this->loadCatalog();
        $committed = $this->committedFrom($catalog['expenses'], $catalog['installments'], $start, $monthKey);
        $summary = $this->summary($income, $spendingLines, $reserveLines, $committed);
        $fixed = $this->fixedPayload($user, $monthKey, $start, $summary['spending_planned'], $filters, $catalog, $committed);

        return [
            'month' => $monthKey,
            'tab' => $tab,
            'navigation' => [
                'label' => Month::label($monthKey),
                'previous' => Month::shift($monthKey, -1),
                'next' => Month::shift($monthKey, 1),
            ],
            'plan' => [
                'exists' => $plan !== null,
                'status' => $plan?->status ?? 'draft',
                'expected_income' => $income,
                'has_values' => $this->hasValues($income, $spendingLines, $reserveLines),
                'spending' => $spendingLines,
                'reserves' => $reserveLines,
                'summary' => $summary,
            ],
            'fixed' => $fixed['payload'],
            'options' => $fixed['options'],
            'installment_preview' => session('installment_preview'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(string $monthKey, array $data): void
    {
        $start = Month::start($monthKey);

        DB::transaction(function () use ($start, $data) {
            $plan = $this->planFor($start);

            if (! $plan) {
                $plan = $this->materialize($start);
            }

            $plan->expected_income = Money::of($data['expected_income']);
            $plan->save();

            foreach ($data['items'] as $item) {
                $this->putItem($plan, (int) $item['category_id'], Money::of($item['amount']));
            }
        });
    }

    public function copy(string $monthKey): void
    {
        $start = Month::start($monthKey);

        DB::transaction(function () use ($start) {
            $previous = $this->previousPlan($start);
            $plan = $this->planFor($start) ?? $this->newPlan($start, '0.00');
            $plan->expected_income = Money::of($previous?->expected_income ?? '0');
            $plan->save();
            $plan->items()->delete();

            foreach ($previous?->items ?? [] as $item) {
                $this->putItem($plan, (int) $item->category_id, Money::of($item->amount));
            }
        });
    }

    public function confirm(string $monthKey): void
    {
        $start = Month::start($monthKey);

        DB::transaction(function () use ($start) {
            $plan = $this->planFor($start) ?? $this->materialize($start);
            $plan->status = 'confirmed';
            $plan->save();
            $this->generator->generateMonth($start);
        });
    }

    public function reopen(string $monthKey): void
    {
        $plan = $this->planFor(Month::start($monthKey));

        if (! $plan) {
            return;
        }

        $plan->status = 'draft';
        $plan->save();
    }

    public function updateHighImpact(User $user, int $percent): void
    {
        $user->high_impact_percent = $percent;
        $user->save();
    }

    /**
     * Archived categories the screen is allowed to keep: already on this plan,
     * on the plan being copied, or with a paid expense in the month.
     *
     * @return array<int, int>
     */
    public function archivedCategoryIds(string $monthStart): array
    {
        $plan = $this->planFor($monthStart);
        $source = $plan ?? $this->previousPlan($monthStart);
        $ids = collect($source?->items ?? [])->pluck('category_id');
        $spent = array_keys($this->spending->totals($monthStart, Month::end(Month::key($monthStart))));

        return $ids->merge($spent)->map(fn ($id) => (int) $id)->unique()->all();
    }

    private function planFor(string $monthStart): ?MonthlyPlan
    {
        return MonthlyPlan::query()->with('items')->whereDate('month', $monthStart)->first();
    }

    private function previousPlan(string $monthStart): ?MonthlyPlan
    {
        return MonthlyPlan::query()
            ->with('items')
            ->where('month', '<', $monthStart)
            ->orderByDesc('month')
            ->first();
    }

    private function materialize(string $monthStart): MonthlyPlan
    {
        $previous = $this->previousPlan($monthStart);
        $plan = $this->newPlan($monthStart, Money::of($previous?->expected_income ?? '0'));

        foreach ($previous?->items ?? [] as $item) {
            $this->putItem($plan, (int) $item->category_id, Money::of($item->amount));
        }

        return $plan;
    }

    private function newPlan(string $monthStart, string $income): MonthlyPlan
    {
        $plan = new MonthlyPlan;
        $plan->month = $monthStart;
        $plan->status = 'draft';
        $plan->expected_income = $income;
        $plan->save();

        return $plan;
    }

    private function putItem(MonthlyPlan $plan, int $categoryId, string $amount): void
    {
        $item = $plan->items()->where('category_id', $categoryId)->first();

        if (! $item instanceof MonthlyPlanItem) {
            $item = new MonthlyPlanItem;
            $item->monthly_plan_id = $plan->id;
            $item->category_id = $categoryId;
        }

        $item->amount = $amount;
        $item->save();
    }

    /**
     * @return array<int, string>
     */
    private function amountMap(?MonthlyPlan $plan): array
    {
        $amounts = [];

        foreach ($plan?->items ?? [] as $item) {
            $amounts[(int) $item->category_id] = Money::of($item->amount);
        }

        return $amounts;
    }

    /**
     * @return array<string, mixed>
     */
    private function line(Category $category, string $planned, string $spent): array
    {
        $reserve = $category->nature === 'reserve';
        $percent = $reserve ? null : Money::usedPercent($spent, $planned);
        $over = ! $reserve && Money::compare($spent, $planned) === 1;

        return [
            'category_id' => $category->id,
            'name' => $category->name,
            'archived' => $category->archived_at !== null,
            'icon' => $category->icon,
            'color' => $category->color,
            'nature' => $category->nature,
            'planned' => $planned,
            'spent' => $reserve ? null : $spent,
            'remaining' => $reserve ? null : Money::sub($planned, $spent),
            'used_percent' => $percent,
            'over' => $over,
            'near_limit' => $percent !== null && $percent >= 90 && $percent <= 100,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $spendingLines
     * @param  array<int, array<string, mixed>>  $reserveLines
     * @return array<string, mixed>
     */
    private function summary(string $income, array $spendingLines, array $reserveLines, string $committed): array
    {
        $spendingPlanned = $this->sumPlanned($spendingLines);
        $reservePlanned = $this->sumPlanned($reserveLines);
        $totalPlanned = Money::add($spendingPlanned, $reservePlanned);
        $totalSpent = '0.00';

        foreach ($spendingLines as $line) {
            $totalSpent = Money::add($totalSpent, $line['spent'] ?? '0');
        }

        $toAllocate = Money::sub($income, $totalPlanned);

        return [
            'expected_income' => $income,
            'total_planned' => $totalPlanned,
            'to_allocate' => $toAllocate,
            'spending_planned' => $spendingPlanned,
            'reserve_planned' => $reservePlanned,
            'total_spent' => $totalSpent,
            'committed' => $committed,
            'committed_percent' => Money::compare($spendingPlanned, '0') === 1
                ? Money::usedPercent($committed, $spendingPlanned)
                : null,
            'ring' => $this->ring($spendingPlanned, $reservePlanned),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function sumPlanned(array $lines): string
    {
        $total = '0.00';

        foreach ($lines as $line) {
            $total = Money::add($total, $line['planned']);
        }

        return $total;
    }

    /**
     * @param  array<int, array<string, mixed>>  $spendingLines
     * @param  array<int, array<string, mixed>>  $reserveLines
     */
    private function hasValues(string $income, array $spendingLines, array $reserveLines): bool
    {
        if (Money::compare($income, '0') === 1) {
            return true;
        }

        foreach ([...$spendingLines, ...$reserveLines] as $line) {
            if (Money::compare($line['planned'], '0') === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{circumference: string, spending: string, reserve: string}
     */
    private function ring(string $spending, string $reserve): array
    {
        $total = Money::add($spending, $reserve);

        if (Money::compare($total, '0') !== 1) {
            return ['circumference' => self::RING, 'spending' => '0.00', 'reserve' => '0.00'];
        }

        if (Money::compare($reserve, '0') !== 1) {
            return ['circumference' => self::RING, 'spending' => self::RING, 'reserve' => '0.00'];
        }

        if (Money::compare($spending, '0') !== 1) {
            return ['circumference' => self::RING, 'spending' => '0.00', 'reserve' => self::RING];
        }

        $spendLen = Money::share($spending, $total, self::RING);

        if (Money::compare($spendLen, self::RING) === 1) {
            $spendLen = self::RING;
        }

        return [
            'circumference' => self::RING,
            'spending' => $spendLen,
            'reserve' => Money::sub(self::RING, $spendLen),
        ];
    }

    /**
     * @param  Collection<int, FixedExpense>  $expenses
     * @param  Collection<int, Installment>  $installments
     */
    private function committedFrom(Collection $expenses, Collection $installments, string $monthStart, string $monthKey): string
    {
        $total = '0.00';

        foreach ($expenses as $expense) {
            if ($this->generator->fixedApplies($expense, $monthStart)) {
                $total = Money::add($total, $expense->amount);
            }
        }

        foreach ($installments as $installment) {
            if ($installment->archived_at !== null) {
                continue;
            }

            $number = $this->calculator->numberInMonth($installment, $monthKey);

            if ($number === null) {
                continue;
            }

            $total = Money::add(
                $total,
                $this->calculator->amountForNumber(
                    (string) $installment->total_amount,
                    (int) $installment->installments_count,
                    $number,
                ),
            );
        }

        return $total;
    }

    /**
     * @return array{expenses: Collection<int, FixedExpense>, installments: Collection<int, Installment>, paid: Collection, locked: array<int, bool>}
     */
    private function loadCatalog(): array
    {
        $relations = [
            'category:id,name,icon,color,archived_at,nature',
            'bank:id,name',
            'paymentMethod:id,name,requires_card',
            'card:id,name,bank_id',
        ];

        $paid = Transaction::query()
            ->whereNotNull('installment_id')
            ->where('status', 'paid')
            ->selectRaw('installment_id, count(*)::int as paid_count')
            ->groupBy('installment_id')
            ->pluck('paid_count', 'installment_id');

        $locked = Transaction::query()
            ->whereNotNull('installment_id')
            ->distinct()
            ->pluck('installment_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        return [
            'expenses' => FixedExpense::query()->with($relations)->get(),
            'installments' => Installment::query()->with($relations)->get(),
            'paid' => $paid,
            'locked' => $locked,
        ];
    }

    /**
     * @param  array{category_id: ?int, min_amount: ?string, max_amount: ?string, sort: string}  $filters
     * @param  array{expenses: Collection<int, FixedExpense>, installments: Collection<int, Installment>, paid: Collection, locked: array<int, bool>}  $catalog
     * @return array{payload: array<string, mixed>, options: array<string, mixed>}
     */
    private function fixedPayload(User $user, string $monthKey, string $monthStart, string $spendingPlanned, array $filters, array $catalog, string $committed): array
    {
        $expenses = $catalog['expenses'];
        $installments = $catalog['installments'];
        $paidCounts = $catalog['paid'];
        $locked = $catalog['locked'];

        $settled = Transaction::query()
            ->where('status', 'paid')
            ->whereDate('generated_for', $monthStart)
            ->where(function ($query) {
                $query->whereNotNull('fixed_expense_id')
                    ->orWhereNotNull('installment_id');
            })
            ->selectRaw('coalesce(sum(amount), 0)::text as total')
            ->value('total');

        $fixedCount = 0;
        $fixedAmount = '0.00';
        $activeInstallments = 0;
        $paidTotal = 0;
        $parcelTotal = 0;
        $percent = (int) ($user->high_impact_percent ?: 30);

        $fixedRows = [];
        $installmentRows = [];

        foreach ($expenses as $expense) {
            $applies = $this->generator->fixedApplies($expense, $monthStart);

            if ($applies) {
                $fixedCount++;
                $fixedAmount = Money::add($fixedAmount, $expense->amount);
            }

            $fixedRows[] = $this->fixedRow($expense, $spendingPlanned, $percent);
        }

        foreach ($installments as $installment) {
            $number = $installment->archived_at === null
                ? $this->calculator->numberInMonth($installment, $monthKey)
                : null;
            $paid = (int) ($paidCounts[$installment->id] ?? 0);

            if ($number !== null) {
                $activeInstallments++;
                $paidTotal += $paid;
                $parcelTotal += (int) $installment->installments_count;
            }

            $monthly = $number === null
                ? $this->calculator->amountForNumber((string) $installment->total_amount, (int) $installment->installments_count, 1)
                : $this->calculator->amountForNumber((string) $installment->total_amount, (int) $installment->installments_count, $number);

            $installmentRows[] = $this->installmentRow(
                $installment,
                $monthly,
                $paid,
                $number,
                $spendingPlanned,
                $percent,
                isset($locked[$installment->id]),
            );
        }

        return [
            'payload' => [
                'high_impact_percent' => $percent,
                'indicators' => [
                    'committed' => $committed,
                    'fixed_count' => $fixedCount,
                    'fixed_amount' => $fixedAmount,
                    'active_installments' => $activeInstallments,
                    'installments_paid' => $paidTotal,
                    'installments_total' => $parcelTotal,
                    'settled' => Money::of($settled),
                    'committed_percent' => Money::compare($spendingPlanned, '0') === 1
                        ? Money::usedPercent($committed, $spendingPlanned)
                        : null,
                ],
                'expenses' => $this->filterRows($fixedRows, $filters, false),
                'archived_expenses' => $this->filterRows($fixedRows, $filters, true),
                'installments' => $this->filterRows($installmentRows, $filters, false),
                'archived_installments' => $this->filterRows($installmentRows, $filters, true),
                'filters' => [
                    'category_id' => $filters['category_id'],
                    'min_amount' => $filters['min_amount'],
                    'max_amount' => $filters['max_amount'],
                    'sort' => $filters['sort'],
                ],
                'has_records' => $expenses->isNotEmpty() || $installments->isNotEmpty(),
            ],
            'options' => $this->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fixedRow(FixedExpense $expense, string $spendingPlanned, int $percent): array
    {
        return [
            'id' => $expense->id,
            'name' => $expense->name,
            'amount' => Money::of($expense->amount),
            'monthly_amount' => Money::of($expense->amount),
            'due_day' => (int) $expense->due_day,
            'due_label' => 'Dia '.$expense->due_day,
            'start_month' => $expense->start_month->format('Y-m'),
            'end_month' => $expense->end_month?->format('Y-m'),
            'notes' => $expense->notes,
            'archived' => $expense->archived_at !== null,
            'high_impact' => Money::reachesPercent($expense->amount, $spendingPlanned, $percent),
            'category' => $this->categoryPayload($expense->category),
            'bank' => $this->named($expense->bank),
            'payment_method' => $this->methodPayload($expense->paymentMethod),
            'card' => $this->cardPayload($expense->card),
            'payment_label' => $this->paymentLabel($expense->bank?->name, $expense->paymentMethod?->name, $expense->card?->name),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function installmentRow(
        Installment $installment,
        string $monthly,
        int $paid,
        ?int $number,
        string $spendingPlanned,
        int $percent,
        bool $locked,
    ): array {
        $count = (int) $installment->installments_count;
        $current = $number ?? min($paid + 1, $count);

        if ($paid >= $count) {
            $current = $count;
        }

        return [
            'id' => $installment->id,
            'name' => $installment->name,
            'total_amount' => Money::of($installment->total_amount),
            'installments_count' => $count,
            'monthly_amount' => $monthly,
            'first_date' => $installment->first_date->toDateString(),
            'due_label' => 'Dia '.$installment->first_date->format('d'),
            'due_day' => (int) $installment->first_date->format('d'),
            'notes' => $installment->notes,
            'archived' => $installment->archived_at !== null,
            'locked' => $locked,
            'high_impact' => Money::reachesPercent($monthly, $spendingPlanned, $percent),
            'paid_count' => $paid,
            'progress_percent' => $count > 0 ? intdiv($paid * 100 + intdiv($count, 2), $count) : 0,
            'progress_label' => 'Parcela '.$current.' de '.$count,
            'category' => $this->categoryPayload($installment->category),
            'bank' => $this->named($installment->bank),
            'payment_method' => $this->methodPayload($installment->paymentMethod),
            'card' => $this->cardPayload($installment->card),
            'payment_label' => $this->paymentLabel($installment->bank?->name, $installment->paymentMethod?->name, $installment->card?->name),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array{category_id: ?int, min_amount: ?string, max_amount: ?string, sort: string}  $filters
     * @return array<int, array<string, mixed>>
     */
    private function filterRows(array $rows, array $filters, bool $archived): array
    {
        $rows = array_values(array_filter($rows, function (array $row) use ($filters, $archived) {
            if ($row['archived'] !== $archived) {
                return false;
            }

            if ($filters['category_id'] !== null && (int) ($row['category']['id'] ?? 0) !== $filters['category_id']) {
                return false;
            }

            if ($filters['min_amount'] !== null && Money::compare($row['monthly_amount'], $filters['min_amount']) < 0) {
                return false;
            }

            if ($filters['max_amount'] !== null && Money::compare($row['monthly_amount'], $filters['max_amount']) > 0) {
                return false;
            }

            return true;
        }));

        usort($rows, function (array $left, array $right) use ($filters) {
            return match ($filters['sort']) {
                'amount' => Money::compare($right['monthly_amount'], $left['monthly_amount']) ?: strcasecmp($left['name'], $right['name']),
                'due' => ($left['due_day'] <=> $right['due_day']) ?: strcasecmp($left['name'], $right['name']),
                default => strcasecmp($left['name'], $right['name']),
            };
        });

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        $categories = Category::query()
            ->where('applies_to', 'expense')
            ->where('nature', 'spending')
            ->orderByRaw('unaccent(name)')
            ->get();

        $active = $categories->filter(fn (Category $category) => $category->archived_at === null)->values();

        return [
            'categories' => $active->map(fn (Category $category) => $this->categoryPayload($category))->all(),
            'filter_categories' => $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->archived_at === null ? $category->name : $category->name.' (arquivado)',
            ])->all(),
            'banks' => \App\Models\Bank::query()->whereNull('archived_at')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($bank) => ['id' => $bank->id, 'name' => $bank->name])->all(),
            'cards' => \App\Models\Card::query()->with('bank:id,name')->whereNull('archived_at')->orderBy('name')->get()
                ->map(fn ($card) => [
                    'id' => $card->id,
                    'name' => $card->name,
                    'bank_id' => $card->bank_id,
                    'bank_name' => $card->bank?->name,
                ])->all(),
            'payment_methods' => \App\Models\PaymentMethod::query()->whereNull('archived_at')->orderBy('name')->get()
                ->map(fn ($method) => [
                    'id' => $method->id,
                    'name' => $method->name,
                    'requires_card' => (bool) $method->requires_card,
                ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function categoryPayload(?Category $category): ?array
    {
        if ($category === null) {
            return null;
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'icon' => $category->icon,
            'color' => $category->color,
            'archived' => $category->archived_at !== null,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function named(?object $model): ?array
    {
        if ($model === null) {
            return null;
        }

        return ['id' => $model->id, 'name' => $model->name];
    }

    /**
     * @return array{id: int, name: string, requires_card: bool}|null
     */
    private function methodPayload(?object $method): ?array
    {
        if ($method === null) {
            return null;
        }

        return [
            'id' => $method->id,
            'name' => $method->name,
            'requires_card' => (bool) $method->requires_card,
        ];
    }

    /**
     * @return array{id: int, name: string, bank_id: int}|null
     */
    private function cardPayload(?object $card): ?array
    {
        if ($card === null) {
            return null;
        }

        return [
            'id' => $card->id,
            'name' => $card->name,
            'bank_id' => $card->bank_id,
        ];
    }

    private function paymentLabel(?string $bank, ?string $method, ?string $card): string
    {
        $payment = $card ? trim(($method ?? '').' '.$card) : $method;

        return implode(' · ', array_filter([$bank, $payment]));
    }
}
