@extends('kopling-admin::layouts.admin')

@section('content')
    <div class="max-w-2xl">
        <h1 class="text-2xl font-bold mb-6">{{ __('kopling-admin::messages.settings') }}</h1>

        @if ($sections->isEmpty())
            <p class="opacity-60">{{ __('kopling-admin::messages.no_settings') }}</p>
        @else
            <form method="POST" action="{{ route('kopling-admin::admin/settings.store') }}" class="flex flex-col gap-8">
                @csrf

                @foreach ($sections as $section)
                    <section class="flex flex-col gap-4">
                        <h2 class="text-lg font-semibold">{{ $section['label'] }}</h2>

                        @foreach ($section['fields'] as $entry)
                            <x-dynamic-component
                                :component="$entry['field']->component"
                                :data="array_merge($entry['field']->data, [
                                    'name' => $entry['field']->id,
                                    'label' => $entry['field']->label,
                                    'description' => $entry['field']->description,
                                    'value' => $entry['value'],
                                ])"
                            />
                        @endforeach
                    </section>
                @endforeach

                <button type="submit" class="btn btn-primary self-start">{{ __('kopling-admin::messages.save') }}</button>
            </form>
        @endif
    </div>
@endsection
