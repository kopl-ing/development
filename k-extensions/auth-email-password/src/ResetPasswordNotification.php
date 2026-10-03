<?php

declare(strict_types=1);

namespace Kopling\AuthEmailPassword;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Kopling\Core\People\Person;

class ResetPasswordNotification extends Notification
{
    public function __construct(public readonly string $token)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(Person $person): array
    {
        return ['mail'];
    }

    public function toMail(Person $person): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage())
            ->subject(__('kopling-auth-email-password::messages.reset_mail_subject'))
            ->line(__('kopling-auth-email-password::messages.reset_mail_intro'))
            ->action(__('kopling-auth-email-password::messages.reset_mail_action'), $this->url($person))
            ->line(__('kopling-auth-email-password::messages.mail_link_expiry', ['minutes' => $minutes]))
            ->line(__('kopling-auth-email-password::messages.reset_mail_ignore'));
    }

    public function url(Person $person): string
    {
        return route('kopling-core::community/password.reset', ['token' => $this->token, 'email' => $person->email]);
    }
}
