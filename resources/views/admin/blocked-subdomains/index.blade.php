@extends('layouts.app', ['title' => 'Blocked subdomains'])

@section('content')
<div>
    <p class="text-sm font-semibold uppercase tracking-wide text-brand-700">Administration</p>
    <h1 class="mt-1 text-3xl font-semibold">Blocked subdomains</h1>
    <p class="mt-2 text-slate-500 dark:text-slate-400">Reserve labels globally or for one managed domain.</p>
</div>

<div class="mt-8 grid gap-6 lg:grid-cols-[2fr_1fr]">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Label</th><th>Applies to</th><th>Reason</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($blocks as $block)
                <tr>
                    <td class="font-mono font-semibold">{{ $block->label }}</td>
                    <td>{{ $block->managedDomain?->name ?? 'All domains' }}</td>
                    <td>{{ $block->reason ?: 'Reserved by administrator' }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.blocked-subdomains.destroy', $block) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn-danger" type="submit">Unblock</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-slate-500">No blocked labels.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <aside class="card h-fit">
        <h2 class="text-lg font-semibold">Block a label</h2>
        <form class="mt-5 space-y-4" method="POST" action="{{ route('admin.blocked-subdomains.store') }}">
            @csrf
            <div>
                <label for="label">Subdomain label</label>
                <input id="label" name="label" value="{{ old('label') }}" placeholder="www" required>
            </div>
            <div>
                <label for="managed_domain_id">Domain scope</label>
                <select id="managed_domain_id" name="managed_domain_id">
                    <option value="">All managed domains</option>
                    @foreach($domains as $domain)
                        <option value="{{ $domain->id }}" @selected(old('managed_domain_id') == $domain->id)>{{ $domain->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="reason">Reason</label>
                <input id="reason" name="reason" value="{{ old('reason') }}" placeholder="Reserved for infrastructure">
            </div>
            <button class="btn-primary w-full" type="submit">Block label</button>
        </form>
    </aside>
</div>
@endsection
