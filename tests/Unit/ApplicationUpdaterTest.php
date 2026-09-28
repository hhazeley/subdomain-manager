<?php

namespace Tests\Unit;

use App\Data\GitHubRelease;
use App\Services\ApplicationUpdater;
use App\Services\GitHubReleaseService;
use Illuminate\Support\Facades\File;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class ApplicationUpdaterTest extends TestCase
{
    private string $temporaryPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryPath = sys_get_temp_dir().'/subdomain-manager-updater-'.bin2hex(random_bytes(5));
        mkdir($this->temporaryPath, 0775, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryPath);

        parent::tearDown();
    }

    public function test_package_must_match_the_published_checksum(): void
    {
        $releases = Mockery::mock(GitHubReleaseService::class);
        $releases->shouldReceive('download')->once()->with('package-url')->andReturn('not-a-zip');
        $releases->shouldReceive('download')->once()->with('checksum-url')->andReturn(str_repeat('a', 64));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('failed SHA-256 verification');

        $this->updater($releases)->install($this->release());
    }

    public function test_package_cannot_overwrite_the_environment_file(): void
    {
        $package = $this->zip(['.env' => 'APP_KEY=unsafe']);
        $releases = Mockery::mock(GitHubReleaseService::class);
        $releases->shouldReceive('download')->once()->with('package-url')->andReturn($package);
        $releases->shouldReceive('download')->once()->with('checksum-url')->andReturn(hash('sha256', $package));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsafe path: .env');

        $this->updater($releases)->install($this->release());
    }

    public function test_package_cannot_traverse_outside_the_application_directory(): void
    {
        $package = $this->zip(['../outside.php' => 'unsafe']);
        $releases = Mockery::mock(GitHubReleaseService::class);
        $releases->shouldReceive('download')->once()->with('package-url')->andReturn($package);
        $releases->shouldReceive('download')->once()->with('checksum-url')->andReturn(hash('sha256', $package));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsafe path');

        $this->updater($releases)->install($this->release());
    }

    private function updater(GitHubReleaseService $releases): ApplicationUpdater
    {
        return new ApplicationUpdater(
            $releases,
            $this->temporaryPath.'/application',
            $this->temporaryPath.'/updates',
        );
    }

    private function release(): GitHubRelease
    {
        return new GitHubRelease(
            version: '1.2.0',
            tag: 'v1.2.0',
            name: 'Version 1.2.0',
            notes: '',
            publishedAt: '2026-09-28T00:00:00Z',
            htmlUrl: 'https://github.com/hhazeley/subdomain-manager/releases/tag/v1.2.0',
            packageUrl: 'package-url',
            checksumUrl: 'checksum-url',
        );
    }

    /**
     * @param  array<string, string>  $files
     */
    private function zip(array $files): string
    {
        $path = $this->temporaryPath.'/fixture-'.bin2hex(random_bytes(4)).'.zip';
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE);

        foreach ($files as $name => $contents) {
            $archive->addFromString($name, $contents);
        }

        $archive->close();

        return (string) file_get_contents($path);
    }
}
