@extends('kopling-admin::layouts.admin')

@section('content')
    <div class="max-w-3xl">
        <h1 class="text-2xl font-bold mb-6">{{ __('kopling-admin::messages.settings') }}</h1>

        @if ($extensions->isEmpty())
            <p class="opacity-60">{{ __('kopling-admin::messages.no_extensions') }}</p>
        @else
            <form method="POST" action="{{ route('kopling-admin::admin/settings.store') }}" class="flex flex-col gap-4">
                @csrf
                @foreach ($extensions as $extension)
                    <x-kopling-admin::settings.partials.card :extension="$extension" />
                @endforeach
                <button type="submit" class="btn btn-primary self-start">{{ __('kopling-admin::messages.save') }}</button>
            </form>
        @endif
    </div>
@endsection
