<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationSetting;
use App\Models\User;
use App\Notifications\AccountApprovedNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserRoleController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'approver' THEN 1 ELSE 2 END")
                ->orderBy('name')
                ->paginate(50),
            'selfRegistrationEnabled' => ApplicationSetting::selfRegistrationEnabled(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in([User::ROLE_USER, User::ROLE_APPROVER, User::ROLE_ADMIN])],
        ]);

        $user = User::create([
            ...$validated,
            'password' => Str::random(64),
            'is_admin' => $validated['role'] === User::ROLE_ADMIN,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        event(new Registered($user));
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors([
                'email' => "The user was created and sent a verification email, but the password setup email failed: {$status}",
            ]);
        }

        return back()->with('success', "{$user->name} was added. Verification and password setup emails were sent.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_USER, User::ROLE_APPROVER, User::ROLE_ADMIN])],
        ]);

        if ($user->is($request->user()) && $validated['role'] !== User::ROLE_ADMIN) {
            return back()->withErrors(['role' => 'You cannot remove your own administrator role.']);
        }

        if ($user->isAdmin()
            && $validated['role'] !== User::ROLE_ADMIN
            && User::query()->where('role', User::ROLE_ADMIN)->count() === 1) {
            return back()->withErrors(['role' => 'At least one full administrator is required.']);
        }

        $user->update([
            'role' => $validated['role'],
            'is_admin' => $validated['role'] === User::ROLE_ADMIN,
            'approved_at' => $validated['role'] === User::ROLE_USER ? $user->approved_at : ($user->approved_at ?? now()),
            'approved_by' => $validated['role'] === User::ROLE_USER ? $user->approved_by : ($user->approved_by ?? $request->user()->id),
        ]);

        return back()->with('success', "{$user->name}'s role was updated.");
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail()) {
            return back()->withErrors(['approval' => "{$user->email} must verify their email address before approval."]);
        }

        if ($user->isApproved()) {
            return back()->withErrors(['approval' => "{$user->email} is already approved."]);
        }

        $user->forceFill([
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ])->save();
        $user->notify(new AccountApprovedNotification);

        return back()->with('success', "{$user->name}'s account was approved.");
    }

    public function updateRegistration(Request $request): RedirectResponse
    {
        ApplicationSetting::setSelfRegistrationEnabled($request->boolean('self_registration_enabled'));

        return back()->with('success', 'Self-registration settings were updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        if ($user->subdomains()->exists()) {
            return back()->withErrors(['user' => 'This user cannot be deleted while they own subdomains.']);
        }

        if ($user->isAdmin() && User::query()->where('role', User::ROLE_ADMIN)->count() === 1) {
            return back()->withErrors(['user' => 'The last full administrator cannot be deleted.']);
        }

        DB::transaction(function () use ($user): void {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        return back()->with('success', "{$user->name} was deleted.");
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', "A password reset link was sent to {$user->email}.")
            : back()->withErrors(['email' => __($status)]);
    }

    public function resendVerification(User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            return back()->withErrors(['email' => "{$user->email} is already verified."]);
        }

        $user->sendEmailVerificationNotification();

        return back()->with('success', "A verification link was sent to {$user->email}.");
    }
}
