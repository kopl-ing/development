<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Theme;

/**
 * Every CSS custom property Kopling exposes as overridable -- deliberately the exact,
 * finite set the compiled "kopling" daisyUI theme (k-core/src/Ux/css/app.css) already
 * defines, no more. A curated catalog, not "arbitrary CSS": a `ChangesTheme` extension or a
 * `theme_tokens` row can only ever target one of these, which keeps an eventual admin editor
 * to a fixed set of meaningful controls (a color picker per color token, a size input per
 * radius token) and keeps every value that ever reaches a raw `<style>` tag checked against
 * a known shape first (see `Token::matches()`) -- never interpolated unchecked.
 */
enum Token: string
{
    case ColorBase100 = '--color-base-100';
    case ColorBase200 = '--color-base-200';
    case ColorBase300 = '--color-base-300';
    case ColorBaseContent = '--color-base-content';
    case ColorPrimary = '--color-primary';
    case ColorPrimaryContent = '--color-primary-content';
    case ColorSecondary = '--color-secondary';
    case ColorSecondaryContent = '--color-secondary-content';
    case ColorAccent = '--color-accent';
    case ColorAccentContent = '--color-accent-content';
    case ColorNeutral = '--color-neutral';
    case ColorNeutralContent = '--color-neutral-content';
    case RadiusBox = '--radius-box';
    case RadiusField = '--radius-field';

    /**
     * Whether `$value` is a shape this token could plausibly accept -- a hex color for a
     * color token, a CSS length for a radius token. Deliberately conservative (hex colors
     * only, not every valid CSS color syntax): good enough for what ships today, and a value
     * that doesn't match is a config error worth catching, not a reason to widen the pattern
     * defensively ahead of an actual need.
     */
    public function matches(string $value): bool
    {
        return (bool) preg_match($this->pattern(), $value);
    }

    protected function pattern(): string
    {
        return match (true) {
            str_starts_with($this->value, '--color-') => '/^#[0-9a-fA-F]{3,8}$/',
            default => '/^\d+(\.\d+)?(px|rem|em)$/',
        };
    }
}
