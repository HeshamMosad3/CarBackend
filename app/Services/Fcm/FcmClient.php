<?php

namespace App\Services\Fcm;

use App\Enums\Notifications\FcmErrorActionEnum;
use Exception;
use Google_Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Replaces app/Utils/FcmNotificationUtils.php. Differences that matter:
 *  - reads the FCM error body instead of throwing it away (http_errors=false)
 *  - sends to many tokens concurrently, one HTTP call per token (FCM's
 *    multicast/batch endpoint was retired in 2024 — there is no bulk call)
 *  - classifies every failure into delete-token / retry / drop
 *  - the OAuth token fetch is lock-protected so concurrent queue workers
 *    don't each mint a fresh JWT assertion on expiry
 */
class FcmClient
{
    private const CONFIG_ERROR_CODES = ['SENDER_ID_MISMATCH', 'THIRD_PARTY_AUTH_ERROR', 'PERMISSION_DENIED'];

    private GuzzleClient $http;

    public function __construct()
    {
        $this->http = new GuzzleClient([
            'http_errors' => false,
            'timeout' => (float) config('services.fcm.timeout', 10),
            'connect_timeout' => 5,
        ]);
    }

    /**
     * Send one message to many device tokens concurrently.
     *
     * @param string[] $tokens
     * @param array $message Platform-shaped FCM message body, WITHOUT 'token'.
     * @return array<string, FcmSendResult> keyed by token.
     */
    public function sendToTokens(array $tokens, array $message): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if (empty($tokens)) {
            return [];
        }

        $url = $this->endpoint();
        $auth = $this->accessToken();
        if (empty($auth)) {
            return [];
        }

        $promises = [];
        foreach ($tokens as $token) {
            $promises[$token] = $this->http->postAsync($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $auth,
                    'Content-Type' => 'application/json',
                ],
                'json' => ['message' => array_merge($message, ['token' => $token])],
            ]);
        }

        $outcomes = Utils::settle($promises)->wait();

        $results = [];
        $expiredAuthTokens = [];

        foreach ($outcomes as $token => $outcome) {
            if ($outcome['state'] !== 'fulfilled') {
                $results[$token] = new FcmSendResult($token, false, null, 'transport_error', FcmErrorActionEnum::Retry);
                continue;
            }

            /** @var \Psr\Http\Message\ResponseInterface $response */
            $response = $outcome['value'];

            if ($response->getStatusCode() === 401) {
                $expiredAuthTokens[] = $token;
                continue;
            }

            $results[$token] = $this->toResult($token, $response);
        }

        // One inline retry, for every token that hit 401 at once, after
        // forcing a fresh OAuth token.
        if (!empty($expiredAuthTokens)) {
            $auth = $this->accessToken(forceRefresh: true);

            foreach ($expiredAuthTokens as $token) {
                try {
                    $response = $this->http->post($url, [
                        'headers' => ['Authorization' => 'Bearer ' . $auth, 'Content-Type' => 'application/json'],
                        'json' => ['message' => array_merge($message, ['token' => $token])],
                    ]);
                    $results[$token] = $this->toResult($token, $response);
                } catch (Exception $e) {
                    $results[$token] = new FcmSendResult($token, false, null, 'transport_error', FcmErrorActionEnum::Retry);
                }
            }
        }

        return $results;
    }

    private function toResult(string $token, \Psr\Http\Message\ResponseInterface $response): FcmSendResult
    {
        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            return new FcmSendResult($token, true, $status);
        }

        $body = json_decode((string) $response->getBody(), true) ?: [];
        $errorCode = $this->extractErrorCode($body);
        $action = $this->classify($status, $errorCode);

        if ($action === FcmErrorActionEnum::Drop) {
            if (in_array($errorCode, self::CONFIG_ERROR_CODES, true)) {
                Log::error('[FCM][CONFIG] permanent configuration error — check service account / APNs key', [
                    'error_code' => $errorCode,
                    'status' => $status,
                ]);
            } else {
                Log::warning('[FCM] dropping send — not retryable', [
                    'error_code' => $errorCode,
                    'status' => $status,
                    'token_suffix' => substr($token, -8),
                ]);
            }
        }

        return new FcmSendResult($token, false, $status, $errorCode, $action);
    }

    private function extractErrorCode(array $body): ?string
    {
        foreach (($body['error']['details'] ?? []) as $detail) {
            if (($detail['@type'] ?? '') === 'type.googleapis.com/google.firebase.fcm.v1.FcmError' && isset($detail['errorCode'])) {
                return $detail['errorCode'];
            }
        }

        return $body['error']['status'] ?? null;
    }

    private function classify(int $status, ?string $errorCode): FcmErrorActionEnum
    {
        return match (true) {
            $errorCode === 'UNREGISTERED' => FcmErrorActionEnum::DeleteToken,
            $status === 404 => FcmErrorActionEnum::DeleteToken,
            $errorCode === 'INVALID_ARGUMENT' && $status === 400 => FcmErrorActionEnum::DeleteToken,
            $errorCode === 'UNAVAILABLE' => FcmErrorActionEnum::Retry,
            $errorCode === 'INTERNAL' => FcmErrorActionEnum::Retry,
            $status === 503 || $status === 500 => FcmErrorActionEnum::Retry,
            $errorCode === 'QUOTA_EXCEEDED' => FcmErrorActionEnum::Retry,
            $status === 429 => FcmErrorActionEnum::Retry,
            default => FcmErrorActionEnum::Drop,
        };
    }

    private function endpoint(): string
    {
        $projectId = config('services.fcm.project_id');

        return "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    }

    private function accessToken(bool $forceRefresh = false): string
    {
        $key = 'fcm_access_token_key';

        if ($forceRefresh) {
            Cache::forget($key);
        } elseif ($cached = Cache::get($key)) {
            return $cached;
        }

        return Cache::lock('fcm_access_token_lock', 15)->block(10, function () use ($key) {
            if ($cached = Cache::get($key)) {
                return $cached;
            }

            $client = new Google_Client();

            $envCredentials = env('FIREBASE_CREDENTIALS');
            $credentialsPath = config('services.fcm.credentials_path');

            if (!empty($envCredentials)) {
                $client->setAuthConfig(json_decode($envCredentials, true));
            } elseif ($credentialsPath && file_exists($credentialsPath)) {
                $client->setAuthConfig($credentialsPath);
            } else {
                Log::warning('[FCM] Firebase credentials not found. Push notifications will be skipped.');
                return '';
            }

            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

            $token = $client->fetchAccessTokenWithAssertion();

            if (empty($token['access_token'])) {
                Log::warning('[FCM] Failed to mint FCM OAuth access token: ' . json_encode($token));
                return '';
            }

            $ttl = max(60, (int) ($token['expires_in'] ?? 3600) - 300);
            Cache::put($key, $token['access_token'], $ttl);

            return $token['access_token'];
        });
    }
}
