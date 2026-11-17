<?php

namespace App\Support;

/**
 * The month the user is working on, shared by Planning and, later, the Dashboard.
 *
 * The session key is `working_month` (`YYYY-MM`). Logout invalidates the session,
 * so the next login starts on the current month again. The Dashboard does not
 * read this yet; it will, when its period filter arrives in module 8.
 */
class WorkingMonth
{
    public const KEY = 'working_month';

    public function get(): ?string
    {
        $value = session(self::KEY);

        if (! is_string($value) || Month::startOrNull($value) === null) {
            return null;
        }

        return $value;
    }

    public function put(string $monthKey): void
    {
        session([self::KEY => $monthKey]);
    }

    public function remember(?string $fromUrl): string
    {
        if (is_string($fromUrl) && $fromUrl !== '' && Month::startOrNull($fromUrl) !== null) {
            $this->put($fromUrl);

            return $fromUrl;
        }

        return $this->get() ?? Month::currentKey();
    }
}
