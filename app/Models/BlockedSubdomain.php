<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedSubdomain extends Model
{
    protected $fillable = [
        'managed_domain_id',
        'label',
        'reason',
        'created_by',
    ];

    public function managedDomain(): BelongsTo
    {
        return $this->belongsTo(ManagedDomain::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
