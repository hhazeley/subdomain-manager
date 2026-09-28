@extends('layouts.app', ['title' => 'DNS - '.$subdomain->fqdn])

@section('content')
<a class="text-sm font-semibold text-brand-700" href="{{ route('dashboard') }}">&larr; Back to dashboard</a>
<div class="mt-4">
    <p class="text-sm font-semibold uppercase tracking-wide text-brand-700">DNS management</p>
    <h1 class="mt-1 font-mono text-3xl font-semibold">{{ $subdomain->fqdn }}</h1>
    <p class="mt-2 text-slate-500">Changes are applied directly to Cloudflare.</p>
</div>

<div class="mt-8 grid gap-6 xl:grid-cols-[2fr_1fr]">
    <section>
        <h2 class="mb-3 text-lg font-semibold">DNS records</h2>
        @if($subdomain->dnsRecords->isEmpty())
            <div class="card text-center text-sm text-slate-500">No DNS records have been added.</div>
        @else
            <div class="space-y-3">
                @foreach($subdomain->dnsRecords as $record)
                    <details class="card">
                        <summary class="flex cursor-pointer list-none items-center gap-3">
                            <span class="badge bg-brand-100 text-brand-700">{{ $record->type }}</span>
                            <span class="min-w-0 flex-1 truncate font-mono text-sm">{{ $record->name }}</span>
                            <span class="max-w-xs truncate text-sm text-slate-500">{{ $record->content ?: 'Structured data' }}</span>
                        </summary>
                        <form class="mt-5 grid gap-4 border-t border-slate-100 pt-5 md:grid-cols-2" method="POST" action="{{ route('records.update', [$subdomain, $record]) }}" data-dns-form>
                            @csrf
                            @method('PUT')
                            @include('subdomains.partials.record-fields', ['values' => $record])
                            <div class="flex gap-2 md:col-span-2">
                                <button class="btn-primary" type="submit">Save changes</button>
                            </div>
                        </form>
                        <form class="mt-3" method="POST" action="{{ route('records.destroy', [$subdomain, $record]) }}" onsubmit="return confirm('Delete this DNS record from Cloudflare?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn-danger" type="submit">Delete record</button>
                        </form>
                    </details>
                @endforeach
            </div>
        @endif
    </section>

    <aside class="card h-fit">
        <h2 class="text-lg font-semibold">Add DNS record</h2>
        <form class="mt-5 grid gap-4" method="POST" action="{{ route('records.store', $subdomain) }}" data-dns-form>
            @csrf
            @include('subdomains.partials.record-fields', ['values' => null])
            <button class="btn-primary w-full" type="submit">Create in Cloudflare</button>
        </form>
    </aside>
</div>
@endsection
