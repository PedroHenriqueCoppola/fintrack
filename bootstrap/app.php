<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as FrameworkPreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Inertia\Inertia;
use Inertia\Support\Header;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

if (! function_exists('error_page_authenticated')) {
    function error_page_authenticated(Request $request): bool
    {
        try {
            if (! $request->hasSession()) {
                $session = app('session')->driver();
                $session->setRequestOnHandler($request);
                $session->start();
                $request->setLaravelSession($session);
            }

            return $request->user() !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ], replace: [
            FrameworkPreventRequestForgery::class => PreventRequestForgery::class,
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() !== 419 || ! $request->header(Header::INERTIA)) {
                return null;
            }

            if (! $exception->getPrevious() instanceof TokenMismatchException) {
                return null;
            }

            return back()->withErrors([
                'session' => 'Sua sessão expirou. Tente de novo.',
            ]);
        });

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof HttpException && $exception->getStatusCode() === 419 && $request->header(Header::INERTIA)) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : null;
            $show = in_array($status, [403, 404], true);

            if ($status === 419) {
                $show = true;
            }

            if (in_array($status, [500, 503], true) && ! config('app.debug')) {
                $show = true;
            }

            if ($status === null && ! config('app.debug')) {
                $status = 500;
                $show = true;
            }

            if (! $show || $status === null) {
                return null;
            }

            return Inertia::render('Error/Index', [
                'code' => $status,
                'authenticated' => error_page_authenticated($request),
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
