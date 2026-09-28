<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Models\User;
use App\Rules\ValidRecaptcha;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login', [
            'selfRegistrationEnabled' => ApplicationSetting::selfRegistrationEnabled(),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The provided credentials are incorrect.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): View|RedirectResponse
    {
        if (! ApplicationSetting::selfRegistrationEnabled()) {
            return redirect()->route('login')
                ->withErrors(['registration' => 'Self-registration is currently disabled. Contact an administrator for an account.']);
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        abort_unless(ApplicationSetting::selfRegistrationEnabled(), 403, 'Self-registration is currently disabled.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'g-recaptcha-response' => [
                Rule::requiredIf((bool) config('recaptcha.enabled')),
                new ValidRecaptcha,
            ],
        ]);

        $user = User::create([
            ...$validated,
            'approved_at' => null,
            'approved_by' => null,
        ]);
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice')
            ->with('success', 'Your account was created. Verify your email, then wait for administrator approval.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
