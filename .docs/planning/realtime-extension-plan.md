# Plan: `kopling/realtime` — an opt-in WebSocket/broadcasting extension

Status: proposed, not started. No code written yet.

## Why this is opt-in, not a core upgrade

[decisions.md, 2026-07-10](decisions.md) already settled this for the community feed: new
Moments show up via plain htmx polling (`hx-trigger="every 12s"`), explicitly **not** SSE or
Reverb, because this install's actual hosting profile is `QUEUE_CONNECTION=sync`,
`SESSION_DRIVER=file`, `CACHE_STORE=file` — shared-hosting tier, no Redis, nothing long-running.
That decision called Reverb/SSE a "known upgrade path, deliberately not pursued without a
concurrency plan."

This plan is that concurrency plan, scoped as a separate, disableable extension rather than a
change to core's default behavior. A Kopling instance on shared hosting installs it never; an
instance with a real host (able to run a persistent Reverb process, willing to set
`QUEUE_CONNECTION` to something other than `sync`) installs it and gets live updates in the
places that opt in. Nothing about core's polling fallback changes — this extension does not
replace it, it gives extensions a second option they can adopt individually.

## What htmx 4 actually gives us: `hx-ws`, and why it can't point at Reverb directly

htmx 4 ships a first-party WebSocket extension (`node_modules/htmx.org/dist/ext/hx-ws.js`,
`htmx-guidance.md`'s "many useful extensions" note). Its contract is deliberately simple:

- `hx-ws:connect="<url>"` opens a raw `WebSocket` to that URL (relative URLs resolved to
  `ws(s)://` against the current origin).
- The server pushes either raw HTML text (swapped wholesale) or JSON shaped
  `{content, target, swap}` — `content` is the HTML fragment, `target`/`swap` are optional
  CSS-selector/swap-style overrides, otherwise it falls back to the connecting element's own
  `hx-target`/`hx-swap`.
- `hx-ws:send` submits a form/element over the same socket as a JSON envelope
  (`{headers, body}`), with reconnect/backoff/pause-on-background handled automatically.

That contract assumes the server speaks *exactly* that frame shape. Laravel's own WebSocket
story — **Reverb** — doesn't: Reverb implements the Pusher protocol (connect, then send a
`pusher:subscribe` frame per channel, receive `{event, channel, data}` envelopes, respond to
Pusher's own ping/pong). Pointing `hx-ws:connect` straight at a Reverb server would open the
socket but every subsequent step (channel subscription, message shape) would need custom
JS anyway — at which point `hx-ws` isn't buying much.

**Two real options, not a false one:**

1. **Roll a from-scratch raw WebSocket server** (Ratchet/ReactPHP or a small Workerman process)
   that speaks `hx-ws`'s exact frame shape natively — no channel-subscribe handshake, no Pusher
   envelope, just `{content, target, swap}` pushed to whichever sockets are subscribed to a
   given path/channel. `hx-ws:connect` then works completely unmodified, zero custom JS.
   Cost: we own connection auth, channel routing, and horizontal scaling ourselves — none of
   that is solved for us.

2. **Use Laravel Reverb** (first-party, actively maintained, part of `laravel/framework`'s own
   ecosystem, supports private/presence channels via the existing `Broadcast::channel()`
   auth model, horizontally scalable via Redis pub/sub) and bridge its Pusher-protocol frames
   into `htmx.swap()` calls with a small first-party JS adapter shipped as this extension's
   compiled asset. We lose the "just write `hx-ws:connect` in Blade" ergonomics — broadcasting
   extensions call a small `Realtime::broadcast(...)` PHP helper instead of hand-writing
   `hx-ws:connect`, and the bridge script (not `hx-ws` itself) does the swap — but we inherit
   Reverb's auth, presence, and scaling story instead of re-implementing it.

**Recommendation: option 2.** Kopling already deviates from "always prefer the native htmx
mechanism" when the alternative is reimplementing infrastructure Laravel already solves well —
same reasoning as trusting `Broadcast::channel()` for auth rather than hand-rolling session
validation over a raw socket. `hx-ws` stays fully available for anything that *does* want a bare
`ws://` endpoint (option 1 territory) — this extension just doesn't force every future realtime
feature through Reverb's protocol via `hx-ws` directly. Flagging this explicitly because it's a
foundational call for a brand-new extension, not a default CLAUDE.md already commits to — worth
confirming before real work starts.

## Shape of the extension

`k-extensions/realtime`, `Kopling\Realtime\Extension`, composer name `kopling/realtime`.

### Composer / infra dependencies

- `laravel/reverb` (the WS server + Laravel Broadcasting driver integration).
- No new PHP dependency for the client side — the bridge is vanilla JS/`fetch`, built the same
  way `tag-input`/`emoji-picker` bundle a real npm dependency into their own compiled asset
  (`resources/js/app.js` → `dist/app.js`, per CLAUDE.md's "How extensions ship compiled assets").
  Candidate npm deps for the Pusher-protocol client: `pusher-js` directly (what `laravel-echo`
  itself wraps) — pull in `laravel-echo` only if its presence-channel/whisper helpers turn out to
  save real code; a raw `pusher-js` subscribe + `.bind()` call is a handful of lines and keeps
  one fewer package to track.
- Requires `config/broadcasting.php` to exist and be published — it currently doesn't
  (`config/` has no `broadcasting.php` today, confirming broadcasting is fully unconfigured).
  Whether this extension's service-provider wiring publishes/merges that config itself, or
  documents a one-time `php artisan install:broadcasting`-equivalent step for the host operator,
  is an open question for the design doc, not this plan — the root Laravel installation holds no
  application code, so this can't be "just add a route/config file to the root install."

### Host requirements (documented, not enforced)

No extension "requirements" contract exists in `k-core` today to gate on missing
infrastructure — an extension either loads or doesn't (`kopling:extensions:enable`/`disable`).
This extension should degrade *quietly*, not throw, when Reverb isn't actually running: a page
with a dead bridge connection is a page that just never gets a live update, same failure mode as
someone's tab losing network — not a 500. Document the real requirements plainly (extension's own
`description()`, an admin-settings note if `HasAdminSettings` is used):

- A persistent `php artisan reverb:start` process (or managed equivalent) — this is the one
  genuinely new operational requirement; nothing else in Kopling today needs a long-running
  process.
- `QUEUE_CONNECTION` away from `sync` recommended (not required — Reverb can broadcast
  synchronously) so a broadcast doesn't block the HTTP response that triggered it.
- `BROADCAST_CONNECTION=reverb` plus the `REVERB_APP_ID`/`REVERB_APP_KEY`/`REVERB_APP_SECRET`/
  `REVERB_HOST`/`REVERB_PORT` env vars.

### PHP-side API other extensions call

Mirrors the existing `RequestsStorageDriver`/`StorageRequest` split (an extension asks for a
capability by declaring intent; the owning extension decides mechanism/placement) rather than
having `realtime` reach into another extension's models — keeps with CLAUDE.md's "Feature
ownership across extensions" test. Concretely, something like:

```php
interface BroadcastsFragments
{
    /** @return array<FragmentBroadcast> */
    public function broadcasts(): array;
}
```

...where a `FragmentBroadcast` declares a channel name, the event(s) that should trigger a push
(a class-string domain event, same events `ListensToEvents` extensions already dispatch/listen
to — e.g. `pin`'s existing use of `RenderingCard`/`QueryingMoments` is the precedent for
"core-dispatched lifecycle events other extensions hook"), and a callback/view resolving to the
HTML fragment + target selector + swap style to push. `realtime`'s own `Manager`-registered
listener renders the fragment server-side and calls `Broadcast::channel(...)->send(...)` (queued
job, respecting whatever `QUEUE_CONNECTION` the host has configured) — consuming extensions never
touch Reverb, Pusher-protocol, or Echo directly.

The exact contract shape (this needs a design pass, not a guess baked into a plan doc) is the
first real implementation task below.

### JS-side bridge

One small compiled asset (`resources/js/app.js` → `dist/app.js`), registered via
`PortalExtension::compiledAssets()` on whichever Portals opt in. Responsibilities:
subscribe to a channel named in a `data-realtime-channel` attribute (or similar) on elements
opting in, and on receipt of an event call `htmx.swap()` with the same `{content, target, swap}`
shape `hx-ws` itself uses server-side — so from a Blade-author's perspective the mental model
("server pushes a fragment, target/swap decide where it lands") stays identical to `hx-ws`, even
though the transport underneath is Echo/Pusher-protocol, not a raw socket. Auth for
private/presence channels goes through Laravel's standard `/broadcasting/auth` POST endpoint,
registered from the extension's own `routes/` per the usual `ExtendsPortals` convention (a
Portal's own middleware/prefix apply automatically, same as `pin`/`reactions`'s own routes).

