<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Minimal Firebase Cloud Messaging HTTP v1 client. Authenticates with a
 * service-account key (RS256 JWT -> OAuth2 access token, cached ~50 min) and
 * needs no extra Composer dependency.
 */
class FcmClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_CACHE_KEY = 'abs:fcm:access-token';

    private ?array $credentials = null;

    public function isConfigured(): bool
    {
        try {
            $this->credentials();
            return $this->projectId() !== '';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Sends one message. $target is ['topic' => name] or ['token' => fcm token].
     *
     * @return array{ok:bool,status:int,error:?string,unregistered:bool}
     */
    public function send(array $target, string $title, string $body, array $data): array
    {
        $message = $target + [
            'notification' => ['title' => $title, 'body' => $body],
            // FCM data values must be strings.
            'data' => array_map(static fn ($value): string => is_scalar($value) ? (string) $value : (string) json_encode($value), $data),
            'android' => [
                'priority' => 'high',
                'notification' => ['channel_id' => 'pulse_alerts'],
            ],
            'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
        ];

        try {
            $response = Http::withToken($this->accessToken())
                ->timeout((int) config('services.fcm.timeout', 10))
                ->acceptJson()
                ->post('https://fcm.googleapis.com/v1/projects/'.$this->projectId().'/messages:send', ['message' => $message]);
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => 0, 'error' => $e->getMessage(), 'unregistered' => false];
        }

        if ($response->successful()) {
            return ['ok' => true, 'status' => $response->status(), 'error' => null, 'unregistered' => false];
        }

        $status = (string) data_get($response->json(), 'error.status', '');
        $errorCode = collect((array) data_get($response->json(), 'error.details', []))
            ->pluck('errorCode')->filter()->first();
        if ($response->status() === 401) Cache::forget(self::TOKEN_CACHE_KEY);

        return [
            'ok' => false,
            'status' => $response->status(),
            'error' => trim(($errorCode ?: $status).' '.(string) data_get($response->json(), 'error.message', 'FCM request failed.')),
            // The token no longer belongs to an installed app instance.
            'unregistered' => isset($target['token']) && ($errorCode === 'UNREGISTERED' || $response->status() === 404),
        ];
    }

    private function projectId(): string
    {
        $configured = trim((string) config('services.fcm.project_id', ''));
        return $configured !== '' ? $configured : (string) ($this->credentials()['project_id'] ?? '');
    }

    private function credentials(): array
    {
        if ($this->credentials !== null) return $this->credentials;
        $path = trim((string) config('services.fcm.credentials', ''));
        if ($path === '' || ! is_readable($path)) {
            throw new RuntimeException('FIREBASE_CREDENTIALS is not set or not readable.');
        }
        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new RuntimeException('FIREBASE_CREDENTIALS is not a service-account key.');
        }
        return $this->credentials = $json;
    }

    private function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(50), function (): string {
            $credentials = $this->credentials();
            $tokenUri = (string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token');
            $now = time();
            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64Url(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => $tokenUri,
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];
            $signature = '';
            if (! openssl_sign(implode('.', $segments), $signature, (string) $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Unable to sign the FCM service-account assertion.');
            }
            $segments[] = $this->base64Url($signature);

            $response = Http::asForm()->timeout((int) config('services.fcm.timeout', 10))->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ]);
            $token = (string) $response->json('access_token', '');
            if (! $response->successful() || $token === '') {
                throw new RuntimeException('FCM OAuth token request failed ('.$response->status().').');
            }
            return $token;
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
