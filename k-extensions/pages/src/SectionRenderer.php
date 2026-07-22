<?php

declare(strict_types=1);

namespace Kopling\Pages;

use Illuminate\Support\Facades\Blade;

/**
 * Compiles a section's template against its own slot values. A "wysiwyg" slot only ever exposes
 * its pre-rendered, DocumentRenderer-sanitized `html` to the template -- never the raw ProseMirror
 * document -- so a template author reaching for `{!! $slot !!}` is echoing already-trusted output,
 * the same boundary `sections/rich-text.blade.php` used to sit behind directly.
 */
class SectionRenderer
{
    public static function render(PageSection $section): string
    {
        $template = $section->template;
        $data = $section->data ?? [];

        $slots = [];

        foreach ($template->slots as $slot) {
            $value = $data[$slot['name']] ?? null;

            $slots[$slot['name']] = SlotType::from($slot['type']) === SlotType::Wysiwyg
                ? (string) ($value['html'] ?? '')
                : (string) ($value ?? '');
        }

        return Blade::render($template->blade_source, $slots);
    }
}
