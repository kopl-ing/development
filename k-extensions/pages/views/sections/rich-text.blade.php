{{-- $section->content_html is rendered server-side at write time through the same
     Ux\Editor\DocumentRenderer whitelist Moment::$body/$body_html already uses -- never trusted
     directly from admin input, see PageSectionsController. --}}
<div class="prose max-w-none">
    {!! $section->content_html !!}
</div>
