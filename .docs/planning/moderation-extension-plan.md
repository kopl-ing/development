# Moderation extension

## Context

`roadmap.md` names "Minimal moderation" as still-needed for Checkpoint 1. The charter goes
further: a dedicated **Moderation Portal** is explicitly named as the *target proof case* for
the Portal pattern itself (charter.html D29/D33). This plan builds that: a `kopling/moderation`
extension with its own Portal, its own `moderate` permission, a paginated stream of flagged
content — plus two requirements that need Core involvement, not just the extension:

1. **A real extensibility hook** so any extension can react when something it registered as
   flaggable actually gets acted on — e.g. an images/attachments extension cascade-cleaning up
   files when a Moment carrying them is deleted, without `moderation` ever knowing images exist.
2. **A person-sanction system** (ban/suspend/shadowban and the space between them) as a
   **core-owned** primitive, not a moderation-extension-only feature — enforcing it (blocking
   login, hiding content from feeds) needs a per-request auth hook only `k-core` can own.

Design was informed by researching how Discourse, Flarum, Mastodon/ActivityPub, Reddit, and
Discord actually do this. The findings that directly shaped what's below:

- **Every mature product's sanction ladder decomposes into three orthogonal axes** —
  communication (can they post?), visibility (is their content shown to others?), access (can
  they log in?) — rather than one linear enum. Modeling it that way is what lets Discourse's
  Silence, Mastodon's shadowban-adjacent Limit, and a full Suspend share one schema instead of
  each needing its own named state.
- **Flarum's own moderation extension (`flarum/flags`) is the precedent for the cascade-hook
  question**: it doesn't hardcode "when a post is deleted, also delete its flags" into `Post`'s
  own code — it listens for core's `Deleted` event from outside `Post` and cleans up its own
  table. Kopling already has the exact mechanism for this (`ListensToEvents`, any class-string
  event) — no new contract is needed for the cascade-hook itself, only event classes to listen to.
