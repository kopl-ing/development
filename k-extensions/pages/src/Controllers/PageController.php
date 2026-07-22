<?php

declare(strict_types=1);

namespace Kopling\Pages\Controllers;

use Illuminate\Contracts\View\View;
use Kopling\Pages\Page;

class PageController
{
    public function index(): View
    {
        $page = Page::where('published', true)->where('is_index', true)->firstOrFail();

        return $this->render($page);
    }

    public function show(string $path): View
    {
        $page = Page::where('published', true)->where('path', $path)->firstOrFail();

        return $this->render($page);
    }

    protected function render(Page $page): View
    {
        return view('kopling-pages::show', [
            'page' => $page,
            'sections' => $page->sections()->with('template')->get(),
        ]);
    }
}
