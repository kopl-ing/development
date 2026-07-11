<?php

declare(strict_types=1);

namespace Kopling\AuthPassword;

use Kopling\AuthPassword\Listeners\AttemptPasswordLogin;
use Kopling\Core\Authentication\Event\AttemptLogin;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ListensToEvents;

class Extension extends AbstractExtension implements ChangesUx, ListensToEvents
{
    public static function name(): string
    {
        return 'Password login';
    }

    public static function description(): string
    {
        return "Username/password sign-in -- the first login method built on Core's ValidateLogin/AttemptLogin events.";
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add(LoginForm::class)
            ->in('core::auth.login-form')
            ->as('form');
    }

    /**
     * @return array<class-string, class-string>
     */
    public function listen(): array
    {
        return [
            AttemptLogin::class => AttemptPasswordLogin::class,
        ];
    }
}
