<?php

namespace App\Http\Controllers\Concerns;

use App\Support\Month;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RedirectsToPlanning
{
    protected function redirectToPlanning(?string $month = null): RedirectResponse
    {
        $previous = Request::create(url()->previous());
        $query = [];

        if ($previous->getSchemeAndHttpHost() === request()->getSchemeAndHttpHost()
            && $previous->getPathInfo() === '/planning') {
            $query = $previous->query();
        }

        $candidate = $month ?: (string) ($query['month'] ?? '');
        $query['month'] = Month::startOrNull($candidate) ? substr($candidate, 0, 7) : Month::currentKey();
        $tab = (string) ($query['tab'] ?? 'plan');
        $query['tab'] = in_array($tab, ['plan', 'fixed'], true) ? $tab : 'plan';

        return redirect()->to('/planning?'.http_build_query($query));
    }
}
