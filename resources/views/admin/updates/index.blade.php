@extends('layouts.app', ['title' => 'Application updates'])

@section('content')
<div>
    <p class="text-sm font-semibold uppercase tracking-wide text-brand-700">Administration</p>
    <h1 class="mt-1 text-3xl font-semibold">Application updates</h1>
    <p class="mt-2 text-slate-500 dark:text-slate-400">
        Install verified production releases from
        <a class="font-semibold text-brand-700" href="https://github.com/{{ config('updater.github_repository') }}" rel="noopener noreferrer" target="_blank">
            {{ config('updater.github_repository') }}
        </a>.
    </p>
</div>

<div class="mt-8 grid gap-6 lg:grid-cols-[1fr_2fr]">
    <section class="card h-fit">
        <h2 class="text-lg font-semibold">Installed release</h2>
        <p class="mt-3 font-mono text-3xl font-semibold">v{{ $currentVersion }}</p>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
            Updates preserve environment settings, uploaded data, installation state, and local SQLite databases.
        </p>
    </section>

    <section class="card">
        <h2 class="text-lg font-semibold">Latest GitHub release</h2>

        @if($error)
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ $error }}
            </div>
        @elseif($latest)
            <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="font-mono text-2xl font-semibold">v{{ $latest->version }}</p>
                    <p class="text-sm text-slate-500">{{ $latest->name }}</p>
                </div>
                @if($latest->isUpdateFor($currentVersion))
                    <form method="POST" action="{{ route('admin.updates.install') }}" onsubmit="return confirm('Install v{{ $latest->version }} now? The application will briefly enter maintenance mode.')">
                        @csrf
                        <input type="hidden" name="version" value="{{ $latest->version }}">
                        <button class="btn-primary" type="submit">Install verified update</button>
                    </form>
                @else
                    <span class="badge bg-emerald-100 text-emerald-800">Up to date</span>
                @endif
            </div>

            @if($latest->notes !== '')
                <div class="mt-5 border-t border-slate-200 pt-5 dark:border-slate-800">
                    <h3 class="font-semibold">Release notes</h3>
                    <div class="mt-2 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300">{{ $latest->notes }}</div>
                </div>
            @endif

            <a class="btn-secondary mt-5" href="{{ $latest->htmlUrl }}" rel="noopener noreferrer" target="_blank">View release on GitHub</a>
        @endif
    </section>
</div>
@endsection
