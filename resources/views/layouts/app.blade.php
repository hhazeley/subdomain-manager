<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>
    <script>
        (() => {
            const theme = localStorage.getItem('theme') || 'system';
            const dark = theme === 'dark' || (theme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-5 px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 font-semibold text-ink dark:text-slate-100">
                <span class="grid size-9 place-items-center rounded-lg bg-brand-600 text-lg text-white">D</span>
                <span>{{ config('app.name') }}</span>
            </a>
            @auth
                <nav class="flex items-center gap-3 text-sm">
                    <a class="font-medium text-slate-600 hover:text-brand-700 dark:text-slate-300 dark:hover:text-brand-500" href="{{ route('dashboard') }}">Dashboard</a>
                    <a class="font-medium text-slate-600 hover:text-brand-700 dark:text-slate-300 dark:hover:text-brand-500" href="{{ route('account.edit') }}">Account</a>
                    @if(auth()->user()->canApprove())
                        <a class="font-medium text-slate-600 hover:text-brand-700 dark:text-slate-300 dark:hover:text-brand-500" href="{{ route('admin.registrations.index') }}">Approvals</a>
                    @endif
                    @if(auth()->user()->isAdmin())
                        <a class="font-medium text-slate-600 hover:text-brand-700 dark:text-slate-300 dark:hover:text-brand-500" href="{{ route('admin.domains.index') }}">Domains</a>
                        <a class="font-medium text-slate-600 hover:text-brand-700 dark:text-slate-300 dark:hover:text-brand-500" href="{{ route('admin.blocked-subdomains.index') }}">Blocked</a>
                        <a class="font-medium text-slate-600 hover:text-brand-700 dark:text-slate-300 dark:hover:text-brand-500" href="{{ route('admin.users.index') }}">Users</a>
                        <a class="font-medium text-slate-600 hover:text-brand-700 dark:text-slate-300 dark:hover:text-brand-500" href="{{ route('admin.updates.index') }}">Updates</a>
                    @endif
                    <label class="sr-only" for="theme-switcher">Theme</label>
                    <select id="theme-switcher" class="w-auto py-2" aria-label="Theme">
                        <option value="system">System theme</option>
                        <option value="light">Light theme</option>
                        <option value="dark">Dark theme</option>
                    </select>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn-secondary" type="submit">Sign out</button>
                    </form>
                </nav>
            @endauth
            @guest
                <label class="sr-only" for="theme-switcher">Theme</label>
                <select id="theme-switcher" class="w-auto py-2" aria-label="Theme">
                    <option value="system">System theme</option>
                    <option value="light">Light theme</option>
                    <option value="dark">Dark theme</option>
                </select>
            @endguest
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-semibold">Please correct the following:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
    <footer class="mx-auto max-w-7xl px-4 pb-6 text-center text-xs text-slate-500 sm:px-6 lg:px-8">
        {{ config('app.name') }} v{{ config('app.version') }}
    </footer>
</body>
</html>
