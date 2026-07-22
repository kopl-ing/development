@extends('kopling-docs::layouts.docs')

@section('title', $page->title ?? __('kopling-docs::messages.title'))

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-10">
        @if ($page === null)
            <p class="opacity-60">{{ __('kopling-docs::messages.no_pages_synced') }}</p>
        @else
            <h1 class="text-3xl font-bold mb-6">{{ $page->title }}</h1>
            <div class="prose max-w-none">
                {!! $page->content_html !!}
            </div>
        @endif
    </div>
@endsection
