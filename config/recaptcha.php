<?php

return [
    'enabled' => filter_var(env('RECAPTCHA_ENABLED', true), FILTER_VALIDATE_BOOL),
    'site_key' => env('RECAPTCHA_SITE_KEY'),
    'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    'verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
];
