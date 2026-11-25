<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\ProfilePasswordRequest;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        $user = request()->user();

        return Inertia::render('Profile/Index', [
            'member_since' => $user ? $this->memberSince($user->created_at) : '',
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->name = $request->validated('name');
        $user->email = $request->validated('email');
        $user->save();

        return back()->with('profile_saved', 'Dados atualizados.');
    }

    public function password(ProfilePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        LoginRequest::assertOpen($request, $user->email, 'current_password');

        if (! Hash::check($request->validated('current_password'), $user->password)) {
            LoginRequest::recordFailure($request, $user->email, 'current_password', 'A senha atual não confere.');
        }

        LoginRequest::clearFailures($request, $user->email);
        $user->password = $request->validated('password');
        $user->save();
        $this->logoutOtherSessions($request, (int) $user->id);
        $request->session()->regenerate();

        return back()->with('password_saved', 'Senha atualizada.');
    }

    private function logoutOtherSessions(ProfilePasswordRequest $request, int $userId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }

    private function memberSince(mixed $createdAt): string
    {
        if (! $createdAt instanceof \DateTimeInterface) {
            return '';
        }

        $created = \Illuminate\Support\Carbon::parse($createdAt)->timezone('America/Sao_Paulo');
        $months = [
            1 => 'janeiro',
            2 => 'fevereiro',
            3 => 'março',
            4 => 'abril',
            5 => 'maio',
            6 => 'junho',
            7 => 'julho',
            8 => 'agosto',
            9 => 'setembro',
            10 => 'outubro',
            11 => 'novembro',
            12 => 'dezembro',
        ];

        return 'Conta criada em '.$months[(int) $created->format('n')].' de '.$created->format('Y');
    }
}
