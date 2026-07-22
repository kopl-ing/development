<?php

declare(strict_types=1);

namespace Kopling\Docs\Controllers;

use Illuminate\Contracts\View\View;
use Kopling\Docs\DocPage;

class DocsController
{
    /**
     * The Portal's own root ("/docs") shows whichever page sorts first in the tree -- no
     * separate "index" concept from Pages' own `is_index` flag; Docs' tree already has a
     * deterministic order, so the first page in it is a reasonable default landing page rather
     * than a distinct thing an author has to remember to designate.
     */
    public function index(): View
    {
        $page = DocPage::orderBy('order')->orderBy('title')->first();

        return view('kopling-docs::show', ['page' => $page]);
    }

    public function show(string $slug): View
    {
        $page = DocPage::where('slug', $slug)->firstOrFail();

        return view('kopling-docs::show', ['page' => $page]);
    }
}
