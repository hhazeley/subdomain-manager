<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(): View
    {
        return view('account.edit');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        return back()->with('success', 'Your password was updated.');
    }

    public function sendPasswordReset(Request $request): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $request->user()->email]);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', __($status))
            : back()->withErrors(['password_reset' => __($status)]);
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($request->user()->id),
            ],
            'email_current_password' => ['required', 'current_password'],
        ]);

        if ($validated['email'] === $request->user()->email) {
            throw ValidationException::withMessages([
                'email' => 'Enter a different email address.',
            ]);
        }

        $request->user()->forceFill([
            'email' => $validated['email'],
            'email_verified_at' => null,
            'remember_token' => Str::random(60),
        ])->save();

        $request->user()->sendEmailVerificationNotification();

        return redirect()->route('verification.notice')
            ->with('success', 'Your email address was updated. Check your new address for a verification link.');
    }
}
