<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Theme;

/**
 * Every CSS custom property Kopling exposes as overridable -- the exact, finite set the compiled
 * "kopling" daisyUI theme already defines, no more. A curated catalog, not arbitrary CSS: every
 * value that reaches a raw `<style>` tag is checked against a known shape first, see `matches()`.
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

    // daisyUI's "state" palette (alert-info, badge-success, ...), left at daisyUI's own
    // defaults; exposed here so a theme can own the full semantic set, not only brand colors.
    case ColorInfo = '--color-info';
    case ColorInfoContent = '--color-info-content';
    case ColorSuccess = '--color-success';
    case ColorSuccessContent = '--color-success-content';
    case ColorWarning = '--color-warning';
    case ColorWarningContent = '--color-warning-content';
    case ColorError = '--color-error';
    case ColorErrorContent = '--color-error-content';

    case RadiusBox = '--radius-box';
    case RadiusField = '--radius-field';
    case RadiusSelector = '--radius-selector';
    case Border = '--border';

    // Tailwind v4 sets --default-font-family: var(--font-sans), so overriding it retints the
    // whole UI font without a rebuild.
    case FontSans = '--font-sans';
    case FontSerif = '--font-serif';
    case FontMono = '--font-mono';

    /**
     * Whether `$value` is a shape this token could plausibly accept -- a hex color for a color
     * token, a CSS length for a radius token. Deliberately conservative, not every valid CSS
     * color syntax.
     */
    public function matches(string $value): bool
    {
        return (bool) preg_match($this->pattern(), $value);
    }

    protected function pattern(): string
    {
        return match (true) {
            str_starts_with($this->value, '--color-') => '/^#[0-9a-fA-F]{3,8}$/',
            // Excludes ()/;{}<> etc. so a value can never close the declaration or inject CSS.
            str_starts_with($this->value, '--font-') => '/^[a-zA-Z0-9 ,"\'\-]{1,200}$/',
            default => '/^\d+(\.\d+)?(px|rem|em)$/',
        };
    }
}
