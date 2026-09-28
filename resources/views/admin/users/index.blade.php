@extends('layouts.app', ['title' => 'Users and roles'])

@section('content')
<div>
    <p class="text-sm font-semibold uppercase tracking-wide text-brand-700">Administration</p>
    <h1 class="mt-1 text-3xl font-semibold">Users and roles</h1>
    <p class="mt-2 text-slate-500 dark:text-slate-400">Approve verified accounts, add users, assign roles, and control public registration.</p>
</div>

<div class="mt-8 grid gap-6 lg:grid-cols-2">
    <section class="card">
        <h2 class="text-lg font-semibold">Add user</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Administrator-created users are pre-approved and receive email verification and password setup links.</p>
        <form class="mt-5 space-y-4" method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div>
                <label for="name">Name</label>
                <input id="name" name="name" value="{{ old('name') }}" required>
            </div>
            <div>
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required>
            </div>
            <div>
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="user" @selected(old('role') === 'user')>User</option>
                    <option value="approver" @selected(old('role') === 'approver')>Approval administrator</option>
                    <option value="admin" @selected(old('role') === 'admin')>Full administrator</option>
                </select>
            </div>
            <button class="btn-primary w-full" type="submit">Add user and send setup emails</button>
        </form>
    </section>

    <section class="card h-fit">
        <h2 class="text-lg font-semibold">Self-registration</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">When disabled, only full administrators can create accounts.</p>
        <form class="mt-5" method="POST" action="{{ route('admin.settings.registration') }}">
            @csrf
            @method('PUT')
            <label class="flex items-center gap-3">
                <input class="size-5 w-auto" type="checkbox" name="self_registration_enabled" value="1" @checked($selfRegistrationEnabled)>
                <span>Allow visitors to register accounts</span>
            </label>
            <button class="btn-primary mt-5" type="submit">Save registration setting</button>
        </form>
    </section>
</div>

<div class="table-wrap mt-8">
    <table class="data-table">
        <thead><tr><th>User</th><th>Email</th><th>Service access</th><th>Role</th><th>Actions</th></tr></thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        @foreach($users as $user)
            <tr>
                <td>
                    <strong>{{ $user->name }}</strong>
                    <div class="text-xs text-slate-500">{{ $user->email }}</div>
                </td>
                <td>
                    <span @class([
                        'badge',
                        'bg-emerald-100 text-emerald-800' => $user->hasVerifiedEmail(),
                        'bg-amber-100 text-amber-800' => ! $user->hasVerifiedEmail(),
                    ])>{{ $user->hasVerifiedEmail() ? 'Verified' : 'Unverified' }}</span>
                </td>
                <td>
                    <span @class([
                        'badge',
                        'bg-emerald-100 text-emerald-800' => $user->isApproved(),
                        'bg-amber-100 text-amber-800' => ! $user->isApproved(),
                    ])>{{ $user->isApproved() ? 'Approved' : 'Pending' }}</span>
                </td>
                <td><span class="badge bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ ucfirst($user->role) }}</span></td>
                <td>
                    <form class="flex min-w-64 gap-2" method="POST" action="{{ route('admin.users.role', $user) }}">
                        @csrf
                        @method('PUT')
                        <select name="role">
                            <option value="user" @selected($user->role === 'user')>User</option>
                            <option value="approver" @selected($user->role === 'approver')>Approval administrator</option>
                            <option value="admin" @selected($user->role === 'admin')>Full administrator</option>
                        </select>
                        <button class="btn-primary" type="submit">Save</button>
                    </form>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @if($user->hasVerifiedEmail() && ! $user->isApproved())
                            <form method="POST" action="{{ route('admin.users.approve', $user) }}">
                                @csrf
                                <button class="btn-primary" type="submit">Approve access</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.users.password-reset', $user) }}">
                            @csrf
                            <button class="btn-secondary" type="submit">Send password reset</button>
                        </form>
                        @unless($user->hasVerifiedEmail())
                            <form method="POST" action="{{ route('admin.users.verification', $user) }}">
                                @csrf
                                <button class="btn-secondary" type="submit">Resend verification</button>
                            </form>
                        @endunless
                        @unless($user->is(auth()->user()))
                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete {{ addslashes($user->name) }}? Users who own subdomains cannot be deleted.')">
                                @csrf
                                @method('DELETE')
                                <button class="btn-danger" type="submit">Delete user</button>
                            </form>
                        @endunless
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $users->links() }}</div>
@endsection
