<?php

namespace App\Security;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Verifies short-lived Google reCAPTCHA v3 tokens against one expected action.
 */
final class RecaptchaV3Verifier
{
    /**
     * Reports whether server-side reCAPTCHA enforcement is enabled.
     */
    public function enabled(): bool
    {
        return (bool) config('services.recaptcha.enabled', false);
    }

    /**
     * Verifies success, action, score and optional hostname without logging the token.
     */
    public function verify(string $token, string $expectedAction, ?string $ipAddress): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        $secretKey = config('services.recaptcha.secret_key');
        $verifyUrl = config('services.recaptcha.verify_url');

        if (! is_string($secretKey) || $secretKey === '' || ! is_string($verifyUrl) || $verifyUrl === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('services.recaptcha.timeout_seconds', 5))
                ->post($verifyUrl, [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $ipAddress,
                ]);
        } catch (ConnectionException) {
            return false;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            return false;
        }

        $action = $response->json('action');
        $score = $response->json('score');

        if (! is_string($action) || ! hash_equals($expectedAction, $action)) {
            return false;
        }

        if (! is_numeric($score) || (float) $score < (float) config('services.recaptcha.minimum_score', 0.5)) {
            return false;
        }

        $expectedHostname = config('services.recaptcha.hostname');

        if (is_string($expectedHostname) && $expectedHostname !== '') {
            $hostname = $response->json('hostname');

            if (! is_string($hostname) || ! hash_equals($expectedHostname, $hostname)) {
                return false;
            }
        }

        return true;
    }
}
