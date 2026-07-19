@props(['data' => [], 'context' => null])
{{--
    A same-page fragment link (e.g. "#tokens") -- not a named route, so `Portal\Navigation\Item`
    (which always calls `route($route)`) doesn't fit here; this is its own tiny anonymous
    component instead, same "bare view file, not a class" convention discussions'/reactions' own
    card-slot entries already use.
--}}
<li><a href="{{ $data['href'] }}">{{ $data['label'] }}</a></li>
