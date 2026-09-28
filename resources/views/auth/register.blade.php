@extends('layouts.app', ['title' => 'Create account'])

@section('content')
<div class="mx-auto max-w-md py-10">
    <div class="card">
        <h1 class="text-2xl font-semibold">Create your account</h1>
        <p class="mt-2 text-sm text-slate-500">Register to request access. After email verification, an administrator must approve your account.</p>
        <form class="mt-6 space-y-4" method="POST" action="{{ url('/register') }}">
            @csrf
            <div>
                <label for="name">Name</label>
                <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>
            </div>
            <div>
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
            </div>
            <div>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" autocomplete="new-password" required>
                <p class="mt-1 text-xs text-slate-500">At least 10 characters, including letters and numbers.</p>
            </div>
            <div>
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
            </div>
            @if(config('recaptcha.enabled'))
                @if(config('recaptcha.site_key'))
                    <div class="g-recaptcha" data-sitekey="{{ config('recaptcha.site_key') }}"></div>
                @else
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        Registration is unavailable until Google reCAPTCHA is configured.
                    </div>
                @endif
            @endif
            <button class="btn-primary w-full" type="submit" @disabled(config('recaptcha.enabled') && ! config('recaptcha.site_key'))>Create account</button>
        </form>
        <p class="mt-5 text-center text-sm text-slate-500">
            Already registered? <a class="font-semibold text-brand-700" href="{{ route('login') }}">Sign in</a>
        </p>
    </div>
</div>
@if(config('recaptcha.enabled') && config('recaptcha.site_key'))
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endif
@endsection
