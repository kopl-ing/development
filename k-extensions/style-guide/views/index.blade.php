@extends('kopling-style-guide::layouts.style-guide')

@section('content')
    <div>
        <h1 class="text-3xl font-bold">{{ __('kopling-style-guide::messages.title') }}</h1>
        <p class="text-base-content/70 mt-2">
            Every {{ '<x-k::*>' }} component core ships, in one place. See
            {{ 'tests/Feature/StyleGuide/ComponentCoverageTest.php' }} for what keeps this page
            honest as core's component inventory grows.
        </p>
    </div>

    @include('kopling-style-guide::sections.tokens')
    @include('kopling-style-guide::sections.forms')
    @include('kopling-style-guide::sections.actions')
    @include('kopling-style-guide::sections.editor')
    @include('kopling-style-guide::sections.card')
    @include('kopling-style-guide::sections.compose')
@endsection
