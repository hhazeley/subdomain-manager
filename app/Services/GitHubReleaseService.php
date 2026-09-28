<?php

namespace App\Services;

use App\Data\GitHubRelease;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GitHubReleaseService
{
    public function latest(): GitHubRelease
    {
        $repository = (string) config('updater.github_repository');

        if (preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository) !== 1) {
            throw new RuntimeException('The GitHub updater repository must use the owner/repository format.');
        }

        try {
            $response = $this->request()->get("/repos/{$repository}/releases/latest");

            if ($response->status() === 404) {
                throw new RuntimeException(
                    'No GitHub release has been published yet. Push a version tag matching VERSION to create the first release.',
                );
            }

            $response->throw();
        } catch (Throwable $exception) {
            throw new RuntimeException('GitHub release check failed: '.$exception->getMessage(), 0, $exception);
        }

        $release = $response->json();

        if (! is_array($release) || ! is_string($release['tag_name'] ?? null)) {
            throw new RuntimeException('GitHub returned an invalid release response.');
        }

        $version = ltrim($release['tag_name'], 'vV');

        if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version) !== 1) {
            throw new RuntimeException('The latest GitHub release does not use a supported semantic version tag.');
        }

        $assets = collect($release['assets'] ?? []);
        $package = $assets->firstWhere('name', config('updater.package_asset'));
        $checksum = $assets->firstWhere('name', config('updater.checksum_asset'));

        if (! is_array($package) || ! is_string($package['url'] ?? null)
            || ! is_array($checksum) || ! is_string($checksum['url'] ?? null)) {
            throw new RuntimeException('The latest release is missing the update package or SHA-256 checksum asset.');
        }

        $name = trim((string) ($release['name'] ?? ''));

        return new GitHubRelease(
            version: $version,
            tag: $release['tag_name'],
            name: $name !== '' ? $name : $release['tag_name'],
            notes: (string) ($release['body'] ?? ''),
            publishedAt: (string) ($release['published_at'] ?? ''),
            htmlUrl: (string) ($release['html_url'] ?? ''),
            packageUrl: $package['url'],
            checksumUrl: $checksum['url'],
        );
    }

    public function download(string $assetUrl): string
    {
        try {
            $response = $this->request('application/octet-stream')
                ->timeout(120)
                ->get($assetUrl);
            $response->throw();
        } catch (Throwable $exception) {
            throw new RuntimeException('GitHub update download failed: '.$exception->getMessage(), 0, $exception);
        }

        $body = $response->body();

        if ($body === '') {
            throw new RuntimeException('GitHub returned an empty update asset.');
        }

        if (strlen($body) > config('updater.maximum_package_bytes')) {
            throw new RuntimeException('The downloaded update asset exceeds the configured size limit.');
        }

        return $body;
    }

    private function request(string $accept = 'application/vnd.github+json'): PendingRequest
    {
        $request = Http::baseUrl((string) config('updater.github_api_url'))
            ->accept($accept)
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => config('app.name').'/'.config('app.version'),
            ])
            ->connectTimeout(10)
            ->timeout(30);

        $token = trim((string) config('updater.github_token'));

        return $token === '' ? $request : $request->withToken($token);
    }
}
