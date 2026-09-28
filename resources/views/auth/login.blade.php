@extends('layouts.app', ['title' => 'Sign in'])

@section('content')
<div class="mx-auto max-w-md py-10">
    <div class="card">
        <h1 class="text-2xl font-semibold">Welcome back</h1>
        <p class="mt-2 text-sm text-slate-500">Sign in to manage your subdomains and DNS records.</p>
        <form class="mt-6 space-y-4" method="POST" action="{{ url('/login') }}">
            @csrf
            <div>
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            </div>
            <div>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required>
                <div class="mt-1 text-right">
                    <a class="text-xs font-semibold text-brand-700" href="{{ route('password.request') }}">Forgot password?</a>
                </div>
            </div>
            <label class="flex items-center gap-2">
                <input class="size-4 w-auto" type="checkbox" name="remember" value="1">
                <span>Remember me</span>
            </label>
            <button class="btn-primary w-full" type="submit">Sign in</button>
        </form>
        @if($selfRegistrationEnabled)
            <p class="mt-5 text-center text-sm text-slate-500">
                Need an account? <a class="font-semibold text-brand-700" href="{{ route('register') }}">Register</a>
            </p>
        @endif
    </div>
</div>
@endsection
