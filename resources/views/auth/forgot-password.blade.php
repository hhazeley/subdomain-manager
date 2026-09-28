@extends('layouts.app', ['title' => 'Reset password'])

@section('content')
<div class="mx-auto max-w-md py-10">
    <div class="card">
        <h1 class="text-2xl font-semibold">Reset your password</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Enter your email address and we will send you a secure reset link.</p>
        <form class="mt-6 space-y-4" method="POST" action="{{ route('password.email') }}">
            @csrf
            <div>
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            </div>
            <button class="btn-primary w-full" type="submit">Send reset link</button>
        </form>
        <p class="mt-5 text-center text-sm text-slate-500">
            <a class="font-semibold text-brand-700" href="{{ route('login') }}">Back to sign in</a>
        </p>
    </div>
</div>
@endsection
