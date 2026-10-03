<?php

declare(strict_types=1);

namespace Kopling\AuthEmailPassword;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Kopling\Core\People\Person;

class VerifyEmailNotification extends Notification
{
    public const EXPIRE_MINUTES = 60;

    /**
     * @return array<int, string>
     */
    public function via(Person $person): array
    {
        return ['mail'];
    }

    public function toMail(Person $person): MailMessage
    {
        return (new MailMessage())
            ->subject(__('kopling-auth-email-password::messages.verify_mail_subject'))
            ->line(__('kopling-auth-email-password::messages.verify_mail_intro'))
            ->action(__('kopling-auth-email-password::messages.verify_mail_action'), $this->url($person))
            ->line(__('kopling-auth-email-password::messages.mail_link_expiry', ['minutes' => self::EXPIRE_MINUTES]))
            ->line(__('kopling-auth-email-password::messages.verify_mail_ignore'));
    }

    public function url(Person $person): string
    {
        return URL::temporarySignedRoute(
            'kopling-core::community/verification.verify',
            now()->addMinutes(self::EXPIRE_MINUTES),
            ['id' => $person->id, 'hash' => sha1((string) $person->email)],
        );
    }
}
