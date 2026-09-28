<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

const INSTALLER_MINIMUM_PHP = 80300;

$basePath = dirname(__DIR__);
$lockPath = $basePath.'/storage/app/installed';
$environmentPath = $basePath.'/.env';

session_name('subdomain_manager_installer');
session_start();

function installerConfigured(string $environmentPath, string $lockPath): bool
{
    if (file_exists($lockPath)) {
        return true;
    }

    return file_exists($environmentPath)
        && preg_match('/^APP_KEY=.+$/m', (string) file_get_contents($environmentPath)) === 1;
}

function installerRequirements(string $basePath): array
{
    $extensions = [
        'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring',
        'openssl', 'pcre', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml', 'zip',
    ];
    $results = [
        'PHP 8.3 or newer' => PHP_VERSION_ID >= INSTALLER_MINIMUM_PHP,
        'Composer dependencies included' => file_exists($basePath.'/vendor/autoload.php'),
        'Production assets included' => file_exists($basePath.'/public/build/manifest.json'),
        'Application directory writable' => is_writable($basePath),
        'storage directory writable' => is_writable($basePath.'/storage'),
        'bootstrap/cache directory writable' => is_writable($basePath.'/bootstrap/cache'),
    ];

    foreach ($extensions as $extension) {
        $results["PHP extension: {$extension}"] = extension_loaded($extension);
    }

    return $results;
}

function installerEnvValue(string $value): string
{
    $escaped = str_replace(
        ["\\", '"', "\r", "\n"],
        ["\\\\", '\\"', '', '\n'],
        $value,
    );

    return '"'.$escaped.'"';
}

function installerEnvironment(array $input): string
{
    $secureCookie = str_starts_with($input['app_url'], 'https://') ? 'true' : 'false';
    $recaptchaEnabled = isset($input['recaptcha_enabled']) ? 'true' : 'false';

    $lines = [
        'APP_NAME='.installerEnvValue('Subdomain Manager'),
        'APP_ENV=production',
        'APP_KEY=base64:'.base64_encode(random_bytes(32)),
        'APP_DEBUG=false',
        'APP_URL='.installerEnvValue($input['app_url']),
        '',
        'APP_LOCALE=en',
        'APP_FALLBACK_LOCALE=en',
        'APP_FAKER_LOCALE=en_US',
        'APP_MAINTENANCE_DRIVER=file',
        'BCRYPT_ROUNDS=12',
        '',
        'LOG_CHANNEL=stack',
        'LOG_STACK=single',
        'LOG_LEVEL=warning',
        '',
        'DB_CONNECTION=mysql',
        'DB_HOST='.installerEnvValue($input['db_host']),
        'DB_PORT='.$input['db_port'],
        'DB_DATABASE='.installerEnvValue($input['db_database']),
        'DB_USERNAME='.installerEnvValue($input['db_username']),
        'DB_PASSWORD='.installerEnvValue($input['db_password']),
        '',
        'SESSION_DRIVER=database',
        'SESSION_LIFETIME=120',
        'SESSION_ENCRYPT=true',
        'SESSION_PATH=/',
        'SESSION_DOMAIN=null',
        'SESSION_SECURE_COOKIE='.$secureCookie,
        '',
        'CACHE_STORE=database',
        'QUEUE_CONNECTION=database',
        'FILESYSTEM_DISK=local',
        'BROADCAST_CONNECTION=log',
        '',
        'MAIL_MAILER=smtp',
        'MAIL_SCHEME=null',
        'MAIL_HOST='.installerEnvValue($input['mail_host']),
        'MAIL_PORT='.$input['mail_port'],
        'MAIL_USERNAME='.installerEnvValue($input['mail_username']),
        'MAIL_PASSWORD='.installerEnvValue($input['mail_password']),
        'MAIL_FROM_ADDRESS='.installerEnvValue($input['mail_from_address']),
        'MAIL_FROM_NAME='.installerEnvValue($input['mail_from_name']),
        '',
        'CLOUDFLARE_API_TOKEN='.installerEnvValue($input['cloudflare_token']),
        'CLOUDFLARE_API_URL=https://api.cloudflare.com/client/v4',
        '',
        'RECAPTCHA_ENABLED='.$recaptchaEnabled,
        'RECAPTCHA_SITE_KEY='.installerEnvValue($input['recaptcha_site_key']),
        'RECAPTCHA_SECRET_KEY='.installerEnvValue($input['recaptcha_secret_key']),
        '',
        'UPDATER_GITHUB_REPOSITORY=hhazeley/subdomain-manager',
        'UPDATER_GITHUB_TOKEN=',
        '',
        'VITE_APP_NAME='.installerEnvValue('Subdomain Manager'),
        '',
    ];

    return implode(PHP_EOL, $lines);
}

