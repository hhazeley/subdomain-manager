<?php

namespace App\Services;

use App\Models\Subdomain;
use App\Models\User;
use App\Notifications\SubdomainLifecycleNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SubdomainService
{
    public function __construct(private readonly DnsRecordService $records) {}

    public function registrationRequested(Subdomain $subdomain): void
    {
        $subdomain->user->notify(new SubdomainLifecycleNotification('requested', $subdomain->fqdn));

        Notification::send(
            User::query()->whereIn('role', [User::ROLE_ADMIN, User::ROLE_APPROVER])->get(),
            new SubdomainLifecycleNotification('approval_requested', $subdomain->fqdn),
        );
    }

    public function approve(Subdomain $subdomain, User $admin): void
    {
        if ($subdomain->status !== Subdomain::STATUS_PENDING) {
            throw ValidationException::withMessages(['subdomain' => 'Only pending registrations can be approved.']);
        }

        $subdomain->update([
            'status' => Subdomain::STATUS_ACTIVE,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'expires_at' => now()->addYear(),
            'rejection_reason' => null,
        ]);

        $subdomain->user->notify(new SubdomainLifecycleNotification(
            'approved',
            $subdomain->fqdn,
            date: $subdomain->expires_at->toFormattedDateString(),
        ));
    }

    public function reject(Subdomain $subdomain, User $admin, string $reason): void
    {
        if ($subdomain->status !== Subdomain::STATUS_PENDING) {
            throw ValidationException::withMessages(['subdomain' => 'Only pending registrations can be rejected.']);
        }

        $subdomain->update([
            'status' => Subdomain::STATUS_REJECTED,
            'approved_by' => $admin->id,
            'rejection_reason' => $reason,
        ]);

        $subdomain->user->notify(new SubdomainLifecycleNotification(
            'rejected',
            $subdomain->fqdn,
            reason: $reason,
        ));

        $subdomain->delete();
    }

    public function renew(Subdomain $subdomain): void
    {
        if (! $subdomain->isRenewable()) {
            throw ValidationException::withMessages([
                'subdomain' => 'Renewal is available during the 45 days before expiration.',
            ]);
        }

        $subdomain->update([
            'expires_at' => $subdomain->expires_at->copy()->addYear(),
            'renewed_at' => now(),
        ]);

        $subdomain->user->notify(new SubdomainLifecycleNotification(
            'renewed',
            $subdomain->fqdn,
            date: $subdomain->expires_at->toFormattedDateString(),
        ));
    }

    public function requestDeletion(Subdomain $subdomain): void
    {
        if ($subdomain->status === Subdomain::STATUS_DELETION_HOLD) {
            throw ValidationException::withMessages(['subdomain' => 'This subdomain is already pending deletion.']);
        }

        $previousStatus = $subdomain->status;
        $subdomain->update([
            'status' => Subdomain::STATUS_DELETION_HOLD,
            'deletion_previous_status' => $previousStatus,
            'deletion_requested_at' => now(),
        ]);

        $notification = new SubdomainLifecycleNotification(
            'deletion_requested',
            $subdomain->fqdn,
            date: $subdomain->deletion_requested_at->copy()->addDays(14)->toFormattedDateString(),
        );
        $subdomain->user->notify($notification);
        Notification::send(
            User::query()->where('role', User::ROLE_ADMIN)->get(),
            $notification,
        );
    }

    public function restore(Subdomain $subdomain): string
    {
        if ($subdomain->status !== Subdomain::STATUS_DELETION_HOLD) {
            throw ValidationException::withMessages(['subdomain' => 'Only held subdomains can be restored.']);
        }

        $status = $subdomain->deletion_previous_status ?: Subdomain::STATUS_ACTIVE;

        $subdomain->update([
            'status' => $status,
            'deletion_previous_status' => null,
            'deletion_requested_at' => null,
        ]);

        if ($status === Subdomain::STATUS_ACTIVE && $subdomain->expires_at?->isPast()) {
            $this->expire($subdomain);

            return Subdomain::STATUS_EXPIRED;
        }

        $subdomain->user->notify(new SubdomainLifecycleNotification('restored', $subdomain->fqdn));

        return $status;
    }

    public function deletePermanently(Subdomain $subdomain, ?string $reason = null): void
    {
        $subdomain->loadMissing(['dnsRecords', 'managedDomain', 'user']);
        $user = $subdomain->user;
        $fqdn = $subdomain->fqdn;

        foreach ($subdomain->dnsRecords as $record) {
            $this->records->delete($record);
        }

        $subdomain->delete();
        $user->notify(new SubdomainLifecycleNotification('deleted', $fqdn, reason: $reason));
    }

    public function expire(Subdomain $subdomain): void
    {
        $subdomain->loadMissing(['dnsRecords', 'managedDomain', 'user']);

        foreach ($subdomain->dnsRecords as $record) {
            $this->records->delete($record);
        }

        $subdomain->update(['status' => Subdomain::STATUS_EXPIRED]);
        $subdomain->user->notify(new SubdomainLifecycleNotification('expired', $subdomain->fqdn));
    }
}
