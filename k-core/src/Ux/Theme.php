<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Illuminate\Support\Facades\DB;
use Kopling\Core\Extension\Manager;
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

        foreach (app(Manager::class)->themes() as $declared) {
            $tokens = [...$tokens, ...$declared];
        }

        foreach (DB::table('theme_tokens')->pluck('value', 'token') as $token => $value) {
            $case = Token::tryFrom($token);

            if ($case !== null && $case->matches($value)) {
                $tokens[$token] = $value;
            }
        }

        return $tokens;
    }
}
