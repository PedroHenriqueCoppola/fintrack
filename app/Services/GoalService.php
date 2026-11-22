<?php

namespace App\Services;

use App\Http\Resources\GoalResource;
use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Transaction;
use App\Support\Money;
use App\Support\Month;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GoalService
{
    public function __construct(
        private ReserveBalanceService $reserves,
        private PeriodRange $periods,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Goal
    {
        $goal = new Goal;
        $this->fill($goal, $data);
        $this->save($goal);

        return $goal;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Goal $goal, array $data): Goal
    {
        $this->fill($goal, $data);
        $this->save($goal);

        return $goal;
    }

    public function archive(Goal $goal): void
    {
        if ($goal->archived_at !== null) {
            return;
        }

        $goal->archived_at = now();
        $goal->save();
    }

    public function restore(Goal $goal): void
    {
        if ($goal->archived_at === null) {
            return;
        }

        $taken = Goal::query()
            ->where('category_id', $goal->category_id)
            ->whereNull('archived_at')
            ->whereKeyNot($goal->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'category_id' => 'Já existe uma meta para essa categoria. Arquive a outra para restaurar esta.',
            ]);
        }

        $goal->archived_at = null;
        $this->save($goal, 'Já existe uma meta para essa categoria. Arquive a outra para restaurar esta.');
    }

    /**
     * @param  array{period: string, from: ?string, to: ?string, status: string}  $filters
     * @return array<string, mixed>
     */
    public function index(array $filters): array
    {
        $range = $this->periods->resolve($filters['period'], $filters['from'], $filters['to']);
        $goals = Goal::query()
            ->with('category:id,name,icon,color,nature,applies_to,archived_at')
            ->get();
        $built = $this->build($goals, $range, $filters, false, null);
        $status = $filters['status'];
        $segment = array_values(array_filter(
            $built['goals'],
            fn (array $goal): bool => $goal['state'] === $status,
        ));
        $this->sortSegment($segment, $status);

        return [
            'filters' => $this->filterPayload($filters, $range),
            'period' => $this->periodPayload($filters, $range),
            'status' => $status,
            'panel' => $built['panel'],
            'chart' => $built['chart'],
            'goals' => $segment,
            'counts' => $built['counts'],
            'has_goals' => $goals->isNotEmpty(),
            'reserve_categories' => $built['reserve_categories'],
            'has_active_reserve' => $built['has_active_reserve'],
        ];
    }

    /**
     * @param  array{period: string, from: ?string, to: ?string, status: string}  $filters
     * @return array<string, mixed>
     */
    public function show(Goal $goal, array $filters): array
    {
        $goal->load('category:id,name,icon,color,nature,applies_to,archived_at');
        $range = $this->periods->resolve($filters['period'], $filters['from'], $filters['to']);
        $built = $this->build(collect([$goal]), $range, $filters, true, $goal);

        return [
            'filters' => $this->filterPayload($filters, $range),
            'period' => $this->periodPayload($filters, $range),
            'goal' => $built['goals'][0],
            'chart' => $built['chart'],
            'movements' => $this->movements($goal, $range),
            'analysis_href' => $this->analysisHref($goal, $filters),
            'list_href' => $this->listHref($filters),
            'reserve_categories' => $built['reserve_categories'],
            'has_active_reserve' => $built['has_active_reserve'],
        ];
    }

    /**
     * Up to four active goals. One goals query. The balance map is the catalog's
     * when the dashboard already loaded it, so the count does not grow with the goals.
     *
     * @return array<string, mixed>
     */
    public function forDashboard(): array
    {
        $goals = Goal::query()
            ->whereNull('archived_at')
            ->get(['id', 'name', 'target_amount', 'deadline', 'category_id', 'archived_at']);

        if ($goals->isEmpty()) {
            return $this->dashboardEmpty('Você ainda não tem metas.', 'Criar meta');
        }

        $balances = $this->reserves->balances();
        $rows = [];

        foreach ($goals as $goal) {
            $target = Money::of($goal->target_amount);
            $progress = $balances[(int) $goal->category_id] ?? '0.00';
            $rows[] = [
                'id' => $goal->id,
                'name' => $goal->name,
                'progress' => $progress,
                'target_amount' => $target,
                'percent' => Money::wholePercent($progress, $target) ?? 0,
                'state' => Money::compare($progress, $target) >= 0 ? 'completed' : 'active',
                'deadline' => $goal->deadline?->toDateString(),
                'href' => '/goals/'.$goal->id,
            ];
        }

        $active = array_values(array_filter($rows, fn (array $row): bool => $row['state'] === 'active'));
        $completed = array_values(array_filter($rows, fn (array $row): bool => $row['state'] === 'completed'));

        if ($active === []) {
            if ($completed !== []) {
                return $this->dashboardEmpty('Todas as suas metas foram concluídas.', 'Ver metas');
            }

            return $this->dashboardEmpty('Você ainda não tem metas.', 'Criar meta');
        }

        usort($active, function (array $left, array $right): int {
            $leftDeadline = $left['deadline'] ?? '9999-12-31';
            $rightDeadline = $right['deadline'] ?? '9999-12-31';

            if ($leftDeadline !== $rightDeadline) {
                return $leftDeadline <=> $rightDeadline;
            }

            if ($left['percent'] !== $right['percent']) {
                return $right['percent'] <=> $left['percent'];
            }

            return strcasecmp($left['name'], $right['name']);
        });

        return [
            'items' => array_map(fn (array $row): array => [
                'id' => $row['id'],
                'name' => $row['name'],
                'progress' => $row['progress'],
                'target_amount' => $row['target_amount'],
                'percent' => $row['percent'],
                'href' => $row['href'],
            ], array_slice($active, 0, 4)),
            'message' => null,
            'shortcut_label' => 'Ver metas',
            'shortcut_href' => '/goals',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(Goal $goal, array $data): void
    {
        $goal->name = $data['name'];
        $goal->target_amount = Money::of($data['target_amount']);
        $goal->category_id = $data['category_id'];
        $goal->notes = $data['notes'] ?? null;
        $goal->deadline = empty($data['deadline']) ? null : Month::start($data['deadline']);
    }

    private function save(
        Goal $goal,
        string $conflict = 'Já existe uma meta para essa categoria. Arquive a outra para criar esta.',
    ): void {
        try {
            $goal->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'category_id' => $conflict,
            ]);
        }
    }

    /**
     * @param  Collection<int, Goal>  $goals
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @param  array{period: string, from: ?string, to: ?string, status: string}  $filters
     * @return array{goals: array<int, array<string, mixed>>, panel: array<string, mixed>, chart: array<string, mixed>, counts: array<string, int>, reserve_categories: array<int, array<string, mixed>>, has_active_reserve: bool}
     */
    private function build(Collection $goals, array $range, array $filters, bool $chartIncludesArchived, ?Goal $formGoal): array
    {
        $balances = $goals->isEmpty() ? [] : $this->reserves->balances();
        $categoryIds = $goals
            ->map(fn (Goal $goal): int => (int) $goal->category_id)
            ->unique()
            ->values()
            ->all();
        $chartIds = $goals
            ->filter(fn (Goal $goal): bool => $chartIncludesArchived || $goal->archived_at === null)
            ->map(fn (Goal $goal): int => (int) $goal->category_id)
            ->unique()
            ->values()
            ->all();
        $saved = $this->periodSaved($categoryIds, $range['from'], $range['to']);
        $months = $this->periods->chartMonths($range);
        $series = $this->series($chartIds, $months);
        $presented = [];
        $counts = ['active' => 0, 'completed' => 0, 'archived' => 0];
        $panelSaved = '0.00';
        $panelPeriod = '0.00';

        foreach ($goals as $goal) {
            $balance = $balances[(int) $goal->category_id] ?? '0.00';
            $periodSaved = $saved[(int) $goal->category_id] ?? '0.00';
            $row = $this->present($goal, $balance, $periodSaved, $this->goalHref($goal, $filters));
            $presented[] = $row;
            $counts[$row['state']]++;

            if ($row['state'] !== 'archived') {
                $panelSaved = Money::add($panelSaved, $row['progress']);
                $panelPeriod = Money::add($panelPeriod, $row['period_saved']);
            }
        }

        $categories = $this->reserveCategories($formGoal);

        return [
            'goals' => $presented,
            'panel' => [
                'saved' => $panelSaved,
                'active_count' => $counts['active'],
                'completed_count' => $counts['completed'],
                'period_saved' => $panelPeriod,
            ],
            'chart' => $this->chart($goals, $chartIds, $series, $months, $chartIncludesArchived),
            'counts' => $counts,
            'reserve_categories' => $categories['items'],
            'has_active_reserve' => $categories['has_active'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Goal $goal, string $balance, string $periodSaved, string $href): array
    {
        $target = Money::of($goal->target_amount);
        $progress = $balance;
        $state = $this->state($goal, $progress, $target);
        $remaining = Money::compare($progress, $target) === 1 ? '0.00' : Money::sub($target, $progress);
        $percent = Money::wholePercent($progress, $target) ?? 0;
        $suggestion = $this->suggestion($goal, $remaining, $state);

        $view = clone $goal;
        $view->progress = $progress;
        $view->percent = $percent;
        $view->remaining = $remaining;
        $view->state = $state;
        $view->monthly_amount = $suggestion['monthly'];
        $view->overdue = $suggestion['overdue'];
        $view->period_saved = $periodSaved;
        $view->href = $href;

        return (new GoalResource($view))->resolve();
    }

    private function state(Goal $goal, string $progress, string $target): string
    {
        if ($goal->archived_at !== null) {
            return 'archived';
        }

        if (Money::compare($progress, $target) >= 0) {
            return 'completed';
        }

        return 'active';
    }

    /**
     * @return array{monthly: ?string, overdue: bool}
     */
    private function suggestion(Goal $goal, string $remaining, string $state): array
    {
        if ($goal->deadline === null || $state !== 'active') {
            return ['monthly' => null, 'overdue' => false];
        }

        $deadline = $goal->deadline->format('Y-m');
        $current = Month::currentKey();

        if ($deadline < $current) {
            return ['monthly' => null, 'overdue' => true];
        }

        $months = $this->periods->inclusiveMonths($current, $deadline);

        return [
            'monthly' => Money::divCeil($remaining, $months),
            'overdue' => false,
        ];
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @return array<int, string>
     */
    private function periodSaved(array $categoryIds, ?string $from, ?string $to): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $query = Transaction::query()
            ->where('type', 'reserve')
            ->where('status', 'paid')
            ->whereIn('category_id', $categoryIds);

        if ($from !== null) {
            $query->where('date', '>=', $from);
        }

        if ($to !== null) {
            $query->where('date', '<=', $to);
        }

        $rows = $query
            ->groupBy('category_id')
            ->selectRaw("
                category_id,
                coalesce(sum(amount) filter (where reserve_action = 'deposit'), 0)
                - coalesce(sum(amount) filter (where reserve_action = 'withdraw'), 0) as saved
            ")
            ->get();

        $saved = [];

        foreach ($rows as $row) {
            $saved[(int) $row->category_id] = Money::of($row->saved);
        }

        return $saved;
    }

    /**
     * Cumulative paid balance at the end of each chart month.
     * Movement before the window is the opening balance. One grouped query.
     *
     * @param  array<int, int>  $categoryIds
     * @param  array<int, string>  $months
     * @return array<int, array<string, string>>
     */
    private function series(array $categoryIds, array $months): array
    {
        $series = [];

        foreach ($categoryIds as $categoryId) {
            $series[$categoryId] = [];
        }

        if ($categoryIds === [] || $months === []) {
            return $series;
        }

        $start = $months[0];
        $end = Month::end($months[array_key_last($months)]);
        $nets = [];
        $rows = Transaction::query()
            ->where('type', 'reserve')
            ->where('status', 'paid')
            ->whereIn('category_id', $categoryIds)
            ->where('date', '<=', $end)
            ->groupBy('category_id')
            ->groupByRaw("to_char(date, 'YYYY-MM')")
            ->selectRaw("
                category_id,
                to_char(date, 'YYYY-MM') as month_key,
                coalesce(sum(amount) filter (where reserve_action = 'deposit'), 0)
                - coalesce(sum(amount) filter (where reserve_action = 'withdraw'), 0) as net
            ")
            ->get();

        foreach ($rows as $row) {
            $nets[(int) $row->category_id][$row->month_key] = Money::of($row->net);
        }

        foreach ($categoryIds as $categoryId) {
            $running = '0.00';
            $byMonth = $nets[$categoryId] ?? [];

            foreach ($byMonth as $month => $net) {
                if ($month < $start) {
                    $running = Money::add($running, $net);
                }
            }

            foreach ($months as $month) {
                if (isset($byMonth[$month])) {
                    $running = Money::add($running, $byMonth[$month]);
                }

                $series[$categoryId][$month] = $running;
            }
        }

        return $series;
    }

    /**
     * @param  Collection<int, Goal>  $goals
     * @param  array<int, int>  $categoryIds
     * @param  array<int, array<string, string>>  $series
     * @param  array<int, string>  $months
     * @return array<string, mixed>
     */
    private function chart(Collection $goals, array $categoryIds, array $series, array $months, bool $single): array
    {
        if ($categoryIds === [] || $months === []) {
            return [
                'aria_label' => 'Sem metas para o gráfico.',
                'points' => [],
                'reference_cents' => null,
                'reference_label' => null,
                'empty_message' => 'Nenhuma meta fora do arquivo para o gráfico.',
            ];
        }

        $withYear = substr($months[0], 0, 4) !== substr($months[array_key_last($months)], 0, 4);
        $points = [];

        foreach ($months as $month) {
            $total = '0.00';

            foreach ($categoryIds as $categoryId) {
                $total = Money::add($total, $series[$categoryId][$month] ?? '0.00');
            }

            $short = Month::short($month);
            $points[] = [
                'label' => $withYear ? $short.'/'.substr($month, 2, 2) : $short,
                'caption' => Month::phrase($month),
                'value' => $total,
                'cents' => (int) Money::cents($total),
            ];
        }

        $first = $points[0]['caption'];
        $last = $points[array_key_last($points)]['caption'];
        $span = $first === $last ? $first : $first.' a '.$last;
        $referenceCents = null;
        $referenceLabel = null;
        $label = 'Total guardado de '.$span.'.';

        if ($single && $goals->count() === 1) {
            $goal = $goals->first();
            $referenceCents = (int) Money::cents($goal->target_amount);
            $referenceLabel = 'Valor alvo: '.Money::label($goal->target_amount);
            $label = 'Evolução de '.$goal->name.' de '.$span.'. '.$referenceLabel.'.';
        }

        return [
            'aria_label' => $label,
            'points' => $points,
            'reference_cents' => $referenceCents,
            'reference_label' => $referenceLabel,
            'empty_message' => null,
        ];
    }

    /**
     * @param  array{from: ?string, to: ?string}  $range
     * @return array<int, mixed>
     */
    private function movements(Goal $goal, array $range): array
    {
        $query = Transaction::query()
            ->where('type', 'reserve')
            ->where('category_id', $goal->category_id)
            ->with([
                'category:id,name,icon,color,applies_to,nature,archived_at',
                'bank:id,name,archived_at',
                'paymentMethod:id,name,requires_card,archived_at',
                'card:id,name,closing_day,bank_id,archived_at',
                'fixedExpense:id,name',
                'installment:id,name,installments_count',
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(10);

        if ($range['from'] !== null) {
            $query->where('date', '>=', $range['from']);
        }

        if ($range['to'] !== null) {
            $query->where('date', '<=', $range['to']);
        }

        return TransactionResource::collection($query->get())->resolve();
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, has_active: bool}
     */
    private function reserveCategories(?Goal $current): array
    {
        $categories = Category::query()
            ->where('applies_to', 'expense')
            ->where('nature', 'reserve')
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'name', 'icon', 'color', 'archived_at']);
        $hasActive = $categories->isNotEmpty();

        if ($current instanceof Goal && $current->category !== null && $categories->doesntContain('id', $current->category_id)) {
            $categories->push($current->category);
        }

        return [
            'has_active' => $hasActive,
            'items' => $categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
                'archived' => $category->archived_at !== null,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $goals
     */
    private function sortSegment(array &$goals, string $status): void
    {
        usort($goals, function (array $left, array $right) use ($status): int {
            if ($status === 'active') {
                $leftDeadline = $left['deadline'] ?? null;
                $rightDeadline = $right['deadline'] ?? null;

                if ($leftDeadline === null && $rightDeadline !== null) {
                    return 1;
                }

                if ($rightDeadline === null && $leftDeadline !== null) {
                    return -1;
                }

                if ($leftDeadline !== $rightDeadline) {
                    return $leftDeadline <=> $rightDeadline;
                }
            }

            return strcasecmp($left['name'], $right['name']);
        });
    }

    /**
     * @param  array{period: string, from: ?string, to: ?string, status: string}  $filters
     */
    private function goalHref(Goal $goal, array $filters): string
    {
        return '/goals/'.$goal->id.$this->queryString($filters, false);
    }

    /**
     * @param  array{period: string, from: ?string, to: ?string, status: string}  $filters
     */
    private function listHref(array $filters): string
    {
        return '/goals'.$this->queryString($filters, true);
    }

    /**
     * @param  array{period: string, from: ?string, to: ?string, status: string}  $filters
     */
    private function analysisHref(Goal $goal, array $filters): string
    {
        $params = [
            'type' => 'reserve',
            'category_id' => $goal->category_id,
        ];

        if ($filters['period'] !== 'current_month') {
            $params['period'] = $filters['period'];
        }

        if ($filters['period'] === 'custom') {
            $params['from'] = $filters['from'];
            $params['to'] = $filters['to'];
        }

        return '/analysis?'.http_build_query($params);
    }

    /**
     * @param  array{period: string, from: ?string, to: ?string, status: string}  $filters
     */
    private function queryString(array $filters, bool $withStatus): string
    {
        $params = [];

        if ($filters['period'] !== 'current_month') {
            $params['period'] = $filters['period'];
        }

        if ($filters['period'] === 'custom') {
            $params['from'] = $filters['from'];
            $params['to'] = $filters['to'];
        }

        if ($withStatus && $filters['status'] !== 'active') {
            $params['status'] = $filters['status'];
        }

        if ($params === []) {
            return '';
        }

        return '?'.http_build_query($params);
    }

    /**
     * @param  array{period: string, from: ?string, to: ?string, status: string}  $filters
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array<string, mixed>
     */
    private function filterPayload(array $filters, array $range): array
    {
        return [
            'period' => $filters['period'],
            'from' => $filters['period'] === 'custom' ? $filters['from'] : $range['from'],
            'to' => $filters['period'] === 'custom' ? $filters['to'] : $range['to'],
            'status' => $filters['status'],
        ];
    }

    /**
     * @param  array{period: string, from: ?string, to: ?string, status: string}  $filters
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array<string, mixed>
     */
    private function periodPayload(array $filters, array $range): array
    {
        return [
            'key' => $filters['period'],
            'from' => $range['from'],
            'to' => $range['to'],
            'label' => $range['label'],
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, message: string, shortcut_label: string, shortcut_href: string}
     */
    private function dashboardEmpty(string $message, string $shortcut): array
    {
        return [
            'items' => [],
            'message' => $message,
            'shortcut_label' => $shortcut,
            'shortcut_href' => '/goals',
        ];
    }
}
