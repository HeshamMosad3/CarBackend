<?php

namespace App\Jobs;

use App\Enums\Notifications\FcmErrorActionEnum;
use App\Models\UserDevice;
use App\Notifications\Payloads\PushPayload;
use App\Services\Fcm\FcmClient;
use App\Services\Fcm\FcmMessageBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one notification to every active device of one user. Unlike
 * a job that logs and moves on, this does NOT swallow
 * exceptions — an infrastructure failure (OAuth mint, DB) must propagate so
 * Laravel retries the job and, after $tries, lands it in failed_jobs instead
 * of vanishing into a log line.
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 60, 300];
    public $timeout = 30;
    public $maxExceptions = 3;

    /**
     * @param array $payloadData PushPayload::toArray()
     * @param string[]|null $onlyTokens Set on a partial retry — send only to these tokens.
     * @param int $sendAttempt 1-based attempt counter for the retryable-token loop below.
     */
    public function __construct(
        public array $payloadData,
        public ?array $onlyTokens = null,
        public int $sendAttempt = 1,
    ) {
        // Set at runtime rather than declared as a class property: Queueable
        // already declares $afterCommit with a different default, and PHP
        // treats a class re-declaring a trait property with an incompatible
        // default as a fatal composition error.
        $this->afterCommit = true;
        $this->onQueue(config('services.fcm.queue', 'notifications'));
    }

    public function handle(FcmClient $fcm): void
    {
        $payload = PushPayload::fromArray($this->payloadData);

        $tokens = $this->onlyTokens ?? UserDevice::active()
            ->where('user_id', $payload->recipientId)
            ->pluck('token')
            ->all();

        if (empty($tokens)) {
            return;
        }

        $message = FcmMessageBuilder::build($payload);
        $results = $fcm->sendToTokens($tokens, $message);

        $retryable = [];

        foreach ($results as $token => $result) {
            if ($result->ok) {
                UserDevice::where('token', $token)->update(['last_used_at' => now(), 'failure_count' => 0]);
                continue;
            }

            match ($result->action) {
                FcmErrorActionEnum::DeleteToken => UserDevice::where('token', $token)->update([
                    'revoked_at' => now(),
                    'revoked_reason' => $result->errorCode ?? 'delete_requested',
                ]),
                FcmErrorActionEnum::Retry => $retryable[] = $token,
                FcmErrorActionEnum::Drop => null,
            };
        }

        // Re-dispatch a NEW job scoped to just the retryable tokens rather than
        // throwing: throwing would re-queue this job's original payload, which
        // Laravel retries in full — re-sending to devices that already
        // succeeded and double-notifying them.
        $maxAttempts = (int) config('services.fcm.max_send_attempts', 3);

        if (!empty($retryable) && $this->sendAttempt < $maxAttempts) {
            $delays = config('services.fcm.retry_delays', [10, 60, 300]);
            $delay = $delays[$this->sendAttempt - 1] ?? end($delays);

            self::dispatch($this->payloadData, $retryable, $this->sendAttempt + 1)
                ->delay(now()->addSeconds($delay));
        } elseif (!empty($retryable)) {
            Log::error('[FCM] gave up retrying after max attempts', [
                'recipient_id' => $payload->recipientId,
                'category' => $payload->category->value,
                'tokens_remaining' => count($retryable),
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('[FCM] SendPushNotificationJob failed permanently', [
            'recipient_id' => $this->payloadData['recipient_id'] ?? null,
            'category' => $this->payloadData['category'] ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
