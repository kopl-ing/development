# Roadmap

Checkpoints are release milestones — a bar to work toward, not a wishlist. Everything not
assigned to the current Checkpoint lives under "Out of scope / to do / to be decided" until it's
pulled in. Once a Checkpoint ships, mark it done and open the next one below it.

---

## Checkpoint 1 — Kopling's own community

**Goal:** an official community for Kopling itself, hosted at kopl.ing, where people can discuss
and upvote feature requests. Kopling dogfooding itself is the bar for "done" here.

### Built (preliminary)

- Sign up / sign in — `auth-email-password` (login + registration forms, event-based on Core's
  Attempt Login/Registration)
- Feed of moments — `k-core` (`Content/Moment` + community feed rendering)
- Composing a moment — `composer` extension (compose-first UI, plain `<textarea>` body)
- Replies / discussion thread per moment — `discussions` extension (activity teaser + engage bar)
  - `reply-dock` (sticky reply bar that morphs into a composer)
  - `thread-title` (moment title slides into sticky topbar on scroll)
- Reactions — `reactions` extension (emoji toggle + optional word, functional end-to-end)
  - blocked on: extension CSS has no way onto the page yet (no head-assets outlet in portal
    chrome), so it currently leans entirely on core's existing safelisted classes
- Tags — `tags` extension (categorise + browse)
- Feed rail widgets — `widgets` extension (community pulse, popular tags)
- Theming — `theme-delft`, `theme-midnight`
- Admin portal scaffold — `admin` extension (no configuration/settings yet — see "Out of scope")

### Still needed

- Upvotes, dual-purposed from `reactions` rather than a separate extension: thumbs up/down become
  a privileged reaction type, sticky above the other emoji in the card sidebar (reactions rail),
  and moments become sortable by thumbs-up count (a "Top" sort mode for the feed).
  - `Reaction::PALETTE` already has 👍, needs 👎 added.
  - new capability: a sort-order toggle for the feed (chronological vs. most-thumbs-up) — the
    feed is chronological-only today.
- Email confirmation on sign-up — `auth-email-password` currently only has password
  *confirmation* (matching fields on the form), no actual email-verification flow.
- Finish `reactions` styling — needs the head-assets outlet (extension CSS currently has no way
  onto the page) before it can ship its own CSS instead of borrowing core's.
- WYSIWYG/rich-text editor integration — `composer`'s body field is a plain `<textarea>`, no
  formatting.
- Minimal moderation — enough to not get overrun by spam/bots on day one.
  - polymorphic flagging on both Moments and replies (one flaggable mechanism, not two)
  - a moderation queue to review flagged content — location TBD (`admin` extension is the
    likely home)
  - rate limiting on posting/replying
  - scope beyond that TBD
- End-to-end test coverage across Checkpoint 1 functionality (sign up/in, post, reply, react,
  tag, pin) — only Pest unit/feature tests exist today, no browser/E2E layer confirmed yet.
- Pin extension — reason dropdown (translatable, fixed set), reason-mapped daisyUI color (no free
  swatch picker), optional start/end datetime, optional Groups targeting.
  - Groups targeting: selecting Groups scopes *for whom the moment is pinned* — everyone outside
    the selected Groups sees the moment as a normal, unpinned item in the regular feed.
  - decided: one active pin per moment (re-pinning replaces it, no history/concurrent pins).
  - decided: Pin's action lives in `Control`'s own dropdown menu (see below — now built), not a
    standalone button.
  - done: `Control::SLOT` (`kopling-core::card.control`) now resolves real menu entries via a new
    reusable `Kopling\Core\Ux\Dropdown` primitive (`k-core/src/Ux/Dropdown.php` +
    `views/ux/dropdown.blade.php`, popover-API-based); Pin registers into it the same way any
    extension targets a slot.
  - blocked on: no `<x-k::modal>` primitive exists in core (every extension needing a modal hand-
    rolls its own Alpine one, e.g. `reactions/views/components/modal.blade.php`).
  - blocked on: no reusable multi-select/Group-picker component exists anywhere — would need
    building from scratch.
  - blocked on: no UI to assign a person to a Group (see "People / Groups" below) — Groups
    targeting has nothing to target in practice until that exists.
  - watch: the new dropdown uses the HTML Popover API + CSS anchor positioning (daisyUI's
    recommended syntax) with no JS fallback — unsupported in older Safari/Firefox, where the
    menu won't open at all. Flagging now in case broader browser support becomes a requirement
    before Checkpoint 1 ships.

---

## Out of scope / to do / to be decided

### Extension configuration
- No extension has configuration/settings yet (including `admin` itself, which is currently a
  scaffold with nothing behind it). Postponed — not needed for Checkpoint 1.

### Feed / community rendering
- No mechanism to reorder or "float" specific items to the top of the feed, or a secondary
  rail/section alongside the main feed. Distinct from the thumbs-up "Top" sort mode (Checkpoint 1,
  above) — this would be for surfacing specific items regardless of vote count, not sorting.

### People / Groups
- No person detail/profile page exists. Needs to cover, at minimum:
  - assigning a person to a Group (the `Group`/`Person::groups()` data model already exists,
    just nothing hangs an admin action off it yet)
  - updating one's own email/password
  - avatar — upload, or fall back to Gravatar
  - blocks: Pin's Groups targeting from being usable end-to-end (see Checkpoint 1 above).

### Root install
- `k-core`'s layout calls `@vite()` unconditionally — throws for anyone installing `kopling/core`
  standalone outside this monorepo (no `public/build/manifest.json` there). Needs a fallback to
  the shipped `k-core/dist/app.css`/`app.js`. Deferred until `kopling/core` is meant to be
  installed anywhere but this monorepo. (Already tracked in root `CLAUDE.md`.)
