@extends('kopling-admin::layouts.admin')

@php
    $driverOptions = ['local' => 'Local', 's3' => 'S3'];
@endphp

@section('content')
    <div class="max-w-4xl flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ __('kopling-admin::messages.drives') }}</h1>
            <x-k::modal id="drive-create" label="{{ __('kopling-admin::messages.new_drive') }}">
                <x-slot:trigger>{{ __('kopling-admin::messages.new_drive') }}</x-slot:trigger>
                @if ($errors->any() && old('_form') === 'drive-create')
                    <div class="alert alert-error mb-4">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('kopling-admin::admin/drives.store') }}" class="flex flex-col gap-4">
                    @csrf
                    <input type="hidden" name="_form" value="drive-create">
                    <x-k::form.input :data="['name' => 'name', 'label' => __('kopling-admin::messages.name'), 'value' => old('name')]" />
                    <x-k::form.select :data="['name' => 'driver', 'label' => __('kopling-admin::messages.driver'), 'options' => $driverOptions, 'value' => old('driver', 'local')]" />
                    <x-k::form.text-area :data="['name' => 'settings', 'label' => __('kopling-admin::messages.settings_json'), 'description' => __('kopling-admin::messages.settings_json_help'), 'value' => old('settings', '{}'), 'rows' => 4]" />
                    <x-k::form.toggle :data="['name' => 'supports_public', 'label' => __('kopling-admin::messages.supports_public'), 'value' => old('supports_public')]" />
                    <x-k::form.toggle :data="['name' => 'supports_signed', 'label' => __('kopling-admin::messages.supports_signed'), 'value' => old('supports_signed')]" />
                    <x-k::form.toggle :data="['name' => 'writable', 'label' => __('kopling-admin::messages.writable'), 'value' => old('writable', true)]" />
                    <button type="submit" class="btn btn-primary">{{ __('kopling-admin::messages.create_drive') }}</button>
                </form>
            </x-k::modal>
        </div>

        @if ($drives->isEmpty())
            <p class="opacity-60">{{ __('kopling-admin::messages.no_drives') }}</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('kopling-admin::messages.name') }}</th>
                        <th>{{ __('kopling-admin::messages.driver') }}</th>
                        <th>{{ __('kopling-admin::messages.capabilities') }}</th>
                        <th>{{ __('kopling-admin::messages.in_use') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($drives as $drive)
                        <tr>
                            <td>{{ $drive->name }}</td>
                            <td><span class="badge badge-ghost">{{ $drive->driver }}</span></td>
                            <td class="flex gap-1 flex-wrap">
                                @if (! $drive->enabled)
                                    <span class="badge badge-warning">{{ __('kopling-admin::messages.disabled') }}</span>
                                @endif
                                @if ($drive->supports_public)
                                    <span class="badge badge-outline">{{ __('kopling-admin::messages.supports_public') }}</span>
                                @endif
                                @if ($drive->supports_signed)
                                    <span class="badge badge-outline">{{ __('kopling-admin::messages.supports_signed') }}</span>
                                @endif
                                @if ($drive->writable)
                                    <span class="badge badge-outline">{{ __('kopling-admin::messages.writable') }}</span>
                                @endif
                            </td>
                            <td>{{ $drive->mappings_count }}</td>
                            <td class="flex gap-2">
                                <x-k::modal id="drive-edit-{{ $drive->id }}" label="{{ __('kopling-admin::messages.edit_drive') }}">
                                    <x-slot:trigger>{{ __('kopling-admin::messages.edit') }}</x-slot:trigger>
                                    @if ($errors->any() && old('_form') === 'drive-edit-'.$drive->id)
                                        <div class="alert alert-error mb-4">{{ $errors->first() }}</div>
                                    @endif
                                    <form method="POST" action="{{ route('kopling-admin::admin/drives.update', $drive) }}" class="flex flex-col gap-4">
                                        @csrf
                                        <input type="hidden" name="_form" value="drive-edit-{{ $drive->id }}">
                                        <x-k::form.input :data="['name' => 'name', 'label' => __('kopling-admin::messages.name'), 'value' => $drive->name]" />
                                        <x-k::form.select :data="['name' => 'driver', 'label' => __('kopling-admin::messages.driver'), 'options' => $driverOptions, 'value' => $drive->driver]" />
                                        <x-k::form.text-area :data="['name' => 'settings', 'label' => __('kopling-admin::messages.settings_json'), 'description' => __('kopling-admin::messages.settings_json_help'), 'value' => json_encode($drive->settings), 'rows' => 4]" />
                                        <x-k::form.toggle :data="['name' => 'supports_public', 'label' => __('kopling-admin::messages.supports_public'), 'value' => $drive->supports_public]" />
                                        <x-k::form.toggle :data="['name' => 'supports_signed', 'label' => __('kopling-admin::messages.supports_signed'), 'value' => $drive->supports_signed]" />
                                        <x-k::form.toggle :data="['name' => 'writable', 'label' => __('kopling-admin::messages.writable'), 'value' => $drive->writable]" />
                                        <x-k::form.toggle :data="['name' => 'enabled', 'label' => __('kopling-admin::messages.enabled'), 'value' => $drive->enabled]" />
                                        <button type="submit" class="btn btn-primary">{{ __('kopling-admin::messages.save') }}</button>
                                    </form>
                                </x-k::modal>
                                <form method="POST" action="{{ route('kopling-admin::admin/drives.destroy', $drive) }}"
                                      onsubmit="return confirm('{{ __('kopling-admin::messages.confirm_delete_drive') }}')">
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
