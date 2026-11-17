<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalysisRequest;
use App\Http\Resources\AnalysisSummaryResource;
use App\Http\Resources\SavedFilterResource;
use App\Http\Resources\TransactionResource;
use App\Services\SavedFilterService;
use App\Services\TransactionOptionsService;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalysisController extends Controller
{
    public const QUERY_KEY = 'analysis_query';

    public function index(
        AnalysisRequest $request,
        TransactionService $transactions,
        TransactionOptionsService $options,
        SavedFilterService $savedFilters,
    ): Response|RedirectResponse {
        if ($redirect = $this->rememberQuery($request)) {
            return $redirect;
        }

        $filters = $request->filters();
        $page = $transactions->paginate($filters);
        $dates = $page->getCollection()
            ->map(fn ($transaction) => $transaction->date?->toDateString())
            ->filter()
            ->unique()
            ->values()
            ->all();
        $totals = $transactions->dayTotals($filters, $dates);

        $page->getCollection()->each(function ($transaction) use ($totals) {
            $date = $transaction->date?->toDateString();
            $transaction->setAttribute(
                'day_total',
                $date !== null && array_key_exists($date, $totals) ? $totals[$date] : null,
            );
        });

        return Inertia::render('Analysis/Index', [
            'filters' => [
                'period' => $filters['period'],
                'type' => $filters['type'],
                'status' => $filters['status'],
                'category_id' => $filters['category_id'],
                'bank_id' => $filters['bank_id'],
                'card_id' => $filters['card_id'],
                'payment_method_id' => $filters['payment_method_id'],
                'min_amount' => $filters['min_amount'],
                'max_amount' => $filters['max_amount'],
                'q' => $filters['q'],
                'sort' => $filters['sort'],
            ],
            'period' => $transactions->period($filters),
            'summary' => (new AnalysisSummaryResource($transactions->summary($filters)))->resolve(),
            'transactions' => Inertia::merge([
                'data' => TransactionResource::collection($page->getCollection())->resolve(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ])->append('data', matchOn: 'id'),
            'savedFilters' => SavedFilterResource::collection($savedFilters->list())->resolve(),
            'options' => $options->catalog(),
        ]);
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->session()->forget(self::QUERY_KEY);

        return redirect()->route('analysis');
    }

    private function rememberQuery(AnalysisRequest $request): ?RedirectResponse
    {
        parse_str((string) $request->server('QUERY_STRING', ''), $query);
        unset($query['page']);

        if ($query === []) {
            $saved = $request->session()->get(self::QUERY_KEY);

            if (is_array($saved) && $saved !== []) {
                return redirect()->route('analysis', $saved);
            }

            return null;
        }

        if ($this->queryIsDefault($query)) {
            $request->session()->forget(self::QUERY_KEY);
        } else {
            $request->session()->put(self::QUERY_KEY, $query);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function queryIsDefault(array $query): bool
    {
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($key === 'period' && $value === 'current_month') {
                continue;
            }

            if ($key === 'sort' && $value === 'date_desc') {
                continue;
            }

            return false;
        }

        return true;
    }
}
