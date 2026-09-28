@extends('layouts.app', ['title' => 'Managed domains'])

@section('content')
<div>
    <p class="text-sm font-semibold uppercase tracking-wide text-brand-700">Administration</p>
    <h1 class="mt-1 text-3xl font-semibold">Managed domains</h1>
    <p class="mt-2 text-slate-500">Each domain is verified against the configured Cloudflare API token before it is saved.</p>
</div>

<div class="mt-8 grid gap-6 lg:grid-cols-[2fr_1fr]">
    <section class="space-y-4">
        @forelse($domains as $domain)
            <details class="card">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                    <span>
                        <strong>{{ $domain->name }}</strong>
                        <span class="ml-2 text-sm text-slate-500">{{ $domain->subdomains_count }} registrations</span>
                    </span>
                    <span class="badge {{ $domain->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                        {{ $domain->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </summary>
                <form class="mt-5 grid gap-4 border-t border-slate-100 pt-5 md:grid-cols-2" method="POST" action="{{ route('admin.domains.update', $domain) }}">
                    @csrf
                    @method('PUT')
                    <div>
                        <label>Domain name</label>
                        <input name="name" value="{{ $domain->name }}" required>
                    </div>
                    <div>
                        <label>Cloudflare zone ID</label>
                        <input class="font-mono" name="cloudflare_zone_id" value="{{ $domain->cloudflare_zone_id }}" required>
                    </div>
                    <label class="flex items-center gap-2">
                        <input class="size-4 w-auto" type="checkbox" name="is_active" value="1" @checked($domain->is_active)>
                        <span>Available for registrations</span>
                    </label>
                    <div class="md:col-span-2">
                        <button class="btn-primary" type="submit">Verify and update</button>
                    </div>
                </form>
            </details>
        @empty
            <div class="card text-center text-sm text-slate-500">No domains configured yet.</div>
        @endforelse
    </section>

    <aside class="card h-fit">
        <h2 class="text-lg font-semibold">Add Cloudflare domain</h2>
        <form class="mt-5 space-y-4" method="POST" action="{{ route('admin.domains.store') }}">
            @csrf
            <div>
                <label for="name">Domain name</label>
                <input id="name" name="name" value="{{ old('name') }}" placeholder="example.com" required>
            </div>
            <div>
                <label for="cloudflare_zone_id">Cloudflare zone ID</label>
                <input class="font-mono" id="cloudflare_zone_id" name="cloudflare_zone_id" value="{{ old('cloudflare_zone_id') }}" required>
            </div>
            <button class="btn-primary w-full" type="submit">Verify and add domain</button>
        </form>
    </aside>
</div>
@endsection
