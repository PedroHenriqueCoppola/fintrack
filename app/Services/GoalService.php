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
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($goal, $data): void {
            $categoryId = (int) $data['category_id'];
            $open = Goal::query()
                ->where('category_id', $categoryId)
                ->whereNull('archived_at')
                ->whereNull('frozen_amount')
                ->lockForUpdate()
                ->first();

            if ($open instanceof Goal) {
                if (Money::compare($this->progress($open), $open->target_amount) < 0) {
                    throw ValidationException::withMessages([
                        'category_id' => 'Já existe uma meta para essa categoria. Arquive a outra para criar esta.',
                    ]);
                }

                $this->freeze($open);
            }

            $hadGoal = Goal::query()->where('category_id', $categoryId)->exists();
            $balance = $this->reserves->balances()[$categoryId] ?? '0.00';
            $this->fill($goal, $data, true);
            $goal->baseline_amount = $hadGoal ? $this->nonNegative($balance) : '0.00';
            $goal->frozen_amount = null;
            $goal->frozen_at = null;
            $this->save($goal);
        });

        return $goal;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Goal $goal, array $data): Goal
    {
        if ($goal->archived_at !== null) {
            throw ValidationException::withMessages([
                'name' => 'Restaure a meta para editá-la.',
            ]);
        }

        if ($goal->frozen_amount !== null) {
            $this->guardFrozenEdit($goal, $data);
            $goal->name = $data['name'];
            $goal->notes = $data['notes'] ?? null;
            $goal->save();

            return $goal;
        }

        $data['category_id'] = $goal->category_id;
        $this->fill($goal, $data, false);
        $this->save($goal);

        return $goal;
    }

    public function archive(Goal $goal): void
    {
        if ($goal->archived_at !== null) {
            return;
        }

        if ($goal->frozen_amount === null) {
            $goal->frozen_amount = $this->progress($goal);
            $goal->frozen_at = now();
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
            ->whereNull('frozen_amount')
            ->whereKeyNot($goal->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'category_id' => 'Já existe uma meta para essa categoria. Arquive a outra para restaurar esta.',
            ]);
        }

        $newer = Goal::query()
            ->where('category_id', $goal->category_id)
            ->whereKeyNot($goal->id)
            ->where(function ($query) use ($goal) {
                $query->where('created_at', '>', $goal->created_at)
                    ->orWhere(function ($query) use ($goal) {
                        $query->where('created_at', $goal->created_at)
                            ->where('id', '>', $goal->id);
                    });
            })
            ->exists();

        if ($newer) {
            throw ValidationException::withMessages([
                'category_id' => 'Já existe uma meta mais nova nessa categoria. Não é possível restaurar esta.',
            ]);
        }

        $goal->archived_at = null;
        $goal->frozen_amount = null;
        $goal->frozen_at = null;
        $this->save($goal, 'Já existe uma meta para essa categoria. Arquive a outra para restaurar esta.');
    }

    /**
     * Progress of one goal. A frozen goal keeps that amount.
     * The others are the paid category balance minus the baseline, never below zero.
     */
    public function progress(Goal $goal, ?string $balance = null): string
    {
        if ($goal->frozen_amount !== null) {
            return $this->nonNegative($goal->frozen_amount);
        }

        if ($balance === null) {
            $balance = $this->reserves->balances()[(int) $goal->category_id] ?? '0.00';
        }

        return $this->nonNegative(Money::sub($balance, $goal->baseline_amount));
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
        $segment = $status === 'all'
            ? $built['goals']
            : array_values(array_filter(
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
            ->get(['id', 'name', 'target_amount', 'baseline_amount', 'frozen_amount', 'deadline', 'category_id', 'archived_at']);

        if ($goals->isEmpty()) {
            return $this->dashboardEmpty('Você ainda não tem metas.', 'Criar meta');
        }

        $balances = $this->reserves->balances();
        $rows = [];

        foreach ($goals as $goal) {
            $target = Money::of($goal->target_amount);
            $progress = $this->progress($goal, $balances[(int) $goal->category_id] ?? '0.00');
            $reached = Money::compare($progress, $target) >= 0;
            $rows[] = [
                'id' => $goal->id,
                'name' => $goal->name,
                'progress' => $progress,
                'target_amount' => $target,
                'percent' => $this->shownPercent($progress, $target, $reached),
                'reached' => $reached,
                'frozen' => $goal->frozen_amount !== null,
                'state' => $reached ? 'completed' : 'active',
                'deadline' => $goal->deadline?->toDateString(),
                'href' => '/goals/'.$goal->id,
            ];
        }

        $active = array_values(array_filter(
            $rows,
            fn (array $row): bool => $row['state'] === 'active' && ! $row['frozen'],
        ));
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
                'reached' => $row['reached'],
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
    private function fill(Goal $goal, array $data, bool $creating): void
    {
        $goal->name = $data['name'];
        $goal->target_amount = Money::of($data['target_amount']);

        if ($creating) {
            $goal->category_id = $data['category_id'];
        }

        $goal->notes = $data['notes'] ?? null;
        $goal->deadline = empty($data['deadline']) ? null : Month::start($data['deadline']);
    }

    private function freeze(Goal $goal): void
    {
        if ($goal->frozen_amount !== null) {
            return;
        }

        $goal->frozen_amount = $this->progress($goal);
        $goal->frozen_at = now();
        $goal->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardFrozenEdit(Goal $goal, array $data): void
    {
        $message = 'Meta concluída: só o nome e a observação podem mudar.';
        $errors = [];

        if (Money::compare(Money::of($data['target_amount']), Money::of($goal->target_amount)) !== 0) {
            $errors['target_amount'] = $message;
        }

        $deadline = empty($data['deadline']) ? null : $data['deadline'];
        $current = $goal->deadline?->format('Y-m');

        if ($deadline !== $current) {
            $errors['deadline'] = $message;
        }

        if ((int) $data['category_id'] !== (int) $goal->category_id) {
            $errors['category_id'] = $message;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function shownPercent(string $progress, string $target, bool $reached): int
    {
        $percent = Money::wholePercent($progress, $target) ?? 0;

        if (! $reached && $percent > 99) {
            return 99;
        }

        return $percent;
    }

    private function nonNegative(mixed $amount): string
    {
        $value = Money::of($amount);

        return Money::compare($value, '0') === -1 ? '0.00' : $value;
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
        $chartIds = $goals
            ->filter(fn (Goal $goal): bool => $chartIncludesArchived || $goal->archived_at === null)
            ->map(fn (Goal $goal): int => (int) $goal->category_id)
            ->unique()
            ->values()
            ->all();
        $saved = $this->periodSaved($goals, $range['from'], $range['to']);
        $open = $range['from'] === null || $range['to'] === null;
        $nets = $open && $chartIds !== []
            ? $this->loadMonthNets($chartIds, Month::end(Month::currentKey()))
            : null;
        $months = $this->periods->chartMonths($range, 60, $nets === null ? null : $this->earliestMonth($nets));
        $series = $nets === null ? $this->series($chartIds, $months) : $this->walkSeries($chartIds, $months, $nets);
        $presented = [];
        $counts = ['active' => 0, 'completed' => 0, 'archived' => 0];
        $panelSaved = '0.00';
        $panelPeriod = '0.00';

        foreach ($goals as $goal) {
            $balance = $balances[(int) $goal->category_id] ?? '0.00';
            $periodSaved = $saved[$goal->id] ?? '0.00';
            $row = $this->present($goal, $balance, $periodSaved, $this->goalHref($goal, $filters));
            $presented[] = $row;
            $counts[$row['state']]++;

            if ($row['state'] !== 'archived') {
                $panelSaved = Money::add($panelSaved, $row['progress']);
                $panelPeriod = Money::add($panelPeriod, $row['period_saved']);
            }
        }

        $categories = $this->reserveCategories($formGoal, $presented);

        return [
            'goals' => $presented,
            'panel' => [
                'saved' => $panelSaved,
                'active_count' => $counts['active'],
                'completed_count' => $counts['completed'],
                'period_saved' => $panelPeriod,
            ],
            'chart' => $this->chart($goals, $series, $months, $chartIncludesArchived),
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
        $progress = $this->progress($goal, $balance);
        $state = $this->state($goal, $progress, $target);
        $reached = Money::compare($progress, $target) >= 0;
        $remaining = $reached ? '0.00' : Money::sub($target, $progress);
        $surplus = $reached ? Money::sub($progress, $target) : '0.00';
        $percent = $this->shownPercent($progress, $target, $reached);
        $suggestion = $this->suggestion($goal, $remaining, $state);

        $view = clone $goal;
        $view->progress = $progress;
        $view->percent = $percent;
        $view->frozen = $goal->frozen_amount !== null;
        $view->remaining = $remaining;
        $view->reached = $reached;
        $view->surplus = $surplus;
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
     * Paid deposit minus withdraw inside each goal's own window.
     * One grouped query, then the windows are applied in memory.
     *
     * @param  Collection<int, Goal>  $goals
     * @return array<int, string>
     */
    private function periodSaved(Collection $goals, ?string $from, ?string $to): array
    {
        $saved = [];

        foreach ($goals as $goal) {
            $saved[$goal->id] = '0.00';
        }

        if ($goals->isEmpty()) {
            return $saved;
        }

        $windows = [];
        $queryFrom = $from;
        $queryTo = $to;
        $openStart = false;
        $openEnd = false;

        foreach ($goals as $goal) {
            [$start, $end] = $this->savedWindow($goal, $from, $to);
            $windows[$goal->id] = [$start, $end];

            if ($start === null) {
                $openStart = true;
            } elseif ($queryFrom === null || $start < $queryFrom) {
                $queryFrom = $start;
            }

            if ($end === null) {
                $openEnd = true;
            } elseif ($queryTo === null || $end > $queryTo) {
                $queryTo = $end;
            }
        }

        if ($openStart) {
            $queryFrom = null;
        }

        if ($openEnd) {
            $queryTo = null;
        }

        $categoryIds = $goals
            ->map(fn (Goal $goal): int => (int) $goal->category_id)
            ->unique()
            ->values()
            ->all();
        $query = Transaction::query()
            ->where('type', 'reserve')
            ->where('status', 'paid')
            ->whereIn('category_id', $categoryIds);

        if ($queryFrom !== null) {
            $query->where('date', '>=', $queryFrom);
        }

        if ($queryTo !== null) {
            $query->where('date', '<=', $queryTo);
        }

        $nets = [];

        foreach ($query->groupBy('category_id')->groupBy('date')->selectRaw("
            category_id,
            date,
            coalesce(sum(amount) filter (where reserve_action = 'deposit'), 0)
            - coalesce(sum(amount) filter (where reserve_action = 'withdraw'), 0) as net
        ")->get() as $row) {
            $nets[(int) $row->category_id][substr((string) $row->date, 0, 10)] = Money::of($row->net);
        }

        foreach ($goals as $goal) {
            [$start, $end] = $windows[$goal->id];

            if ($start !== null && $end !== null && $start > $end) {
                continue;
            }

            $total = '0.00';

            foreach ($nets[(int) $goal->category_id] ?? [] as $date => $net) {
                if (($start === null || $date >= $start) && ($end === null || $date <= $end)) {
                    $total = Money::add($total, $net);
                }
            }

            $saved[$goal->id] = $total;
        }

        return $saved;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function savedWindow(Goal $goal, ?string $from, ?string $to): array
    {
        $start = $from;

        if (Money::compare($goal->baseline_amount, '0') === 1) {
            $created = $goal->created_at?->timezone('America/Sao_Paulo')->toDateString();

            if ($created !== null && ($start === null || $created > $start)) {
                $start = $created;
            }
        }

        $end = $to;

        if ($goal->frozen_at !== null) {
            $frozen = $goal->frozen_at->timezone('America/Sao_Paulo')->toDateString();

            if ($end === null || $frozen < $end) {
                $end = $frozen;
            }
        }

        return [$start, $end];
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
        if ($categoryIds === [] || $months === []) {
            return $this->walkSeries($categoryIds, $months, []);
        }

        $end = Month::end($months[array_key_last($months)]);

        return $this->walkSeries($categoryIds, $months, $this->loadMonthNets($categoryIds, $end));
    }

    /**
     * Paid reserve net by category and month, up to $end. One grouped query.
     *
     * @param  array<int, int>  $categoryIds
     * @return array<int, array<string, string>>
     */
    private function loadMonthNets(array $categoryIds, string $end): array
    {
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

        return $nets;
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @param  array<int, string>  $months
     * @param  array<int, array<string, string>>  $nets
     * @return array<int, array<string, string>>
     */
    private function walkSeries(array $categoryIds, array $months, array $nets): array
    {
        $series = [];

        foreach ($categoryIds as $categoryId) {
            $series[$categoryId] = [];
        }

        if ($categoryIds === [] || $months === []) {
            return $series;
        }

        $start = $months[0];

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
     * @param  array<int, array<string, string>>  $nets
     */
    private function earliestMonth(array $nets): ?string
    {
        $first = null;

        foreach ($nets as $byMonth) {
            foreach ($byMonth as $month => $net) {
                if ($first === null || $month < $first) {
                    $first = $month;
                }
            }
        }

        return $first;
    }

    /**
     * @param  Collection<int, Goal>  $goals
     * @param  array<int, array<string, string>>  $series
     * @param  array<int, string>  $months
     * @return array<string, mixed>
     */
    private function chart(Collection $goals, array $series, array $months, bool $single): array
    {
        $included = $goals
            ->filter(fn (Goal $goal): bool => $single || $goal->archived_at === null)
            ->values();

        if ($included->isEmpty() || $months === []) {
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

            foreach ($included as $goal) {
                $cumulative = $series[(int) $goal->category_id][$month] ?? '0.00';
                $total = Money::add($total, $this->chartValue($goal, $cumulative, $month));
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

        if ($single && $included->count() === 1) {
            $goal = $included->first();
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

    private function chartValue(Goal $goal, string $cumulative, string $month): string
    {
        if ($goal->frozen_at !== null) {
            $frozenMonth = $goal->frozen_at->timezone('America/Sao_Paulo')->format('Y-m');

            if ($month >= $frozenMonth) {
                return $this->nonNegative($goal->frozen_amount);
            }
        }

        return $this->nonNegative(Money::sub($cumulative, $goal->baseline_amount));
    }

    /**
     * @param  array{from: ?string, to: ?string}  $range
     * @return array<int, mixed>
     */
    private function movements(Goal $goal, array $range): array
    {
        [$start, $end] = $this->savedWindow($goal, $range['from'], $range['to']);

        if ($start !== null && $end !== null && $start > $end) {
            return [];
        }

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

        if ($start !== null) {
            $query->where('date', '>=', $start);
        }

        if ($end !== null) {
            $query->where('date', '<=', $end);
        }

        return TransactionResource::collection($query->get())->resolve();
    }

    /**
     * @param  array<int, array<string, mixed>>  $presented
     * @return array{items: array<int, array<string, mixed>>, has_active: bool}
     */
    private function reserveCategories(?Goal $current, array $presented): array
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

        $open = [];
        $restart = [];

        foreach ($presented as $row) {
            $categoryId = (int) $row['category_id'];

            if ($row['state'] !== 'archived' && empty($row['frozen'])) {
                $open[$categoryId] = [
                    'name' => $row['name'],
                    'state' => $row['state'],
                ];
            } else {
                $restart[$categoryId] = true;
            }
        }

        return [
            'has_active' => $hasActive,
            'items' => $categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
                'archived' => $category->archived_at !== null,
                'open_goal' => $open[$category->id] ?? null,
                'restarts' => isset($restart[$category->id]),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $goals
     */
    private function sortSegment(array &$goals, string $status): void
    {
        $rank = ['active' => 0, 'completed' => 1, 'archived' => 2];

        usort($goals, function (array $left, array $right) use ($status, $rank): int {
            if ($status === 'all' && $left['state'] !== $right['state']) {
                return ($rank[$left['state']] ?? 9) <=> ($rank[$right['state']] ?? 9);
            }

            if (($status === 'active' || $status === 'all') && $left['state'] === 'active') {
                $byDeadline = $this->byDeadline($left, $right);

                if ($byDeadline !== 0) {
                    return $byDeadline;
                }
            }

            return strcasecmp($left['name'], $right['name']);
        });
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function byDeadline(array $left, array $right): int
    {
        $leftDeadline = $left['deadline'] ?? null;
        $rightDeadline = $right['deadline'] ?? null;

        if ($leftDeadline === null && $rightDeadline === null) {
            return 0;
        }

        if ($leftDeadline === null) {
            return 1;
        }

        if ($rightDeadline === null) {
            return -1;
        }

        return $leftDeadline <=> $rightDeadline;
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
