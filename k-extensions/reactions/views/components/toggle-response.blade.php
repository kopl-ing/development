{{--
    The htmx response for the toggle route: the freshly rendered rail (the primary swap target
    every button that posts here already uses) followed by the "Latest reactions" strip as an
    out-of-band swap, so a chip's own remove button -- which also posts here, see
    `words.blade.php`'s own docblock -- actually disappears without a page reload, the same
    two-fragments-one-request shape `words-response.blade.php` already uses in reverse.
--}}
<x-kopling-reactions::rail :context="$context" />
<x-kopling-reactions::words :context="$context" :oob="true" />
