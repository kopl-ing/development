# Plan: ActivityPub federation

Status: proposed, not started. No code written yet.

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

- **`k-core`** — owns `people`/`moments` schema (adds `origin` + identity columns), owns the new
  `Portal::$middleware` capability, owns the new `Extend\Federation` declaration +
  `HasFederatedModels` contract: the extensibility surface other packages register through. Core
  defines the *shape*, the same way it already does for `ValidatesModels`/`RequestsStorageDriver`
  — it does not itself depend on the activitypub package.
- **`k-extensions/activitypub`** — owns the Portal, all `/ap/*` + `/.well-known/*` routes,
  signature verification, JSON-LD serialization, the delivery queue, and its own `follows` table.
  Reads `HasFederatedModels` declarations the same way `ValidatesModels` is read today.
- **`k-extensions/discussions`** — owns adding `origin`/`remote_id`/`federated_at` to its own
  `replies` table (it's discussions' own schema either way — whether a Reply is local or remote
  is a fact about what a Reply *is*), and registers `Reply` against `HasFederatedModels` with its
  own inbound/outbound closures, the same way it already owns `Reply::forMoment()` etc.

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

**0b. Federation identity columns**, one migration per owning package:

- `k-core/migrations/..._add_federation_columns_to_people_table.php`: on `people` — `origin`
  (nullable string, null = local), `remote_id` (nullable string, unique-when-set — the canonical
  AP actor URI), `inbox_url`/`outbox_url`/`shared_inbox_url` (nullable, remote actors only),
  `public_key`/`private_key` (nullable text, `private_key` only ever set for local Persons),
  `fetched_at` (nullable timestamp — last WebFinger/actor refetch). Also relax `password` and
  `email` to nullable (a remote actor has neither), and audit wherever `email` is read for
  display (profile page, admin People list) for the "always present" assumption that no longer
  holds.
- `k-core/migrations/..._add_federation_columns_to_moments_table.php`: `origin` + `remote_id` +
  `federated_at` on `moments`, mirroring the above — schema-ready per the charter promise, even
  though inbound remote-Moment ingestion isn't built in this plan.
- `Person` (`k-core/src/People/Person.php`) gains `isRemote()`/`isLocal()` helpers
  (`!is_null($this->origin)`). `hasPermission()` needs no change — a remote Person naturally has
  zero `group_person` rows, so it already returns `false`, same reasoning `Guest` documents.

**0c. `Extend\Federation` + `HasFederatedModels`** (mirrors the existing `Extend\Model` +
`ExtendsModels` pair): new `k-core/src/Extend/Federation.php` — fluent, one instance per
federatable model: `apType(string)` (`'Note'`, `'Article'`, ...), `contentField(string)`,
`attributedToRelation(string)`, `toActivity(Closure)` (outbound serialization hook),
`fromActivity(Closure)` (inbound: given a decoded activity + resolved author `Person`,
return-or-create the local row). New `k-core/src/Extension/Contract/HasFederatedModels.php`:
`federatedModels(): array<Federation>`.

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

- `GET /.well-known/webfinger?resource=acct:{name}@{domain}` → resolves a local `Person` by
  name, returns the JRD pointing at their actor URI.
- `GET /ap/people/{person}` → actor JSON-LD (name, `inbox`, `outbox`, `publicKey` from the
  columns added in 0b). A local `Person`'s key pair is generated lazily here (or on first
  outbound send), via an `Extend\Model(Person::class)->saving(...)` hook the activitypub
  extension registers through the *existing* `Extend\Model`/`ExtendsModels` mechanism
  (2026-07-15 decision) — core's own `Person` model needs no federation-aware code at all; the
  extension supplies the behavior, core only supplied the columns.
- `GET /ap/{type}/{id}` — one generic controller, resolves the model via each package's
  registered `Extend\Federation` (`apType()`/`contentField()`/`toActivity()`), so this route
  never hardcodes "Moment" or "Reply" by name; adding a third federatable model later is a
  registration, not a route change.
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
if a `people` row with that `remote_id` exists and `fetched_at` is fresh, return it; otherwise
fetch the actor document and `Person::updateOrCreate(['remote_id' => $uri], [...])`, with
`origin` set to the actor's host, `public_key` stored, `password`/`email` left null. Every
existing Blade/Ux path that reads a `Person` (avatar, `Person::colorFor()`, `Card\Author`, the
admin People list) keeps working unchanged, because a remote actor genuinely is a `Person` row —
this is what "remote content is first-class, never a shadow table" cashes out to.
`hasPermission()` returning `false` (0 group rows) means a remote actor can never accidentally
pass a permission check meant for real Group grants.

## Phase 6 — Inbound: `Create{Note}` → `Reply`

- `discussions`' own migration adds `origin`/`remote_id`/`federated_at` to `replies` (its own
  table, its own decision to opt in — see Ownership map above).
- `discussions/src/Extension.php` implements `HasFederatedModels`, registering `Reply` with
  `apType('Note')`, `contentField('body_html')`, and a `fromActivity` closure that resolves the
  target `Moment` from the activity's `inReplyTo` (matching on `remote_id`, falling back to
  parsing the local `/ap/moments/{id}` URI), then calls `Reply::create([...])` exactly the way
  `DiscussionController::store()` does for local replies today.
- Inbound `content` HTML is sanitized through the **same whitelist** the editor already uses for
  local Tiptap output (`k-core/src/Ux/Editor`'s `Editor::allow()`/`DocumentRenderer` path) — per
  the charter, this whitelist is explicitly "the XSS and federation-sanitization model," so this
  reuses an existing boundary rather than building a new one.

## Phase 7 — Admin surface

Reuse `HasAdminSettings`/`Ux\Form\*` (2026-07-14 decision) for: federation on/off, remote-domain
blocklist, and per-Person "accepts followers" default — no new admin framework needed.

## Phase 8 — Hosting posture

Given `QUEUE_CONNECTION=sync` by default and no scheduler wired up yet anywhere in this codebase,
delivery/retry must not assume a real queue worker. `DeliverActivity`/`ProcessInboundActivity`
get real `tries`/`backoff` for hosts that do run a worker; the extension additionally ships a
`federation:deliver-pending` console command (via `HasCommands`) as the degraded-host fallback a
host operator can cron — consistent with the charter's "polling/SSE-over-FPM fallback, cron
scheduler" posture, not a new mechanism invented for federation specifically.

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