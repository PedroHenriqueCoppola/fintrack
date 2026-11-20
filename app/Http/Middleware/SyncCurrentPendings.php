<?php

namespace App\Http\Middleware;

use App\Services\PlanGenerator;
use App\Support\Month;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SyncCurrentPendings
{
    public function __construct(private PlanGenerator $generator) {}

    /**
     * Once per calendar month of the login session, generate the current
     * month's pending fixed expenses and installments. A failure is logged
     * and the page still opens. The key stays unset so the next request retries.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            $month = Month::currentKey();

            if ($request->session()->get('pendings_synced_month') !== $month) {
                try {
                    $this->generator->generateMonth(Month::currentStart());
                    $request->session()->put('pendings_synced_month', $month);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }

        return $next($request);
    }
}
