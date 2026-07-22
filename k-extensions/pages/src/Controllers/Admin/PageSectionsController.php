<?php

declare(strict_types=1);

namespace Kopling\Pages\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Editor\DocumentRenderer;
use Kopling\Core\Ux\Editor\Rules\ValidDocument;
use Kopling\Pages\Page;
use Kopling\Pages\PageSection;
use Kopling\Pages\PageSectionTemplate;
use Kopling\Pages\SlotType;

class PageSectionsController
{
    public function store(Request $request, Page $page, Manager $manager): RedirectResponse
    {
        $template = PageSectionTemplate::findOrFail($request->input('template_id'));

        $page->sections()->create([
            'template_id' => $template->id,
            'order' => (int) $page->sections()->max('order') + 1,
            'data' => $this->slotData($request, $template, $manager),
        ]);

        return redirect()->route('kopling-admin::admin/pages.edit', $page);
    }

    public function update(Request $request, Page $page, PageSection $section, Manager $manager): RedirectResponse
    {
        $section->update([
            'data' => $this->slotData($request, $section->template, $manager),
        ]);

        return redirect()->route('kopling-admin::admin/pages.edit', $page);
    }

    public function destroy(Page $page, PageSection $section): RedirectResponse
    {
        $section->delete();

        return redirect()->route('kopling-admin::admin/pages.edit', $page);
    }

    /**
     * Swaps this section's `order` with its immediate neighbor in the requested direction --
     * simplest correct reordering for a short, admin-managed list; no drag-and-drop needed yet.
     */
    public function move(Request $request, Page $page, PageSection $section): RedirectResponse
    {
        $up = $request->input('direction') === 'up';

        $neighbor = $page->sections()
            ->where('order', $up ? '<' : '>', $section->order)
            ->orderBy('order', $up ? 'desc' : 'asc')
            ->first();

        if ($neighbor !== null) {
            [$section->order, $neighbor->order] = [$neighbor->order, $section->order];
            $section->save();
            $neighbor->save();
        }

        return redirect()->route('kopling-admin::admin/pages.edit', $page);
    }

    /**
     * One value per the template's own declared slots -- a "wysiwyg" slot is validated and
     * rendered to HTML server-side, at write time, through the same DocumentRenderer whitelist
     * Moment::$body/$body_html uses (see SectionRenderer for why only the rendered `html` is ever
     * exposed to the template itself).
     */
    protected function slotData(Request $request, PageSectionTemplate $template, Manager $manager): array
    {
        $data = [];

        foreach ($template->slots as $slot) {
            $name = $slot['name'];

            if (SlotType::from($slot['type']) === SlotType::Wysiwyg) {
                $request->validate([$name => ['nullable', 'string', new ValidDocument($manager->editorNodes())]]);

                $json = $request->input($name);
                $data[$name] = $json !== null
                    ? ['json' => $json, 'html' => DocumentRenderer::render($json, $manager->editorNodes())]
                    : null;
            } else {
                $request->validate([$name => ['nullable', 'string', 'max:2000']]);

                $data[$name] = $request->input($name);
            }
        }

        return $data;
    }
}
