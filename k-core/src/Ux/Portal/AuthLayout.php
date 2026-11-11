<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The shared shell of every sign-in, sign-up and account-recovery page, so a site restyles them
 * all by overriding one view or adding entries to its slots.
 */
class AuthLayout extends Component
{
    public const HEADER = 'kopling-core::auth.header';

    public const ASIDE = 'kopling-core::auth.aside';

    public const FOOTER = 'kopling-core::auth.footer';

    public function __construct(public string $page, public string $title)
    {
    }

    public function render(): View
    {
        return view('kopling-core::portal.auth-layout');
    }
}
