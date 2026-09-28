@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
<div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-brand-700">Account</p>
        <h1 class="mt-1 text-3xl font-semibold">Your subdomains</h1>
        <p class="mt-2 text-slate-500">New registrations require approval. Renewals during the final 45 days are automatic.</p>
    </div>
</div>

<div class="mt-8 grid gap-6 lg:grid-cols-[2fr_1fr]">
    <section>
        @if($subdomains->isEmpty())
            <div class="card text-center">
                <p class="font-semibold">No subdomains yet</p>
                <p class="mt-1 text-sm text-slate-500">Use the form to submit your first registration.</p>
            </div>
        @else
            <div class="grid gap-4">
                @foreach($subdomains as $subdomain)
                    <article class="card flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-mono text-lg font-semibold">{{ $subdomain->fqdn }}</h2>
                                <span @class([
                                    'badge',
                                    'bg-amber-100 text-amber-800' => $subdomain->status === 'pending',
                                    'bg-emerald-100 text-emerald-800' => $subdomain->status === 'active',
                                    'bg-red-100 text-red-800' => in_array($subdomain->status, ['rejected', 'expired']),
                                ])>{{ ucfirst($subdomain->status) }}</span>
                            </div>
                            @if($subdomain->expires_at)
                                <p class="mt-1 text-sm text-slate-500">Expires {{ $subdomain->expires_at->format('M j, Y') }}</p>
                            @endif
                            @if($subdomain->rejection_reason)
                                <p class="mt-2 text-sm text-red-700">{{ $subdomain->rejection_reason }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if($subdomain->status === 'active')
                                <a class="btn-secondary" href="{{ route('subdomains.show', $subdomain) }}">Manage DNS</a>
                            @endif
                            @if($subdomain->isRenewable())
                                <form method="POST" action="{{ route('subdomains.renew', $subdomain) }}">
                                    @csrf
                                    <button class="btn-primary" type="submit">Renew one year</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('subdomains.destroy', $subdomain) }}" onsubmit="return confirm('Hide this subdomain now and permanently delete it after 14 days unless an administrator restores it?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn-danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <aside class="card h-fit">
        <h2 class="text-lg font-semibold">Request a subdomain</h2>
        @if($domains->isEmpty())
            <p class="mt-2 text-sm text-slate-500">No domains are currently available. Contact an administrator.</p>
        @else
            <form class="mt-5 space-y-4" method="POST" action="{{ route('subdomains.store') }}">
                @csrf
                <div>
                    <label for="label">Subdomain</label>
                    <input id="label" name="label" value="{{ old('label') }}" placeholder="my-project" required>
                    <p class="mt-1 text-xs text-slate-500">Letters, numbers, and hyphens only.</p>
                </div>
                <div>
                    <label for="managed_domain_id">Domain</label>
                    <select id="managed_domain_id" name="managed_domain_id" required>
                        @foreach($domains as $domain)
                            <option value="{{ $domain->id }}" @selected(old('managed_domain_id') == $domain->id)>.{{ $domain->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn-primary w-full" type="submit">Submit for approval</button>
            </form>
        @endif
    </aside>
</div>
@endsection
