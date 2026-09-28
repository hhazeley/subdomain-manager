<?php

return [
    'github_repository' => env('UPDATER_GITHUB_REPOSITORY', 'hhazeley/subdomain-manager'),
    'github_token' => env('UPDATER_GITHUB_TOKEN'),
    'github_api_url' => env('UPDATER_GITHUB_API_URL', 'https://api.github.com'),
    'package_asset' => env('UPDATER_PACKAGE_ASSET', 'Subdomain-Manager-cPanel.zip'),
    'checksum_asset' => env('UPDATER_CHECKSUM_ASSET', 'Subdomain-Manager-cPanel.zip.sha256'),
    'maximum_package_bytes' => (int) env('UPDATER_MAXIMUM_PACKAGE_BYTES', 104857600),
];
