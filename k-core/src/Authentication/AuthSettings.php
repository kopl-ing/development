<?php

declare(strict_types=1);

namespace Kopling\Core\Authentication;

use Kopling\Core\Settings\Settings;

/**
 * Core's sign-up and redirect settings, as declared in `Core::adminSettings()`.
 */
class AuthSettings
{
    public const REGISTRATION_ENABLED = 'kopling-core::registration-enabled';

    public const REGISTRATION_PATH = 'kopling-core::registration-path';

    public const LOGIN_PATH = 'kopling-core::login-path';

    public const REDIRECT_PATH = 'kopling-core::auth-redirect-path';

    public static function registrationEnabled(): bool
    {
        return Settings::get(self::REGISTRATION_ENABLED, '1') !== '0';
    }

    public static function loginPath(): string
    {
        return self::path(Settings::get(self::LOGIN_PATH), 'login');
    }

    public static function registrationPath(): string
    {
        return self::path(Settings::get(self::REGISTRATION_PATH), 'register');
    }

    /**
     * Null means: back to the page the person came from (Laravel's "intended" URL).
     */
    public static function redirectPath(): ?string
    {
        $path = self::path(Settings::get(self::REDIRECT_PATH), '');

        return $path === '' ? null : '/'.$path;
    }

    /**
     * A site-relative path without surrounding slashes, or `$default` when empty or not a plain path.
     */
    public static function path(mixed $value, string $default): string
    {
        $path = trim((string) $value, '/ ');

        return $path !== '' && preg_match('#^[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+)*$#', $path) === 1 ? $path : $default;
    }
}
