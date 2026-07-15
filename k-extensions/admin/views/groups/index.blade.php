@extends('kopling-admin::layouts.admin')

@section('content')
    <div class="max-w-3xl flex flex-col gap-6">
        <h1 class="text-2xl font-bold">{{ __('kopling-admin::messages.groups') }}</h1>

        <form method="POST" action="{{ route('kopling-admin::admin/groups.store') }}" class="flex gap-2">
            @csrf
            <input type="text" name="name" placeholder="{{ __('kopling-admin::messages.new_group_name') }}"
                   class="input" required>
            <button type="submit" class="btn btn-primary">{{ __('kopling-admin::messages.create_group') }}</button>
        </form>

        @if ($groups->isEmpty())
            <p class="opacity-60">{{ __('kopling-admin::messages.no_groups') }}</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('kopling-admin::messages.name') }}</th>
                        <th>{{ __('kopling-admin::messages.people') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $group)
                        <tr>
                            <td>
                                <form method="POST" action="{{ route('kopling-admin::admin/groups.update', $group) }}" class="flex gap-2">
                                    @csrf
                                    <input type="text" name="name" value="{{ $group->name }}" class="input input-sm">
                                    <button type="submit" class="btn btn-sm">{{ __('kopling-admin::messages.rename') }}</button>
                                </form>
                            </td>
                            <td>{{ $group->people_count }}</td>
                            <td>
                                <form method="POST" action="{{ route('kopling-admin::admin/groups.destroy', $group) }}"
                                      onsubmit="return confirm('{{ __('kopling-admin::messages.confirm_delete_group') }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('kopling-admin::messages.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
