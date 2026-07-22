<?php

declare(strict_types=1);

namespace Kopling\Pages;

/**
 * A closed, small set for v1 -- resist growing this into a generic field-type registry until a
 * real template needs a third type.
 */
enum SlotType: string
{
    case String = 'string';
    case Wysiwyg = 'wysiwyg';
}
