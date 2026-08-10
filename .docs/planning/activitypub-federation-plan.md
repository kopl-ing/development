# Plan: ActivityPub federation

Status: v1 implemented (Phases 0-8), including tests. `people`/`moments`/`replies` carry only
`origin`; every AP-protocol-shaped fact lives in `activitypub`'s own tables
(`activitypub_actors`/`activitypub_objects`/`activitypub_follows`/`activitypub_deliveries`).
WebFinger, actor/object JSON-LD, outbound signing + delivery (with a persisted, cron-retriable
delivery record), inbound signature verification, Follow/Accept/Undo, remote-actor resolution,
`Create{Note}` → `Reply` ingestion (HTML sanitized via a dedicated allowlist, not
`DocumentRenderer`), the admin domain-blocklist setting, and `ExtendsFederatedObjects` (lets a
third extension, e.g. an image gallery, contribute to a federated object it doesn't own) are all
built and covered by `tests/Feature/Activitypub/*`. Known gap, decided out of scope: no in-app way
to set a Person's
own `handle` yet (see Phase 7's own note) — a per-person settings page doesn't exist in this
codebase and building one is separate work.

## Why now, and what's already been decided

The charter already treats federation as settled architecture ("ActivityPub, forum-shaped
conversation semantics... Federation may ship post-1.0; schema readiness ships day one") and
promises "every federatable row carries UUID + origin from migration #1 — remote content is
first-class, never a shadow table" ([decisions.md](decisions.md) doesn't have its own federation
entry yet; that promise currently lives only in the charter). In practice it hasn't been built:
`people` and `moments` have UUID primary keys but no `origin` column, and every route in the app
today goes through a Portal, which always carries the `web` middleware group (session + CSRF) —
there's no route path a remote AP server could safely POST an inbox delivery to.

Three things were decided with Luceos before drafting this plan, and this plan treats them as
fixed rather than re-litigating them:

1. `origin` and the rest of the identity columns live on `people`, in **core** — core owns that
   table already, so this is core adding columns to its own schema, not reaching into anyone
   else's domain.
2. The ActivityPub protocol itself (actor/object JSON-LD, HTTP Signatures, inbox/outbox, delivery
   queue) lives in a new **extension**, `k-extensions/activitypub`, with its own Portal — kept
   disableable, same as every other optional capability in this codebase.
3. Portal needs a new capability: **a Portal can declare its own middleware**, overriding the
   hardcoded `['web']` every Portal gets today, so a federation Portal can be stateless/CSRF-free.

Scope is **full bidirectional**: local People/Moments become fetchable + deliverable, and remote
actors can reply into local threads — remote replies stored as real rows, not a shadow table,
which is where "fake People objects" comes in: a remote actor is just a `Person` row with
`origin` set.

## Scope for v1 (and what's deliberately out)

In scope: WebFinger, actor discovery, Follow/Accept between a local Person and a remote actor,
outbound delivery of a local Person's Moments to their followers' inboxes, inbound ingestion of
remote replies into `discussions`' own `replies` table, HTTP Signature verification both ways.

Deliberately narrower than "federate everything": a **Moment** gets federation-ready columns (so
a future remote-originated Moment isn't a schema migration away) and is outbound-fetchable, but
*inbound creation of a whole new Moment from a remote AP object* is not part of this plan —
Kopling's federation shape is forum-style (local threads, federated participation), not mirroring
whole remote communities. Called out explicitly here rather than silently narrowing what was
asked for.

Also out of scope: a native local "follow a person" feature — none exists yet, and the `follows`
table this plan adds is `activitypub`'s own bookkeeping for the AP follow handshake, not a
general social graph. If Kopling gets a native follow feature later, reconciling the two is that
feature's decision to make, per the extension-ownership rules in `CLAUDE.md`.

## Architecture / ownership map

- **`k-core`** — owns `people`/`moments` schema, but only the generic `origin` column (`people`
  already has it; `moments` gains it here) — the one domain fact every federatable row carries
  per charter D6, regardless of protocol. Owns the new `Portal::$middleware` capability, and the
  new `Extend\Federation` declaration + `HasFederatedModels` contract: the extensibility surface
  other packages register through. Core defines the *shape*, the same way it already does for
  `ValidatesModels`/`RequestsStorageDriver` — it does not itself depend on the activitypub
  package, and it never carries an AP-protocol-shaped column (`remote_id`, key material, an
  inbox URL) itself.
- **`k-extensions/activitypub`** — owns the Portal, all `/ap/*` + `/.well-known/*` routes,
  signature verification, JSON-LD serialization, the delivery queue, its own `activitypub_follows`
  table, and two more of its own tables that carry every AP-protocol-specific fact for *any*
  federatable row, so core/discussions never gain a column shaped by ActivityPub's own protocol
  (2026-08-10 decision, see Phase 0b):
  - `activitypub_actors` — one-to-one with `people` (`person_id` unique FK): `handle` (nullable,
    unique-when-set — a local Person's own chosen fediverse handle, the WebFinger
    `acct:{handle}@domain` local-part, set from their own settings page per the 2026-08-10
    decision below, never derived from `people.name`, which is free-text and not unique),
    `federation_enabled` (boolean, default `true` — lets a Person who's already set a handle
    pause federation without losing it), `remote_id` (the actor's real AP URI — arbitrary per
    remote server, e.g. Mastodon's `https://mastodon.social/users/alice`, structurally unrelated
    to any Kopling UUID, so it cannot be reconstructed from `origin` + `id` the way a
    *locally-minted* federated ID can), `inbox_url`/`outbox_url`/`shared_inbox_url`,
    `public_key`/`private_key`, `fetched_at`. A row existing here (regardless of `people.origin`)
    is what makes a Person an AP actor; absence (or `handle === null`) means "never opted in."
  - `activitypub_objects` — polymorphic, one row per federatable non-Person object
    (`federatable_type`/`federatable_id`, `remote_id`, `federated_at`), covering `Moment` and
    `Reply` uniformly so a third federatable model later needs no new table, just a new
    `Extend\Federation` registration. `moments`/`replies` themselves gain only `origin`.
  Reads `HasFederatedModels` declarations the same way `ValidatesModels` is read today.
- **`k-extensions/discussions`** — owns adding `origin` (only) to its own `replies` table (it's
  discussions' own schema either way — whether a Reply is local or remote is a fact about what a
  Reply *is*, independent of which federation protocol carries it), and registers `Reply` against
  `HasFederatedModels` with its own inbound/outbound closures, the same way it already owns
  `Reply::forMoment()` etc. `remote_id`/`federated_at` for a Reply live in activitypub's own
  `activitypub_objects` table, not on `replies`.

## Phase 0 — Core prerequisites

**0a. Portal-level custom middleware** (`k-core/src/Portal/Portal.php`,
`k-core/routes/web.php`): add `public readonly ?array $middleware = null` to `Portal`'s
constructor — `null` preserves every existing Portal's current `['web']` behavior, so this is
additive, not breaking. In `k-core/routes/web.php`:

```php
$middleware = $portal->middleware ?? ['web'];
if ($portal->permission) { $middleware[] = "can:$portal->permission"; }
```

Thread `middleware` through `Portal::toArray()`/`fromArray()` so it round-trips through
`Manager::portals()`'s cache the same way `path`/`permission` already do. The activitypub Portal
will declare `middleware: ['api']` — Laravel's built-in `api` group (stateless,
`SubstituteBindings`, no session/CSRF) is exactly what actor/webfinger/inbox routes need; no
`bootstrap/app.php` change required, since Laravel registers `web`/`api` by default even though
this codebase never explicitly configures either.

**0b. `origin` only, in core; every AP-protocol-shaped column lives in `activitypub`'s own tables**
(2026-08-10 decision, made with Luceos while drafting this plan — see the Ownership map above for
the full reasoning). `people.origin` already exists (`k-core/migrations/2026_07_09_000001_create_people_table.php`)
and `password`/`email` are already nullable — nothing to add there. What's actually needed:

- `k-core/migrations/..._add_origin_to_moments_table.php`: `origin` (nullable string, index,
  null = local) on `moments` only — schema-ready per the charter promise, even though inbound
  remote-Moment ingestion isn't built in this plan.
- `Person` (`k-core/src/People/Person.php`) gains `isRemote()` (`!$this->isLocal()`) alongside
  the existing `isLocal()`. `hasPermission()` needs no change — a remote Person naturally has
  zero `group_person` rows, so it already returns `false`, same reasoning `Guest` documents.
- `k-extensions/activitypub`'s own migrations (see Phase 1) create `activitypub_actors`
  (`person_id` unique FK → `people`, `remote_id` unique-when-set, `inbox_url`/`outbox_url`/
  `shared_inbox_url`, `public_key`/`private_key`, `fetched_at`) and `activitypub_objects`
  (`federatable_type`/`federatable_id` unique pair, `remote_id` unique-when-set, `federated_at`) —
  the latter covers `Moment` and `Reply` uniformly, so a third federatable model needs no new
  table, just an `Extend\Federation` registration.

**0c. `Extend\Federation` + `HasFederatedModels`** (mirrors the existing `Extend\Model` +
`ExtendsModels` pair): new `k-core/src/Extend/Federation.php` — fluent, one instance per
federatable model: `apType(string)` (`'Note'`, `'Article'`, ...), `contentField(string)`,
`attributedToRelation(string)`, `toActivity(Closure)` (outbound serialization hook),
`fromActivity(Closure)` (inbound: given a decoded activity + resolved author `Person`,
return-or-create the local row). New `k-core/src/Extension/Contract/HasFederatedModels.php`:
`federatedModels(): array<Federation>`. Core defines this shape without knowing about
`activitypub_objects` at all — resolving a model against that table (both directions: "does this
row have a `remote_id`" and "find the local row for this `remote_id`") is `activitypub`'s own
`Federation\Manager` responsibility, reading `Manager::federatedModels()` the same way
`ValidatesModels` is read today, keyed by each registration's `Federation::$model` class.

**Gotcha to carry into implementation**: `Manager::models()` is deliberately *not* routed through
`RegistrationCache` — it's instance-cached per-request instead, specifically because
`Extend\Model`'s `creating`/`saving`/`saved` closures can't survive `var_export()` (see
`ValidatesModels`'s own docblock on why its rules must stay closure-free to stay cacheable).
`Extend\Federation` carries closures the same way, so `Manager::federatedModels()` must be wired
the same *uncached, per-request* way `models()` is — not through the flatfile cache path
`portals()`/`permissions()`/`modelValidationRules()` use.

## Phase 1 — `k-extensions/activitypub` scaffold

New package, structured like `k-extensions/example`/`k-extensions/discussions`: `composer.json`
(type `kopling-extension`), `src/Extension.php` implementing `ExtendsPortals`, `HasPermissions`,
`HasCommands`, `HasAdminSettings`. Declares its own Portal:

```php
new Portal(id: 'activitypub', label: 'ActivityPub', path: '', layout: null, middleware: ['api'])
```

No HTML layout needed — every route in this Portal returns JSON-LD, never a Blade view.
Permissions via `HasPermissions`: `kopling-activitypub::manage-federation` (admin toggle + domain
blocklist), reusing the existing `Extend\Permission` + Gate mechanism, no new pattern.

## Phase 2 — Outbound: actors and objects become fetchable

- `GET /.well-known/webfinger?resource=acct:{handle}@{domain}` → resolves a local `Person` by
  their chosen `activitypub_actors.handle` (2026-08-10 decision — never `people.name`, which is
  free-text and not unique), returns the JRD pointing at their actor URI. 404s (never a
  disambiguating error) for an unset/wrong domain or no matching handle.
- `GET /ap/people/{person}` → actor JSON-LD (name, `inbox`, `outbox`, `publicKey` read from this
  Person's `activitypub_actors` row) — 404 unless `isFederating()` (has a `handle` and
  `federation_enabled`). The key pair is generated on `ActivitypubActor::creating()` (a local
  row, i.e. no `remote_id`, with no `private_key` yet) — decoupled from *what* creates the row,
  since there's currently no in-app way to (see Phase 7's known gap): a manual/tinker step today,
  a settings-page controller once one exists, either way gets keys for free.
- `GET /ap/{type}/{id}` — one generic controller, resolves the model via each package's
  registered `Extend\Federation` (`apType()`/`contentField()`/`toActivity()`), so this route
  never hardcodes "Moment" or "Reply" by name; adding a third federatable model later is a
  registration, not a route change.
- **`ExtendsFederatedObjects`** (2026-08-10 addition, post-v1): a third extension can contribute
  extra outbound fields to a model it doesn't own — e.g. an image-gallery extension adding an
  `attachment` array to `Moment`'s own JSON-LD without `core`'s `Federation(Moment::class)`
  registration knowing the gallery extension exists. Same ownership shape `ValidatesModels`
  already established for extra validation rules on a model you don't own; new
  `Kopling\Core\Extension\Contract\ExtendsFederatedObjects::federatedObjectContributions():
  array<class-string, Closure>`, aggregated the same *uncached, per-request* way
  `federatedModels()` is (closures, same Phase 0c gotcha), merged into
  `Federation\Manager::toActivityJson()` after the owning registration's own output — envelope
  fields (`@context`/`id`/`type`/`attributedTo`) are merged last, so neither `toActivity()` nor a
  contribution can override them regardless of what either returns. Outbound only; no inbound
  counterpart (see the contract's own docblock for why).
- Canonical AP `id`s are their own `/ap/...` URIs, deliberately *not* content-negotiated onto the
  existing human-facing `/p/{person}` (owned by `profile`) or moment/reply pages (owned by
  `discussions`) — this avoids the activitypub extension reaching into either extension's own
  controllers, consistent with the ownership rule; a stable dereferenceable URI doesn't have to
  be the human page's own URL.

## Phase 3 — Outbound: Follow/Accept and delivery

- Own migration: `activitypub_follows` (`follower_uri`/`following_person_id` or the reverse,
  `state`: pending/accepted). This is the extension's own bookkeeping table for the AP handshake,
  not a general social graph (see Scope).
- `Federation\Jobs\DeliverActivity` (queued, `tries`/`backoff` set) — signs the outbound activity
  with the local actor's `private_key` using HTTP Signatures (draft-cavage scheme — what
  Mastodon and the rest of the deployed fediverse actually check today; RFC 9421 support can
  follow later without changing the outbound shape) and POSTs to each follower's
  `inbox`/`shared_inbox`.
- New `Moment` created → dispatch delivery to the author's accepted followers. Wire this via the
  existing `ListensToEvents`/event mechanism this codebase already uses for cross-cutting hooks
  (e.g. `QueryingMoments`), not a new observer pattern.

## Phase 4 — Inbound: signature verification and the inbox

- `POST /ap/people/{person}/inbox` (and one shared inbox) — a route-level `VerifyHttpSignature`
  middleware (only this route, not Portal-wide — actor/webfinger GETs stay unauthenticated)
  resolves the sending actor (fetching + caching it, see Phase 5), verifies the `Signature`
  header against its `public_key`, and rejects before any body parsing on failure.
- Verified requests dispatch `Jobs\ProcessInboundActivity` (queued — never processed inline off
  an unauthenticated-until-verified request), which switches on the AP `type` (`Follow`,
  `Accept`, `Undo`, `Create`, `Delete`, `Like`) to the relevant handler.

## Phase 5 — Inbound: remote actors as real `Person` rows

The "fake People" idea, made concrete: `Federation\Manager::resolveActor(string $uri): Person` —
if an `activitypub_actors` row with that `remote_id` exists and `fetched_at` is fresh, return its
`Person`; otherwise fetch the actor document, `Person::create(['origin' => host(...), ...])` (or
reuse the existing Person if one's `activitypub_actors` row already matches `remote_id`), and
`ActivitypubActor::updateOrCreate(['remote_id' => $uri], ['person_id' => ..., 'public_key' => ...,
'inbox_url' => ..., 'fetched_at' => now()])`, `password`/`email` left null on the `Person` row
itself. Every existing Blade/Ux path that reads a `Person` (avatar, `Person::colorFor()`,
`Card\Author`, the admin People list) keeps working unchanged, because a remote actor genuinely is
a `Person` row — this is what "remote content is first-class, never a shadow table" cashes out to.
`hasPermission()` returning `false` (0 group rows) means a remote actor can never accidentally
pass a permission check meant for real Group grants.

## Phase 6 — Inbound: `Create{Note}` → `Reply`

- `discussions`' own migration adds `origin` (only) to `replies` (its own table, its own decision
  to opt in — see Ownership map above); `remote_id`/`federated_at` for a Reply live in
  activitypub's own `activitypub_objects` table instead.
- `discussions/src/Extension.php` implements `HasFederatedModels`, registering `Reply` with
  `apType('Note')`, `contentField('body_html')`, and a `fromActivity` closure that resolves the
  target `Moment` from the activity's `inReplyTo` (matching against `activitypub_objects.remote_id`,
  falling back to parsing the local `/ap/moments/{id}` URI), then calls `Reply::create([...])`
  exactly the way `DiscussionController::store()` does for local replies today — `activitypub`'s
  own `Federation\Manager` writes the resulting `activitypub_objects` row after `fromActivity`
  returns the new `Reply`, so `discussions` never touches that table itself.
- Inbound `content` HTML is sanitized through the **same whitelist** the editor already uses for
  local Tiptap output (`k-core/src/Ux/Editor`'s `Editor::allow()`/`DocumentRenderer` path) — per
  the charter, this whitelist is explicitly "the XSS and federation-sanitization model," so this
  reuses an existing boundary rather than building a new one.

## Phase 7 — Admin surface

Reuse `HasAdminSettings`/`Ux\Form\*` (2026-07-14 decision) for: the remote-domain blocklist —
genuinely admin-owned, no new admin framework needed. No separate global federation on/off
toggle (2026-08-10 reversal — see decisions.md): `kopling:extensions:enable`/`disable` already is
that switch, and a second one living behind the same admin surface it would also gate is a
redundant kill-switch, not a more surgical one.

**Known gap, decided out of scope for this plan (2026-08-10):** setting a Person's own
`handle`/`federation_enabled` (Phase 2) needs a per-person settings page, which doesn't exist
anywhere in this codebase yet (`HasAdminSettings` is explicitly admin-only — its own docblock
reserves the name `HasSettings` for a future per-person contract that was never built). Building
that page is its own project, not this plan's. Until it exists, setting a `handle` is a manual
`ActivitypubActor::create()`/tinker step — every route/job in this plan that reads `handle`/
`isFederating()` works correctly once one exists, they just have no in-app way to create one yet.

## Phase 8 — Hosting posture

Given `QUEUE_CONNECTION=sync` by default and no scheduler wired up yet anywhere in this codebase,
delivery/retry must not assume a real queue worker. Outbound delivery is a persisted
`activitypub_deliveries` row (`person_id`, `inbox_url`, `activity`, `attempts`, `last_error`,
`delivered_at`), not just a queued job's in-memory payload — created by the one entry point,
`Federation\Manager::queueDelivery()`, before `DeliverActivity` (which takes just the delivery's
id) ever dispatches; a `sync`-driver failure that would otherwise vanish the moment the triggering
request ends instead leaves a real, findable row. `DeliverActivity`/`ProcessInboundActivity` get
real `tries`/`backoff` for hosts that do run a worker; the extension additionally ships
`kopling:activitypub:deliver-pending` (via `HasCommands` — named to match this codebase's own
`kopling:{extension}:*` convention, not the plan's original `federation:deliver-pending`
shorthand) as the degraded-host fallback a host operator can cron: re-dispatches
`DeliverActivity` for any `activitypub_deliveries` row still undelivered and stale (no update in
5 minutes, so it never races an in-flight real-worker attempt) and under a 10-attempt cap —
consistent with the charter's "polling/SSE-over-FPM fallback, cron scheduler" posture, not a new
mechanism invented for federation specifically.

## Testing

Pest `Feature/` tests (booted app needed — signature verification and route-model binding both
need it, same reasoning as the existing `Manager::ux()` gotcha): webfinger resolution, actor
JSON-LD shape, outbound signing round-trips through a real verifier, inbound signature rejection
on tampered bodies, `Create{Note}` → `Reply` ingestion end-to-end using `Http::fake()` for the
remote actor fetch. Use `fakeManager()` (`tests/Pest.php`) for the closure-free parts
(`HasPermissions`, `HasAdminSettings`); the `HasFederatedModels` registration itself can't go
through the same fixture-based cache control `ValidatesModels` uses, per the Phase 0c gotcha —
test it against real `Manager::federatedModels()` instead.

## Open questions worth a real answer before Phase 4/6 start

- Authorized fetch (signed GETs required to view actor/object JSON, not just inbox POSTs) —
  Mastodon defaults to requiring this now; deciding v1 requires it changes Phase 2's routes.
- Remote-domain allowlist vs. blocklist as the default admin posture.
- Whether NodeInfo (`/.well-known/nodeinfo`) ships alongside WebFinger in v1 — cheap to add, not
  strictly required for basic Follow/Create interop, and not asked for here.