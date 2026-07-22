<?php

declare(strict_types=1);

namespace Kopling\Pages\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Kopling\Pages\Page;
use Kopling\Pages\PageSectionTemplate;

class PagesController
{
    public function index(): View
    {
        return view('kopling-pages::admin.pages.index', [
            'pages' => Page::withCount('sections')->orderBy('title')->get(),
        ]);
    }

    public function create(): View
    {
        return view('kopling-pages::admin.pages.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $page = Page::create($this->validated($request));

        $this->enforceSingleIndex($page);

        return redirect()->route('kopling-admin::admin/pages.edit', $page);
    }

    public function edit(Page $page): View
    {
        return view('kopling-pages::admin.pages.edit', [
            'page' => $page,
            'sections' => $page->sections()->with('template')->get(),
            'templates' => PageSectionTemplate::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $page->update($this->validated($request, $page));

        $this->enforceSingleIndex($page);

        return redirect()->route('kopling-admin::admin/pages.edit', $page);
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return redirect()->route('kopling-admin::admin/pages');
    }

    /**
     * @return array{path: string, title: string, meta_description: ?string, published: bool, show_in_nav: bool, nav_order: int, is_index: bool}
     */
    protected function validated(Request $request, ?Page $page = null): array
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:150', Rule::unique('pages', 'path')->ignore($page?->id)],
            'title' => ['required', 'string', 'max:150'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);

        return [
            ...$validated,
            'path' => trim($validated['path'], '/'),
            'published' => $request->boolean('published'),
            'show_in_nav' => $request->boolean('show_in_nav'),
            'nav_order' => (int) $request->input('nav_order', 0),
            'is_index' => $request->boolean('is_index'),
        ];
    }

    /**
     * Setting a page as the index page implicitly unsets it on every other page, rather than
     * blocking the save with a validation error -- the friendlier of the two for a single-admin
     * "make this the home page" toggle, and there is nothing to reconcile: at most one page
     * being the index is the only invariant that matters, not which one was set first.
     */
    protected function enforceSingleIndex(Page $page): void
    {
        if ($page->is_index) {
            Page::where('id', '!=', $page->id)->update(['is_index' => false]);
        }
    }
}
