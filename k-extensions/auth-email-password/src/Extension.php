<?php

declare(strict_types=1);

namespace Kopling\AuthEmailPassword;

use Kopling\AuthEmailPassword\Listeners\AttemptPasswordLogin;
use Kopling\AuthEmailPassword\Listeners\AttemptPasswordRegistration;
use Kopling\Core\Authentication\Event\AttemptLogin;
use Kopling\Core\Authentication\Event\AttemptRegistration;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ListensToEvents;

class Extension extends AbstractExtension implements ChangesUx, ListensToEvents
{
    public static function name(): string
    {
        return 'Email/password login';
    }

    public static function description(): string
    {
        return "Email/password sign-in and registration -- built on Core's Validate/Attempt Login and Registration events.";
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add(LoginForm::class)
            ->in('kopling-core::auth.login-form')
            ->as('login-form')
            ->add(RegistrationForm::class)
            ->in('kopling-core::auth.registration-form')
            ->as('registration-form')
            ->add(AuthLink::class, [
                'label' => __('kopling-core::auth.log_in'),
                'route' => 'kopling-core::community/login',
                'variant' => 'ghost',
            ])
            ->in('kopling-core::community.topbar')
            ->as('login-link')
            ->when('kopling-core::guest')
            ->add(AuthLink::class, [
                'label' => __('kopling-core::auth.register'),
                'route' => 'kopling-core::community/register',
                'variant' => 'primary',
            ])
            ->in('kopling-core::community.topbar')
            ->as('register-link')
            ->when('kopling-core::guest')
            ->after('login-link');
    }

    /**
     * @return array<class-string, class-string>
     */
    public function listen(): array
    {
        return [
            AttemptLogin::class => AttemptPasswordLogin::class,
            AttemptRegistration::class => AttemptPasswordRegistration::class,
        ];
    }
}
