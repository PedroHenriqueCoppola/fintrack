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
    private const RETRY_AFTER_SECONDS = 300;

    public function __construct(private PlanGenerator $generator) {}

    /**
     * Once per calendar month of the login session, generate the current
     * month's pending fixed expenses and installments. A failure is logged,
     * the page still opens, and the next try waits five minutes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            $month = Month::currentKey();

            if ($request->session()->get('pendings_synced_month') !== $month && $this->mayRetry($request)) {
                try {
                    $this->generator->generateMonth(Month::currentStart());
                    $request->session()->put('pendings_synced_month', $month);
                    $request->session()->forget('pendings_sync_failed_at');
                } catch (Throwable $exception) {
                    report($exception);
                    $request->session()->put('pendings_sync_failed_at', time());
                }
            }
        }

        return $next($request);
    }

    private function mayRetry(Request $request): bool
    {
        $failedAt = $request->session()->get('pendings_sync_failed_at');

        if (! is_int($failedAt) && ! (is_string($failedAt) && ctype_digit($failedAt))) {
            return true;
        }

        return time() - (int) $failedAt >= self::RETRY_AFTER_SECONDS;
    }
}
