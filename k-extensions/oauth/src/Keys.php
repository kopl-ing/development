<?php

declare(strict_types=1);

namespace Kopling\OAuth;

use Laravel\Passport\Passport;
use phpseclib4\Crypt\RSA;

/**
 * Same files and permissions as `passport:keys`, which only exists on the console, so the admin page can create them too.
 */
class Keys
{
    public static function exist(): bool
    {
        return (config('passport.private_key') && config('passport.public_key'))
            || (is_file(Passport::keyPath('oauth-private.key')) && is_file(Passport::keyPath('oauth-public.key')));
    }

    public static function generate(int $length = 4096): void
    {
        $key = RSA::createKey($length);
        $public = Passport::keyPath('oauth-public.key');
        $private = Passport::keyPath('oauth-private.key');

        file_put_contents($public, (string) $key->getPublicKey());
        file_put_contents($private, (string) $key);

        if (! windows_os()) {
            chmod($public, 0660);
            chmod($private, 0600);
        }
    }
}
