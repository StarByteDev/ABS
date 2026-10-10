<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** FCM HTTP v1: OAuth2 service account, no legacy FCM server key. */
class PulseFirebasePushService
{
    public function configured(): bool
    {
        $path = (string) config('services.firebase.service_account_path');
        return $path !== '' && is_readable($path);
    }

    private function credentials(): array
    {
        $path = (string) config('services.firebase.service_account_path');
        if ($path === '' || ! is_readable($path)) throw new RuntimeException('Firebase service account path is missing/unreadable.');
        $creds = json_decode((string) file_get_contents($path), true);
        if (! is_array($creds) || ($creds['type'] ?? '') !== 'service_account'
            || empty($creds['client_email']) || empty($creds['private_key']) || empty($creds['project_id'])) {
            throw new RuntimeException('Invalid Firebase service account JSON.');
        }
        return $creds;
    }

    private function accessToken(array $credentials): string
    {
        $key = 'abs:fcm:token:'.sha1((string) $credentials['client_email']);
        return Cache::remember($key, now()->addMinutes(45), function () use ($credentials): string {
            $now = time();
            $encode = static fn (array $data) => rtrim(strtr(base64_encode(json_encode($data, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
            $signingInput = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]);
            $signature = '';
            if (! openssl_sign($signingInput, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Unable to sign Firebase OAuth request.');
            }
            $jwt = $signingInput.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
            $response = Http::asForm()->timeout(12)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt,
            ]);
            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw new RuntimeException('Firebase OAuth token request failed (HTTP '.$response->status().').');
            }
            return $response->json('access_token');
        });
    }

    public function send(string $token, string $title, string $body, array $data): array
    {
        $credentials = $this->credentials();
        $project = (string) (config('services.firebase.project_id') ?: $credentials['project_id']);
        if (! preg_match('/^[a-zA-Z0-9-]+$/', $project)) throw new RuntimeException('Invalid Firebase project ID.');
        $response = Http::withToken($this->accessToken($credentials))->acceptJson()->timeout(8)
            ->post('https://fcm.googleapis.com/v1/projects/'.$project.'/messages:send', [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data),
                    'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'pulse_macro', 'sound' => 'default']],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ]);
        $error = $response->json('error');
        $code = (string) (is_array($error) ? ($error['status'] ?? 'HTTP_'.$response->status()) : 'HTTP_'.$response->status());
        $details = is_array($error) ? ($error['details'] ?? []) : [];
        $unregistered = $code === 'UNREGISTERED';
        foreach (is_array($details) ? $details : [] as $detail) {
            if (($detail['errorCode'] ?? '') === 'UNREGISTERED') $unregistered = true;
        }
        // INVALID_ARGUMENT is not always an invalid token; only treat the
        // explicit FCM error detail as invalid so we never wrongly discard tokens.
        $invalidToken = $unregistered;
        foreach (is_array($details) ? $details : [] as $detail) {
            if (($detail['errorCode'] ?? '') === 'INVALID_ARGUMENT') $invalidToken = true;
        }
        return ['ok' => $response->successful(), 'invalid_token' => $invalidToken, 'error' => $code];
    }
}
