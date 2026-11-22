<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardRequest;
use App\Services\DashboardService;
use App\Services\TransactionOptionsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(
        DashboardRequest $request,
        DashboardService $dashboard,
        TransactionOptionsService $options,
    ): Response {
        $only = $this->partial($request);
        $page = $dashboard->page($request->filters(), $only);

        if ($only === null || in_array('options', $only, true)) {
            $page['options'] = $options->catalog();
        }

        return Inertia::render('Dashboard/Index', $page);
    }

    /**
     * @return array<int, string>|null
     */
    private function partial(Request $request): ?array
    {
        if (! $request->header('X-Inertia')) {
            return null;
        }

        $header = $request->header('X-Inertia-Partial-Data');

        if (! is_string($header) || $header === '') {
            return null;
        }

        $keys = array_values(array_filter(array_map('trim', explode(',', $header))));

        return $keys === [] ? null : $keys;
    }
}
