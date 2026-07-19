@props(['data' => [], 'context' => null])
{{--
    A reply's own body -- no title (unlike core's own `Content`, a reply doesn't have one).
    Registered into `Reply::BODY_SLOT`, this slot's only default. Same server-rendered-at-write-
    time trust as `Content`'s own `$bodyHtml` -- never client-supplied HTML sanitized on the way
    out, see `DocumentRenderer`'s own docblock.
--}}
@php
    $reply = $context?->getSubject();
@endphp
<div class="kop-content">{!! $reply?->body_html !!}</div>
