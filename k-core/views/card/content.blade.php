<h2 class="card-title">
    @if ($url)
        <a href="{{ $url }}" class="link link-hover">{{ $title }}</a>
    @else
        {{ $title }}
    @endif
</h2>
{{-- $bodyHtml is rendered server-side by DocumentRenderer from a closed, PHP-declared node/
     mark catalog, at write time -- never client-supplied HTML sanitized on the way out. This
     is the one place in this view auto-escaping is deliberately off; DocumentRenderer's own
     correctness is what keeps it safe, see its docblock. --}}
<div class="kop-content">{!! $bodyHtml !!}</div>
