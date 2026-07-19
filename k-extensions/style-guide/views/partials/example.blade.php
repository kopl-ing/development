{{--
    Reads $path's own raw file straight off disk rather than keeping a second, hand-copied
    string of "what the code looks like" -- there's exactly one copy of each example's markup,
    so the snippet shown here can never drift from what's actually rendered above it. $with lets
    a caller forward extra variables (a fixture model, say) into the included view; nothing beyond
    the current scope leaks in by accident since it's passed explicitly, not relied on implicitly.
--}}
@php
    $source = trim(file_get_contents(app('view')->getFinder()->find($path)));
@endphp
<div class="flex flex-col gap-2">
    <span class="text-sm font-medium">{{ $label }}</span>
    <div class="border border-base-300 rounded-box p-4">
        @include($path, $with ?? [])
    </div>
    <pre class="bg-base-200 rounded-box p-3 text-xs overflow-x-auto"><code>{{ $source }}</code></pre>
</div>
