<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsToPlanning;
use App\Http\Requests\InstallmentPreviewRequest;
use App\Http\Requests\PlanningSettingsRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Models\Category;
use App\Services\InstallmentService;
use App\Services\PlanningService;
use App\Support\Month;
use App\Support\WorkingMonth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanningController extends Controller
{
    use RedirectsToPlanning;

    public function index(Request $request, PlanningService $planning, WorkingMonth $workingMonth): Response|RedirectResponse
    {
        $raw = $request->query('month');

        if (is_string($raw) && $raw !== '' && Month::startOrNull($raw) === null) {
            return redirect()->route('planning', $this->queryWithout($request, 'month', Month::currentKey()));
        }

        $month = $workingMonth->remember(is_string($raw) ? $raw : null);

        $tab = $request->query('tab', 'plan');
        $tab = in_array($tab, ['plan', 'fixed'], true) ? $tab : 'plan';

        return Inertia::render('Planning/Index', $planning->page(
            $request->user(),
            $month,
            $tab,
            $this->filters($request),
        ));
    }

    public function update(UpdatePlanRequest $request, string $month, PlanningService $planning): RedirectResponse
    {
        $planning->save($month, $request->validated());

        return $this->redirectToPlanning($month);
    }

    public function copy(Request $request, string $month, PlanningService $planning): RedirectResponse
    {
        $this->assertMonth($month);
        $planning->copy($month);

        return $this->redirectToPlanning($month);
    }

    public function confirm(Request $request, string $month, PlanningService $planning): RedirectResponse
    {
        $this->assertMonth($month);
        $planning->confirm($month);

        return $this->redirectToPlanning($month);
    }

    public function reopen(Request $request, string $month, PlanningService $planning): RedirectResponse
    {
        $this->assertMonth($month);
        $planning->reopen($month);

        return $this->redirectToPlanning($month);
    }

    public function settings(PlanningSettingsRequest $request, PlanningService $planning): RedirectResponse
    {
        $planning->updateHighImpact($request->user(), (int) $request->validated('high_impact_percent'));

        return $this->redirectToPlanning();
    }

    public function preview(InstallmentPreviewRequest $request, InstallmentService $installments): RedirectResponse
    {
        $amounts = $installments->amounts(
            (string) $request->validated('total_amount'),
            (int) $request->validated('installments_count'),
        );

        return $this->redirectToPlanning()->with('installment_preview', [
            'total_amount' => $request->validated('total_amount'),
            'installments_count' => (int) $request->validated('installments_count'),
            'amounts' => array_values($amounts),
        ]);
    }

    private function assertMonth(string $month): void
    {
        Month::start($month);
    }

    /**
     * @return array{category_id: ?int, min_amount: ?string, max_amount: ?string, sort: string}
     */
    private function filters(Request $request): array
    {
        $sort = (string) $request->query('sort', 'name');

        if (! in_array($sort, ['name', 'amount', 'due'], true)) {
            $sort = 'name';
        }

        $categoryId = ctype_digit((string) $request->query('category_id'))
            ? (int) $request->query('category_id')
            : null;

        if ($categoryId !== null && ! Category::query()->whereKey($categoryId)->exists()) {
            $categoryId = null;
        }

        return [
            'category_id' => $categoryId,
            'min_amount' => $this->moneyQuery($request->query('min_amount')),
            'max_amount' => $this->moneyQuery($request->query('max_amount')),
            'sort' => $sort,
        ];
    }

    private function moneyQuery(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $string = str_replace(',', '.', trim((string) $value));

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $string)) {
            return null;
        }

        return \App\Support\Money::of($string);
    }

    /**
     * @return array<string, mixed>
     */
    private function queryWithout(Request $request, string $replace, string $month): array
    {
        $query = $request->query();
        $query['month'] = $month;
        unset($query[$replace]);
        $query['month'] = $month;

        return $query;
    }
}
