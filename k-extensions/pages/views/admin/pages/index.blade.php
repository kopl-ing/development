@extends('kopling-admin::layouts.admin')

@section('content')
    <div class="max-w-4xl flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ __('kopling-pages::messages.pages') }}</h1>
            <a href="{{ route('kopling-admin::admin/pages.create') }}" class="btn btn-primary">{{ __('kopling-pages::messages.new_page') }}</a>
        </div>

        @if ($pages->isEmpty())
            <p class="opacity-60">{{ __('kopling-pages::messages.no_pages') }}</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('kopling-pages::messages.title') }}</th>
                        <th>{{ __('kopling-pages::messages.path') }}</th>
                        <th>{{ __('kopling-pages::messages.published') }}</th>
                        <th>{{ __('kopling-pages::messages.sections') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        <tr>
                            <td>
                                {{ $page->title }}
                                @if ($page->is_index)
                                    <span class="badge badge-primary badge-sm">{{ __('kopling-pages::messages.is_index') }}</span>
                                @endif
                            </td>
                            <td class="font-mono text-xs">/{{ $page->path }}</td>
                            <td>
                                @if ($page->published)
                                    <span class="badge badge-success badge-sm">{{ __('kopling-pages::messages.published') }}</span>
                                @else
                                    <span class="badge badge-ghost badge-sm">{{ __('kopling-admin::messages.disabled') }}</span>
                                @endif
                            </td>
                            <td>{{ $page->sections_count }}</td>
                            <td class="flex gap-2">
                                <a href="{{ route('kopling-admin::admin/pages.edit', $page) }}" class="btn btn-sm">{{ __('kopling-admin::messages.edit') }}</a>
                                <form method="POST" action="{{ route('kopling-admin::admin/pages.destroy', $page) }}"
                                      onsubmit="return confirm('{{ __('kopling-pages::messages.confirm_delete_page') }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('kopling-pages::messages.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
