<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Theme\ThemeToken;
use Kopling\Core\Ux\Theme\Token;

/**
 * Renders the runtime override layer for the compiled "kopling" daisyUI theme -- a `<style>`
 * block's worth of CSS custom-property declarations, scoped to `:root[data-theme="kopling"]`
 * (specificity (0,2,0), reliably beating the compiled theme's own `[data-theme=kopling]` rule
 * (0,1,0) regardless of where in <head> this ends up rendered). Anything not overridden here
 * simply falls through to the compiled default -- an install with no ChangesTheme extension
 * and no theme_tokens rows renders `css()` as an empty string, identical to having no theme
 * system at all.
 *
 * Two layers, applied in priority order (later wins): every installed `ChangesTheme`
 * extension's declared tokens, then `theme_tokens` DB rows on top. Two different trust
 * levels, two different failure behaviours to match: `ChangesTheme` tokens are validated once,
 * at declaration time, in `Manager::themes()` (an unrecognized key or malformed value throws
 * immediately -- an extension author's own bug, caught in dev). `theme_tokens` rows are
 * validated again, here, on every read, and a row that fails is silently skipped rather than
 * thrown on -- this runs on every page load, and one bad settings row should never be able to
 * take the whole site down over a single token.
 */
class Theme
{
    /**
     * The visitor's chosen theme id, set by the topbar switcher (ThemeController). A plain
     * cookie rather than a per-Person setting on purpose: it works for guests too, needs no
     * schema, and matches theme_tokens being an instance-wide (not per-account) concern --
     * per-account theming is a charter-open question, deliberately not pre-empted here.
     */
    public const COOKIE = 'kopling_theme';

    /**
     * Every installed theme the visitor can pick between: `[id => label]`, label = the
     * ChangesTheme extension's `name()`. Empty when no theme extension is installed.
     *
     * @return array<string, string>
     */
    public static function available(): array
    {
        return app(Manager::class)->themeChoices();
    }

    /**
     * The active theme id: the visitor's cookie when it names a still-installed theme,
     * otherwise a deterministic default (first theme by id, so the choice never silently
     * flips with extension load order the way the old merge-everything behaviour did). Null
     * only when no theme extension is installed at all.
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

        if ($tokens === []) {
            return '';
        }

        $declarations = collect($tokens)
            ->map(fn (string $value, string $token) => "{$token}:{$value};")
            ->implode('');

        return ':root[data-theme="kopling"]{'.$declarations.'}';
    }

    /**
     * @return array<string, string>
     */
    protected static function resolve(): array
    {
        $tokens = [];

        // Apply ONLY the active theme's tokens (a real pick between installed themes), not
        // every ChangesTheme extension merged together -- the latter let whichever theme
        // loaded last silently win the whole palette.
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
