@extends('layouts.app', ['title' => 'Account pending approval'])

@section('content')
<div class="mx-auto max-w-lg py-10">
    <div class="card text-center">
        <h1 class="text-2xl font-semibold">Account pending approval</h1>
        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
            Your email address is verified. An administrator must approve your account before you can request subdomains or manage DNS records.
        </p>
        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
            You can sign out and return later. Access will be available as soon as your account is approved.
        </p>
    </div>
</div>
@endsection
