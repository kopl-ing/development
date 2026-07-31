<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Kopling\Core\Ux\Form\IconSearch\FontAwesomeIconSearch;

/**
 * Backs `Ux\Form\IconPicker` -- a shared, core-owned search endpoint, unlike `Ux\Form\TagInput`'s
 * caller-supplied `searchUrl`: what an icon search returns never varies by caller, so every
 * `IconPicker` across every extension hits this same route rather than each declaring its own.
 * Returns an HTML fragment for `icon-picker.js`'s `hx-get`, not JSON rebuilt into markup client-side.
 */
class IconSearchController
{
    public function __invoke(Request $request, FontAwesomeIconSearch $search): View
    {
        $term = trim((string) $request->query('q', ''));

        return view('kopling-core::ux.form.icon-picker-results', [
            'icons' => $term === '' ? [] : $search->search($term),
            'term' => $term,
        ]);
    }
}
