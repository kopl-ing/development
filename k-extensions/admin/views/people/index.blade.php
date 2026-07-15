@extends('kopling-admin::layouts.admin')

@section('content')
    <div class="max-w-3xl">
        <h1 class="text-2xl font-bold mb-6">{{ __('kopling-admin::messages.people') }}</h1>

        @if ($people->isEmpty())
            <p class="opacity-60">{{ __('kopling-admin::messages.no_people') }}</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('kopling-admin::messages.name') }}</th>
                        <th>{{ __('kopling-admin::messages.email') }}</th>
                        <th>{{ __('kopling-admin::messages.groups') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($people as $person)
                        <tr>
                            <td>{{ $person->name }}</td>
                            <td>{{ $person->email }}</td>
                            <td>{{ $person->groups->pluck('name')->join(', ') }}</td>
                            <td>
                                <x-k::modal :label="__('kopling-admin::messages.manage_groups')">
                                    <x-slot:trigger>{{ __('kopling-admin::messages.manage_groups') }}</x-slot:trigger>
                                    <form method="POST" action="{{ route('kopling-admin::admin/people.groups', $person) }}" class="flex flex-col gap-4">
                                        @csrf
                                        <h2 class="text-lg font-semibold">{{ $person->name }}</h2>
                                        <x-k::form.multi-select :data="[
                                            'name' => 'groups',
                                            'label' => __('kopling-admin::messages.groups'),
                                            'options' => $groups->pluck('name', 'id'),
                                            'value' => $person->groups->pluck('id'),
                                        ]" />
                                        <button type="submit" class="btn btn-primary self-start">{{ __('kopling-admin::messages.save') }}</button>
                                    </form>
                                </x-k::modal>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
