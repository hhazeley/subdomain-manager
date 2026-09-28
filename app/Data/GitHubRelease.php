<?php

namespace App\Data;

final readonly class GitHubRelease
{
    public function __construct(
        public string $version,
        public string $tag,
        public string $name,
        public string $notes,
        public string $publishedAt,
        public string $htmlUrl,
        public string $packageUrl,
        public string $checksumUrl,
    ) {}

    public function isUpdateFor(string $currentVersion): bool
    {
        return version_compare($this->version, $currentVersion, '>');
    }
}
