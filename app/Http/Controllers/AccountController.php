<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * Show the account settings page.
     */
    public function edit(Request $request): View
    {
        return view('account.edit', ['user' => $request->user()]);
    }

    /**
     * Update the user's profile details.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('status', 'Your details have been saved.');
    }

    /**
     * Set or change the user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validateWithBag('password', [
            'current_password' => [Rule::requiredIf($user->hasPassword()), 'nullable', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => $validated['password']]);

        return back()->with('status', 'Your password has been saved. You can now sign in with it or with a magic link.');
    }

    /**
     * Remove the user's password so they can only sign in with magic links.
     */
    public function destroyPassword(Request $request): RedirectResponse
    {
        $request->validateWithBag('removePassword', [
            'current_password' => ['required', 'current_password'],
        ]);

        $request->user()->forceFill(['password' => null])->save();

        return back()->with('status', 'Your password has been removed. Use a magic link to sign in.');
    }
}