- **"Hide" and "delete" aren't two mechanisms, they're Laravel's own soft-delete/force-delete,
  reused directly**: `deleted_at` (Laravel's stock `SoftDeletes`) already *is* "excluded from
  default queries, row still present, reversible via `restore()`" — exactly what "hide" needs.
  There's no reason to invent a parallel `hidden_at`/custom-scope mechanism duplicating it. Hide
  = `delete()`. Delete = `forceDelete()` — the row is actually gone. One stock trait, its two
  native methods, mapped onto the two moderation rungs.
- **Discourse's real, named gap**: staff-action logging isn't uniformly enforced — some actions
  historically weren't logged. The fix isn't "remember to log it," it's making the log write
  structurally inseparable from the action (`Sanction::issue()` always writes the row and always
  fires the event; `ContentModerator` always sets the actor/reason columns in the same call that
  soft- or force-deletes — neither is something a call site can forget).
- **Reddit's old shadowban (undisclosed, no appeal) is the single most-criticized pattern
  researched** — deprecated site-wide specifically because of it, and it conflicts with the
  charter's own D17 commitment ("people who are removed for harm can still come back with
  dignity"). This plan does not build a covert, undisclosed shadowban.
- **Mastodon's federation constraint is structural, not a policy choice**: once content
  federates, a local "delete" cannot reach a remote instance's copy. Sanctions here are scoped to
  *this instance's own enforcement*, deliberately not "delete everywhere" — federation-era
  defederation is a distinct, later mechanism.

Split into three phases, separable and shippable in order:

---

## Phase 1 — Reporting, the queue, and content hide/unhide

### Hide = soft delete. Stock Laravel, not a custom mechanism.

No custom trait/scope is needed here at all — `SoftDeletes` already gives "excluded from default
queries, row still present, reversible" for free, and it's the right generic mechanism for that
job, not something moderation should reinvent under a different name. Core adopts it directly on
its own `Moment` (own migration), `discussions` directly on its own `Reply` — `moderation` never
touches either model's file or adds cross-extension columns.

Two extra nullable columns ride alongside the stock `deleted_at`, added in the same migration:
`deleted_by` (FK people, nullOnDelete) and `deleted_reason` (string). This is what makes the
GDPR angle work out cleanly: that attribution only exists for as long as the row itself does. It
persists while something is soft-deleted (deliberately — a moderator's reason for a *reversible*
action is worth keeping while it's pending review or appeal), but the moment anyone actually
`forceDelete()`s the row (Phase 2), the row and its `deleted_by`/`deleted_reason` disappear
together — there's no path where "why we removed this" outlives the thing it's about.

```php
// k-core/src/Content/Moment.php
class Moment extends DatabaseModel
{
    use SoftDeletes;
    // ...
}
```

**This closes a gap the first draft of this plan had explicitly left open**: `SoftDeletes`'s
scope is global, so `Moment::findOrFail()` on a direct permalink is excluded too, not just
feed/thread *listings* — no separate per-controller wiring needed to get that for free.

### Data model

`k-extensions/moderation/migrations/create_flags_table.php` — the only migration this extension
owns (polymorphic, modeled on `reactions`' `reactable_type`/`reactable_id` shape):

```
id             uuid primary
flaggable_type string   -- auto-derived alias, see ModerationTarget below -- never a raw class name
flaggable_id   uuid
person_id      uuid, FK people, nullOnDelete   -- reporter; nullable so the audit trail survives
                                                   the reporter's own account being deleted
reason         string   -- ModerationReason: spam | inappropriate | off_topic | illegal | other
note           text, nullable   -- always rendered alongside the reason select, never conditional
                                    on picking "other" — Flarum's own flag form does the same,
                                    and reporters regularly need to add context regardless of
                                    which category they picked
status         string, default 'pending'       -- pending | actioned | dismissed
resolved_by    uuid, FK people, nullable, nullOnDelete
resolved_at    timestamp, nullable
timestamps

unique(['flaggable_type', 'flaggable_id', 'person_id'])
```

Reason taxonomy is Discourse's own set (research-endorsed as "enough to route and analyze, not
so granular it needs a settings page"). Defined once as `Kopling\Core\Moderation\ModerationReason`
(backed string enum) — core-owned since Phase 3's `Sanction` shares it.

### Extensibility contract: `RegistersModerationTargets`

New core infrastructure (`RegistersModerationTargets` contract + `Extend\ModerationTarget` VO +
`AggregatesModerationTargets` concern composed into `Manager`) — same shape as `ValidatesModels`.
Deliberately minimal, everything derivable is derived rather than author-declared, to close off
exactly the clash/misconfiguration risk a free-form alias would otherwise invite:

```php
class ModerationTarget
{
    public string $alias;          // set by Manager, never by the declaring extension -- see below
    public bool $softDeletable;    // computed by Manager from the model's own trait usage --
                                    // covers both Hide (delete()) and Delete (forceDelete()):
                                    // a SoftDeletes model always supports both, there's no
                                    // scenario where one is available and not the other

    public function __construct(
        public readonly string $model,   // FQCN
        public readonly string $label,
        public readonly string $preview, // Blade view, receives ['flaggable' => $model]
    ) {}
}
```

`AggregatesModerationTargets`, while looping declared targets, does three things per target that
an author never has to get right by hand:

1. **Derives `alias`** as `Manager::id($declaringPackage) . '-' . Str::kebab(class_basename($model))`
   — the exact same auto-prefix-by-declaring-package convention already applied to every other
   id in the codebase (permission ids, Portal ids, `UxEntry` ids). Two different extensions can
   never collide on this, by construction, the same way `manage-things` in one extension can
   never collide with `manage-things` in another.
2. **Registers it into `Relation::morphMap()`** itself (same `morphAlias()` mechanism
   `Extend\Model` already provides, just applied by the Manager on the declaring extension's
   behalf rather than requiring a second, separately-written `morphAlias()` call an author has
   to remember to keep in sync with the target declaration).
3. **Computes `softDeletable`** via `in_array(SoftDeletes::class, class_uses_recursive($model))`
   — reflects what the model actually supports, not a boolean an author could get stale.

`moderation` implements this for its three built-ins: Moment, Reply (`class_exists`-guarded),
Person (`Person` doesn't use `SoftDeletes` — a flagged Person's row only ever leads to Phase 3's
sanction flow, never hide/delete). Any other extension adds its own model the same way, entirely
unknown to `moderation`. `Flag::resolveFlaggable()` resolves `{type}` the same IDOR-safe way
`Reaction` already does (`Relation::getMorphedModel()`, never a raw class name), and
`FlagController::store()` additionally checks the resolved alias is actually present in
`Manager::moderationTargets()` — what makes the registry load-bearing, not decorative.

### Reporting

`Ux\ReportControlEntry` → `Card\Control::SLOT` (Moments) + the literal string
`'kopling-discussions::reply.control'` (Replies) — same pattern `pin`'s `ControlEntry` uses.
"Report" trigger, reason select + note textarea (always shown, see above), posts to
`POST /_xhr/kopling-moderation/{type}/{id}`. `auth` middleware only — no `moderate` permission
needed to report. `FlagController::store()` `updateOrCreate`s keyed on
`[flaggable_type, flaggable_id, person_id]`.

### Hide / unhide

`Ux\HideControlEntry` — control-slot entry, gated `->when('moderate')`, registered only where
`$target->softDeletable` (computed, see above), dual-state render (Hide vs Unhide) same shape as
`pin`'s `ControlEntry`.

```
POST /_xhr/kopling-moderation/{type}/{id}/hide
POST /_xhr/kopling-moderation/{type}/{id}/unhide
```

Routed through `Kopling\Moderation\Support\ContentModerator` — the single place that keeps the
actor/reason write and the actual soft-delete atomic, so no call site can do one without the
other:

```php
public function hide(Model $flaggable, Person $moderator, ?string $reason): void
{
    $flaggable->forceFill(['deleted_by' => $moderator->id, 'deleted_reason' => $reason]);
    $flaggable->delete();   // soft delete -- sets deleted_at, excluded from every default query
                             // including direct permalinks, from here on
    Flag::where('flaggable_type', $flaggable->getMorphClass())
        ->where('flaggable_id', $flaggable->id)->where('status', 'pending')
        ->update(['status' => 'actioned', 'resolved_by' => $moderator->id, 'resolved_at' => now()]);
}

public function unhide(Model $flaggable): void
{
    $flaggable->restore();   // clears deleted_at -- restore() alone doesn't touch deleted_by/
                              // deleted_reason, so clear those explicitly too
    $flaggable->forceFill(['deleted_by' => null, 'deleted_reason' => null])->save();
}
```

Because `SoftDeletes`'s scope is global, no separate feed/thread-exclusion wiring is needed
anywhere — `IndexController`'s feed, `DiscussionController::show()`'s thread, and direct
permalinks are all covered automatically.

### The Portal

```php
new Portal(id: 'moderation', label: __(...), path: 'moderation',
    layout: 'kopling-moderation::layouts.moderation', permission: 'moderate');
```

One flat permission, `moderate`, gating the Portal, the queue, and every moderation action
(reporting needs none). Layout reuses `<x-k::community.chrome>` exactly like
`kopling-admin::layouts.admin` does. Stream via the same `Context`/`Pagination` mechanism the
Moments feed already uses:

```php
$query = Flag::query()->whereIn('flaggable_type', $manager->moderationTargets()->pluck('alias'))
    ->when($status = request('status', 'pending'), fn ($q) => $q->where('status', $status))
    ->with(['flaggable' => fn ($q) => $q->withTrashed(), 'person', 'resolvedBy'])->latest();
```

(`withTrashed()` on the eager-load — otherwise an already-hidden (soft-deleted) flaggable would
silently vanish from its own moderation-queue row, defeating the point.) Each row dispatches to the target's
registered `preview` view, falls back gracefully if the target extension was since uninstalled or
the flaggable was hard-deleted. `?status=` toggles pending/actioned/dismissed; default `pending`.
Entry point: a link in the existing `UserMenu::SLOT`, gated `->when('moderate')`.

### Demo seeder

`src/Command/SeedModeratorsCommand.php`, `HasCommands` → `kopling:moderation:seed-demo`, mirrors
`SeedAdminCommand`'s shape but scoped (not blanket-grant-everything), and also seeds a couple of
fake flags against existing demo content so the queue isn't empty on first look:

```php
$group = Group::query()->firstOrCreate(['name' => 'Moderators']);
$group->givePermissionTo('kopling-moderation::moderate');

Moment::inRandomOrder()->take(2)->get()->each(fn (Moment $moment) => Flag::factory()->create([
    'flaggable_type' => 'kopling-core-moment',
    'flaggable_id' => $moment->id,
    'person_id' => Person::inRandomOrder()->first()?->id,
    'reason' => fake()->randomElement(ModerationReason::cases())->value,
    'note' => fake()->boolean(60) ? fake()->sentence() : null,
]));
```

(guarded on `Moment::exists()` — this command is meant to run after `kopling:demo:seed-fake-data`,
not instead of it, and does nothing destructive if run standalone with no content yet.)

---

## Phase 2 — Delete, and the cascade-hook extensibility mechanism

Direct answer to "extensions should be able to add logic when something they registered as
moderable is moderated, e.g. triggering deletion of related models or images." Hide (Phase 1,
`delete()`/soft-delete) is reversible and non-destructive by design — it was never going to be
the cascade trigger, and correctly so: a soft-deleted Moment's attachments must **not** get
cascade-cleaned, since `restore()` is expected to bring everything back intact. The cascade
trigger is specifically `forceDelete()` — the row is actually, irreversibly gone — matching
Flarum's own precedent (`flarum/flags` listens to core's post-`Deleted` event, the real deletion,
not a soft/reversible one).

```
DELETE /_xhr/kopling-moderation/{type}/{id}
```

`ContentModerator::delete()` — force-deletes, bulk-resolves pending flags the same way `hide()`
does, then fires `Kopling\Moderation\Event\ContentDeleted` itself (`forceDelete()` has no native
hook to carry a reason/moderator through, so this stays an explicit call moderation makes, not
something happens automatically — fired after the row is gone, but the in-memory `$flaggable`
PHP object still holds its old attributes, so listeners can still read `$event->subject->id`/
whatever they need to find and clean up their own related rows):

```php
public function delete(Model $flaggable, Person $moderator, ?string $reason): void
{
    $flaggable->forceFill(['deleted_by' => $moderator->id, 'deleted_reason' => $reason])->save();
    Flag::where('flaggable_type', $flaggable->getMorphClass())
        ->where('flaggable_id', $flaggable->id)->where('status', 'pending')
        ->update(['status' => 'actioned', 'resolved_by' => $moderator->id, 'resolved_at' => now()]);
    $flaggable->forceDelete();
    event(new ContentDeleted($flaggable, $reason, $moderator));
}
```

**The extension point itself needs no new core mechanism** — `ListensToEvents::listen(): array
<class-string, class-string>` already accepts any event class. A hypothetical future
images/attachments extension:

```php
public function listen(): array
{
    return class_exists(\Kopling\Moderation\Event\ContentDeleted::class)
        ? [\Kopling\Moderation\Event\ContentDeleted::class => DeleteAttachedImages::class]
        : [];
}
```

— the same `class_exists`-guarded soft-dependency shape `reactions` already uses for `Reply`,
inverted (listening *into* `moderation` rather than *out of* an owned model). `moderation` never
learns images exist; the images extension never becomes a hard Composer dependency of it.

`Ux\DeleteControlEntry` — reuses the same `$target->softDeletable` flag `HideControlEntry` does
(a `SoftDeletes` model always supports both), gated `moderate`, confirmation step via
`<x-k::modal>` since this is destructive.

---

## Phase 3 — Person sanctions (core-owned)

Investigated first: today, banning is fully greenfield — `Person` has no status concept,
`ValidateLogin`/`AttemptLogin` fire once at sign-in only (never per-request), the only global
`web`-group middleware is `InjectPortal` (no per-request "still allowed here" check exists), and
there's no primitive to forcibly invalidate an already-issued session. This is why it has to be
core-owned — enforcement means hooking the auth/session layer itself.

### The three axes, not a status enum

Communication (can they post/reply/flag?), visibility (is their content shown to others?),
access (can they log in?) — independent fields, not one enum, so Discourse's Silence
(comms-only), Mastodon's Limit (visibility-only), and a full Suspend (all three) share one
schema.

New columns directly on `people` (a `k-core` migration):

```
communication_blocked_at  timestamp, nullable   -- null = can post/reply/flag
visibility                string, default 'normal'   -- 'normal' | 'hidden' (see below)
access_blocked_at         timestamp, nullable   -- null = can log in
access_blocked_until      timestamp, nullable   -- null + access_blocked_at set = permanent ban;
                                                    a real value = temporary suspend, expires then
```

**Visibility axis — deliberately simpler than Mastodon's three-tier ladder, and deliberately
disclosed, not covert.** Mastodon's "Limit" depends on a followers graph Kopling's flat community
feed doesn't have, so there's no meaningful middle tier here; it collapses to
`normal`/`hidden`. This is **not** an undisclosed shadowban — Reddit's old one is the
most-criticized pattern researched, and the charter already commits to "people who are removed
for harm can still come back with dignity" (D17). `visibility = 'hidden'` excludes the person's
content from feeds/threads for everyone except themselves, **and** they see a plain notice when
signed in ("your posts currently have limited visibility") — silent to other people, never silent
to the person it's applied to.

Enforced via a global scope Core registers directly on its own `Moment` (and `discussions`
registers directly on `Reply`) — the same "generalize it, don't bolt it on per-controller"
principle `SoftDeletes` already benefits from in Phase 1, and it means **no new event/listener
wiring is needed in `discussions` at all** for this (an earlier draft of this plan needed a new
`QueryingReplies` event just for shadowban filtering; a scope makes that unnecessary):

```php
class AuthorVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $viewerId = Auth::id();
        $builder->where(fn ($q) => $q
            ->whereHas('person', fn ($p) => $p->where('visibility', '!=', 'hidden'))
            ->orWhere($model->qualifyColumn('person_id'), $viewerId));
    }
}
```

### `Sanction` — the audit log, and the single enforcement-consistent entry point

`k-core/src/People/Sanction.php` — append-only history, one row per issue/lift action, linking
back to whichever `Flag` triggered it where applicable (Mastodon's "reference back to the
originating report" — Discourse's own audit log lacks this):

```
id, person_id (FK), issued_by (FK), lifted_by (FK, nullable), flag_id (FK, nullable)
communication_blocked (bool), visibility ('normal'|'hidden', nullable),
access_blocked_until (nullable, null = permanent)
reason (ModerationReason), note (text, nullable)
issued_at, lifted_at (nullable)
```

```php
class Sanction extends Model
{
    public static function issue(Person $person, array $attributes, Person $issuedBy, ?Flag $flag = null): self
    {
        $sanction = static::create([...$attributes, 'person_id' => $person->id, 'issued_by' => $issuedBy->id,
            'flag_id' => $flag?->id, 'issued_at' => now()]);

        $person->forceFill([
            'communication_blocked_at' => ($attributes['communication_blocked'] ?? false) ? now() : null,
            'visibility' => $attributes['visibility'] ?? 'normal',
            'access_blocked_at' => ($attributes['access_blocked'] ?? false) ? now() : null,
            'access_blocked_until' => $attributes['access_blocked_until'] ?? null,
        ])->save();

        event(new PersonSanctioned($person, $sanction));

        return $sanction;
    }

    public static function lift(self $sanction, Person $liftedBy): void { /* clears the person's current-state columns, stamps lifted_by/lifted_at, fires PersonSanctionLifted */ }
}
```

Any future caller (`moderation`'s controller today, a hypothetical automated anti-spam extension
tomorrow) goes through `Sanction::issue()` — the log write and the event dispatch are inseparable
from applying the sanction, not something a call site can skip. Directly answers Discourse's own
documented gap (some staff actions historically weren't logged).

`Person` gains exactly **one** new method, not three — `communication_blocked_at !== null` and
`visibility === 'hidden'` are plain single-column checks callers can read directly with no
wrapper needed; `isAccessBlocked()` is the one that earns a method, since it's real logic
(comparing two columns against `now()`, not a null-check):

```php
public function isAccessBlocked(): bool
{
    return $this->access_blocked_at !== null
        && ($this->access_blocked_until === null || $this->access_blocked_until->isFuture());
}
```

`PersonSanctioned`/`PersonSanctionLifted` live under `Kopling\Core\Moderation\Event` —
core-owned since `Sanction` itself is core-owned (unlike Phase 2's `ContentDeleted`, which stays
moderation-owned since "delete with a moderation reason" is moderation's own invented concept,
not a core lifecycle event).

### Enforcement

- **New global middleware**, `Kopling\Core\Http\Middleware\EnforceSanctions`, appended to the
  `web` group in `ServiceProvider::boot()` exactly the way `InjectPortal` already is: for an
  authenticated request where `$user->isAccessBlocked()`, force `Auth::logout()` + redirect to a
  new "account access is currently blocked" page (reason, plus expiry if temporary) — the
  per-request check that doesn't exist today anywhere in the codebase.
- **Login-time rejection**: `LoginController`, right after `AttemptLogin` resolves
  `$event->person` and before `Auth::login()`, checks `isAccessBlocked()` and fails the attempt
  with a clear message instead of establishing a session the very next request would immediately
  kill.
- **Not building**: a true instant multi-tab/multi-device kill switch (needs the DB session
  driver plus a delete-by-`person_id` query, or broadcasting) — the middleware achieves
  "effectively logged out within one request" without that infrastructure change. Flagged as a
  deliberate v1 boundary.

### UI

A "Sanction" action on a flagged Person's row in the moderation queue (`softDeletable` naturally
computes to `false` for `Person`, since it doesn't use `SoftDeletes` — flagging a person only
ever leads here) — a form covering all three axes, reason, note, and an optional duration
for a temporary access block, posting to `moderation`'s own `SanctionController::store()`, a thin
wrapper calling `Sanction::issue()`. Lifting: same controller, reachable from a "Sanctioned
people" queue filter. `moderation` owns this workflow/UI; `k-core` owns the mechanism and
enforcement — the same split `Portal`/`Permission`/`Group` already have with `admin`'s CRUD
around them.

**Explicitly not built here**: network/IP-level bans (a genuinely separable, later concern — even
Flarum needs a whole separate `ban-ips` extension for it), and cross-instance/federation-aware
sanction propagation — per the Mastodon research, "delete everywhere" is structurally impossible
once content federates, so this stays scoped to *this instance's own* enforcement by design.

---

## File layout

```
k-core/src/
  Database/Scopes/AuthorVisibilityScope.php                new
  Content/Moment.php                                        + SoftDeletes, AuthorVisibilityScope
  Moderation/ModerationReason.php                           new backed enum
  Moderation/Event/PersonSanctioned.php, PersonSanctionLifted.php   new
  Extension/Contract/RegistersModerationTargets.php          new
  Extend/ModerationTarget.php                                new
  Extension/Concerns/AggregatesModerationTargets.php         new -- also derives alias, registers
                                                               morph map, computes softDeletable
  Extension/Manager.php                                      compose the new trait
  People/Sanction.php                                        new
  People/Person.php                                          + isAccessBlocked()
  Http/Middleware/EnforceSanctions.php                       new
  Http/Controllers/SanctionEnforcementController.php         the "access blocked" page
  Provider/ServiceProvider.php                               register middleware + route
  Authentication/Controller/LoginController.php               + access-block check before Auth::login()
  migrations/  create_sanctions_table.php   -- a genuinely new table, its own migration
               -- softDeletes()+deleted_by+deleted_reason (Phase 1) and the four sanction
               -- columns (Phase 3) are folded directly into create_people_table.php/
               -- create_moments_table.php themselves, not separate add_*_to_*_table
               -- migrations -- no tagged release existed yet when these were built, so there
               -- was no installed schema history to preserve by keeping them as separate
               -- ALTER-style migrations.

k-extensions/discussions/src/
  Reply.php                                                  + SoftDeletes, AuthorVisibilityScope
  migrations/  -- softDeletes()+deleted_by+deleted_reason folded into create_replies_table.php
               -- directly, same reasoning as k-core's own moments/people tables above.

k-extensions/moderation/
  composer.json
  src/
    Extension.php                    HasPermissions, HasPortals, ExtendsPortals, ExtendsModels,
                                      ChangesUx, HasCommands, RegistersModerationTargets
    Flag.php
    Controllers/FlagController.php         store(), dismiss()
    Controllers/ModerationController.php   hide(), unhide(), destroy() [delete]
    Controllers/SanctionController.php     store(), lift()
    Controllers/QueueController.php        index()
    Support/ContentModerator.php
    Event/ContentDeleted.php
    Ux/ReportControlEntry.php, HideControlEntry.php, DeleteControlEntry.php
    Command/SeedModeratorsCommand.php
  migrations/create_flags_table.php   -- the only migration this extension owns
  routes/web.php
  views/  layouts/moderation.blade.php, queue/index.blade.php, ux/*.blade.php,
          preview/{moment,reply,person}.blade.php, sanction/form.blade.php
  lang/en/permissions.php, moderation.php
  icon/lg.png, sm.png
```

## Verification

- `vendor/bin/pest` — `tests/Feature/Moderation/*`: reporting + unique-key re-flag behavior;
  permission gating on Portal/queue/hide/delete/dismiss/sanction routes; hiding a Moment excludes
  it from feed, thread, *and* direct permalink alike (`assertSoftDeleted`), unhide reverses it
  (`fresh()->trashed()` false) and clears `deleted_by`/`deleted_reason`; deleting a Moment
  actually removes the row (`assertDatabaseMissing`) and fires `ContentDeleted`;
  `RegistersModerationTargets` aggregation deriving a distinct alias per declaring extension via
  a disposable fixture (and correctly computing `softDeletable` from actual trait usage);
  `Sanction::issue()` always writing a log row and firing its event; the `EnforceSanctions`
  middleware logging out and redirecting an access-blocked person mid-session, not just at login;
  `AuthorVisibilityScope` excluding a shadowbanned person's content for other viewers but not for
  themselves.
- `composer update kopling/moderation` after registering the new path repo.
- Manual pass once seeded (`kopling:demo:seed-fake-data` then `kopling:moderation:seed-demo`,
  which now leaves a couple of flags already in the queue): report a Moment and a Reply, dismiss
  one, hide the other (confirm it drops from feed/thread/permalink immediately, and that it's
  still visible from the queue itself via `withTrashed()`), delete a third (confirm the row is
  actually gone and a disposable test listener on `ContentDeleted` fires), sanction a flagged
  Person with a temporary access block and confirm they're logged out on their very next request
  and shown the reason, then lift it and confirm normal access returns.
