@extends('layouts.app', ['title' => 'Registration approvals'])

@section('content')
<div>
    <p class="text-sm font-semibold uppercase tracking-wide text-brand-700">Administration</p>
    <h1 class="mt-1 text-3xl font-semibold">Registration approvals</h1>
    <p class="mt-2 text-slate-500">Approvals activate a registration for one year. User renewals are automatically approved.</p>
</div>

<div class="table-wrap mt-8">
    <table class="data-table">
        <thead>
            <tr>
                <th>Subdomain</th>
                <th>User</th>
                <th>Status</th>
                <th>Submitted / expires</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($registrations as $registration)
                <tr>
                    <td class="font-mono font-medium">{{ $registration->fqdn }}</td>
                    <td>
                        {{ $registration->user->name }}
                        <div class="text-xs text-slate-500">{{ $registration->user->email }}</div>
                    </td>
                    <td><span class="badge bg-slate-100 text-slate-700">{{ str($registration->status)->replace('_', ' ')->title() }}</span></td>
                    <td>
                        {{ $registration->created_at->format('M j, Y') }}
                        @if($registration->expires_at)
                            <div class="text-xs text-slate-500">Expires {{ $registration->expires_at->format('M j, Y') }}</div>
                        @endif
                    </td>
                    <td>
                        @if($registration->status === 'pending')
                            <div class="flex min-w-64 flex-col gap-2">
                                <form method="POST" action="{{ route('admin.registrations.approve', $registration) }}">
                                    @csrf
                                    <button class="btn-primary" type="submit">Approve</button>
                                </form>
                                <form class="flex gap-2" method="POST" action="{{ route('admin.registrations.reject', $registration) }}">
                                    @csrf
                                    <input name="reason" placeholder="Reason for rejection" required>
                                    <button class="btn-danger" type="submit">Reject</button>
                                </form>
                            </div>
                        @endif
                        @if($registration->status === 'deletion_hold' && auth()->user()->isAdmin())
                            <div class="mb-2 text-xs text-amber-700">
                                Delete after {{ $registration->deletion_requested_at->copy()->addDays(14)->format('M j, Y') }}
                            </div>
                            <form class="mb-2" method="POST" action="{{ route('admin.registrations.restore', $registration) }}">
                                @csrf
                                <button class="btn-primary" type="submit">Restore</button>
                            </form>
                        @endif
                        @if(auth()->user()->isAdmin())
                            <form class="flex min-w-64 gap-2" method="POST" action="{{ route('admin.registrations.destroy', $registration) }}" onsubmit="return confirm('Permanently delete this subdomain and every DNS record now?')">
                                @csrf
                                @method('DELETE')
                                <input name="reason" placeholder="Deletion reason (optional)">
                                <button class="btn-danger" type="submit">Delete permanently</button>
                            </form>
                        @elseif($registration->status !== 'pending')
                            <span class="text-sm text-slate-500">No action available</span>
                        @endif
                        @if($registration->rejection_reason)
                            <div class="mt-2 text-sm text-red-700">{{ $registration->rejection_reason }}</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-slate-500">No registrations found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $registrations->links() }}</div>
@endsection
