@extends('layouts.app', ['title' => 'Choose a new password'])

@section('content')
<div class="mx-auto max-w-md py-10">
    <div class="card">
        <h1 class="text-2xl font-semibold">Choose a new password</h1>
        <form class="mt-6 space-y-4" method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required>
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
            <button class="btn-primary w-full" type="submit">Reset password</button>
        </form>
    </div>
</div>
@endsection
