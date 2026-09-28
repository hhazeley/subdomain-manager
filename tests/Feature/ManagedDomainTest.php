<?php

namespace Tests\Feature;

use App\Models\ManagedDomain;
use App\Models\Subdomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ManagedDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_identity_cannot_change_after_registrations_exist(): void
    {
        config(['services.cloudflare.api_token' => 'test-token']);
        Http::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $domain = ManagedDomain::create([
            'name' => 'example.com',
            'cloudflare_zone_id' => str_repeat('a', 32),
            'is_active' => true,
        ]);
        Subdomain::create([
            'user_id' => User::factory()->create()->id,
            'managed_domain_id' => $domain->id,
            'label' => 'customer',
            'fqdn' => 'customer.example.com',
            'status' => Subdomain::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.domains.update', $domain), [
                'name' => 'other.example',
                'cloudflare_zone_id' => str_repeat('b', 32),
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame('example.com', $domain->fresh()->name);
        Http::assertNothingSent();
    }
}
