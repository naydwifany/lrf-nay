<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Notification $notification;

    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }

    public function handle(EmailService $emailService): void
    {
        try {
            \Log::info("🚀 Sending email notification", [
                'notification_id' => $this->notification->id,
                'recipient_name'  => $this->notification->recipient_name,
                'recipient_nik'   => $this->notification->recipient_nik,
                'type'            => $this->notification->type,
            ]);

            $emailService->sendNotificationEmail($this->notification);

            \Log::info("✅ Email notification sent", [
                'notification_id' => $this->notification->id,
            ]);
        } catch (\Throwable $e) {
            \Log::error("❌ Failed to send email notification", [
                'notification_id' => $this->notification->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}