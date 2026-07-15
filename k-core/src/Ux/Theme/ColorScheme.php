<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Theme;

/**
 * The CSS `color-scheme` property values Kopling cares about -- what native browser chrome
 * (form controls, scrollbars) should render as. Deliberately narrow: the compiled "kopling"
 * default hardcodes `color-scheme: light` (k-core/src/Ux/css/app.css), so a dark `ChangesTheme`
 * extension (e.g. Midnight) needs a way to say so, or native chrome stays light underneath a
 * dark palette. Not a stand-in for accessibility contrast (`prefers-contrast`) -- that's a
 * separate, unaddressed axis (see .docs/planning/roadmap.md, "Theming").
 */
enum ColorScheme: string
{
    case Light = 'light';
    case Dark = 'dark';
}
