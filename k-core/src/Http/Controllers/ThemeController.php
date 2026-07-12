<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kopling\Core\Ux\Theme;

/**
 * Stores the visitor's theme choice in the `kopling_theme` cookie and sends them back where
 * they were -- the page then re-renders under the newly-active theme (see Theme::resolve).
 * Only an id that names an actually-installed theme is honoured; anything else is a silent
 * no-op, so a stale or hand-crafted value can never wedge the site into a broken palette.
 */
class ThemeController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $theme = (string) $request->input('theme');

        if (! isset(Theme::available()[$theme])) {
            return back();
        }

        return back()->withCookie(cookie()->forever(Theme::COOKIE, $theme));
    }
}
