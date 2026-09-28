<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_verification_email_and_blocks_unverified_dashboard_access(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->isApproved());
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_needs_administrator_approval_for_service_access(): void
    {
        $user = User::factory()->unapproved()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('approval.notice'));

        $this->actingAs($user)
            ->get(route('approval.notice'))
            ->assertOk()
            ->assertSee('Account pending approval');
    }

    public function test_self_registration_can_be_disabled(): void
    {
        ApplicationSetting::setSelfRegistrationEnabled(false);

        $this->get(route('register'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('registration');

        $this->post(route('register'), [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
    }

    public function test_enabled_recaptcha_is_verified_with_google(): void
    {
        Notification::fake();
        config([
            'recaptcha.enabled' => true,
            'recaptcha.secret_key' => 'secret-key',
        ]);
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        $this->post(route('register'), [
            'name' => 'Captcha User',
            'email' => 'captcha@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'g-recaptcha-response' => 'browser-token',
        ])->assertRedirect(route('verification.notice'));

        Http::assertSent(fn ($request) => $request['secret'] === 'secret-key'
            && $request['response'] === 'browser-token');
    }
}
