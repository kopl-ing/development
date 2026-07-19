<?php

declare(strict_types=1);

namespace Kopling\Tags\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Kopling\Core\Extension\Manager;
use Kopling\Tags\Tag;

/**
 * Plain create/edit/delete for Tags -- mirrors GroupsController's shape. Declares validation
 * rules for its own fields only (name/slug/color); anything else a tag's row happens to carry
 * (e.g. reactions' own `upvote_emoji`/`downvote_emoji`) comes in entirely through
 * `Manager::modelValidationRules()` -- this controller merges it in blind, never naming those
 * fields itself. See decisions.md, 2026-07-18.
 */
class TagsController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    public function index(): View
    {
        return view('kopling-tags::admin.index', [
            'tags' => Tag::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Tag::forceCreate($this->validated($request));

        return redirect()->route('kopling-admin::admin/tags');
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $tag->forceFill($this->validated($request, $tag))->save();

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
     * `forceCreate()`/`forceFill()` (not `create()`/`update()`) deliberately bypass `$fillable`
     * -- by the time this returns, every key in it already passed validation built from this
     * exact merged rule set, so a *second* mass-assignment allow-list check on top would only
     * be friction, not protection. This is also what lets an extension-contributed field (like
     * `upvote_emoji`) persist at all without `Tag::$fillable` ever having to name it.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Tag $tag = null): array
    {
        $merged = $this->manager->mergeModelValidationRules(Tag::class, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('tags', 'slug')->ignore($tag)],
            'color' => ['nullable', 'string', 'max:32'],
            'icon' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        return $request->validate($merged['rules'], $merged['messages']);
    }
}
