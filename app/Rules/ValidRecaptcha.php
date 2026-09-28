<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ValidRecaptcha implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! config('recaptcha.enabled')) {
            return;
        }

        $secret = config('recaptcha.secret_key');

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('Google reCAPTCHA is enabled but its secret key is not configured.');
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->retry(2, 250)
                ->post(config('recaptcha.verify_url'), [
                    'secret' => $secret,
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            report($exception);
            $fail('Google reCAPTCHA could not be reached. Please try again.');

            return;
        }

        if (! is_array($response) || ($response['success'] ?? false) !== true) {
            $fail('Google reCAPTCHA verification failed. Please try again.');
        }
    }
}
