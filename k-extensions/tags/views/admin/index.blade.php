@extends('kopling-admin::layouts.admin')
@php use Kopling\Core\Ux\Context; @endphp

@section('content')
    @php
        // Which dialog's own fields should show old() values rather than the tag's actual
        // current ones -- `<x-k::modal>` now handles *re-opening* itself internally (any modal
        // whose own `$id` matches a hidden `_form` field's round-tripped `old()` value self-
        // opens, see Ux/Modal.php), but it has no way to know what fields live inside its own
        // slot or how to prefill them -- that part stays this view's own job. The value compared
        // against is each modal's own full `$id` (e.g. "modal-tag-create"), the exact same
        // string each form's hidden `_form` input carries, so both concerns read one shared
        // convention rather than two related-but-different strings. Any extension filling the
        // `kopling-tags::admin.tag-form` slot below (see `Context::$subject` carrying the tag
        // being edited, null on create) recomputes this same convention itself from that context
        // -- e.g. `reactions`' own vote-emoji fields -- so its old()/reopen behaviour matches
        // these fields' without tags needing to pass anything extra through.
        $reopening = old('_form');
    @endphp
    <div class="max-w-4xl flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ __('kopling-tags::messages.admin_tags') }}</h1>

            <x-k::modal :label="__('kopling-tags::messages.create_tag')" id="modal-tag-create">
                <x-slot:trigger>{{ __('kopling-tags::messages.create_tag') }}</x-slot:trigger>
                <form method="POST" action="{{ route('kopling-admin::admin/tags.store') }}" class="flex flex-col gap-4">
                    @csrf
                    <input type="hidden" name="_form" value="modal-tag-create">
                    <h2 class="text-lg font-semibold">{{ __('kopling-tags::messages.create_tag') }}</h2>
                    <x-k::form.input :data="['name' => 'name', 'label' => __('kopling-tags::messages.name'), 'value' => $reopening === 'modal-tag-create' ? old('name') : '']" />
                    <x-k::form.input :data="['name' => 'slug', 'label' => __('kopling-tags::messages.slug'), 'value' => $reopening === 'modal-tag-create' ? old('slug') : '']" />
                    <x-k::form.input :data="['name' => 'color', 'label' => __('kopling-tags::messages.color'), 'value' => $reopening === 'modal-tag-create' ? old('color') : '']" />
                    {{-- No :context on create -- Context::getSubject() requires a real subject
                         or a query Builder, neither of which exists yet for a brand new tag.
                         Omitting :context leaves every resolved entry's own $context null, and
                         tag-vote-fields.blade.php's `$context?->getSubject()` short-circuits on
                         that cleanly rather than ever calling getSubject() on a null subject. --}}
                    <x-k::portal.slot name="kopling-tags::admin.tag-form" />
                    @if ($reopening === 'modal-tag-create' && $errors->any())
                        <p class="text-error text-sm">{{ $errors->first() }}</p>
                    @endif
                    <button type="submit" class="btn btn-primary self-start">{{ __('kopling-tags::messages.save') }}</button>
                </form>
            </x-k::modal>
        </div>

        @if ($tags->isEmpty())
            <p class="opacity-60">{{ __('kopling-tags::messages.no_tags') }}</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('kopling-tags::messages.name') }}</th>
                        <th>{{ __('kopling-tags::messages.slug') }}</th>
                        <th>{{ __('kopling-tags::messages.color') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tags as $tag)
                        @php $modalId = 'modal-tag-edit-'.$tag->id; @endphp
                        <tr>
                            <td>{{ $tag->name }}</td>
                            <td>{{ $tag->slug }}</td>
                            <td>
                                @if ($tag->color)
                                    <span class="badge badge-sm" style="background-color:{{ $tag->color }};border-color:{{ $tag->color }};color:#fff">{{ $tag->color }}</span>
                                @endif
                            </td>
                            <td class="flex gap-2">
                                <x-k::modal :label="__('kopling-tags::messages.edit')" :id="$modalId">
                                    <x-slot:trigger>{{ __('kopling-tags::messages.edit') }}</x-slot:trigger>
                                    <form method="POST" action="{{ route('kopling-admin::admin/tags.update', $tag) }}" class="flex flex-col gap-4">
                                        @csrf
                                        <input type="hidden" name="_form" value="{{ $modalId }}">
                                        <h2 class="text-lg font-semibold">{{ $tag->name }}</h2>
                                        <x-k::form.input :data="['name' => 'name', 'label' => __('kopling-tags::messages.name'), 'value' => $reopening === $modalId ? old('name') : $tag->name]" />
                                        <x-k::form.input :data="['name' => 'slug', 'label' => __('kopling-tags::messages.slug'), 'value' => $reopening === $modalId ? old('slug') : $tag->slug]" />
                                        <x-k::form.input :data="['name' => 'color', 'label' => __('kopling-tags::messages.color'), 'value' => $reopening === $modalId ? old('color') : $tag->color]" />
                                        <x-k::portal.slot name="kopling-tags::admin.tag-form" :context="new Context(subject: $tag)" />
                                        @if ($reopening === $modalId && $errors->any())
                                            <p class="text-error text-sm">{{ $errors->first() }}</p>
                                        @endif
                                        <button type="submit" class="btn btn-primary self-start">{{ __('kopling-tags::messages.save') }}</button>
                                    </form>
                                </x-k::modal>
                                <form method="POST" action="{{ route('kopling-admin::admin/tags.destroy', $tag) }}"
                                      onsubmit="return confirm('{{ __('kopling-tags::messages.confirm_delete_tag') }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('kopling-tags::messages.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
