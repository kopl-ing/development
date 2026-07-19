@props(['data' => [], 'context' => null])
{{--
    Fills tags' own `kopling-tags::admin.tag-form` slot -- the two vote-emoji fields, entirely
    reactions' own concept, rendered from reactions' own view so `tags` never has to know they
    exist. `$context->subject` is the `Tag` being edited (see tags/admin/index.blade.php), or
    null on the create form.

    Recomputes the exact same "which dialog is this" id tags' own fields use (`modal-tag-create`,
    or `modal-tag-edit-{tag id}` -- see tags/admin/index.blade.php's own `$modalId`) independently
    from this tag's own id -- both sides only need to agree on the string shape, not share any
    actual state, since both read the same `old('_form')` from the one shared page-level request.
    This is also the exact id `<x-k::modal>` itself now checks to decide whether to self-reopen
    (see Ux/Modal.php) -- one convention, three independent readers.
--}}
@php
    $tag = $context?->getSubject();
    $modalId = $tag ? 'modal-tag-edit-'.$tag->id : 'modal-tag-create';
    $reopening = old('_form');
    $upvote = $reopening === $modalId ? old('upvote_emoji') : $tag?->upvote_emoji;
    $downvote = $reopening === $modalId ? old('downvote_emoji') : $tag?->downvote_emoji;
@endphp
<x-k::form.emoji-picker :data="['name' => 'upvote_emoji', 'label' => __('kopling-reactions::messages.upvote_emoji'), 'value' => $upvote]" />
<x-k::form.emoji-picker :data="['name' => 'downvote_emoji', 'label' => __('kopling-reactions::messages.downvote_emoji'), 'value' => $downvote]" />
