<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Closure;

/**
 * Lets an extension contribute extra outbound ActivityStreams fields to a model it doesn't own
 * -- e.g. an image-gallery extension adding an `attachment` array to `Moment`'s own JSON-LD,
 * without `core`'s own `Extend\Federation(Moment::class)` registration needing to know the
 * gallery extension exists. Same ownership shape `ValidatesModels` already established for extra
 * validation rules on a model you don't own; this is that mechanism's federation counterpart.
 *
 * Each closure is `Closure(object $model): array<string, mixed>` -- extra fields merged into
 * `Federation\Manager::toActivityJson()`'s already-built envelope, after the owning
 * `Extend\Federation::toActivity()`/`contentField()` output. Outbound only: there's no inbound
 * counterpart yet (accepting a remote object's own attachments back into a local gallery is a
 * bigger feature than contributing to what this instance federates out), so this contract has
 * nothing to say about `fromActivity()`.
 */
interface ExtendsFederatedObjects
{
    /**
     * @return array<class-string, Closure>
     */
    public function federatedObjectContributions(): array;
}
