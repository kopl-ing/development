@extends('kopling-admin::layouts.admin')

@section('content')
    <div class="max-w-3xl flex flex-col gap-6">
        <h1 class="text-2xl font-bold">{{ __('kopling-admin::messages.portals') }}</h1>

        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('kopling-admin::messages.portal') }}</th>
                    <th>{{ __('kopling-admin::messages.default_path') }}</th>
                    <th>{{ __('kopling-admin::messages.path') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($portals as $portal)
                    @php $overridden = $portal->path !== $portal->defaultPath; @endphp
                    <tr>
                        <td>
                            <div class="font-medium">{{ $portal->label }}</div>
                            <div class="text-xs opacity-60">{{ $portal->id }}</div>
                        </td>
                        <td class="font-mono text-xs">/{{ $portal->defaultPath }}</td>
                        <td>
                            @if ($errors->any() && old('_form') === 'portal-'.$portal->id)
                                <div class="alert alert-error alert-sm mb-2">{{ $errors->first('path') }}</div>
                            @endif
                            <form method="POST" action="{{ route('kopling-admin::admin/portals.update') }}" class="flex gap-2 items-center">
                                @csrf
                                <input type="hidden" name="_form" value="portal-{{ $portal->id }}">
                                <input type="hidden" name="id" value="{{ $portal->id }}">
                                <span class="opacity-60">/</span>
                                <input type="text" name="path" value="{{ $portal->path }}" class="input input-sm">
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('kopling-admin::messages.save') }}</button>
                            </form>
                        </td>
                        <td>
                            @if ($overridden)
                                <span class="badge badge-warning badge-sm">{{ __('kopling-admin::messages.overridden') }}</span>
                                <form method="POST" action="{{ route('kopling-admin::admin/portals.reset') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $portal->id }}">
                                    <button type="submit" class="btn btn-sm btn-ghost">{{ __('kopling-admin::messages.reset_to_default') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
