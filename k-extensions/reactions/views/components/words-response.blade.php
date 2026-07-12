{{--
    The htmx response for adding a worded reaction: the freshly rendered "Latest reactions"
    strip (the form's swap target) followed by the rail as an out-of-band swap, so both stay
    in sync from one request. Both resolve to the same anonymous components the footer slot
    renders, so a poll-updated strip is identical to a page-load one.
--}}
<x-kopling-reactions::words :context="$context" />
<x-kopling-reactions::rail :context="$context" :oob="true" />
