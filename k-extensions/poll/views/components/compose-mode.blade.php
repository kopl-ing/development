@props(['data' => []])

<div x-data="{ rows: [0, 1], nextRow: 2 }">
    <x-k::form.input :data="[
        'name' => 'poll_question',
        'label' => __('kopling-poll::messages.question_label'),
        'placeholder' => __('kopling-poll::messages.question_placeholder'),
    ]" />

    {{--
        Each row shares the same static `poll_options[]`/`poll_option_emoji[]` names -- Alpine
        clones this template client-side, it doesn't re-render Blade per row, so a per-index
        name isn't reachable here anyway. Browsers submit repeated same-named fields in DOM
        order, so the extension's own `saved()` hook zips the two arrays back together by
        position instead.
    --}}
    <div class="flex flex-col gap-2 mt-2">
        <template x-for="row in rows" :key="row">
            <div class="flex items-center gap-2">
                <x-k::form.emoji-picker :data="['name' => 'poll_option_emoji[]']" />
                <input type="text" name="poll_options[]"
                       placeholder="{{ __('kopling-poll::messages.option_placeholder') }}"
                       class="input input-sm w-full">
                <button type="button" class="btn btn-ghost btn-sm btn-circle" x-show="rows.length > 2"
                        @click="rows.splice(rows.indexOf(row), 1)">✕</button>
            </div>
        </template>
        <button type="button" class="btn btn-ghost btn-sm self-start" @click="rows.push(nextRow++)">
            {{ __('kopling-poll::messages.add_option') }}
        </button>
    </div>

    <div class="flex flex-wrap items-end gap-4 mt-3">
        <x-k::form.toggle :data="[
            'name' => 'poll_multiple',
            'label' => __('kopling-poll::messages.multiple_choice'),
        ]" />
        <x-k::form.input :data="[
            'name' => 'poll_max_choices',
            'label' => __('kopling-poll::messages.max_choices'),
            'type' => 'number',
        ]" />
        <x-k::form.input :data="[
            'name' => 'poll_closes_at',
            'label' => __('kopling-poll::messages.closes_at'),
            'type' => 'datetime-local',
        ]" />
    </div>

    <x-k::form.select :data="[
        'name' => 'poll_results_visibility',
        'label' => __('kopling-poll::messages.results_visibility'),
        'options' => [
            'always' => __('kopling-poll::messages.results_always'),
            'after_vote' => __('kopling-poll::messages.results_after_vote'),
            'after_close' => __('kopling-poll::messages.results_after_close'),
        ],
        'value' => 'after_vote',
    ]" />

    <x-k::form.multi-select :data="[
        'name' => 'poll_groups',
        'label' => __('kopling-poll::messages.groups_label'),
        'options' => \Kopling\Core\People\Group::query()->pluck('name', 'id'),
    ]" />
</div>
