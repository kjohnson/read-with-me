<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /**
     * Show the registration form.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Register a new user with just an email address and sign them in.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ], [
            'email.unique' => 'That email already has an account. Sign in instead.',
        ]);

        $user = User::create(['email' => $validated['email']]);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('stories.index')
            ->with('status', 'Welcome! Next time, just enter your email and we\'ll send you a sign-in link.');
    }
}
