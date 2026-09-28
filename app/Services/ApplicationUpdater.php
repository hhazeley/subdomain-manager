<?php

namespace App\Services;

use App\Data\GitHubRelease;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

class ApplicationUpdater
{
    private string $basePath;

    private string $updatesPath;

    public function __construct(
        private readonly GitHubReleaseService $releases,
        ?string $basePath = null,
        ?string $updatesPath = null,
    ) {
        $this->basePath = $basePath ?? base_path();
        $this->updatesPath = $updatesPath ?? storage_path('app/updates');
    }

    public function install(GitHubRelease $release): void
    {
        if (! $release->isUpdateFor((string) config('app.version'))) {
            throw new RuntimeException('The selected release is not newer than the installed version.');
        }

        $this->ensureDirectory($this->updatesPath);
        $lock = fopen($this->updatesPath.'/.lock', 'c+');

        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another application update is already running.');
        }

        try {
            $this->installLocked($release);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function installLocked(GitHubRelease $release): void
    {
        $workspace = $this->updatesPath.'/'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
        $packagePath = $workspace.'/package.zip';
        $stagingPath = $workspace.'/staging';
        $backupPath = $workspace.'/backup';

        $this->ensureDirectory($workspace);
        $package = $this->releases->download($release->packageUrl);
        $checksum = $this->parseChecksum($this->releases->download($release->checksumUrl));

        if (! hash_equals($checksum, hash('sha256', $package))) {
            throw new RuntimeException('The update package failed SHA-256 verification.');
        }

        if (file_put_contents($packagePath, $package, LOCK_EX) === false) {
            throw new RuntimeException('The update package could not be saved.');
        }

        $this->extractPackage($packagePath, $stagingPath);
        $this->validateStaging($stagingPath, $release);

        $files = $this->stagedFiles($stagingPath);
        $this->preflight($files);
        $this->ensureDirectory($backupPath);

        $applied = [];
        $maintenanceEnabled = false;
        $migrationsStarted = false;

        try {
            if (Artisan::call('down', ['--retry' => 60]) !== 0) {
                throw new RuntimeException('The application could not enter maintenance mode.');
            }

            $maintenanceEnabled = true;

            foreach ($files as $relative => $source) {
                $destination = $this->basePath.'/'.$relative;
                $backup = $backupPath.'/'.$relative;
                $existed = is_file($destination);

                if ($existed) {
                    $this->ensureDirectory(dirname($backup));

                    if (! copy($destination, $backup)) {
                        throw new RuntimeException("Could not back up {$relative}.");
                    }
                }

                $this->ensureDirectory(dirname($destination));
                $temporary = $destination.'.update-'.bin2hex(random_bytes(4));

                if (! copy($source, $temporary)) {
                    throw new RuntimeException("Could not stage {$relative}.");
                }

                if (! @rename($temporary, $destination)) {
                    if (is_file($destination) && ! unlink($destination)) {
                        @unlink($temporary);
                        throw new RuntimeException("Could not replace {$relative}.");
                    }

                    if (! rename($temporary, $destination)) {
                        @unlink($temporary);
                        throw new RuntimeException("Could not install {$relative}.");
                    }
                }

                $applied[] = ['relative' => $relative, 'existed' => $existed];
            }

            $migrationsStarted = true;

            if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                throw new RuntimeException('Database migrations failed: '.trim(Artisan::output()));
            }

            if (Artisan::call('optimize:clear') !== 0) {
                throw new RuntimeException('Application caches could not be cleared: '.trim(Artisan::output()));
            }

            $this->recordSuccess($release, $workspace);
            @unlink($packagePath);
            File::deleteDirectory($stagingPath);

            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
        } catch (Throwable $exception) {
            if (! $migrationsStarted) {
                $rollbackError = $this->rollback($applied, $backupPath);

                throw new RuntimeException(
                    'Update failed and application files were restored: '.$exception->getMessage().$rollbackError,
                    0,
                    $exception,
                );
            }

            throw new RuntimeException(
                'The update files were installed, but a post-install step failed. Files were left in place to remain compatible with any database migrations that completed. '
                .$exception->getMessage()." Backup: {$backupPath}",
                0,
                $exception,
            );
        } finally {
            if ($maintenanceEnabled) {
                Artisan::call('up');
            }
        }
    }

    private function parseChecksum(string $contents): string
    {
        if (preg_match('/\b([a-f0-9]{64})\b/i', $contents, $matches) !== 1) {
            throw new RuntimeException('The release checksum asset is invalid.');
        }

        return strtolower($matches[1]);
    }

