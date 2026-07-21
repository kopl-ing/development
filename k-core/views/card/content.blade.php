{{-- $bodyHtml is rendered server-side by DocumentRenderer from a closed, PHP-declared node/
     mark catalog, at write time -- never client-supplied HTML sanitized on the way out. This
     is the one place in this view auto-escaping is deliberately off; DocumentRenderer's own
     correctness is what keeps it safe, see its docblock. --}}
@if ($bodyHtml)
    <div class="kop-content">{!! $bodyHtml !!}</div>
@endif
