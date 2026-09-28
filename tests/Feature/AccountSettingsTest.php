<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_account_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.edit'))
            ->assertOk()
            ->assertSee('Account settings')
            ->assertSee($user->email);
    }

    public function test_user_can_update_password_with_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CurrentPassword123'),
        ]);
        $oldRememberToken = $user->remember_token;

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'CurrentPassword123',
                'password' => 'UpdatedPassword456',
                'password_confirmation' => 'UpdatedPassword456',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $user->refresh();

        $this->assertTrue(Hash::check('UpdatedPassword456', $user->password));
        $this->assertNotSame($oldRememberToken, $user->remember_token);
    }

    public function test_password_update_requires_the_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CurrentPassword123'),
        ]);

        $this->actingAs($user)
            ->from(route('account.edit'))
            ->put(route('account.password.update'), [
                'current_password' => 'IncorrectPassword123',
                'password' => 'UpdatedPassword456',
                'password_confirmation' => 'UpdatedPassword456',
            ])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('CurrentPassword123', $user->refresh()->password));
    }

    public function test_user_can_request_a_password_reset_from_account_settings(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('account.password-reset'))
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_user_can_update_email_and_must_verify_the_new_address(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => Hash::make('CurrentPassword123'),
        ]);

        $this->actingAs($user)
            ->put(route('account.email.update'), [
                'email' => 'NEW@example.com',
                'email_current_password' => 'CurrentPassword123',
            ])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('success');

        $user->refresh();

        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_email_update_requires_a_unique_address_and_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'current@example.com',
            'password' => Hash::make('CurrentPassword123'),
        ]);
        User::factory()->create(['email' => 'used@example.com']);

        $this->actingAs($user)
            ->from(route('account.edit'))
            ->put(route('account.email.update'), [
                'email' => 'used@example.com',
                'email_current_password' => 'IncorrectPassword123',
            ])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHasErrors(['email', 'email_current_password']);

        $this->assertSame('current@example.com', $user->refresh()->email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_guests_cannot_access_account_settings(): void
    {
        $this->get(route('account.edit'))->assertRedirect(route('login'));
        $this->put(route('account.password.update'))->assertRedirect(route('login'));
        $this->post(route('account.password-reset'))->assertRedirect(route('login'));
        $this->put(route('account.email.update'))->assertRedirect(route('login'));
    }
}
