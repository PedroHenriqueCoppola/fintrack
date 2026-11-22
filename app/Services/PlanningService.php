<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\Card;
use App\Models\Category;
use App\Models\FixedExpense;
use App\Models\Installment;
use App\Models\MonthlyPlan;
use App\Models\MonthlyPlanItem;
use App\Models\PaymentMethod;
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
        [$plan, $previous] = $this->plansForPage($start);
        $amounts = $this->amountMap($plan);
        $income = Money::of($plan?->expected_income ?? '0');
        $movement = $this->monthMovement($start, $end);
        $categories = Category::query()
            ->where('applies_to', 'expense')
            ->orderByRaw("case when nature = 'reserve' then 1 else 0 end")
            ->orderByRaw('unaccent(name)')
            ->get();

        $catalog = $this->loadCatalog();
        $committedByCategory = $this->committedByCategory($catalog['expenses'], $catalog['installments'], $start, $monthKey);
        $templateCommitted = '0.00';

        foreach ($committedByCategory as $amount) {
            $templateCommitted = Money::add($templateCommitted, $amount);
        }

        $committed = $templateCommitted;

        foreach ($movement['pending'] as $categoryId => $amount) {
            $committedByCategory[$categoryId] = Money::add($committedByCategory[$categoryId] ?? '0.00', $amount);
            $committed = Money::add($committed, $amount);
        }

        $spendingLines = [];
        $reserveLines = [];

        foreach ($categories as $category) {
            $known = array_key_exists($category->id, $amounts);
            $planned = $amounts[$category->id] ?? '0.00';
            $categorySpent = $movement['spent'][$category->id] ?? '0.00';
            $savedAmount = $movement['saved'][$category->id] ?? '0.00';
            $hasSpent = Money::compare($categorySpent, '0') === 1;
            $hasSaved = $category->nature === 'reserve' && Money::compare($savedAmount, '0') !== 0;

            if ($category->archived_at !== null && ! $known && ! $hasSpent && ! $hasSaved) {
                continue;
            }

            $line = $this->line(
                $category,
                $planned,
                $categorySpent,
                $committedByCategory[$category->id] ?? '0.00',
            );

            if ($category->nature === 'reserve') {
                $line['saved'] = $savedAmount;
                $reserveLines[] = $line;
            } else {
                $spendingLines[] = $line;
            }
        }
        $summary = $this->summary($income, $spendingLines, $reserveLines, $committed);
        $fixed = $this->fixedPayload($user, $monthKey, $start, $summary['spending_planned'], $filters, $catalog, $templateCommitted);

        return [
            'month' => $monthKey,
            'tab' => $tab,
            'navigation' => [
                'label' => Month::label($monthKey),
                'previous' => Month::shift($monthKey, -1),
                'next' => Month::shift($monthKey, 1),
                'current' => Month::currentKey(),
                'is_current' => $monthKey === Month::currentKey(),
                'is_future' => $monthKey > Month::currentKey(),
                'is_past' => $monthKey < Month::currentKey(),
            ],
            'plan' => [
                'exists' => $plan !== null,
                'status' => $plan?->status ?? 'draft',
                'previous_plan_label' => $previous instanceof MonthlyPlan
                    ? Month::phrase($previous->month->format('Y-m'))
                    : null,
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

    /**
     * Spending categories for the Dashboard: the same line the planning page
     * builds (competence date, percent, remaining, near the limit, over).
     * Only a saved plan counts. At most six lines, highest percent first,
     * plus the month total of every spending category that has a value or a spend.
     *
     * @return array<string, mixed>
     */
    public function usage(string $monthKey): array
    {
        $start = Month::start($monthKey);
        $plan = $this->planFor($start);
        $payload = [
            'month' => $monthKey,
            'phrase' => Month::phrase($monthKey),
            'exists' => $plan !== null,
            'lines' => [],
            'total' => null,
        ];

        if ($plan === null) {
            return $payload;
        }

        $amounts = $this->amountMap($plan);
        $spent = $this->spending->totals($start, Month::end($monthKey));
        $categories = Category::query()
            ->where('applies_to', 'expense')
            ->where('nature', 'spending')
            ->orderByRaw('unaccent(name)')
            ->get();

        $lines = [];

        foreach ($categories as $index => $category) {
            $known = array_key_exists($category->id, $amounts);
            $planned = $amounts[$category->id] ?? '0.00';
            $categorySpent = $spent[$category->id] ?? '0.00';
            $hasSpent = Money::compare($categorySpent, '0') === 1;
            $hasPlanned = Money::compare($planned, '0') === 1;

            if ($category->archived_at !== null && ! $known && ! $hasSpent) {
                continue;
            }

            if (! $hasPlanned && ! $hasSpent) {
                continue;
            }

            $line = $this->line($category, $planned, $categorySpent, '0.00');
            $line['sort'] = $index;
            $lines[] = $line;
        }

        $totalPlanned = '0.00';
        $totalSpent = '0.00';

        foreach ($lines as $line) {
            $totalPlanned = Money::add($totalPlanned, $line['planned']);
            $totalSpent = Money::add($totalSpent, $line['spent'] ?? '0');
        }

        usort($lines, function (array $left, array $right): int {
            $leftRank = $left['used_percent'] ?? ($left['over'] ? 100000 : -1);
            $rightRank = $right['used_percent'] ?? ($right['over'] ? 100000 : -1);

            if ($leftRank !== $rightRank) {
                return $rightRank <=> $leftRank;
            }

            return $left['sort'] <=> $right['sort'];
        });

        $visible = array_map(function (array $line): array {
            unset($line['sort'], $line['committed'], $line['committed_over']);

            return $line;
        }, array_slice($lines, 0, 6));

        $percent = Money::usedPercent($totalSpent, $totalPlanned);

        $payload['lines'] = $visible;
        $payload['total'] = [
            'planned' => $totalPlanned,
            'spent' => $totalSpent,
            'remaining' => Money::sub($totalPlanned, $totalSpent),
            'used_percent' => $percent,
            'over' => Money::compare($totalSpent, $totalPlanned) === 1 && Money::compare($totalPlanned, '0') === 1,
            'near_limit' => $percent !== null && $percent >= 90 && $percent <= 100,
        ];

        return $payload;
    }

    public function updateHighImpact(User $user, int $percent): void
    {
        $user->high_impact_percent = $percent;
        $user->save();
    }

    /**
     * Archived categories this month may keep: already on the saved plan,
     * with a paid expense, or with money saved in a reserve category.
     * The previous plan does not grant them until Copy writes them here.
     *
     * @return array<int, int>
     */
    public function archivedCategoryIds(string $monthStart): array
    {
        $plan = $this->planFor($monthStart);
        $ids = collect($plan?->items ?? [])->pluck('category_id');
        $movement = $this->monthMovement($monthStart, Month::end(Month::key($monthStart)));
        $active = [];

        foreach (['spent', 'saved'] as $bucket) {
            foreach ($movement[$bucket] as $id => $amount) {
                if (Money::compare($amount, '0') === 1) {
                    $active[] = (int) $id;
                }
            }
        }

        return $ids->merge($active)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function planFor(string $monthStart): ?MonthlyPlan
    {
        return MonthlyPlan::query()->with('items')->whereDate('month', $monthStart)->first();
    }

    /**
     * Latest plan before this month. Used only by copy(), which replaces the
     * month on purpose. page() and materialize() do not inherit its values.
     */
    private function previousPlan(string $monthStart): ?MonthlyPlan
    {
        return MonthlyPlan::query()
            ->with('items')
            ->where('month', '<', $monthStart)
            ->orderByDesc('month')
            ->first();
    }

    /**
     * The saved plan for the month, plus the latest earlier plan (month only)
     * so the copy button can name its source. One query for both rows.
     *
     * @return array{0: ?MonthlyPlan, 1: ?MonthlyPlan}
     */
    private function plansForPage(string $monthStart): array
    {
        $rows = MonthlyPlan::query()
            ->where('month', '<=', $monthStart)
            ->orderByDesc('month')
            ->limit(2)
            ->get();

        $current = $rows->first(fn (MonthlyPlan $plan) => $plan->month->toDateString() === $monthStart);

        if ($current instanceof MonthlyPlan) {
            $current->load('items');
        }

        $previous = $rows->first(fn (MonthlyPlan $plan) => $plan->month->toDateString() < $monthStart);

        return [$current, $previous];
    }

    /**
     * Paid expense, paid reserve and hand-entered pending expense of the month,
     * grouped by category. One query.
     *
     * @return array{spent: array<int, string>, saved: array<int, string>, pending: array<int, string>}
     */
    private function monthMovement(string $start, string $end): array
    {
        $rows = Transaction::query()
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($paid) use ($start, $end) {
                    $paid->where('transactions.status', 'paid')
                        ->whereRaw('coalesce(transactions.competence_date, transactions.date) between ? and ?', [$start, $end]);
                })->orWhere(function ($pending) use ($start, $end) {
                    $pending->where('transactions.type', 'expense')
                        ->where('transactions.status', 'pending')
                        ->whereNull('transactions.fixed_expense_id')
                        ->whereNull('transactions.installment_id')
                        ->where('categories.nature', 'spending')
                        ->whereBetween('transactions.date', [$start, $end]);
                });
            })
            ->groupBy('transactions.category_id')
            ->selectRaw("
                transactions.category_id as category_id,
                coalesce(sum(transactions.amount) filter (where transactions.type = 'expense' and transactions.status = 'paid'), 0) as spent,
                coalesce(sum(case
                    when transactions.type = 'reserve' and transactions.status = 'paid' and transactions.reserve_action = 'deposit' then transactions.amount
                    when transactions.type = 'reserve' and transactions.status = 'paid' and transactions.reserve_action = 'withdraw' then -transactions.amount
                    else 0
                end), 0) as saved,
                coalesce(sum(transactions.amount) filter (
                    where transactions.type = 'expense'
                    and transactions.status = 'pending'
                    and transactions.fixed_expense_id is null
                    and transactions.installment_id is null
                    and categories.nature = 'spending'
                ), 0) as manual_pending
            ")
            ->get();

        $spent = [];
        $saved = [];
        $pending = [];

        foreach ($rows as $row) {
            $id = (int) $row->category_id;
            $spent[$id] = Money::of($row->spent);
            $saved[$id] = Money::of($row->saved);
            $manual = Money::of($row->manual_pending);

            if (Money::compare($manual, '0') === 1) {
                $pending[$id] = $manual;
            }
        }

        return ['spent' => $spent, 'saved' => $saved, 'pending' => $pending];
    }

    private function materialize(string $monthStart): MonthlyPlan
    {
        return $this->newPlan($monthStart, '0.00');
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
    private function line(Category $category, string $planned, string $spent, string $committed): array
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
            'committed' => $reserve ? null : $committed,
            'committed_over' => ! $reserve && Money::compare($committed, $planned) === 1,
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
    /**
     * Active fixed expenses and the parcel that falls in the month, keyed by category.
     * Archived templates are left out. The same pass feeds the summary total.
     *
     * @param  Collection<int, FixedExpense>  $expenses
     * @param  Collection<int, Installment>  $installments
     * @return array<int, string>
     */
    private function committedByCategory(Collection $expenses, Collection $installments, string $monthStart, string $monthKey): array
    {
        $amounts = [];

        foreach ($expenses as $expense) {
            if (! $this->generator->fixedApplies($expense, $monthStart)) {
                continue;
            }

            $categoryId = (int) $expense->category_id;
            $amounts[$categoryId] = Money::add($amounts[$categoryId] ?? '0.00', $expense->amount);
        }

        foreach ($installments as $installment) {
            if ($installment->archived_at !== null) {
                continue;
            }

            $number = $this->calculator->numberInMonth($installment, $monthKey);

            if ($number === null) {
                continue;
            }

            $categoryId = (int) $installment->category_id;
            $amounts[$categoryId] = Money::add(
                $amounts[$categoryId] ?? '0.00',
                $this->calculator->amountForNumber(
                    (string) $installment->total_amount,
                    (int) $installment->installments_count,
                    $number,
                ),
            );
        }

        return $amounts;
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
        $early = $this->earlyPayments($monthStart);

        $fixedCount = 0;
        $fixedAmount = '0.00';
        $activeInstallments = 0;
        $paidTotal = 0;
        $parcelTotal = 0;
        $percent = (int) ($user->high_impact_percent ?: 30);

        $activeFixed = [];
        $outsideFixed = [];
        $archivedFixed = [];
        $activeInstallmentsRows = [];
        $outsideInstallments = [];
        $archivedInstallments = [];

        foreach ($expenses as $expense) {
            $row = $this->fixedRow($expense, $spendingPlanned, $percent);

            if ($expense->archived_at !== null) {
                $archivedFixed[] = $row;

                continue;
            }

            if ($this->generator->fixedApplies($expense, $monthStart)) {
                $fixedCount++;
                $fixedAmount = Money::add($fixedAmount, $expense->amount);
                $row['paid_early_label'] = $early['fixed'][$expense->id] ?? null;
                $activeFixed[] = $row;

                continue;
            }

            $row['reason'] = $this->fixedReason($expense, $monthStart);
            $outsideFixed[] = $row;
        }

        foreach ($installments as $installment) {
            $number = $installment->archived_at === null
                ? $this->calculator->numberInMonth($installment, $monthKey)
                : null;
            $paid = (int) ($paidCounts[$installment->id] ?? 0);
            $monthlyNumber = $number ?? 1;
            $monthly = $this->calculator->amountForNumber(
                (string) $installment->total_amount,
                (int) $installment->installments_count,
                $monthlyNumber,
            );
            $row = $this->installmentRow(
                $installment,
                $monthly,
                $paid,
                $number,
                $spendingPlanned,
                $percent,
                isset($locked[$installment->id]),
            );

            if ($installment->archived_at !== null) {
                $archivedInstallments[] = $row;

                continue;
            }

            if ($number !== null) {
                $activeInstallments++;
                $paidTotal += $paid;
                $parcelTotal += (int) $installment->installments_count;
                $row['paid_early_label'] = $early['installments'][$installment->id.'-'.$number] ?? null;
                $activeInstallmentsRows[] = $row;

                continue;
            }

            $row['reason'] = $this->installmentReason($installment, $monthKey);
            $outsideInstallments[] = $row;
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
                'expenses' => $this->filterRows($activeFixed, $filters),
                'outside_expenses' => $this->filterRows($outsideFixed, $filters),
                'archived_expenses' => $this->filterRows($archivedFixed, $filters),
                'installments' => $this->filterRows($activeInstallmentsRows, $filters),
                'outside_installments' => $this->filterRows($outsideInstallments, $filters),
                'archived_installments' => $this->filterRows($archivedInstallments, $filters),
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
            'paid_early_label' => null,
            'reason' => null,
        ];
    }

    /**
     * Paid generated rows whose payment month differs from generated_for.
     * One query for the whole month. An earlier month is early; a later month is late.
     *
     * @return array{fixed: array<int, string>, installments: array<string, string>}
     */
    private function earlyPayments(string $monthStart): array
    {
        $rows = Transaction::query()
            ->where('status', 'paid')
            ->whereDate('generated_for', $monthStart)
            ->whereRaw("to_char(date, 'YYYY-MM') <> to_char(generated_for, 'YYYY-MM')")
            ->get(['fixed_expense_id', 'installment_id', 'installment_number', 'date', 'generated_for']);

        $fixed = [];
        $installments = [];

        foreach ($rows as $row) {
            $paidKey = $row->date->format('Y-m');
            $referenceKey = $row->generated_for->format('Y-m');

            if ($paidKey === $referenceKey) {
                continue;
            }

            $prefix = $paidKey < $referenceKey ? 'Pago antecipado em ' : 'Pago em ';
            $label = $prefix.$row->date->format('d/m');

            if ($row->fixed_expense_id !== null) {
                $fixed[(int) $row->fixed_expense_id] = $label;
            }

            if ($row->installment_id !== null) {
                $installments[(int) $row->installment_id.'-'.(int) $row->installment_number] = $label;
            }
        }

        return [
            'fixed' => $fixed,
            'installments' => $installments,
        ];
    }

    private function fixedReason(FixedExpense $expense, string $monthStart): string
    {
        if ($expense->start_month->toDateString() > $monthStart) {
            return 'Começa em '.Month::phrase($expense->start_month->format('Y-m'));
        }

        return 'Terminou em '.Month::phrase($expense->end_month->format('Y-m'));
    }

    private function installmentReason(Installment $installment, string $monthKey): string
    {
        $first = $installment->first_date->format('Y-m');

        if ($monthKey < $first) {
            return 'Começa em '.Month::phrase($installment->first_date->format('Y-m'));
        }

        return 'Quitado';
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
            'progress_label' => $number === null ? null : 'Parcela '.$number.' de '.$count,
            'reason' => null,
            'category' => $this->categoryPayload($installment->category),
            'bank' => $this->named($installment->bank),
            'payment_method' => $this->methodPayload($installment->paymentMethod),
            'card' => $this->cardPayload($installment->card),
            'payment_label' => $this->paymentLabel($installment->bank?->name, $installment->paymentMethod?->name, $installment->card?->name),
            'paid_early_label' => null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array{category_id: ?int, min_amount: ?string, max_amount: ?string, sort: string}  $filters
     * @return array<int, array<string, mixed>>
     */
    private function filterRows(array $rows, array $filters): array
    {
        $rows = array_values(array_filter($rows, function (array $row) use ($filters) {
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
            'banks' => Bank::query()->whereNull('archived_at')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($bank) => ['id' => $bank->id, 'name' => $bank->name])->all(),
            'cards' => Card::query()->with('bank:id,name')->whereNull('archived_at')->orderBy('name')->get()
                ->map(fn ($card) => [
                    'id' => $card->id,
                    'name' => $card->name,
                    'bank_id' => $card->bank_id,
                    'bank_name' => $card->bank?->name,
                ])->all(),
            'payment_methods' => PaymentMethod::query()->whereNull('archived_at')->orderBy('name')->get()
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
