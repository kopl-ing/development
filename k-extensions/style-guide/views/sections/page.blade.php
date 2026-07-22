{{--
    Paginates the real `Moment` table -- shows real page controls once there are enough rows to
    need them, renders nothing otherwise (see `Context::getSubjectPaginator()`/`hasPages()`), same
    "shows the real live state" reasoning `compose.blade.php`/`editor.blade.php` already follow.
--}}
<section id="page" class="flex flex-col gap-6">
    <h2 class="text-xl font-semibold border-b border-base-300 pb-2">
        {{ __('kopling-style-guide::messages.page') }}
    </h2>

    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.page.pagination', 'label' => 'Pagination'])
</section>
