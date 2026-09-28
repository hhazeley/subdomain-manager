@extends('layouts.app', ['title' => 'Verify email'])

@section('content')
<div class="mx-auto max-w-lg py-10">
    <div class="card text-center">
        <h1 class="text-2xl font-semibold">Verify your email address</h1>
        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
            We sent a verification link to <strong>{{ auth()->user()->email }}</strong>.
            After verification, an administrator must approve your account before you can register or manage subdomains.
        </p>
        <form class="mt-6" method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button class="btn-primary" type="submit">Send another verification email</button>
        </form>
    </div>
</div>
@endsection
