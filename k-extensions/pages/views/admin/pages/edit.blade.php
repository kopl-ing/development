@extends('kopling-admin::layouts.admin')

@section('content')
    <div class="max-w-3xl flex flex-col gap-10">
        <div>
            <h1 class="text-2xl font-bold mb-6">{{ __('kopling-pages::messages.edit_page') }}</h1>

            <form method="POST" action="{{ route('kopling-admin::admin/pages.update', $page) }}" class="flex flex-col gap-4">
                @csrf
                <x-k::form.input :data="['name' => 'title', 'label' => __('kopling-pages::messages.title'), 'value' => $page->title]" />
                <x-k::form.input :data="['name' => 'path', 'label' => __('kopling-pages::messages.path'), 'value' => $page->path]" />
                <x-k::form.text-area :data="['name' => 'meta_description', 'label' => __('kopling-pages::messages.meta_description'), 'value' => $page->meta_description]" />
                <x-k::form.toggle :data="['name' => 'published', 'label' => __('kopling-pages::messages.published'), 'value' => $page->published]" />
                <x-k::form.toggle :data="['name' => 'show_in_nav', 'label' => __('kopling-pages::messages.show_in_nav'), 'value' => $page->show_in_nav]" />
                <x-k::form.input :data="['name' => 'nav_order', 'label' => __('kopling-pages::messages.nav_order'), 'type' => 'number', 'value' => $page->nav_order]" />
                <x-k::form.toggle :data="['name' => 'is_index', 'label' => __('kopling-pages::messages.is_index'), 'value' => $page->is_index]" />
                <button type="submit" class="btn btn-primary self-start">{{ __('kopling-admin::messages.save') }}</button>
            </form>
        </div>

        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">{{ __('kopling-pages::messages.sections') }}</h2>
                <div class="flex gap-2">
                    <x-k::modal id="section-create-rich-text" label="{{ __('kopling-pages::messages.kind_rich_text') }}">
                        <x-slot:trigger>{{ __('kopling-pages::messages.add_section') }}: {{ __('kopling-pages::messages.kind_rich_text') }}</x-slot:trigger>
                        <form method="POST" action="{{ route('kopling-admin::admin/pages.sections.store', $page) }}" class="flex flex-col gap-4">
                            @csrf
                            <input type="hidden" name="kind" value="rich-text">
                            <x-k::editor name="content" placeholder="{{ __('kopling-pages::messages.content') }}" />
                            <button type="submit" class="btn btn-primary">{{ __('kopling-pages::messages.add_section') }}</button>
                        </form>
                    </x-k::modal>
                    <x-k::modal id="section-create-hero" label="{{ __('kopling-pages::messages.kind_hero') }}">
                        <x-slot:trigger>{{ __('kopling-pages::messages.add_section') }}: {{ __('kopling-pages::messages.kind_hero') }}</x-slot:trigger>
                        <form method="POST" action="{{ route('kopling-admin::admin/pages.sections.store', $page) }}" class="flex flex-col gap-4">
                            @csrf
                            <input type="hidden" name="kind" value="hero">
                            <x-k::form.input :data="['name' => 'subtitle', 'label' => __('kopling-pages::messages.hero_subtitle')]" />
                            <x-k::form.input :data="['name' => 'cta_label', 'label' => __('kopling-pages::messages.hero_cta_label')]" />
                            <x-k::form.input :data="['name' => 'cta_url', 'label' => __('kopling-pages::messages.hero_cta_url')]" />
                            <button type="submit" class="btn btn-primary">{{ __('kopling-pages::messages.add_section') }}</button>
                        </form>
                    </x-k::modal>
                </div>
            </div>

            @if ($sections->isEmpty())
                <p class="opacity-60">{{ __('kopling-pages::messages.no_sections') }}</p>
            @else
                <div class="flex flex-col gap-3">
                    @foreach ($sections as $section)
                        <div class="card bg-base-100 border border-base-300 p-4 flex flex-row items-center justify-between gap-4">
                            <div>
                                <span class="badge badge-ghost badge-sm">
                                    {{ $section->kind === 'rich-text' ? __('kopling-pages::messages.kind_rich_text') : __('kopling-pages::messages.kind_hero') }}
                                </span>
                                @if ($section->kind === 'hero')
                                    <span class="ml-2 text-sm opacity-70">{{ $section->data['subtitle'] ?? '' }}</span>
                                @endif
                            </div>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('kopling-admin::admin/pages.sections.move', [$page, $section]) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" class="btn btn-sm btn-ghost">{{ __('kopling-pages::messages.move_up') }}</button>
                                </form>
                                <form method="POST" action="{{ route('kopling-admin::admin/pages.sections.move', [$page, $section]) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" class="btn btn-sm btn-ghost">{{ __('kopling-pages::messages.move_down') }}</button>
                                </form>

                                <x-k::modal id="section-edit-{{ $section->id }}" label="{{ __('kopling-admin::messages.edit') }}">
                                    <x-slot:trigger>{{ __('kopling-admin::messages.edit') }}</x-slot:trigger>
                                    <form method="POST" action="{{ route('kopling-admin::admin/pages.sections.update', [$page, $section]) }}" class="flex flex-col gap-4">
                                        @csrf
                                        @if ($section->kind === 'rich-text')
                                            <x-k::editor name="content" :value="$section->content" placeholder="{{ __('kopling-pages::messages.content') }}" />
                                        @else
                                            <x-k::form.input :data="['name' => 'subtitle', 'label' => __('kopling-pages::messages.hero_subtitle'), 'value' => $section->data['subtitle'] ?? '']" />
                                            <x-k::form.input :data="['name' => 'cta_label', 'label' => __('kopling-pages::messages.hero_cta_label'), 'value' => $section->data['cta_label'] ?? '']" />
                                            <x-k::form.input :data="['name' => 'cta_url', 'label' => __('kopling-pages::messages.hero_cta_url'), 'value' => $section->data['cta_url'] ?? '']" />
                                        @endif
                                        <button type="submit" class="btn btn-primary">{{ __('kopling-admin::messages.save') }}</button>
                                    </form>
                                </x-k::modal>

                                <form method="POST" action="{{ route('kopling-admin::admin/pages.sections.destroy', [$page, $section]) }}"
                                      onsubmit="return confirm('{{ __('kopling-pages::messages.confirm_delete_section') }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('kopling-pages::messages.delete') }}</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
