<?php

namespace App\Services;

use App\Models\DnsRecord;
use App\Models\Subdomain;
use Illuminate\Support\Arr;
use RuntimeException;
use Throwable;

class DnsRecordService
{
    public function __construct(private readonly CloudflareClient $cloudflare) {}

    public function create(Subdomain $subdomain, array $attributes): DnsRecord
    {
        $record = $subdomain->dnsRecords()->create($attributes);

        try {
            $result = $this->cloudflare->createDnsRecord(
                $subdomain->managedDomain->cloudflare_zone_id,
                $this->payload($record),
            );

            if (! is_string($result['id'] ?? null) || $result['id'] === '') {
                throw new RuntimeException('Cloudflare did not return a DNS record ID.');
            }

            $record->update(['cloudflare_record_id' => $result['id']]);
        } catch (Throwable $exception) {
            $record->delete();
            throw $exception;
        }

        return $record->fresh();
    }

    public function update(DnsRecord $record, array $attributes): DnsRecord
    {
        $candidate = $record->replicate()->forceFill($attributes);
        $result = $this->cloudflare->updateDnsRecord(
            $record->subdomain->managedDomain->cloudflare_zone_id,
            $record->cloudflare_record_id,
            $this->payload($candidate),
        );

        $record->update([
            ...$attributes,
            'cloudflare_record_id' => $result['id'] ?? $record->cloudflare_record_id,
        ]);

        return $record->fresh();
    }

    public function delete(DnsRecord $record): void
    {
        if ($record->cloudflare_record_id) {
            $this->cloudflare->deleteDnsRecord(
                $record->subdomain->managedDomain->cloudflare_zone_id,
                $record->cloudflare_record_id,
            );
        }

        $record->delete();
    }

    private function payload(DnsRecord $record): array
    {
        return Arr::whereNotNull([
            'type' => $record->type,
            'name' => $record->name,
            'content' => $record->content,
            'ttl' => $record->ttl,
            'proxied' => $record->proxied,
            'priority' => $record->priority,
            'comment' => $record->comment,
            'data' => $record->data,
            'tags' => $record->tags,
        ]);
    }
}
