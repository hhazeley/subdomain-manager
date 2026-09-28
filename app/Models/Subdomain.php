<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subdomain extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DELETION_HOLD = 'deletion_hold';

    protected $fillable = [
        'user_id',
        'managed_domain_id',
        'label',
        'fqdn',
        'status',
        'expires_at',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'renewed_at',
        'deletion_previous_status',
        'deletion_requested_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'renewed_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function managedDomain(): BelongsTo
    {
        return $this->belongsTo(ManagedDomain::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dnsRecords(): HasMany
    {
        return $this->hasMany(DnsRecord::class);
    }

    public function isRenewable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->expires_at !== null
            && now()->greaterThanOrEqualTo($this->expires_at->copy()->subDays(45))
            && now()->lessThan($this->expires_at);
    }
}
