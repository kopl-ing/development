@extends('kopling-pages::layouts.pages')

@section('title', $page->title)

@section('content')
    @foreach ($sections as $section)
        {!! \Kopling\Pages\SectionRenderer::render($section) !!}
    @endforeach
@endsection
