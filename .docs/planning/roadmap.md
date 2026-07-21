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
- Reactions — `reactions` extension (emoji toggle + optional word, functional end-to-end,
  ships its own CSS via the head-assets outlet)
- Tags — `tags` extension (categorise + browse)
- Feed rail widgets — `widgets` extension (community pulse, popular tags)
- Theming — `theme-delft`, `theme-midnight`
- Admin settings framework — `admin` extension now has a real settings page (`/admin/settings`,
  gated behind a new `manage-settings` permission), Admin's first real `ExtendsPortals`
  attachment. `HasAdminSettings::adminSettings(): array<Field>` lets any extension declare
  fields, rendered via new `Ux/Form/*` components (`Toggle`/`Input`/`TextArea`), persisted in a
  flat `settings` key-value table. See decisions.md, 2026-07-14.
- People/Groups admin UI — `admin` extension can now list people and assign them to Groups, and
  create/rename/delete Groups (`/admin/people`, `/admin/groups`), gated behind the
  already-declared `manage-people` permission. See decisions.md, 2026-07-15.
- `Pin` extension — pin a Moment with a reason (translatable, fixed set: Announcement, Event,
  Important, Help), a reason-mapped daisyUI color, an optional start/end datetime window, and
  optional Groups targeting (empty = visible to everyone). A pinned-and-visible moment renders in
  a separate "pinned" section above the regular feed (the community layout's existing
  `content-top` slot) with a reason-colored card border, and is excluded from the regular
  chronological feed so it never shows twice. Gated behind a new `kopling-pin::pin-moments`
  permission. Built on two new small, generic Core mechanisms rather than Pin-specific ones:
  `Content\Event\QueryingMoments` (lets an extension filter the feed query) and
  `Ux\Card\Event\RenderingCard` (lets an extension append a class to a card's outer wrapper) —
  both reuse the existing `ListensToEvents`/`Manager::listeners()` mechanism, no new contract.
  See decisions.md, 2026-07-16.
- Upvotes — per-tag `upvote_emoji`/`downvote_emoji` config (not a `PALETTE` addition: that
  renders unconditionally on every card, which would make voting global rather than scoped to
  tags the community actually wants it on). Dedicated vote buttons sit above the reactions rail
  on any moment carrying a voting-enabled tag; reuses the existing `reactions` table, no new
  schema. A `?sort=top` feed mode orders by thumbs-up count. Also shipped Tags' first admin CRUD
  (`/admin/tags`, gated behind a new `manage-tags` permission), since per-tag vote config needed
  somewhere to be set. See decisions.md, 2026-07-18.

### Still needed

- Email confirmation on sign-up — `auth-email-password` currently only has password
  *confirmation* (matching fields on the form), no actual email-verification flow.
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
- Actual settings fields for real extensions (e.g. `reactions`) — the admin settings framework
  itself is built (see "Built" above), nothing has declared a real field with it yet.
- Person detail/profile page — see "People / Groups" below.
- Browser-verify the Popover-API-based `Dropdown`/native-`<dialog>`-based `Modal` primitives in
  older Safari/Firefox — flagged as a watch item when each was built, not yet checked.

---

## Out of scope / to do / to be decided

### Feed / community rendering
- No *general* mechanism to reorder or "float" specific items to the top of the feed, or a
  secondary rail/section alongside the main feed — still true for anything other than Pin. `Pin`
  (Checkpoint 1) solved its own specific instance of this need (a separate "pinned" section via
  the existing `content-top` slot, plus a `QueryingMoments` event any extension can use to filter
  the regular feed query), rather than building a general "float this item" primitive. Revisit if
  a second, different feature needs the same shape of thing.

### People / Groups
- People/Groups admin UI now exists (`/admin/people`, `/admin/groups` — see Checkpoint 1, Built).
  Still no person detail/profile page. Needs to cover, at minimum:
  - updating one's own email/password
  - avatar — upload, or fall back to Gravatar

### Ux / extensibility
- `UxEntry`/`SlotResolver`/`Manager::ux()` have no concept of Portal scoping — a `UxEntry`
  carries only a slot-name string, never which Portal it belongs to. Isolation between portals'
  regions (Community's nav/sidebar/rail vs. Admin's) is enforced purely by naming convention
  (`{package}::{portal}.{region}`), never structurally: two extensions picking the same slot
  string can silently leak into each other's chrome.
  - surfaced 2026-07-14: `example`'s illustrative nav registration and `admin`'s real Settings
    link both targeted the same literal `kopling-core::side-navigation` string (see
    decisions.md). Fixed for now by retargeting both to portal-owned slot names.
  - real Portal-scoping inside `UxEntry`/`SlotResolver` (e.g. requiring/validating a Portal id
    alongside the slot name) is a larger change touching every extension's `ux()` method and
    isn't designed yet.

### Content model / Moments
- A Moment can't currently be "feature-only" (an image, a poll, a product — with no title/body
  at all). Attaching a new content kind *alongside* a Moment's title+body is already fully
  extension-buildable today (teaser's own pattern: own model + `ChangesUx` into `Card\Body`/
  `Badges`/`Footer`) — only the textless case is blocked, by `moments.title`/`body` being
  `NOT NULL`, `StoreMomentRequest` hardcoding both `required` with no way for an extension to
  relax that, and `Card\Content`/`card.body.blade.php` rendering unconditionally rather than
  collapsing when empty. See decisions.md, 2026-07-21.

### Theming
- No high-contrast support. `ChangesTheme::colorScheme()` (added 2026-07-15) only covers the CSS
  `color-scheme` property (`light`/`dark`, native form-control/scrollbar chrome) for `theme-delft`/
  `theme-midnight`. `prefers-contrast: more` / boosted-contrast token variants are a separate,
  unaddressed axis — not something `ColorScheme` should grow into, since the CSS spec has no
  "high-contrast" color-scheme value.

### Root install
- `k-core`'s layout calls `@vite()` unconditionally — throws for anyone installing `kopling/core`
  standalone outside this monorepo (no `public/build/manifest.json` there). Needs a fallback to
  the shipped `k-core/dist/app.css`/`app.js`. Deferred until `kopling/core` is meant to be
  installed anywhere but this monorepo. (Already tracked in root `CLAUDE.md`.)
