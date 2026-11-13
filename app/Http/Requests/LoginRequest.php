<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const LOCK_UNTIL = 'login.lock_until';

    private const PAIR_MAX_FAILURES = 5;

    private const PAIR_LOCK_SECONDS = 300;

    private const IP_MAX_FAILURES = 20;

    private const IP_WINDOW_SECONDS = 900;

    private const IP_LOCK_SECONDS = 900;

    public function authorize(): bool
    {
        return true;
    }

    public static function remainingLockSeconds(Request $request): int
    {
        $until = (int) $request->session()->get(self::LOCK_UNTIL, 0);
        $remaining = $until - time();

        if ($remaining <= 0) {
            $request->session()->forget(self::LOCK_UNTIL);

            return 0;
        }

        return $remaining;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email', ''))),
            'remember' => $this->boolean('remember'),
        ]);

        $this->rejectIfLocked();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Informe o e-mail.',
            'email.string' => 'Informe um e-mail válido.',
            'email.email' => 'Informe um e-mail válido.',
            'password.required' => 'Informe a senha.',
            'password.string' => 'Informe a senha.',
            'remember.boolean' => 'O valor de manter conectado é inválido.',
        ];
    }

    public function authenticate(): void
    {
        $this->rejectIfLocked();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $until = max($this->recordPairFailure() ?? 0, $this->recordIpFailure() ?? 0);

            if ($until > time()) {
                $this->throwLocked($until);
            }

            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha incorretos.',
            ]);
        }

        Cache::forget($this->pairFailureKey());
        Cache::forget($this->ipFailureKey());
        $this->session()->forget(self::LOCK_UNTIL);
    }

    protected function rejectIfLocked(): void
    {
        $until = $this->activeUntil();

        if ($until === null) {
            return;
        }

        $this->throwLocked($until);
    }

    protected function throwLocked(int $until): void
    {
        $this->rememberSessionLock($until);

        $seconds = max(1, $until - time());

        throw ValidationException::withMessages([
            'email' => "Muitas tentativas de login. Tente novamente em {$seconds} segundos.",
        ]);
    }

    protected function rememberSessionLock(int $until): void
    {
        $current = (int) $this->session()->get(self::LOCK_UNTIL, 0);

        if ($until > $current) {
            $this->session()->put(self::LOCK_UNTIL, $until);
        }
    }

    protected function activeUntil(): ?int
    {
        $until = max(
            $this->activeLock($this->pairLockKey()) ?? 0,
            $this->activeLock($this->ipLockKey()) ?? 0,
        );

        return $until > time() ? $until : null;
    }

    protected function activeLock(string $key): ?int
    {
        $until = Cache::get($key);

        if (! is_int($until) && ! (is_string($until) && ctype_digit($until))) {
            return null;
        }

        $until = (int) $until;

        if ($until <= time()) {
            Cache::forget($key);

            return null;
        }

        return $until;
    }

    protected function recordPairFailure(): ?int
    {
        $key = $this->pairFailureKey();
        $count = ((int) Cache::get($key, 0)) + 1;

        if ($count >= self::PAIR_MAX_FAILURES) {
            Cache::forget($key);

            return $this->startLock($this->pairLockKey(), self::PAIR_LOCK_SECONDS);
        }

        Cache::forever($key, $count);

        return null;
    }

    protected function recordIpFailure(): ?int
    {
        $now = time();
        $key = $this->ipFailureKey();
        $stamps = Cache::get($key, []);

        if (! is_array($stamps)) {
            $stamps = [];
        }

        $stamps = array_values(array_filter(
            $stamps,
            fn ($stamp) => is_int($stamp) && $stamp > $now - self::IP_WINDOW_SECONDS,
        ));
        $stamps[] = $now;

        if (count($stamps) >= self::IP_MAX_FAILURES) {
            Cache::forget($key);

            return $this->startLock($this->ipLockKey(), self::IP_LOCK_SECONDS);
        }

        Cache::put($key, $stamps, self::IP_WINDOW_SECONDS);

        return null;
    }

    protected function startLock(string $key, int $seconds): int
    {
        $existing = $this->activeLock($key);

        if ($existing !== null) {
            return $existing;
        }

        $until = time() + $seconds;
        Cache::put($key, $until, $seconds);

        return $until;
    }

    protected function pairFailureKey(): string
    {
        return 'login-failures:pair:'.$this->pairHash();
    }

    protected function pairLockKey(): string
    {
        return 'login-lock:pair:'.$this->pairHash();
    }

    protected function ipFailureKey(): string
    {
        return 'login-failures:ip:'.$this->ipHash();
    }

    protected function ipLockKey(): string
    {
        return 'login-lock:ip:'.$this->ipHash();
    }

    protected function pairHash(): string
    {
        return sha1(Str::transliterate(Str::lower($this->string('email'))).'|'.$this->ip());
    }

    protected function ipHash(): string
    {
        return sha1((string) $this->ip());
    }
}
