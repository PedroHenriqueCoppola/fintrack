<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\ProfilePasswordRequest;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Profile/Index');
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->name = $request->validated('name');
        $user->email = $request->validated('email');
        $user->save();

        return back()->with('status', 'Dados atualizados.');
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
        $request->session()->regenerate();

        return back()->with('status', 'Senha atualizada.');
    }
}
