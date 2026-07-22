@extends('kopling-admin::layouts.admin')

@section('content')
    <div class="max-w-4xl flex flex-col gap-6">
        <h1 class="text-2xl font-bold">{{ __('kopling-admin::messages.storage') }}</h1>

        @if ($rows->isEmpty())
            <p class="opacity-60">{{ __('kopling-admin::messages.no_storage_requests') }}</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('kopling-admin::messages.purpose') }}</th>
                        <th>{{ __('kopling-admin::messages.access') }}</th>
                        <th>{{ __('kopling-admin::messages.permission') }}</th>
                        <th>{{ __('kopling-admin::messages.drive') }}</th>
                        <th>{{ __('kopling-admin::messages.prefix') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $request = $row['request'];
                            $mapping = $row['mapping'];
                            $stale = $mapping && (! $mapping->drive || ! $mapping->drive->enabled);
                        @endphp
                        <tr>
                            <td>
                                <div class="font-medium">{{ $request->label }}</div>
                                <div class="text-xs opacity-60">{{ $request->id }}</div>
                                @if ($stale)
                                    <span class="badge badge-warning badge-sm mt-1">{{ __('kopling-admin::messages.drive_disabled') }}</span>
                                @endif
                            </td>
                            <td><span class="badge badge-ghost">{{ $request->access->value }}</span></td>
                            <td><span class="badge badge-ghost">{{ $request->permission->value }}</span></td>
                            <td colspan="3">
                                <form method="POST" action="{{ route('kopling-admin::admin/storage.store') }}" class="flex gap-2 items-center">
                                    @csrf
                                    <input type="hidden" name="request_id" value="{{ $request->id }}">
                                    <select name="drive_id" class="select select-sm" required>
                                        <option value="" disabled @selected(! $mapping)>{{ __('kopling-admin::messages.choose_drive') }}</option>
                                        @foreach ($row['eligibleDrives'] as $drive)
                                            <option value="{{ $drive->id }}" @selected($mapping && $mapping->drive_id === $drive->id)>{{ $drive->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="prefix" value="{{ $mapping->prefix ?? '' }}"
                                           placeholder="{{ __('kopling-admin::messages.prefix') }}" class="input input-sm w-32">
                                    <button type="submit" class="btn btn-sm btn-primary">{{ __('kopling-admin::messages.save') }}</button>
                                    @if ($mapping)
                                        <button type="submit" formaction="{{ route('kopling-admin::admin/storage.destroy') }}" class="btn btn-sm btn-ghost">
                                            {{ __('kopling-admin::messages.unmap') }}
                                        </button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($orphaned->isNotEmpty())
            <div class="flex flex-col gap-2">
                <h2 class="text-lg font-semibold">{{ __('kopling-admin::messages.orphaned_mappings') }}</h2>
                <p class="opacity-60 text-sm">{{ __('kopling-admin::messages.orphaned_mappings_help') }}</p>
                <table class="table">
                    <tbody>
                        @foreach ($orphaned as $mapping)
                            <tr>
                                <td class="font-mono text-xs">{{ $mapping->request_id }}</td>
                                <td>{{ $mapping->drive->name ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('kopling-admin::admin/storage.destroy') }}">
                                        @csrf
                                        <input type="hidden" name="request_id" value="{{ $mapping->request_id }}">
                                        <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('kopling-admin::messages.delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