function installerValidate(array $input): array
{
    $errors = [];

    foreach ([
        'app_url', 'db_host', 'db_port', 'db_database', 'db_username',
        'admin_name', 'admin_email', 'admin_password', 'admin_password_confirmation',
        'cloudflare_token', 'mail_host', 'mail_port', 'mail_username', 'mail_password',
        'mail_from_address', 'mail_from_name', 'php_cli_path',
    ] as $field) {
        if (trim((string) ($input[$field] ?? '')) === '') {
            $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
        }
    }

    if (! filter_var($input['app_url'] ?? '', FILTER_VALIDATE_URL)) {
        $errors[] = 'Application URL must be a valid absolute URL.';
    }

    if (! filter_var($input['admin_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Administrator email must be valid.';
    }

    if (! filter_var($input['mail_from_address'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Mail sender address must be valid.';
    }

    $password = (string) ($input['admin_password'] ?? '');

    if (strlen($password) < 10 || ! preg_match('/[A-Za-z]/', $password) || ! preg_match('/\d/', $password)) {
        $errors[] = 'Administrator password must be at least 10 characters and contain letters and numbers.';
    }

    if ($password !== ($input['admin_password_confirmation'] ?? null)) {
        $errors[] = 'Administrator passwords do not match.';
    }

    if (isset($input['recaptcha_enabled'])
        && (trim((string) ($input['recaptcha_site_key'] ?? '')) === ''
            || trim((string) ($input['recaptcha_secret_key'] ?? '')) === '')) {
        $errors[] = 'Both reCAPTCHA keys are required when reCAPTCHA is enabled.';
    }

    foreach (['db_port', 'mail_port'] as $port) {
        if (filter_var($input[$port] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
            $errors[] = ucfirst(str_replace('_', ' ', $port)).' must be between 1 and 65535.';
        }
    }

    if (! str_starts_with((string) ($input['php_cli_path'] ?? ''), '/')
        || preg_match('/[\r\n;&|`$<>]/', (string) ($input['php_cli_path'] ?? ''))) {
        $errors[] = 'PHP CLI path must be an absolute path without shell operators.';
    }

    return $errors;
}

function installerTestDatabase(array $input): void
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $input['db_host'],
        (int) $input['db_port'],
        $input['db_database'],
    );

    new PDO($dsn, $input['db_username'], $input['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 8,
    ]);
}

function installerTestCloudflare(string $token): void
{
    $handle = curl_init('https://api.cloudflare.com/client/v4/user/tokens/verify');
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$token, 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

    if ($response === false) {
        throw new RuntimeException('Cloudflare connection failed: '.curl_error($handle));
    }

    $body = json_decode($response, true);

    if ($status !== 200 || ! is_array($body) || ($body['success'] ?? false) !== true) {
        $message = $body['errors'][0]['message'] ?? 'Cloudflare rejected the API token.';
        throw new RuntimeException($message);
    }
}

function installerTestSmtp(string $host, int $port): void
{
    $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 8);

    if (! is_resource($socket)) {
        throw new RuntimeException("Could not connect to Mailgun SMTP: {$errorMessage} ({$errorCode}).");
    }

    fclose($socket);
}

function installerField(array $input, string $key, string $default = ''): string
{
    return htmlspecialchars((string) ($input[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}

$requirements = installerRequirements($basePath);
$isInstalled = installerConfigured($environmentPath, $lockPath);
$errors = [];
$warnings = [];
$success = false;
$input = $_POST;

if (! isset($_SESSION['installer_csrf'])) {
    $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! $isInstalled) {
    if (! hash_equals($_SESSION['installer_csrf'], (string) ($_POST['_token'] ?? ''))) {
        $errors[] = 'The installer session expired. Refresh the page and try again.';
    }

    if (in_array(false, $requirements, true)) {
        $errors[] = 'All server requirements must pass before installation.';
    }

    $errors = [...$errors, ...installerValidate($input)];

    if ($errors === []) {
        try {
            installerTestDatabase($input);
            installerTestCloudflare($input['cloudflare_token']);
            installerTestSmtp($input['mail_host'], (int) $input['mail_port']);

            $previousEnvironment = file_exists($environmentPath)
                ? file_get_contents($environmentPath)
                : null;
            $temporaryEnvironment = $environmentPath.'.installing';

            if (file_put_contents($temporaryEnvironment, installerEnvironment($input), LOCK_EX) === false) {
                throw new RuntimeException('Could not write the temporary environment file.');
            }

            if (file_exists($environmentPath) && ! unlink($environmentPath)) {
                throw new RuntimeException('Could not replace the existing environment file.');
            }

            if (! rename($temporaryEnvironment, $environmentPath)) {
                throw new RuntimeException('Could not activate the environment file.');
            }

            @chmod($environmentPath, 0600);

            require_once $basePath.'/vendor/autoload.php';
            $app = require $basePath.'/bootstrap/app.php';
            $kernel = $app->make(Kernel::class);
            $kernel->bootstrap();

            Artisan::call('migrate', ['--force' => true]);

            User::query()->updateOrCreate(
                ['email' => strtolower($input['admin_email'])],
                [
                    'name' => $input['admin_name'],
                    'password' => Hash::make($input['admin_password']),
                    'email_verified_at' => now(),
                    'role' => User::ROLE_ADMIN,
                    'is_admin' => true,
                    'approved_at' => now(),
                ],
            );

            $mailWarning = null;

            try {
                Mail::raw(
                    'Your Subdomain Manager installation is ready.',
                    fn ($message) => $message
                        ->to($input['admin_email'])
                        ->subject('Subdomain Manager installed'),
                );
            } catch (Throwable $exception) {
                report($exception);
                $mailWarning = 'Installation succeeded, but Mailgun did not accept the test email: '.$exception->getMessage();
            }

            if (! is_dir(dirname($lockPath))) {
                mkdir(dirname($lockPath), 0775, true);
            }

            $lockWritten = file_put_contents($lockPath, json_encode([
                'installed_at' => date(DATE_ATOM),
                'app_url' => $input['app_url'],
                'php_cli_path' => $input['php_cli_path'],
                'version' => trim((string) file_get_contents($basePath.'/VERSION')),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

            if ($lockWritten === false) {
                throw new RuntimeException('Installation completed, but the installer lock could not be written.');
            }

            $warnings = array_filter([$mailWarning]);
            $success = true;
            $isInstalled = true;
        } catch (Throwable $exception) {
            if (isset($previousEnvironment)) {
                file_put_contents($environmentPath, $previousEnvironment, LOCK_EX);
            } elseif (file_exists($environmentPath)) {
                unlink($environmentPath);
            }

            $errors[] = $exception->getMessage();
        }
    }
}

$defaultUrl = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    .'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');
$defaultPath = $input['php_cli_path'] ?? '/usr/local/bin/ea-php83';

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install Subdomain Manager</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; color: #13212f; background: #f1f5f9; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px 16px; }
        main { max-width: 920px; margin: auto; }
        h1 { margin-bottom: 4px; }
        h2 { margin: 0 0 18px; font-size: 20px; }
        .card { background: white; border: 1px solid #dbe3eb; border-radius: 14px; padding: 24px; margin: 20px 0; box-shadow: 0 2px 8px #0f172a0d; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .full { grid-column: 1 / -1; }
        label { display: block; font-size: 14px; font-weight: 650; margin-bottom: 6px; }
        input, select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
        input[type=checkbox] { width: auto; margin-right: 8px; }
        button { width: 100%; border: 0; border-radius: 9px; padding: 13px 18px; color: white; background: #0369a1; font-weight: 700; cursor: pointer; }
        .ok { color: #047857; } .bad { color: #b91c1c; }
        .notice { border-radius: 9px; padding: 12px 14px; margin: 12px 0; background: #fee2e2; color: #991b1b; }
        .success { background: #dcfce7; color: #166534; }
        code { display: block; padding: 12px; border-radius: 8px; background: #0f172a; color: #e2e8f0; overflow-x: auto; margin: 8px 0; }
        small { color: #64748b; }
        @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } .full { grid-column: auto; } }
    </style>
</head>
<body>
<main>
    <h1>Subdomain Manager</h1>
    <p>cPanel installation wizard &middot; v<?= htmlspecialchars(trim((string) file_get_contents($basePath.'/VERSION'))) ?></p>

    <?php if ($isInstalled && ! $success): ?>
        <section class="card">
            <h2>Application already installed</h2>
            <p>The installer is locked. Open <a href="/">Subdomain Manager</a> or delete <code><?= htmlspecialchars($lockPath) ?></code> only if you intentionally need to reinstall.</p>
        </section>
    <?php elseif ($success): ?>
        <section class="card">
            <h2>Installation complete</h2>
            <div class="notice success">Subdomain Manager is ready. Sign in with the administrator account you created.</div>
            <?php foreach ($warnings as $warning): ?><div class="notice"><?= htmlspecialchars($warning) ?></div><?php endforeach; ?>
            <p>Add these jobs in <strong>cPanel &gt; Cron Jobs</strong>:</p>
            <code>* * * * * <?= htmlspecialchars($defaultPath) ?> <?= htmlspecialchars($basePath) ?>/artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code>
            <code>* * * * * <?= htmlspecialchars($defaultPath) ?> <?= htmlspecialchars($basePath) ?>/artisan queue:work --stop-when-empty --tries=3 &gt;&gt; /dev/null 2&gt;&amp;1</code>
            <p><a href="/">Open Subdomain Manager</a></p>
        </section>
    <?php else: ?>
        <?php foreach ($errors as $error): ?><div class="notice"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>

        <section class="card">
            <h2>Server requirements</h2>
            <div class="grid">
                <?php foreach ($requirements as $label => $passed): ?>
                    <div class="<?= $passed ? 'ok' : 'bad' ?>"><?= $passed ? 'PASS' : 'FAIL' ?> — <?= htmlspecialchars($label) ?></div>
                <?php endforeach; ?>
            </div>
        </section>

        <form method="post">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['installer_csrf']) ?>">

            <section class="card">
                <h2>Application</h2>
                <div class="grid">
                    <div><label>Application URL</label><input type="url" name="app_url" value="<?= installerField($input, 'app_url', $defaultUrl) ?>" required></div>
                    <div class="full"><label>PHP CLI path for cron jobs</label><input name="php_cli_path" value="<?= installerField($input, 'php_cli_path', '/usr/local/bin/ea-php83') ?>" required><small>Common cPanel value: /usr/local/bin/ea-php83</small></div>
                </div>
            </section>

            <section class="card">
                <h2>MySQL database</h2>
                <div class="grid">
                    <div><label>Host</label><input name="db_host" value="<?= installerField($input, 'db_host', 'localhost') ?>" required></div>
                    <div><label>Port</label><input type="number" name="db_port" value="<?= installerField($input, 'db_port', '3306') ?>" required></div>
                    <div><label>Database</label><input name="db_database" value="<?= installerField($input, 'db_database') ?>" required></div>
                    <div><label>Username</label><input name="db_username" value="<?= installerField($input, 'db_username') ?>" required></div>
                    <div class="full"><label>Password</label><input type="password" name="db_password" required></div>
                </div>
            </section>

            <section class="card">
                <h2>First administrator</h2>
                <div class="grid">
                    <div><label>Name</label><input name="admin_name" value="<?= installerField($input, 'admin_name') ?>" required></div>
                    <div><label>Email</label><input type="email" name="admin_email" value="<?= installerField($input, 'admin_email') ?>" required></div>
                    <div><label>Password</label><input type="password" name="admin_password" required></div>
                    <div><label>Confirm password</label><input type="password" name="admin_password_confirmation" required></div>
                </div>
            </section>

            <section class="card">
                <h2>Cloudflare and Google reCAPTCHA</h2>
                <div class="grid">
                    <div class="full"><label>Cloudflare API token</label><input type="password" name="cloudflare_token" required></div>
                    <div class="full"><label><input type="checkbox" name="recaptcha_enabled" value="1" <?= isset($input['recaptcha_enabled']) || $input === [] ? 'checked' : '' ?>>Enable Google reCAPTCHA v2</label></div>
                    <div><label>reCAPTCHA site key</label><input name="recaptcha_site_key" value="<?= installerField($input, 'recaptcha_site_key') ?>"></div>
                    <div><label>reCAPTCHA secret key</label><input type="password" name="recaptcha_secret_key"></div>
                </div>
            </section>

            <section class="card">
                <h2>Mailgun SMTP</h2>
                <div class="grid">
                    <div><label>SMTP host</label><input name="mail_host" value="<?= installerField($input, 'mail_host', 'smtp.mailgun.org') ?>" required></div>
                    <div><label>SMTP port</label><input type="number" name="mail_port" value="<?= installerField($input, 'mail_port', '2525') ?>" required></div>
                    <div><label>SMTP username</label><input name="mail_username" value="<?= installerField($input, 'mail_username') ?>" required></div>
                    <div><label>SMTP password</label><input type="password" name="mail_password" required></div>
                    <div><label>From address</label><input type="email" name="mail_from_address" value="<?= installerField($input, 'mail_from_address') ?>" required></div>
                    <div><label>From name</label><input name="mail_from_name" value="<?= installerField($input, 'mail_from_name', 'Subdomain Manager') ?>" required></div>
                </div>
            </section>

            <button type="submit" <?= in_array(false, $requirements, true) ? 'disabled' : '' ?>>Install Subdomain Manager</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
