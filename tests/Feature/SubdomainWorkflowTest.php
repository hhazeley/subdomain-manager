<?php

namespace Tests\Feature;

use App\Models\BlockedSubdomain;
use App\Models\ManagedDomain;
use App\Models\Subdomain;
use App\Models\User;
use App\Notifications\SubdomainLifecycleNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubdomainWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_and_admin_can_approve_a_subdomain(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $domain = ManagedDomain::create([
            'name' => 'example.com',
            'cloudflare_zone_id' => str_repeat('a', 32),
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('subdomains.store'), [
            'managed_domain_id' => $domain->id,
            'label' => 'My-Project',
        ])->assertRedirect();

        $subdomain = Subdomain::firstOrFail();
        $this->assertSame('my-project.example.com', $subdomain->fqdn);
        $this->assertSame(Subdomain::STATUS_PENDING, $subdomain->status);

        $this->actingAs($admin)
            ->post(route('admin.registrations.approve', $subdomain))
            ->assertRedirect();

        $subdomain->refresh();
        $this->assertSame(Subdomain::STATUS_ACTIVE, $subdomain->status);
        $this->assertSame($admin->id, $subdomain->approved_by);
        $this->assertNotNull($subdomain->expires_at);
    }

    public function test_rejected_registration_is_immediately_deleted_after_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $approver = User::factory()->create(['role' => User::ROLE_APPROVER]);
        $domain = ManagedDomain::create([
            'name' => 'example.com',
            'cloudflare_zone_id' => str_repeat('9', 32),
            'is_active' => true,
        ]);
        $subdomain = Subdomain::create([
            'user_id' => $user->id,
            'managed_domain_id' => $domain->id,
            'label' => 'rejected',
            'fqdn' => 'rejected.example.com',
            'status' => Subdomain::STATUS_PENDING,
        ]);

        $this->actingAs($approver)
            ->post(route('admin.registrations.reject', $subdomain), ['reason' => 'Not eligible'])
            ->assertRedirect();

        $this->assertDatabaseMissing('subdomains', ['id' => $subdomain->id]);
        Notification::assertSentTo(
            $user,
            SubdomainLifecycleNotification::class,
            fn ($notification) => $notification->event === 'rejected'
                && $notification->reason === 'Not eligible',
        );
    }

    public function test_only_admins_can_access_approval_queue(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.registrations.index'))
            ->assertForbidden();
    }

    public function test_approval_admin_can_only_access_registration_approval_routes(): void
    {
        $approver = User::factory()->create(['role' => User::ROLE_APPROVER]);

        $this->actingAs($approver)
            ->get(route('admin.registrations.index'))
            ->assertOk();

        $this->actingAs($approver)
            ->get(route('admin.domains.index'))
            ->assertForbidden();

        $this->actingAs($approver)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_user_cannot_view_another_users_subdomain(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $domain = ManagedDomain::create([
            'name' => 'example.com',
            'cloudflare_zone_id' => str_repeat('b', 32),
            'is_active' => true,
        ]);
        $subdomain = Subdomain::create([
            'user_id' => $owner->id,
            'managed_domain_id' => $domain->id,
            'label' => 'private',
            'fqdn' => 'private.example.com',
            'status' => Subdomain::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        $this->actingAs($otherUser)
            ->get(route('subdomains.show', $subdomain))
            ->assertForbidden();
    }

    public function test_renewal_is_auto_approved_only_in_final_45_days(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $domain = ManagedDomain::create([
            'name' => 'example.com',
            'cloudflare_zone_id' => str_repeat('c', 32),
            'is_active' => true,
        ]);
        $subdomain = Subdomain::create([
            'user_id' => $user->id,
            'managed_domain_id' => $domain->id,
            'label' => 'renewable',
            'fqdn' => 'renewable.example.com',
            'status' => Subdomain::STATUS_ACTIVE,
            'expires_at' => now()->addDays(44),
        ]);
        $originalExpiration = $subdomain->expires_at->copy();

        $this->actingAs($user)
            ->post(route('subdomains.renew', $subdomain))
            ->assertRedirect();

        $this->assertTrue($subdomain->fresh()->expires_at->equalTo($originalExpiration->addYear()));
        $this->assertSame(Subdomain::STATUS_ACTIVE, $subdomain->fresh()->status);
    }

    public function test_renewal_is_rejected_before_final_45_days(): void
    {
        $user = User::factory()->create();
        $domain = ManagedDomain::create([
            'name' => 'example.com',
            'cloudflare_zone_id' => str_repeat('f', 32),
            'is_active' => true,
        ]);
        $subdomain = Subdomain::create([
            'user_id' => $user->id,
            'managed_domain_id' => $domain->id,
            'label' => 'too-early',
            'fqdn' => 'too-early.example.com',
            'status' => Subdomain::STATUS_ACTIVE,
            'expires_at' => now()->addDays(46),
        ]);
        $originalExpiration = $subdomain->expires_at->copy();

        $this->actingAs($user)
            ->post(route('subdomains.renew', $subdomain))
            ->assertSessionHasErrors('subdomain');

        $this->assertTrue($subdomain->fresh()->expires_at->equalTo($originalExpiration));
    }

    public function test_blocked_and_registered_names_cannot_be_requested(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create();
        $domain = ManagedDomain::create([
            'name' => 'example.com',
            'cloudflare_zone_id' => str_repeat('1', 32),
            'is_active' => true,
        ]);
        BlockedSubdomain::create([
            'managed_domain_id' => $domain->id,
            'label' => 'reserved',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($user)->post(route('subdomains.store'), [
            'managed_domain_id' => $domain->id,
            'label' => 'Reserved',
        ])->assertSessionHasErrors('label');

        Subdomain::create([
            'user_id' => $user->id,
            'managed_domain_id' => $domain->id,
            'label' => 'taken',
            'fqdn' => 'taken.example.com',
            'status' => Subdomain::STATUS_PENDING,
        ]);

        $this->actingAs(User::factory()->create())->post(route('subdomains.store'), [
            'managed_domain_id' => $domain->id,
            'label' => 'TAKEN',
        ])->assertSessionHasErrors('label');
    }

    public function test_user_deletion_is_hidden_and_admin_can_restore_it(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $domain = ManagedDomain::create([
            'name' => 'example.com',
            'cloudflare_zone_id' => str_repeat('2', 32),
            'is_active' => true,
        ]);
        $subdomain = Subdomain::create([
            'user_id' => $user->id,
            'managed_domain_id' => $domain->id,
            'label' => 'held',
            'fqdn' => 'held.example.com',
            'status' => Subdomain::STATUS_ACTIVE,
            'expires_at' => now()->addMonths(6),
        ]);

        $this->actingAs($user)
            ->delete(route('subdomains.destroy', $subdomain))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(Subdomain::STATUS_DELETION_HOLD, $subdomain->fresh()->status);
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('held.example.com');

        $this->actingAs($admin)
            ->post(route('admin.registrations.restore', $subdomain))
            ->assertRedirect();

        $this->assertSame(Subdomain::STATUS_ACTIVE, $subdomain->fresh()->status);
    }
}
