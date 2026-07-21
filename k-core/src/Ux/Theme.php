<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Theme\ColorScheme;
use Kopling\Core\Ux\Theme\ThemeToken;
use Kopling\Core\Ux\Theme\Token;

/**
 * Renders the runtime override layer for the compiled "kopling" daisyUI theme -- a `<style>`
 * block scoped to `:root[data-theme="kopling"]` (specificity (0,2,0), beats the compiled
 * theme's own (0,1,0) rule). Two layers, later wins: every installed `ChangesTheme` extension's
 * tokens, then `theme_tokens` DB rows on top. DB rows are re-validated on every read and a bad
 * one is silently skipped (not thrown) -- this runs on every page load, so one bad settings row
 * can't take the site down.
 */
class Theme
{
    /**
     * A plain cookie rather than a per-Person setting -- works for guests too, and matches
     * theme_tokens being an instance-wide, not per-account, concern.
     */
    public const COOKIE = 'kopling_theme';

    /**
     * @return array<string, string>
     */
    public static function available(): array
    {
        return app(Manager::class)->themeChoices();
    }

    /**
     * The visitor's cookie when it names a still-installed theme, otherwise the first theme by
     * id (deterministic, doesn't silently flip with extension load order). Null if none installed.
     */
    public static function active(): ?string
    {
        $available = static::available();

        if ($available === []) {
            return null;
        }

        $chosen = request()->cookie(static::COOKIE);

        if (is_string($chosen) && isset($available[$chosen])) {
            return $chosen;
        }

        $ids = array_keys($available);
        sort($ids);

        return $ids[0];
    }

    public static function css(): string
    {
        $tokens = static::resolve();
        $scheme = static::activeColorScheme();

        if ($tokens === [] && $scheme === null) {
            return '';
        }

        $declarations = collect($tokens)
            ->map(fn (string $value, string $token) => "{$token}:{$value};")
            ->implode('');

        if ($scheme !== null) {
            $declarations = "color-scheme:{$scheme->value};".$declarations;
        }

        return ':root[data-theme="kopling"]{'.$declarations.'}';
    }

    /** Drives the `color-scheme` declaration `css()` emits, so native form controls/scrollbars match. */
    protected static function activeColorScheme(): ?ColorScheme
    {
        $active = static::active();

        if ($active === null) {
            return null;
        }

        return app(Manager::class)->themeColorSchemes()->get($active);
    }

    /**
     * @return array<string, string>
     */
    protected static function resolve(): array
    {
        $tokens = [];

        // Only the active theme's tokens, not every ChangesTheme extension merged together.
        $active = static::active();

        if ($active !== null) {
            $tokens = app(Manager::class)->themes()->get($active, []);
        }

        foreach (ThemeToken::query()->pluck('value', 'token') as $token => $value) {
            $case = Token::tryFrom($token);

            if ($case !== null && $case->matches($value)) {
                $tokens[$token] = $value;
            }
        }

        return $tokens;
    }
}
