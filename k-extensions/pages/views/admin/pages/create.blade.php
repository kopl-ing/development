@extends('kopling-admin::layouts.admin')

@section('content')
    <div class="max-w-2xl flex flex-col gap-6">
        <h1 class="text-2xl font-bold">{{ __('kopling-pages::messages.new_page') }}</h1>

        <form method="POST" action="{{ route('kopling-admin::admin/pages.store') }}" class="flex flex-col gap-4">
            @csrf
            <x-k::form.input :data="['name' => 'title', 'label' => __('kopling-pages::messages.title'), 'value' => old('title')]" />
            <x-k::form.input :data="['name' => 'path', 'label' => __('kopling-pages::messages.path'), 'value' => old('path')]" />
            <x-k::form.text-area :data="['name' => 'meta_description', 'label' => __('kopling-pages::messages.meta_description'), 'value' => old('meta_description')]" />
            <x-k::form.toggle :data="['name' => 'published', 'label' => __('kopling-pages::messages.published'), 'value' => old('published')]" />
            <x-k::form.toggle :data="['name' => 'show_in_nav', 'label' => __('kopling-pages::messages.show_in_nav'), 'value' => old('show_in_nav')]" />
            <x-k::form.input :data="['name' => 'nav_order', 'label' => __('kopling-pages::messages.nav_order'), 'type' => 'number', 'value' => old('nav_order', 0)]" />
            <x-k::form.toggle :data="['name' => 'is_index', 'label' => __('kopling-pages::messages.is_index'), 'value' => old('is_index')]" />
            <button type="submit" class="btn btn-primary">{{ __('kopling-pages::messages.create_page') }}</button>
        </form>
    </div>
@endsection
