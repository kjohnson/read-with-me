<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MagicLink;
use App\Models\User;
use App\Notifications\MagicLinkNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MagicLinkController extends Controller
{
    /**
     * Show the sign-in form.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Email a magic sign-in link to the user, if they have an account.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $user = User::firstWhere('email', mb_strtolower($validated['email']));

        if ($user !== null) {
            $user->notify(new MagicLinkNotification(MagicLink::issueFor($user)));
        }

        return redirect()->route('login')
            ->withInput($request->only('email'))
            ->with('magic_link_sent', true);
    }

    /**
     * Show the confirmation page for a magic link.
     *
     * Signing in requires a POST so that email link scanners which prefetch
     * links cannot use up the single-use token.
     */
    public function show(string $token): View
    {
        return view('auth.magic-link', [
            'token' => $token,
            'isUsable' => MagicLink::findUsable($token) !== null,
        ]);
    }

    /**
     * Sign the user in with a magic link.
     */
    public function consume(Request $request, string $token): RedirectResponse
    {
        $link = MagicLink::findUsable($token);

        if ($link === null || ! $link->markAsUsed()) {
            return redirect()->route('login')
                ->withErrors(['email' => 'That sign-in link has expired or was already used. Request a new one below.']);
        }

        $user = $link->user;

        DB::transaction(function () use ($user): void {
            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $user->magicLinks()->whereNull('used_at')->delete();
        });

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('stories.index'));
    }
}
