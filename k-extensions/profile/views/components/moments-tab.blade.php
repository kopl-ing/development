@props(['data' => [], 'context' => null, 'checked' => false])
@php use Kopling\Core\Ux\Context; @endphp
@php
    $person = $context?->getSubject();
    $momentsContext = new Context(subject: $person->moments()->getQuery());
    $moments = $momentsContext->getSubjectPaginator();
@endphp
<label class="tab bg-base-200 hover:bg-base-300">
    <input type="radio" name="posts" @checked($checked) />
    @choice('kopling-core::community.moments', $moments->total())
</label>
<div class="tab-content bg-base-200 border-base-300 p-6">
    <div id="moments-feed" class="flex flex-col gap-4 sm:gap-8">
        @foreach ($moments as $moment)
            @include('kopling-core::community.moment', ['moment' => $moment])
        @endforeach

        <x-k::page.pagination :context="$momentsContext" target="#moments-feed" />
    </div>
</div>
