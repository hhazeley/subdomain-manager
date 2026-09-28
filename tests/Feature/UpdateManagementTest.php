<?php

namespace Tests\Feature;

use App\Data\GitHubRelease;
use App\Models\User;
use App\Services\ApplicationUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_check_the_latest_github_release(): void
    {
        $this->fakeLatestRelease('v1.2.0');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.updates.index'))
            ->assertOk()
            ->assertSee('Application updates')
            ->assertSee('v1.1.1')
            ->assertSee('v1.2.0')
            ->assertSee('Install verified update');

        Http::assertSent(fn ($request) => $request->url()
            === 'https://api.github.com/repos/hhazeley/subdomain-manager/releases/latest');
    }

    public function test_admin_can_install_the_release_they_reviewed(): void
    {
        $this->fakeLatestRelease('v1.2.0');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->mock(ApplicationUpdater::class, function (MockInterface $mock): void {
            $mock->shouldReceive('install')
                ->once()
                ->withArgs(fn (GitHubRelease $release) => $release->version === '1.2.0');
        });

        $this->actingAs($admin)
            ->post(route('admin.updates.install'), ['version' => '1.2.0'])
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('success');
    }

    public function test_install_is_rejected_if_the_latest_release_changed(): void
    {
        $this->fakeLatestRelease('v1.3.0');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->mock(ApplicationUpdater::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('install');
        });

        $this->actingAs($admin)
            ->from(route('admin.updates.index'))
            ->post(route('admin.updates.install'), ['version' => '1.2.0'])
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHasErrors('update');
    }

    public function test_non_admin_cannot_access_application_updates(): void
    {
        Http::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.updates.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.updates.install'), ['version' => '1.2.0'])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    private function fakeLatestRelease(string $tag): void
    {
        Http::fake([
            'api.github.com/repos/hhazeley/subdomain-manager/releases/latest' => Http::response([
                'tag_name' => $tag,
                'name' => "Subdomain Manager {$tag}",
                'body' => 'Release notes',
                'published_at' => '2026-09-28T00:00:00Z',
                'html_url' => "https://github.com/hhazeley/subdomain-manager/releases/tag/{$tag}",
                'assets' => [
                    [
                        'name' => 'Subdomain-Manager-cPanel.zip',
                        'url' => 'https://api.github.com/repos/hhazeley/subdomain-manager/releases/assets/1',
                    ],
                    [
                        'name' => 'Subdomain-Manager-cPanel.zip.sha256',
                        'url' => 'https://api.github.com/repos/hhazeley/subdomain-manager/releases/assets/2',
                    ],
                ],
            ]),
        ]);
    }
}
