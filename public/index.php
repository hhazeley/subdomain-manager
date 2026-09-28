<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// A fresh cPanel upload has no application key yet and must be initialized
// before Laravel can use database-backed sessions.
$environmentFile = __DIR__.'/../.env';
$installed = file_exists(__DIR__.'/../storage/app/installed');
$configured = file_exists($environmentFile)
    && preg_match('/^APP_KEY=.+$/m', (string) file_get_contents($environmentFile)) === 1;

if (! $installed && ! $configured) {
    header('Location: /install.php');
    exit;
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
