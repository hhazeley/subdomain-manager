<?php

namespace Tests\Feature;

use App\Models\DnsRecord;
use App\Models\ManagedDomain;
use App\Models\Subdomain;
use App\Models\User;
use App\Notifications\SubdomainLifecycleNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ExpireSubdomainsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiration_removes_cloudflare_records_and_marks_registration_expired(): void
    {
        Notification::fake();
        config(['services.cloudflare.api_token' => 'test-token']);
        Http::fake([
            '*' => Http::response(['success' => true, 'result' => ['id' => 'record-id']]),
        ]);
        $domain = ManagedDomain::create([
            'name' => 'example.com',
            'cloudflare_zone_id' => str_repeat('e', 32),
            'is_active' => true,
        ]);
        $subdomain = Subdomain::create([
            'user_id' => User::factory()->create()->id,
            'managed_domain_id' => $domain->id,
            'label' => 'expired',
            'fqdn' => 'expired.example.com',
            'status' => Subdomain::STATUS_ACTIVE,
            'expires_at' => now()->subMinute(),
        ]);
        DnsRecord::create([
            'subdomain_id' => $subdomain->id,
            'cloudflare_record_id' => 'record-id',
            'type' => 'A',
            'name' => $subdomain->fqdn,
            'content' => '192.0.2.1',
            'ttl' => 1,
            'proxied' => false,
        ]);

        $this->artisan('dns:expire')->assertSuccessful();

        $this->assertSame(Subdomain::STATUS_EXPIRED, $subdomain->fresh()->status);
        $this->assertSame(0, DnsRecord::count());
        Http::assertSent(fn ($request) => $request->method() === 'DELETE');
        Notification::assertSentTo($subdomain->user, SubdomainLifecycleNotification::class);
    }

    public function test_deletion_hold_is_purged_after_fourteen_days(): void
    {
        Notification::fake();
        config(['services.cloudflare.api_token' => 'test-token']);
        Http::fake([
            '*' => Http::response(['success' => true, 'result' => ['id' => 'record-id']]),
        ]);
        $user = User::factory()->create();
        $domain = ManagedDomain::create([
            'name' => 'example.com',
            'cloudflare_zone_id' => str_repeat('f', 32),
            'is_active' => true,
        ]);
        $subdomain = Subdomain::create([
            'user_id' => $user->id,
            'managed_domain_id' => $domain->id,
            'label' => 'held',
            'fqdn' => 'held.example.com',
            'status' => Subdomain::STATUS_DELETION_HOLD,
            'deletion_previous_status' => Subdomain::STATUS_ACTIVE,
            'deletion_requested_at' => now()->subDays(14)->subMinute(),
            'expires_at' => now()->addMonth(),
        ]);
        DnsRecord::create([
            'subdomain_id' => $subdomain->id,
            'cloudflare_record_id' => 'record-id',
            'type' => 'A',
            'name' => $subdomain->fqdn,
            'content' => '192.0.2.1',
            'ttl' => 1,
            'proxied' => false,
        ]);

        $this->artisan('dns:purge-deletion-holds')->assertSuccessful();

        $this->assertDatabaseMissing('subdomains', ['id' => $subdomain->id]);
        $this->assertSame(0, DnsRecord::count());
        Notification::assertSentTo($user, SubdomainLifecycleNotification::class);
    }
}
