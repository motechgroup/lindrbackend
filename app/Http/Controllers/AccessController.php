<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccessController extends Controller
{
    /**
     * Display the protected admin login page.
     */
    public function showLoginForm(Request $request): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->isAdmin() && Auth::user()->isActive()) {
            return redirect()->to('/admin');
        }

        return view('access');
    }

    /**
     * Handle administrator authentication attempt.
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember', true);

        if (Auth::attempt($credentials, $remember)) {
            /** @var User $user */
            $user = Auth::user();

            if (! $user->isAdmin() || ! $user->isActive()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                RateLimiter::hit($throttleKey);

                throw ValidationException::withMessages([
                    'email' => ['Access denied. This portal is strictly for system administrators.'],
                ]);
            }

            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            return redirect()->intended('/admin');
        }

        RateLimiter::hit($throttleKey);

        throw ValidationException::withMessages([
            'email' => ['Invalid administrator credentials provided.'],
        ]);
    }

    /**
     * Log out administrator and redirect to /access portal.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to('/access');
    }
}
