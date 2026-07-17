@php
    use Illuminate\Support\Str;
    use Kopling\Core\Content\Moment;
    use Kopling\Core\Ux\Context;
    use Kopling\Core\Ux\Editor\PlainTextExtractor;

    // Only on a discussion page — the discussions.show route binds {moment} to a Moment; the
    // feed's route has none, so this renders nothing there.
    $moment = request()->route('moment');
@endphp

@if ($moment instanceof Moment)
    @php
        // $moment->body is a ProseMirror JSON document, not plain text or HTML -- strip_tags()
        // would either no-op on the raw JSON or mangle it, so the fallback title is extracted
        // via PlainTextExtractor the same way Reply::statsFor()'s word count is.
        $title = trim((string) $moment->title) !== ''
            ? $moment->title
            : Str::limit(trim(PlainTextExtractor::extract((string) $moment->body)), 60);
    @endphp

    {{-- Hidden until you scroll past the OP, then eases in as an absolute overlay that covers
         the header and centers the title (see css/app.css). Alpine + scroll only. --}}
    <div x-data="{ shown: false }"
         x-init="const check = () => shown = window.scrollY > 220; check(); window.addEventListener('scroll', check, { passive: true })"
         x-show="shown" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         class="kop-thread">
        <x-k::icon name="kopling-thread-title::thread" class="kop-thread__icon" width="15" height="15" />
        <a href="{{ (new Context(subject: $moment))->getSubjectUrl() }}"
           class="kop-thread__link" title="{{ $title }}">{{ $title }}</a>
    </div>
@endif