### Candidate first consumers (not built as part of this plan)

Named here only to sanity-check the contract shape against real call sites once implementation
starts — none of these are in scope for the plan itself:

- `discussions` — a new reply appearing live in an open thread.
- `reactions` — a reaction count ticking up without a manual refresh.
- `widgets` — the existing `PulseWidget` (`hx-trigger="every 60s"` today, per
  `htmx-opportunities.md`) becoming push-driven instead of polled, as a concrete before/after of
  the same UI moving from the polling default to the realtime opt-in.

## Open questions to resolve before implementation

1. **Config publishing** — how `config/broadcasting.php` gets onto a host that has no
   application code at the root. Likely answer: the extension's Laravel service-provider layer
   (inside `Manager`'s existing extension-boot path) merges the config in-memory the same way
   Laravel packages normally do (`mergeConfigFrom`), never requiring the host to `php artisan
   vendor:publish` into the root — but confirm against how `k-core` itself handles any config
   today before assuming.
2. **`FragmentBroadcast`/`BroadcastsFragments` contract shape** — needs its own short design
   pass (method signatures, how the fragment view receives its data, how channel names get
   namespaced per-extension the way permissions/Ux ids already are via `Manager`'s prefixing).
3. **Presence/auth model** — do any early consumers need presence channels (who's currently
   viewing a thread) or is private-channel auth (can this person see this Moment) sufficient
   for the first cut? Presence adds real complexity (join/leave events, member lists) worth
   deferring unless a concrete consumer needs it.
4. **Local dev story** — running `php artisan reverb:start` alongside `php artisan serve`/Vite
   during monorepo development; whether `composer.json`'s dev scripts should know about it at
   all given it's opt-in infra, or whether that's purely a per-developer README step.
5. **`pusher-js` vs `laravel-echo`** — confirm the lighter dependency is actually sufficient
   once the first real consumer's needs (presence or not, per Q3) are known.

## Suggested implementation order

1. Resolve the open questions above (a short design doc, not code).
2. Scaffold `k-extensions/realtime` following the `example` extension's layout
   (`src/Extension.php`, `routes/`, `resources/{css,js}/`, `lang/`, `composer.json`), wired for
   `ExtendsPortals` + `compiledAssets()` only — no domain logic yet.
3. Land the `BroadcastsFragments` contract in `k-core` (new file under
   `Extension/Contract/`, aggregated by `Manager`, same shape as `ValidatesModels`/
   `RequestsStorageDriver`).
4. Implement the PHP broadcast pipeline (render fragment → queue job → `Broadcast::channel()`)
   end to end with a throwaway test consumer (mirroring how `example` demonstrates every
   mechanism without being real functionality).
5. Implement the JS bridge, verified against that throwaway consumer in a real browser (per
   CLAUDE.md's UI-testing rule — Pest alone can't confirm a live push actually swaps the DOM).
6. Pick one real consumer (`widgets`' `PulseWidget` is the smallest, already-polling candidate)
   and convert it, as the first real proof this holds together end to end.
7. Record the final contract shape and the Reverb-vs-raw-socket call as a `decisions.md` entry
   once implemented — this plan documents the investigation, not the decision itself.
