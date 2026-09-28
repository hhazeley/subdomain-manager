@extends('layouts.app', ['title' => 'Account settings'])

@section('content')
<div class="mx-auto max-w-4xl">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-brand-700">Account</p>
        <h1 class="mt-1 text-3xl font-semibold">Account settings</h1>
        <p class="mt-2 text-slate-500 dark:text-slate-400">Update your sign-in credentials and verified email address.</p>
    </div>

    <div class="mt-8 grid gap-6 md:grid-cols-2">
        <section class="card">
            <h2 class="text-lg font-semibold">Change password</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Confirm your current password before choosing a new one.</p>

            <form class="mt-5 space-y-4" method="POST" action="{{ route('account.password.update') }}">
                @csrf
                @method('PUT')
                <div>
                    <label for="current_password">Current password</label>
                    <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
                </div>
                <div>
                    <label for="password">New password</label>
                    <input id="password" type="password" name="password" autocomplete="new-password" required>
                    <p class="mt-1 text-xs text-slate-500">At least 10 characters, including letters and numbers.</p>
                </div>
                <div>
                    <label for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
                </div>
                <button class="btn-primary w-full" type="submit">Update password</button>
            </form>

            <form class="mt-4 text-center" method="POST" action="{{ route('account.password-reset') }}">
                @csrf
                <p class="text-sm text-slate-500">Cannot remember your current password?</p>
                <button class="mt-2 font-semibold text-brand-700 hover:text-brand-600" type="submit">Email me a reset link</button>
            </form>
        </section>

        <section class="card">
            <h2 class="text-lg font-semibold">Change email address</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Current address: <strong>{{ auth()->user()->email }}</strong>
            </p>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                You must verify the new address before you can manage subdomains again.
            </p>

            <form class="mt-5 space-y-4" method="POST" action="{{ route('account.email.update') }}">
                @csrf
                @method('PUT')
                <div>
                    <label for="email">New email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                </div>
                <div>
                    <label for="email_current_password">Current password</label>
                    <input id="email_current_password" type="password" name="email_current_password" autocomplete="current-password" required>
                </div>
                <button class="btn-primary w-full" type="submit">Update email address</button>
            </form>
        </section>
    </div>
</div>
@endsection
