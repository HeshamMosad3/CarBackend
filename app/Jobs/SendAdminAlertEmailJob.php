<?php

namespace App\Jobs;

use App\Models\AdminAlert;
use App\Models\AdminNotificationEmail;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Emails one "action needed" alert to every recipient who is switched on and chose that kind of alert.
 */
class SendAdminAlertEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 120];

    public function __construct(public int $alertId) {}

    public function handle(EmailService $emailService): void
    {
        $alert = AdminAlert::find($this->alertId);
        $type = $alert?->alertType();
        if (!$alert || !$type) {
            return;
        }

        $recipients = AdminNotificationEmail::where('is_active', true)->get()->filter(fn ($r) => $r->wants($type));
        if ($recipients->isEmpty()) {
            return;
        }

        $html = self::render($alert->title, $alert->body, $alert->url, $alert->tone, $type->label());
        $subject = 'تنبيه: ' . $alert->title;

        $failed = 0;
        foreach ($recipients as $recipient) {
            if (!$emailService->sendEmail($recipient->email, $subject, $html)) {
                $failed++;
            }
        }

        Log::info("Alert #{$alert->id} ({$type->value}) emailed to " . ($recipients->count() - $failed) . ' of ' . $recipients->count() . ' recipients.');

        // EmailService swallows errors; re-run the job if nobody could be reached (the mail server may be down).
        if ($failed === $recipients->count()) {
            $this->release(60);
        }
    }

    public static function render(string $title, ?string $body, ?string $url, string $tone, string $typeLabel, ?string $buttonLabel = null): string
    {
        return view('emails.admin-alert', [
            'title' => $title,
            'body' => $body,
            'url' => $url ? (str_starts_with($url, 'http') ? $url : url($url)) : null,
            'tone' => $tone,
            'typeLabel' => $typeLabel,
            'buttonLabel' => $buttonLabel,
        ])->render();
    }
}
