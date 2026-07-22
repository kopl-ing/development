@extends('kopling-pages::layouts.pages')

@section('title', $page->title)

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-10 flex flex-col gap-12">
        {{-- A hero section already renders the page's own title as its heading (see
             sections/hero.blade.php) -- shown here only when the page doesn't open with one, so
             the title never appears twice. --}}
        @unless ($sections->first()?->kind === \Kopling\Pages\SectionKind::Hero->value)
            <h1 class="text-3xl font-bold">{{ $page->title }}</h1>
        @endunless
        @foreach ($sections as $section)
            @if ($section->kind === \Kopling\Pages\SectionKind::RichText->value)
                @include('kopling-pages::sections.rich-text', ['page' => $page, 'section' => $section])
            @elseif ($section->kind === \Kopling\Pages\SectionKind::Hero->value)
                @include('kopling-pages::sections.hero', ['page' => $page, 'section' => $section])
            @endif
        @endforeach
    </div>
@endsection
