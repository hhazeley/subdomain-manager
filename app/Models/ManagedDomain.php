<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagedDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'cloudflare_zone_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function subdomains(): HasMany
    {
        return $this->hasMany(Subdomain::class);
    }

    public function blockedSubdomains(): HasMany
    {
        return $this->hasMany(BlockedSubdomain::class);
    }
}
