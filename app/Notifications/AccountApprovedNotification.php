<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Subdomain Manager account was approved')
            ->greeting("Hello {$notifiable->name},")
            ->line('An administrator approved your account. You can now request subdomains and manage DNS records.')
            ->action('Open Subdomain Manager', route('dashboard'));
    }
}
