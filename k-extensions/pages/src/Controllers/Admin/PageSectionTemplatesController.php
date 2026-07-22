<?php

declare(strict_types=1);

namespace Kopling\Pages\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Kopling\Pages\PageSectionTemplate;
use Kopling\Pages\SlotType;

/**
 * Plain create/edit/delete for `PageSectionTemplate`s -- gated by its own
 * "kopling-pages::manage-page-templates" permission (see the route group and
 * Extension::permissions()), never "manage-pages": a template's `blade_source` compiles and runs
 * as real PHP at render time (SectionRenderer -- Blade::render()), so authoring one is a
 * fundamentally more trusted action than writing page content.
 */
class PageSectionTemplatesController
{
    public function index(): View
    {
        return view('kopling-pages::admin.section-templates.index', [
            'templates' => PageSectionTemplate::withCount('sections')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PageSectionTemplate::create($this->validated($request));

        return redirect()->route('kopling-admin::admin/section-templates');
    }

    public function update(Request $request, PageSectionTemplate $template): RedirectResponse
    {
        $template->update($this->validated($request));

        return redirect()->route('kopling-admin::admin/section-templates');
    }

    /**
     * `page_sections.template_id` is `restrictOnDelete()` -- a template still used by a section
     * throws straight from the DB rather than silently orphaning the section. Caught here into a
     * normal validation-style redirect instead of a raw 500, same shape as
     * DrivesController::destroy().
     */
    public function destroy(PageSectionTemplate $template): RedirectResponse
    {
        try {
            $template->delete();
        } catch (QueryException) {
            return back()->withErrors(['template' => __('kopling-pages::messages.template_in_use')]);
        }

        return redirect()->route('kopling-admin::admin/section-templates');
    }

    /**
     * @return array{name: string, blade_source: string, slots: array}
     */
    protected function validated(Request $request): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'blade_source' => ['required', 'string', 'max:20000'],
        ]);

        $slots = json_decode((string) $request->input('slots', '[]'), true);

        if (! is_array($slots)) {
            throw ValidationException::withMessages([
                'slots' => __('kopling-pages::messages.invalid_slots_json', ['error' => json_last_error_msg()]),
            ]);
        }

        foreach ($slots as $slot) {
            $name = is_array($slot) ? (string) ($slot['name'] ?? '') : '';

            if (! is_array($slot)
                || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)
                || SlotType::tryFrom((string) ($slot['type'] ?? '')) === null
                || trim((string) ($slot['label'] ?? '')) === ''
            ) {
                throw ValidationException::withMessages([
                    'slots' => __('kopling-pages::messages.invalid_slots_shape'),
                ]);
            }
        }

        return [
            'name' => $request->input('name'),
            'blade_source' => $request->input('blade_source'),
            'slots' => $slots,
        ];
    }
}
