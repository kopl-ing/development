<?php

declare(strict_types=1);

namespace Kopling\AuthEmailPassword;

use Kopling\AuthEmailPassword\Listeners\AttemptPasswordLogin;
use Illuminate\Auth\Events\Registered;
use Kopling\AuthEmailPassword\Listeners\AttemptPasswordRegistration;
use Kopling\AuthEmailPassword\Listeners\SendVerificationEmail;
use Kopling\Core\Authentication\AuthSettings;
use Kopling\Core\Authentication\Event\AttemptLogin;
use Kopling\Core\Authentication\Event\AttemptRegistration;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extend\Ux\ProvidesUxEntries;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasAdminSettings;
use Kopling\Core\Extension\Contract\ListensToEvents;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Settings\Settings;
use Kopling\Core\Ux\Form\Field;
use Kopling\Core\Ux\Form\Input;
use Kopling\Core\Ux\Form\Toggle;
use Kopling\Core\Ux\Link;

class Extension extends AbstractExtension implements ChangesUx, ExtendsPortals, HasAdminSettings, ListensToEvents
{
    public const PASSWORD_RESET_PATH = 'kopling-auth-email-password::password-reset-path';

    public const VERIFICATION_REQUIRED = 'kopling-auth-email-password::verification-required';

    public const VERIFICATION_PATH = 'kopling-auth-email-password::verification-path';

    public static function verificationPath(): string
    {
        return AuthSettings::path(Settings::get(self::VERIFICATION_PATH), 'verify-email');
    }

    public static function verificationRequired(): bool
    {
        return Settings::get(self::VERIFICATION_REQUIRED, '1') !== '0';
    }

    public static function passwordResetPath(): string
    {
        return AuthSettings::path(Settings::get(self::PASSWORD_RESET_PATH), 'forgot-password');
    }

    public static function name(): string
    {
        return 'Email/password login';
    }

    public static function description(): string
    {
        return "Email/password sign-in, registration and password reset -- built on Core's Validate/Attempt Login and Registration events.";
    }

    /**
     * @return array<Field>
     */
    public function adminSettings(): array
    {
        return [
            new Field(
                id: 'verification-required',
                label: 'Require email verification',
                component: Toggle::class,
                default: true,
                description: 'New accounts can only sign in after confirming their email address via the link they are sent.',
            ),
            new Field(
                id: 'verification-path',
                label: 'Email verification path',
                component: Input::class,
                default: 'verify-email',
                description: 'Path of the "check your email" page and the confirmation links, e.g. "confirm". Changing it breaks links already sent. Run `php artisan route:clear` after changing it on a site that caches routes.',
            ),
            new Field(
                id: 'password-reset-path',
                label: 'Password reset path',
                component: Input::class,
                default: 'forgot-password',
                description: 'Path of the "forgot password" page, e.g. "reset". Run `php artisan route:clear` after changing it on a site that caches routes.',
            ),
        ];
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            (new PortalExtension('kopling-core::community'))
                ->routes(__DIR__.'/../routes/community.php'),
        ];
    }

    public function ux(): ProvidesUxEntries
    {
        return Ux::make()
            ->add(LoginForm::class)
            ->in('kopling-core::auth.login-form')
            ->as('login-form')
            ->add(RegistrationForm::class)
            ->in('kopling-core::auth.registration-form')
            ->as('registration-form')
            ->add(Link::class, [
                'label' => __('kopling-core::auth.log_in'),
                'route' => 'kopling-core::community/login',
                'variant' => 'ghost',
            ])
            ->in('kopling-core::community.topbar')
            ->as('login-link')
            ->when('kopling-core::guest')
            ->add(Link::class, [
                'label' => __('kopling-core::auth.register'),
                'route' => 'kopling-core::community/register',
                'variant' => 'primary',
            ])
            ->in('kopling-core::community.topbar')
            ->as('register-link')
            ->when('kopling-core::registration-open')
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
            Registered::class => SendVerificationEmail::class,
        ];
    }
}
