<?php

declare(strict_types=1);

namespace Kopling\Tags\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Kopling\Tags\Tag;

/**
 * Plain create/edit/delete for Tags -- mirrors GroupsController's shape. Per-tag vote-emoji
 * config (see Tag::$fillable) lives on the same form as name/slug/color; there's no dedicated
 * "voting settings" screen.
 */
class TagsController
{
    public function index(): View
    {
        return view('kopling-tags::admin.index', [
            'tags' => Tag::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Tag::create($this->validated($request));

        return redirect()->route('kopling-admin::admin/tags');
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $tag->update($this->validated($request, $tag));

        return redirect()->route('kopling-admin::admin/tags');
    }

    /**
     * `moment_tag` rows cascade via their own FK constraint -- no manual cleanup needed here.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return redirect()->route('kopling-admin::admin/tags');
    }

    /**
     * `different:downvote_emoji` only runs once both fields are actually present -- `nullable`
     * skips the rest of a field's own rules when it's null, so a tag with only one direction
     * configured (or neither) never trips the "must differ" check; it only fires when both
     * emoji are set and equal.
     *
     * @return array{name: string, slug: string, color: ?string, upvote_emoji: ?string, downvote_emoji: ?string}
     */
    private function validated(Request $request, ?Tag $tag = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('tags', 'slug')->ignore($tag)],
            'color' => ['nullable', 'string', 'max:32'],
            'upvote_emoji' => ['nullable', 'string', 'max:16', 'different:downvote_emoji'],
            'downvote_emoji' => ['nullable', 'string', 'max:16'],
        ], [
            'upvote_emoji.different' => __('kopling-tags::messages.vote_emoji_must_differ'),
        ]);
    }
}
