@php
    use Illuminate\Support\Str;
    use Kopling\Core\Content\Moment;

    // Only on a discussion page — the discussions.show route binds {moment} to a Moment; the
    // feed's route has none, so this renders nothing there.
    $moment = request()->route('moment');
@endphp

@if ($moment instanceof Moment)
    @php
        $title = trim((string) $moment->title) !== ''
            ? $moment->title
            : Str::limit(trim(strip_tags((string) $moment->body)), 60);
    @endphp

    {{-- Hidden until you scroll past the OP, then eases in as an absolute overlay that covers
         the header and centers the title (see css/app.css). Alpine + scroll only. --}}
    <div x-data="{ shown: false }"
         x-init="const check = () => shown = window.scrollY > 220; check(); window.addEventListener('scroll', check, { passive: true })"
         x-show="shown" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         class="kop-thread">
        <svg class="kop-thread__icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        <a href="{{ route('kopling-core::community/discussions.show', $moment->id) }}"
           class="kop-thread__link" title="{{ $title }}">{{ $title }}</a>
    </div>
@endif
