@props(['data' => [], 'context' => null])
@php
    $moment = $context?->getSubject();
    $poll = $moment?->poll;
    $options = $poll?->options ?? collect();
    $initialCount = max($options->count(), 2);
    $selectedGroupIds = $poll?->groups?->pluck('id')->map(fn ($id) => (string) $id)->all() ?? [];
@endphp

<div x-data="{ rows: [], nextRow: {{ $initialCount }}, totalRows: {{ $initialCount }} }">
    <x-k::form.input :data="[
        'name' => 'poll_question',
        'label' => __('kopling-poll::messages.question_label'),
        'placeholder' => __('kopling-poll::messages.question_placeholder'),
        'value' => $poll?->question,
    ]" />

    {{--
        The poll's existing options (if any), server-rendered with real per-row values --
        Alpine's own `<template x-for>` below clones one compiled DOM fragment for every row, so
        it has no way to give two rows two different initial values; newly-added rows are always
        blank (there's nothing yet to prefill), which is exactly what that template already
        handled before editing existed. `totalRows` is the one reactive count both kinds of row
        share, so a delete button on either kind correctly hides once only 2 options remain
        either way -- `rows.length` alone (the original check) only ever counted the
        Alpine-tracked half.
    --}}
    <div class="flex flex-col gap-2 mt-2">
        @for ($i = 0; $i < $initialCount; $i++)
            @php $option = $options->get($i); @endphp
            <div class="flex items-center gap-2" data-poll-option-row>
                <x-k::form.emoji-picker :data="['name' => 'poll_option_emoji[]', 'value' => $option?->emoji]" />
                <input type="text" name="poll_options[]" value="{{ $option?->label }}"
                       placeholder="{{ __('kopling-poll::messages.option_placeholder') }}"
                       class="input input-sm w-full">
                <button type="button" class="btn btn-ghost btn-sm btn-circle" x-show="totalRows > 2"
                        @click="totalRows--; $el.closest('[data-poll-option-row]').remove()">✕</button>
            </div>
        @endfor

        <template x-for="row in rows" :key="row">
            <div class="flex items-center gap-2" data-poll-option-row>
                <x-k::form.emoji-picker :data="['name' => 'poll_option_emoji[]']" />
                <input type="text" name="poll_options[]"
                       placeholder="{{ __('kopling-poll::messages.option_placeholder') }}"
                       class="input input-sm w-full">
                <button type="button" class="btn btn-ghost btn-sm btn-circle" x-show="totalRows > 2"
                        @click="totalRows--; rows.splice(rows.indexOf(row), 1)">✕</button>
            </div>
        </template>
        <button type="button" class="btn btn-ghost btn-sm self-start" @click="rows.push(nextRow++); totalRows++">
            {{ __('kopling-poll::messages.add_option') }}
        </button>
    </div>

    <div class="flex flex-wrap items-end gap-4 mt-3">
        <x-k::form.toggle :data="[
            'name' => 'poll_multiple',
            'label' => __('kopling-poll::messages.multiple_choice'),
            'value' => $poll?->multiple_choice,
        ]" />
        <x-k::form.input :data="[
            'name' => 'poll_max_choices',
            'label' => __('kopling-poll::messages.max_choices'),
            'type' => 'number',
            'value' => $poll?->max_choices,
        ]" />
        <x-k::form.input :data="[
            'name' => 'poll_closes_at',
            'label' => __('kopling-poll::messages.closes_at'),
            'type' => 'datetime-local',
            'value' => $poll?->closes_at?->format('Y-m-d\TH:i'),
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
        'value' => $poll?->results_visibility ?? 'after_vote',
    ]" />

    <x-k::form.multi-select :data="[
        'name' => 'poll_groups',
        'label' => __('kopling-poll::messages.groups_label'),
        'options' => \Kopling\Core\People\Group::query()->pluck('name', 'id'),
        'value' => $selectedGroupIds,
    ]" />
</div>
