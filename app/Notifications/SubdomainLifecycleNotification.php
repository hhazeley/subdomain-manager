<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubdomainLifecycleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $event,
        public readonly string $fqdn,
        public readonly ?string $reason = null,
        public readonly ?string $date = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return match ($this->event) {
            'requested' => (new MailMessage)
                ->subject("Subdomain registration submitted: {$this->fqdn}")
                ->line("A registration request for {$this->fqdn} was submitted.")
                ->line('An administrator must approve it before DNS records can be configured.'),
            'approval_requested' => (new MailMessage)
                ->subject("Subdomain approval required: {$this->fqdn}")
                ->line("A user requested {$this->fqdn}.")
                ->action('Review registrations', route('admin.registrations.index')),
            'approved' => (new MailMessage)
                ->subject("Subdomain approved: {$this->fqdn}")
                ->line("Your registration for {$this->fqdn} was approved.")
                ->line("It expires on {$this->date}.")
                ->action('Manage DNS', route('dashboard')),
            'rejected' => (new MailMessage)
                ->subject("Subdomain registration rejected: {$this->fqdn}")
                ->line("Your registration for {$this->fqdn} was rejected.")
                ->line("Reason: {$this->reason}"),
            'deletion_requested' => (new MailMessage)
                ->subject("Subdomain scheduled for deletion: {$this->fqdn}")
                ->line("The deletion of {$this->fqdn} was requested.")
                ->line("It is hidden from the user and will be permanently deleted after {$this->date} unless an administrator restores it."),
            'restored' => (new MailMessage)
                ->subject("Subdomain restored: {$this->fqdn}")
                ->line("The deletion request for {$this->fqdn} was cancelled by an administrator.")
                ->action('Manage DNS', route('dashboard')),
            'deleted' => (new MailMessage)
                ->subject("Subdomain deleted: {$this->fqdn}")
                ->line("{$this->fqdn} and all of its DNS records were permanently deleted.")
                ->when($this->reason, fn (MailMessage $mail) => $mail->line("Reason: {$this->reason}")),
            'expired' => (new MailMessage)
                ->subject("Subdomain expired: {$this->fqdn}")
                ->line("{$this->fqdn} expired and its DNS records were removed."),
            'renewed' => (new MailMessage)
                ->subject("Subdomain renewed: {$this->fqdn}")
                ->line("{$this->fqdn} was renewed automatically through {$this->date}."),
            default => (new MailMessage)
                ->subject("Subdomain update: {$this->fqdn}")
                ->line("The status of {$this->fqdn} changed."),
        };
    }
}
