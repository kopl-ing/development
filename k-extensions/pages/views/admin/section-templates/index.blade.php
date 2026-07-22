@extends('kopling-admin::layouts.admin')

@section('content')
    <div class="max-w-4xl flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ __('kopling-pages::messages.section_templates') }}</h1>
            <x-k::modal id="template-create" label="{{ __('kopling-pages::messages.new_template') }}">
                <x-slot:trigger>{{ __('kopling-pages::messages.new_template') }}</x-slot:trigger>
                @if ($errors->any() && old('_form') === 'template-create')
                    <div class="alert alert-error mb-4">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('kopling-admin::admin/section-templates.store') }}" class="flex flex-col gap-4">
                    @csrf
                    <input type="hidden" name="_form" value="template-create">
                    <x-k::form.input :data="['name' => 'name', 'label' => __('kopling-pages::messages.template_name'), 'value' => old('name')]" />
                    <x-k::form.text-area :data="['name' => 'blade_source', 'label' => __('kopling-pages::messages.blade_source'), 'description' => __('kopling-pages::messages.blade_source_help'), 'value' => old('blade_source'), 'rows' => 10]" />
                    <x-k::form.text-area :data="['name' => 'slots', 'label' => __('kopling-pages::messages.slots_json'), 'description' => __('kopling-pages::messages.slots_json_help'), 'value' => old('slots', '[]'), 'rows' => 4]" />
                    <button type="submit" class="btn btn-primary">{{ __('kopling-pages::messages.create_template') }}</button>
                </form>
            </x-k::modal>
        </div>

        @if ($templates->isEmpty())
            <p class="opacity-60">{{ __('kopling-pages::messages.no_templates') }}</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('kopling-pages::messages.template_name') }}</th>
                        <th>{{ __('kopling-pages::messages.slots') }}</th>
                        <th>{{ __('kopling-pages::messages.in_use') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($templates as $template)
                        <tr>
                            <td>{{ $template->name }}</td>
                            <td class="flex gap-1 flex-wrap">
                                @foreach ($template->slots as $slot)
                                    <span class="badge badge-ghost badge-sm">{{ $slot['name'] }}: {{ $slot['type'] }}</span>
                                @endforeach
                            </td>
                            <td>{{ $template->sections_count }}</td>
                            <td class="flex gap-2">
                                <x-k::modal id="template-edit-{{ $template->id }}" label="{{ __('kopling-pages::messages.edit_template') }}">
                                    <x-slot:trigger>{{ __('kopling-admin::messages.edit') }}</x-slot:trigger>
                                    @if ($errors->any() && old('_form') === 'template-edit-'.$template->id)
                                        <div class="alert alert-error mb-4">{{ $errors->first() }}</div>
                                    @endif
                                    <form method="POST" action="{{ route('kopling-admin::admin/section-templates.update', $template) }}" class="flex flex-col gap-4">
                                        @csrf
                                        <input type="hidden" name="_form" value="template-edit-{{ $template->id }}">
                                        <x-k::form.input :data="['name' => 'name', 'label' => __('kopling-pages::messages.template_name'), 'value' => $template->name]" />
                                        <x-k::form.text-area :data="['name' => 'blade_source', 'label' => __('kopling-pages::messages.blade_source'), 'description' => __('kopling-pages::messages.blade_source_help'), 'value' => $template->blade_source, 'rows' => 10]" />
                                        <x-k::form.text-area :data="['name' => 'slots', 'label' => __('kopling-pages::messages.slots_json'), 'description' => __('kopling-pages::messages.slots_json_help'), 'value' => json_encode($template->slots), 'rows' => 4]" />
                                        <button type="submit" class="btn btn-primary">{{ __('kopling-admin::messages.save') }}</button>
                                    </form>
                                </x-k::modal>
                                <form method="POST" action="{{ route('kopling-admin::admin/section-templates.destroy', $template) }}"
                                      onsubmit="return confirm('{{ __('kopling-pages::messages.confirm_delete_template') }}')">
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
