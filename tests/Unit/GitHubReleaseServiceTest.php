<?php

namespace Tests\Unit;

use App\Services\GitHubReleaseService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GitHubReleaseServiceTest extends TestCase
{
    public function test_release_must_include_the_package_and_checksum_assets(): void
    {
        Http::fake([
            'api.github.com/repos/hhazeley/subdomain-manager/releases/latest' => Http::response([
                'tag_name' => 'v1.1.0',
                'name' => 'Subdomain Manager v1.1.0',
                'assets' => [],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing the update package or SHA-256 checksum');

        app(GitHubReleaseService::class)->latest();
    }

    public function test_release_tag_must_be_a_semantic_version(): void
    {
        Http::fake([
            'api.github.com/repos/hhazeley/subdomain-manager/releases/latest' => Http::response([
                'tag_name' => 'latest',
                'assets' => [],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('semantic version');

        app(GitHubReleaseService::class)->latest();
    }
}
