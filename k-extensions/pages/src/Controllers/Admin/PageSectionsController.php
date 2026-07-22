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
use Kopling\Pages\SectionKind;

class PageSectionsController
{
    public function store(Request $request, Page $page, Manager $manager): RedirectResponse
    {
        $kind = SectionKind::from($request->input('kind'));

        $page->sections()->create([
            'kind' => $kind->value,
            'order' => (int) $page->sections()->max('order') + 1,
            ...$this->kindData($request, $kind, $manager),
        ]);

        return redirect()->route('kopling-admin::admin/pages.edit', $page);
    }

    public function update(Request $request, Page $page, PageSection $section, Manager $manager): RedirectResponse
    {
        $section->update($this->kindData($request, SectionKind::from($section->kind), $manager));

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
     * @return array{content: ?string, content_html: ?string, data: ?array}
     */
    protected function kindData(Request $request, SectionKind $kind, Manager $manager): array
    {
        return match ($kind) {
            SectionKind::RichText => $this->richTextData($request, $manager),
            SectionKind::Hero => $this->heroData($request),
        };
    }

    /**
     * `content_html` is rendered server-side from the validated document here, at write time --
     * the exact same DocumentRenderer whitelist Moment::$body/$body_html uses, never a second
     * sanitization codepath just because this content is admin-authored rather than
     * person-authored.
     */
    protected function richTextData(Request $request, Manager $manager): array
    {
        $request->validate([
            'content' => ['required', 'string', new ValidDocument($manager->editorNodes())],
        ]);

        $content = $request->input('content');

        return [
            'content' => $content,
            'content_html' => DocumentRenderer::render($content, $manager->editorNodes()),
            'data' => null,
        ];
    }

    protected function heroData(Request $request): array
    {
        $request->validate([
            'subtitle' => ['nullable', 'string', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'cta_url' => ['nullable', 'string', 'max:2000'],
        ]);

        return [
            'content' => null,
            'content_html' => null,
            'data' => [
                'subtitle' => $request->input('subtitle'),
                'cta_label' => $request->input('cta_label'),
                'cta_url' => $request->input('cta_url'),
            ],
        ];
    }
}
