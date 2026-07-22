<?php

declare(strict_types=1);

namespace Kopling\Pages;

/**
 * A closed, small set for v1 -- resist growing this into a generic block-type registry until a
 * real page needs a third kind (see .docs/planning/pages-docs-portal-plan.md).
 */
enum SectionKind: string
{
    case RichText = 'rich-text';
    case Hero = 'hero';
}
