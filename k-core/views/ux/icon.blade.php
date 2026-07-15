{{--
    `width`/`height` default to "1em" (scales with the surrounding font-size, the standard
    icon-font sizing convention) so every icon has a real HTML size attribute from first paint --
    a bare Font Awesome SVG only carries a `viewBox` (no intrinsic width/height), so with nothing
    here a browser renders it at its native viewBox pixel size (e.g. 512x512) until CSS loads and
    a caller's own sizing class (Tailwind utility, daisyUI, ...) takes over, flashing every icon
    briefly at near-fullscreen size on first paint. An explicit HTML attribute applies before any
    CSS does; `array_merge` puts it first so an explicit width/height a caller does pass through
    `:width=`/`:height=` still wins.
--}}
{{ svg(
    $icon,
    $attributes->get('class', ''),
    array_merge(['width' => '1em', 'height' => '1em'], $attributes->except('class')->getAttributes())
) }}
