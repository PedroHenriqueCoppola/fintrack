<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Inertia\Support\Header;

class PreventRequestForgery extends Middleware
{
    /**
     * Same-origin requests skip the CSRF token in this Laravel version.
     * Inertia requests still require a token, so an expired session is rejected.
     *
     * @param  Request  $request
     * @return mixed
     *
     * @throws TokenMismatchException
     */
    public function handle($request, Closure $next)
    {
        if ($request->header(Header::INERTIA) && ! $this->isReading($request) && ! $this->inExceptArray($request) && ! $this->tokensMatch($request)) {
            throw new TokenMismatchException('CSRF token mismatch.');
        }

        return parent::handle($request, $next);
    }
}
