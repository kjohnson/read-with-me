<?php

namespace App\Notifications;

use App\Models\MagicLink;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLinkNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $token) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your sign-in link for '.config('app.name'))
            ->greeting('Hello!')
            ->line('Click the button below to sign in. No password needed.')
            ->action('Sign in to '.config('app.name'), route('magic-link.show', $this->token))
            ->line('This link expires in '.MagicLink::LIFETIME_MINUTES.' minutes and can only be used once.')
            ->line("If you didn't ask to sign in, you can safely ignore this email.");
    }
}
