<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Editor;

/**
 * Every TipTap node/mark type an extension is allowed to switch on -- deliberately the exact,
 * finite set `DocumentRenderer` knows how to safely turn into HTML, no more (mirrors
 * `Ux\Theme\Token`'s own role: a curated catalog, not "arbitrary TipTap"). Case values are the
 * literal ProseMirror/TipTap `type` string (e.g. "bulletList", "codeBlock") so a submitted
 * document's own `type`/mark `type` values can be checked straight against `self::tryFrom()`,
 * no separate mapping table to keep in sync.
 *
 * `paragraph`/`text`/`doc`/`listItem`/`taskItem` are always-on base schema (matches
 * StarterKit's own always-on core nodes) and have no case here -- they can never be disabled,
 * so there's nothing for an extension to vote on.
 */
enum EditorNode: string
{
    case Heading = 'heading';
    case Bold = 'bold';
    case Italic = 'italic';
    case Strike = 'strike';
    case Underline = 'underline';
    case Code = 'code';
    case CodeBlock = 'codeBlock';
    case Blockquote = 'blockquote';
    case BulletList = 'bulletList';
    case OrderedList = 'orderedList';
    case TaskList = 'taskList';
    case HardBreak = 'hardBreak';
    case HorizontalRule = 'horizontalRule';
    case Link = 'link';
}
