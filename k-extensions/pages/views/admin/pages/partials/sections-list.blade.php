<div id="page-sections-list">
    @if ($sections->isEmpty())
        <p class="opacity-60">{{ __('kopling-pages::messages.no_sections') }}</p>
    @else
        <div class="flex flex-col gap-3">
            @foreach ($sections as $section)
                <div class="card bg-base-100 border border-base-300 p-4 flex flex-row items-center justify-between gap-4">
                    <span class="badge badge-ghost badge-sm">{{ $section->template->name }}</span>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('kopling-admin::admin/pages.sections.move', [$page, $section]) }}"
                              hx-post="{{ route('kopling-admin::admin/pages.sections.move', [$page, $section]) }}"
                              hx-target="#page-sections-list" hx-swap="outerHTML">
                            @csrf
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="btn btn-sm btn-ghost">{{ __('kopling-pages::messages.move_up') }}</button>
                        </form>
                        <form method="POST" action="{{ route('kopling-admin::admin/pages.sections.move', [$page, $section]) }}"
                              hx-post="{{ route('kopling-admin::admin/pages.sections.move', [$page, $section]) }}"
                              hx-target="#page-sections-list" hx-swap="outerHTML">
                            @csrf
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="btn btn-sm btn-ghost">{{ __('kopling-pages::messages.move_down') }}</button>
                        </form>

                        <x-k::modal id="section-edit-{{ $section->id }}" label="{{ __('kopling-admin::messages.edit') }}">
                            <x-slot:trigger>{{ __('kopling-admin::messages.edit') }}</x-slot:trigger>
                            <form method="POST" action="{{ route('kopling-admin::admin/pages.sections.update', [$page, $section]) }}" hx-boost="true" class="flex flex-col gap-4">
                                @csrf
                                @foreach ($section->template->slots as $slot)
                                    @if ($slot['type'] === 'wysiwyg')
                                        <x-k::editor :name="$slot['name']" :value="$section->data[$slot['name']]['json'] ?? null" placeholder="{{ $slot['label'] }}" />
                                    @else
                                        <x-k::form.input :data="['name' => $slot['name'], 'label' => $slot['label'], 'value' => $section->data[$slot['name']] ?? '']" />
                                    @endif
                                @endforeach
                                <button type="submit" class="btn btn-primary">{{ __('kopling-admin::messages.save') }}</button>
                            </form>
                        </x-k::modal>

                        <form method="POST" action="{{ route('kopling-admin::admin/pages.sections.destroy', [$page, $section]) }}"
                              hx-boost="true" hx-confirm="{{ __('kopling-pages::messages.confirm_delete_section') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('kopling-pages::messages.delete') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
