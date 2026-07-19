<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kopling\Core\Ux\Form\IconSearch\FontAwesomeIconSearch;

/**
 * Backs `Ux\Form\IconPicker` -- a shared, core-owned search endpoint, unlike `Ux\Form\TagInput`'s
 * caller-supplied `searchUrl`: what an icon search returns never varies by caller, so every
 * `IconPicker` across every extension hits this same route rather than each declaring its own.
 */
class IconSearchController
{
    public function __invoke(Request $request, FontAwesomeIconSearch $search): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json([]);
        }

        return response()->json($search->search($term));
    }
}