    private function extractPackage(string $packagePath, string $stagingPath): void
    {
        $archive = new ZipArchive;

        if ($archive->open($packagePath) !== true) {
            throw new RuntimeException('The downloaded update package is not a valid ZIP archive.');
        }

        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = str_replace('\\', '/', (string) $archive->getNameIndex($index));
                $isDirectory = str_ends_with($name, '/');
                $segments = explode('/', rtrim($name, '/'));

                if ($name === '' || str_starts_with($name, '/') || str_contains($name, '../')
                    || in_array('..', $segments, true) || str_contains($name, ':')
                    || (! $isDirectory && $this->isProtectedPath(rtrim($name, '/')))) {
                    throw new RuntimeException("The update package contains an unsafe path: {$name}");
                }

                $archive->getExternalAttributesIndex($index, $operatingSystem, $attributes);
                $fileType = ($attributes >> 16) & 0170000;

                if ($operatingSystem === ZipArchive::OPSYS_UNIX && $fileType === 0120000) {
                    throw new RuntimeException("The update package contains a symbolic link: {$name}");
                }
            }

            $this->ensureDirectory($stagingPath);

            if (! $archive->extractTo($stagingPath)) {
                throw new RuntimeException('The update package could not be extracted.');
            }
        } finally {
            $archive->close();
        }
    }

    private function validateStaging(string $stagingPath, GitHubRelease $release): void
    {
        foreach (['VERSION', 'artisan', 'vendor/autoload.php', 'public/index.php'] as $required) {
            if (! is_file($stagingPath.'/'.$required)) {
                throw new RuntimeException("The update package is missing {$required}.");
            }
        }

        $packageVersion = trim((string) file_get_contents($stagingPath.'/VERSION'));

        if ($packageVersion !== $release->version) {
            throw new RuntimeException('The package version does not match the GitHub release tag.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function stagedFiles(string $stagingPath): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stagingPath, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($stagingPath) + 1));
            $files[$relative] = $file->getPathname();
        }

        uksort($files, static function (string $left, string $right): int {
            if ($left === 'VERSION') {
                return 1;
            }

            if ($right === 'VERSION') {
                return -1;
            }

            return $left <=> $right;
        });

        return $files;
    }

    /**
     * @param  array<string, string>  $files
     */
    private function preflight(array $files): void
    {
        foreach ($files as $relative => $source) {
            if (! is_readable($source)) {
                throw new RuntimeException("The staged file {$relative} is not readable.");
            }

            $destination = $this->basePath.'/'.$relative;

            if (is_file($destination) && ! is_writable($destination)) {
                throw new RuntimeException("The application file {$relative} is not writable.");
            }

            $parent = dirname($destination);

            while (! is_dir($parent) && $parent !== dirname($parent)) {
                $parent = dirname($parent);
            }

            if (! is_writable($parent)) {
                throw new RuntimeException("The destination directory for {$relative} is not writable.");
            }
        }
    }

    /**
     * @param  array<int, array{relative: string, existed: bool}>  $applied
     */
    private function rollback(array $applied, string $backupPath): string
    {
        $failures = [];

        foreach (array_reverse($applied) as $file) {
            $destination = $this->basePath.'/'.$file['relative'];
            $backup = $backupPath.'/'.$file['relative'];

            if ($file['existed']) {
                if (! copy($backup, $destination)) {
                    $failures[] = $file['relative'];
                }
            } elseif (is_file($destination) && ! unlink($destination)) {
                $failures[] = $file['relative'];
            }
        }

        return $failures === []
            ? ''
            : ' Manual recovery is required for: '.implode(', ', $failures).'.';
    }

    private function recordSuccess(GitHubRelease $release, string $workspace): void
    {
        $historyPath = $this->updatesPath.'/history.json';
        $history = [];

        if (is_file($historyPath)) {
            $decoded = json_decode((string) file_get_contents($historyPath), true);
            $history = is_array($decoded) ? $decoded : [];
        }

        $history[] = [
            'version' => $release->version,
            'installed_at' => now()->toAtomString(),
            'backup_path' => $workspace.'/backup',
        ];

        if (file_put_contents(
            $historyPath,
            json_encode(array_slice($history, -10), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX,
        ) === false) {
            throw new RuntimeException('The update completed, but update history could not be recorded.');
        }
    }

    private function isProtectedPath(string $path): bool
    {
        return $path === '.env'
            || (str_starts_with($path, '.env.') && $path !== '.env.example')
            || $path === 'auth.json'
            || str_starts_with($path, 'database/database.sqlite')
            || (str_starts_with($path, 'storage/') && basename($path) !== '.gitignore')
            || str_starts_with($path, '.git/')
            || str_starts_with($path, 'node_modules/')
            || str_starts_with($path, 'tests/');
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException("Could not create directory {$path}.");
        }
    }
}
