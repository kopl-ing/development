<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Kopling\Core\Ux\Theme\ColorScheme;

/**
 * Ships a named theme -- a set of CSS custom-property overrides layered on top of the
 * compiled "kopling" daisyUI theme, plus which native color scheme it wants. Keys of
 * `theme()` must be one of `Kopling\Core\Ux\Theme\Token`'s own values (`Manager::themes()`
 * throws on anything else -- an extension author's own typo, caught immediately, not
 * something to degrade gracefully around); a token this theme doesn't mention simply keeps
 * whatever the compiled default (or another layer -- see `Kopling\Core\Ux\Theme::css()`)
 * already says for it, so a theme can override as few or as many tokens as it actually
 * wants to. `colorScheme()` is not sparse the same way: the compiled default hardcodes
 * `color-scheme: light`, so a dark theme must say so explicitly or native form
 * controls/scrollbars render light underneath a dark palette.
 */
interface ChangesTheme
{
    /**
     * @return array<string, string> keyed by Token::*->value
     */
    public function theme(): array;

    public function colorScheme(): ColorScheme;
}
