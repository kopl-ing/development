<?php

declare(strict_types=1);

namespace Kopling\Core\Moderation;

/**
 * The reason taxonomy for both a `Flag` (moderation extension) and a `Sanction` (this class'
 * own package, `k-core`) -- kept in one place so the two never drift apart. Discourse's own
 * fixed set: enough to route and analyze, not so granular it needs a settings page.
 */
enum ModerationReason: string
{
    case Spam = 'spam';
    case Inappropriate = 'inappropriate';
    case OffTopic = 'off_topic';
    case Illegal = 'illegal';
    case Other = 'other';
}
