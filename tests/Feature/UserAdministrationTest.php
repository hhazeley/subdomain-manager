<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\ManagedDomain;
use App\Models\Subdomain;
use App\Models\User;
use App\Notifications\AccountApprovedNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_a_password_reset_link(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.password-reset', $user))
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_admin_can_resend_verification_to_an_unverified_user(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->unverified()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.verification', $user))
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_non_admin_cannot_send_account_emails(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $target = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('admin.users.password-reset', $target))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.users.verification', $target))
            ->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_admin_can_add_an_approved_user_and_send_setup_emails(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Managed User',
                'email' => 'MANAGED@example.com',
                'role' => User::ROLE_USER,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $user = User::query()->where('email', 'managed@example.com')->firstOrFail();

        $this->assertTrue($user->isApproved());
        $this->assertSame($admin->id, $user->approved_by);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_admin_can_approve_a_verified_user(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->unapproved()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.approve', $user))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($user->refresh()->isApproved());
        $this->assertSame($admin->id, $user->approved_by);
        Notification::assertSentTo($user, AccountApprovedNotification::class);
    }

    public function test_unverified_user_cannot_be_approved(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->unverified()->unapproved()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.approve', $user))
            ->assertSessionHasErrors('approval');

        $this->assertFalse($user->refresh()->isApproved());
        Notification::assertNothingSent();
    }

    public function test_admin_can_toggle_self_registration(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->put(route('admin.settings.registration'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse(ApplicationSetting::selfRegistrationEnabled());
    }

    public function test_admin_can_delete_a_user_without_subdomains(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_user_with_subdomains_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create();
        $domain = ManagedDomain::create([
            'name' => 'example.com',
            'cloudflare_zone_id' => str_repeat('a', 32),
            'is_active' => true,
        ]);
        Subdomain::create([
            'user_id' => $user->id,
            'managed_domain_id' => $domain->id,
            'label' => 'owned',
            'fqdn' => 'owned.example.com',
            'status' => Subdomain::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
