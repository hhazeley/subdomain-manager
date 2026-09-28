<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DnsRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'subdomain_id',
        'cloudflare_record_id',
        'type',
        'name',
        'content',
        'ttl',
        'proxied',
        'priority',
        'comment',
        'data',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'proxied' => 'boolean',
            'data' => 'array',
            'tags' => 'array',
        ];
    }

    public function subdomain(): BelongsTo
    {
        return $this->belongsTo(Subdomain::class);
    }
}
