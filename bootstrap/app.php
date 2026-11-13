<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as FrameworkPreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Inertia\Support\Header;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
    })->create();
