<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $acceptUrl,
        private string $appName,
        private string $inviterName,
    ) {
        $this->queue = 'notifications';
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.app_invitation.subject', ['app' => $this->appName]))
            ->line(__('mail.app_invitation.line', [
                'inviter' => $this->inviterName,
                'app' => $this->appName,
            ]))
            ->action(__('mail.app_invitation.action'), $this->acceptUrl);
    }
}
