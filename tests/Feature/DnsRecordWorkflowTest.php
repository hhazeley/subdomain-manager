<?php

namespace Tests\Feature;

use App\Models\DnsRecord;
use App\Models\ManagedDomain;
use App\Models\Subdomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DnsRecordWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_owner_can_create_a_scoped_cloudflare_record(): void
    {
        config(['services.cloudflare.api_token' => 'test-token']);
        Http::fake([
            'api.cloudflare.com/client/v4/zones/*/dns_records' => Http::response([
                'success' => true,
                'result' => ['id' => 'cloudflare-record-id'],
            ]),
        ]);

        [$user, $subdomain] = $this->activeSubdomain();

        $this->actingAs($user)->post(route('records.store', $subdomain), [
            'type' => 'A',
            'name' => 'www',
            'content' => '192.0.2.20',
            'ttl' => 300,
            'proxied' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('dns_records', [
            'subdomain_id' => $subdomain->id,
            'cloudflare_record_id' => 'cloudflare-record-id',
            'name' => 'www.customer.example.com',
            'content' => '192.0.2.20',
            'proxied' => true,
        ]);

        Http::assertSent(fn ($request) => $request['name'] === 'www.customer.example.com'
            && $request['type'] === 'A'
            && $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_failed_cloudflare_creation_does_not_leave_a_local_record(): void
    {
        config(['services.cloudflare.api_token' => 'test-token']);
        Http::fake([
            '*' => Http::response(['success' => false, 'errors' => [['message' => 'Invalid record']]], 400),
        ]);
        [$user, $subdomain] = $this->activeSubdomain();

        $this->actingAs($user)->post(route('records.store', $subdomain), [
            'type' => 'A',
            'name' => '@',
            'content' => 'invalid',
            'ttl' => 1,
        ])->assertSessionHasErrors('cloudflare');

        $this->assertSame(0, DnsRecord::count());
    }

    public function test_priority_is_required_only_for_record_types_that_need_it(): void
    {
        config(['services.cloudflare.api_token' => 'test-token']);
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'result' => ['id' => 'record-id'],
            ]),
        ]);
        [$user, $subdomain] = $this->activeSubdomain();

        $this->actingAs($user)->post(route('records.store', $subdomain), [
            'type' => 'MX',
            'name' => '@',
            'content' => 'mail.example.com',
            'ttl' => 300,
        ])->assertSessionHasErrors('priority');

        $this->actingAs($user)->post(route('records.store', $subdomain), [
            'type' => 'A',
            'name' => '@',
            'content' => '192.0.2.30',
            'ttl' => 300,
            'priority' => 99,
            'data' => '{"ignored":true}',
        ])->assertRedirect();

        $record = DnsRecord::firstOrFail();
        $this->assertNull($record->priority);
        $this->assertNull($record->data);
    }

    private function activeSubdomain(): array
    {
        $user = User::factory()->create();
        $domain = ManagedDomain::create([
            'name' => 'example.com',
            'cloudflare_zone_id' => str_repeat('d', 32),
            'is_active' => true,
        ]);
        $subdomain = Subdomain::create([
            'user_id' => $user->id,
            'managed_domain_id' => $domain->id,
            'label' => 'customer',
            'fqdn' => 'customer.example.com',
            'status' => Subdomain::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        return [$user, $subdomain];
    }
}
