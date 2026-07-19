<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form\IconSearch;

/**
 * Renders a bare Font Awesome icon id (e.g. "star") to SVG markup via the locally-bundled Blade
 * Icons package, solid style only -- shared by `FontAwesomeIconSearch` (rendering every search
 * result) and `Ux\Form\IconPicker` (rendering the field's current value), so both agree on
 * exactly one rule for "does this id actually resolve to something renderable" rather than each
 * keeping its own copy of the same try/catch.
 */
class IconRenderer
{
    /**
     * `$color`, when given, is set via an inline `style="color:..."` attribute -- Blade Icons'
     * output already renders `fill="currentColor"`, so this is enough to tint the icon without
     * touching the SVG's own markup. Optional: most callers (chrome icons via `Ux\Icon`) never
     * pass one and just inherit whatever text color already surrounds them.
     */
    public static function svg(string $id, string $size = '1.25em', ?string $color = null): ?string
    {
        try {
            $attributes = ['width' => $size, 'height' => $size];

            if ($color !== null && $color !== '') {
                $attributes['style'] = 'color:'.$color;
            }

            return svg('fas-'.$id, '', $attributes)->toHtml();
        } catch (\Throwable) {
            return null;
        }
    }
}
