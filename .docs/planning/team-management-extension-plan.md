# Plan: a Dutch youth soccer team management extension

Status: renamed from `kopling/soccer-management` to `kopling/sports-management` (2026-09-29, see
decisions.md). All three phases built, plus a reworked tracking page (2026-09-29): a field with K/D/M/F
zones and a bench of avatars, a stored pre-kick-off lineup, and a single match clock driven by
Kick off / Break / Continue / End match / Resume. Package `kopling/sports-management`
(`k-extensions/sports-management`), own Portal `sports-management`, every table `sm_`-prefixed.
See decisions.md, 2026-09-21, 2026-09-28 (×2) and 2026-09-29 (×3).

## Where we left off (2026-09-30)

The extension's tests (61) pass. 2026-09-29 work is committed (`0b6532b`); 2026-09-30 changes are not.
Migrations were edited in place (pre-production); the dev database was patched to match, a fresh
install needs nothing extra.

### Changes 2026-09-29
- **Roster:** positions are zero or more of K/D/M/F (`Position` enum, JSON `positions` column,
  `x-k::form.multi-select`); birth year removed. Roster sorted by name; its heading shows a count
  of permanent players and, separately, guests.
- **Team page:** Roster, Matches and Staff each in a daisyUI card (`card card-border`), in that
  order, team info + edit on top, a confirmed **Delete team** at the bottom (keeps `Person` rows).
- **Tracking page, field:** the top of the screen is a field in four zones (F, M, D, K), the bottom
  a bench. Players are core avatars (`<x-k::person.avatar>`, round) with one initial, or first + last
  initial when another player shares the first letter (`TeamMember::shortInitials()`).
  Drag (pointer events, works on touch) or tap-then-tap: bench → zone puts a player on, bench →
  field player replaces them (they go to the bench), field → field player swaps zones, field →
  bench takes a player off. One dependency-free delegated script, `js/app.js`; each move submits a
  hidden boosted form.
- **Zones in the data:** "on" substitutions carry a `zone`; `MatchTimeline::onField()` returns the
  zone per player; moves are computed by `FieldMove`.
- **Lineup:** `sm_match_lineups` stores the lineup before kick-off (`manage-matches`); kick-off (or
  the first period entered afterwards) turns it into the starting "on" events. After kick-off,
  field moves are substitutions at "now" (`track-matches`).
- **Absent players** (availability = absent) are left off the tracking page and refused by the
  lineup/field endpoints, unless they already have a lineup slot, substitution or goal.
- **Avatar badges:** core `<x-k::person.avatar>` got an optional `indicators` slot (daisyUI
  `indicator`); the tracking page uses it for a live-ticking minutes-played badge, an availability
  dot before kick-off, and a "G" for guests.
- **Match clock:** Kick off → Break / Continue (any number of times) → End match, with a
  confirmed **Resume match** afterwards. Periods still exist in the data but are internal. One
  clock counts play time only; a separate timer shows break length. Events are shown and entered
  by match minute (`MatchTimeline::matchSecond()` / `momentAt()`); a blank minute means "now".
  Times are stamped by the server on arrival (decided over browser tap time).
- **"Enter afterwards"** is one collapsed section: add a play/break with a length, and a
  substitution at a match minute (with zone).
- **Delete match** (confirmed) at the bottom of the tracking page too; removes lineup,
  availability, periods, substitutions and goals.
- **Match screen (field-side UX, round 1).** Designed for one coach, alone, phone in portrait,
  a squad of about 9 with 1–3 on the bench:
  - Two tabs: **Field** (sticky control bar, field, bench, Goal button) and **Report** (title,
    event log, time played, "Enter afterwards", Delete match). The chosen tab survives reloads.
  - Match controls live in the portal's top bar, in the left-hand `topbar-start` slot
    (`Ux\MatchControls`, renders only on the tracking route; the user menu keeps the right side): score, opponent **+1**, match clock, break timer, one main
    button (Kick off / Break / Continue), and a ⋯ menu (core `x-k::dropdown`) holding End match /
    Resume match. The match screen drops the left sidebar (`@extends(..., ['sidebar' => false])`).
  - **Goal in two taps:** Goal → tap the scorer → tap the assist or Skip. Goal dropdowns only
    remain in "Enter afterwards".
  - **Undo** for 10 seconds after a field change or goal (daisyUI `toast`); the server remembers
    the ids that action created in the session and deletes only those (60-second grace).
    Break / Continue / End match have no undo.
  - Moves show instantly (the avatar moves and pulses until the server confirms). htmx 4's
    `hx-optimistic` was checked and doesn't fit moving an existing element.
  - Screen kept awake (Wake Lock API) while the match is live; pull-to-refresh blocked.
  - Bench sorted by fewest minutes played after kick-off; avatars bigger (64px).
  - **Limits:** at most one keeper, and at most the format's players-on-field (e.g. JO10: 6),
    enforced by the server for both lineup and field, and checked in the browser first. No limit
    without a format preset. D/M/F are not limited.

### Changes 2026-09-30
- **One player order** everywhere (`TeamMember::sorted()`): permanent players before guests, each
  by name (natural, case-insensitive). The roster now lists guests last too.
- **Back leaves the match screen:** its actions replace the history entry instead of pushing one
  (`hx-replace-url:inherited` on the page and the top-bar controls).
- **Minutes badges tinted** against the squad shown: fewest amber (`warning`), around the middle
  plain, most blue (`info`), via `color-mix` on daisyUI's `--badge-color`. Set on each server
  render; a player only changes shade after the next action or reload.
- **Match page buttons equal size:** `x-k::modal` takes the trigger's classes from
  `<x-slot:trigger class="...">` when given (core change, default unchanged).
- **Availability buttons are icons** (check / question / cross, declared via `HasIcons`) on one row
  with the name, which truncates. The "Unknown" button is gone: no answer is shown as none
  selected, still stored as no row, and read-only viewers still see "Unknown".
- **Available count badge** is green once the format's players-on-field is reached, red below it;
  only "available" counts. No format preset: stays neutral.
- **Match page header:** back link + Track / Edit on one row, opponent, date, format and address
  below; Delete match moved to the bottom (same as Delete team).
- `decisions.md`: outdated "not started" statuses corrected (names left as-is, the 2026-09-29
  rename entry explains them).

### Still to check visually
- 2026-09-30 changes: one Back from the match screen returns to the match page, badge tints in
  light and dark, the match page on a phone (header row fits, availability icons on one row with
  the name, their colors when selected, red/green count badge), guests listed last on the roster.
- The match screen on a phone: two-tap goal, undo toast, instant moves, refused drop (red flash)
  for a second keeper or one player too many, screen staying awake, the ⋯ menu, tabs, and whether
  field + bench + Goal button fit under the top bar (height `calc(100dvh - 10rem)` is a guess).
- Top bar width on a phone: portal name + match controls + user menu in one row may not fit.
- The whole tracking page on a phone: dragging doesn't scroll the page, the dragged avatar follows
  the finger, tap-then-tap works, badges keep ticking after a move (boosted body swap), field and
  bench fit one screen under the top bar (height assumes a 4rem bar).
- Break timer ticking (fixed 2026-09-29: an inherited `$ticking` suppressed it), match clock
  standing still during a break, **Resume match** confirmation.
- Team page cards, the positions multi-select and roster counts.
- Earlier item not yet confirmed: edit controls hidden for staff missing a permission.

### Next
Visual pass on a phone (list above), then decide format presets (below).

### Open
- **Later:** a second person operating the phone; preparing substitutions ahead and applying
  them together.
- **Editing format presets:** still no screen for them; the seed leaves `rules_url` empty, so the
  "Rules" link never shows. Undecided: admin CRUD (recommended: in the admin portal), fill URLs in
  the seed only, or defer.
- **No signal on the sideline:** an action sent without a connection fails; queuing offline
  actions was left out on purpose.
- **Avatar icons from extensions** (e.g. roles): a `RenderingAvatar` event, same shape as
  `RenderingCard`, deferred until a first real use.

### Settled, no action needed
- **Edit team button** on the team page keeps the modal's default small trigger; looked fine.
- **Subsplit:** split to the read-only repository `kopl-ing/sports-management`
  (`.github/subsplit-config.json`), since 2026-09-29.

## Context

Not on `roadmap.md` yet — this is a new use case: managing one or more Dutch youth soccer teams'
rosters, fixtures, and match tracking as a Kopling extension. Three feature areas for iteration 1,
as given:

1. **Roster** — declare team members. These aren't Kopling accounts; they're virtual entries the
   same way `activitypub` declares an outside actor (see below).
2. **Match planning** — opponent, location/address, home or away, a reusable format preset
   (players-on-field, round length, number of rounds, link to the rules), guest players, and
   marking who's available/absent ahead of the match.
3. **Match tracking** — live/after-the-fact recording of substitutions, goals (scorer), match
   clock, and per-player time played.

## The roster pattern: `Person` rows, no login, extension-owned satellite table

`activitypub` (`k-extensions/activitypub/src/ActivitypubActor.php`) is the precedent named in the
request: a remote actor is a real `people` row, plus `activitypub_actors` — an extension-owned,
one-to-one satellite table carrying every fact that's specific to that extension's own domain
(`decisions.md`, 2026-08-10: AP-protocol columns live in `activitypub`'s own tables, never
bolted onto `people`).

The same shape fits here: a roster member is a real `Person` row (so `Card\Author`, permissions,
and every other core mechanism that already understands "a person" keeps working unchanged), plus
this extension's own satellite table (`sm_team_members`) holding roster-specific facts — jersey
number, zero or more positions (K/D/M/F), whether they're a guest. No
contact/guardian info (see "Decided (round 2)" below). Nothing about `people` itself changes;
this extension never touches that migration.

Unlike an AP remote actor, a roster member has no `origin` (that column means "federation
origin domain" per its own migration comment — not a generic "not-a-real-account" flag, so it's
the wrong field to reuse for "virtual"). What actually makes a roster member non-loginable is
simply the *absence* of anything from `auth-email-password` (or any other auth extension)
attached to that `Person` row — same as how a `Person` already works for anyone never given
credentials. No new "is virtual" column needed on `people` itself.

## Decided (round 1)

- **Teams, not single-team.** A `sm_teams` table: `name`, `club` (plain string for now — no
  `clubs` entity, no club-level multi-team management; that's explicitly excluded from v1),
  `season` (plain string, e.g. `"2026/2027"`), `format_preset_id`.
- **Staff is many-to-many, per-team.** An account (`Person`) can staff multiple teams; a team can
  have multiple staff accounts. A `sm_team_staff` pivot (`team_id`, `person_id`). Per pin's own
  precedent (`decisions.md`, 2026-07-16 — "no per-instance/ownership policy exists in this
  codebase"), this doesn't need a new core mechanism: it's this extension's own table, and its own
  controllers check `$team->staff` membership directly, same as any other extension-local
  authorization decision — not something core needs to grow a general concept for.
- **Availability: coach/admin-only for v1, real accounts later.** V1 has no self-service response
  flow — the coach/admin marks a roster member's availability directly. The roster pattern above
  already leaves the door open for "later": giving a roster member's `Person` row real login
  credentials (wiring up `auth-email-password` or similar) is the whole upgrade path — no schema
  rework needed, since a roster member already *is* a plain `Person` row, identical in shape to
  one that can log in. When that lands, availability becomes something the linked account (player
  or guardian) can set on their own roster row instead of the coach setting it for them.
- **Live tracking: single operator.** One coach/admin enters events (goals, subs, periods) on
  their own device, live or after the fact. No multi-viewer live-updating requirement, so this
  needs nothing beyond normal htmx form posts — `k-extensions/realtime` (still just a plan, not
  built) isn't a dependency here.
- **Format preset: KNVB categories, players-on-field only.** A small fixed set of presets
  (JO7 4v4, JO9 6v6, JO11 8v8, JO13/15/17/19 11v11, etc.) — each just a name, a players-on-field
  count, and a rules-link URL. **Round length and number of rounds are deliberately excluded from
  the preset.** Feedback from prior tooling: a fixed round/break schedule baked into the preset
  gave no way to amend a forgotten or early break marker during a live match. Instead, periods
  (halves/thirds/quarters, breaks) are tracked as their own start/end events during match
  tracking (see below) — freely correctable after the fact, not derived from a fixed schedule.
  Defaults on `Team` (a team plays one age category all season); overridable per `Match` for the
  rare exception fixture.

## Decided (round 2)

- **Guest players are regular roster rows**, `sm_team_members.guest = true`. Reusable if the same
  guest plays again; shows up in history/stats like any other member — no separate match-scoped
  entity.
- **Assists are in scope for v1** alongside scorer. Own-goal/penalty flags and cards are **not**
  — strictly goals (+ assist) + substitutions + time played, as originally scoped.
- **No guardian/contact info, no guardian accounts, for now.** Coach/staff stay in sole control
  of the roster and availability; nothing about a linked guardian is modeled yet. `sm_team_members`
  carries no contact columns. This also simplifies the "real accounts later" upgrade path down to
  one case worth designing for when it actually comes up — a *player's own* `Person` row gaining
  login credentials — rather than two (player vs. guardian-on-behalf-of-player).

## Open questions

None outstanding from the design rounds. Open implementation items are listed under "Where we left off" above.

### Decided by default (flag if wrong)

- **Substitutions are unlimited/rolling**, not a fixed sub count — matches Dutch youth football's
  "vrij wisselen" at most age categories. The model needs to support arbitrarily many in/out
  events per player per match.
- **Periods are explicit start/end events** (see "Format preset" above) — not derived from a
  preset schedule, freely correctable (add a missed break, adjust a mistaken end time) after
  the fact. Since 2026-09-29 they're internal: the user presses Break / Continue / End match and
  sees one match clock.
- **Address is freeform text** for v1, not structured street/postcode/city — cheap to split
  later if map-linking/geocoding ever becomes a real requirement.
- **Match result is derived from goal events**, not a separately-entered score — one source of
  truth, no risk of the entered score and the goal log disagreeing.
- **Rules link is a plain URL field** on the format preset (e.g. the KNVB spelregels page for
  that category).
- **Permissions are separable**, not one undifferentiated role: distinct `HasPermissions`
  permissions for managing a team's roster, planning its matches, and tracking a live match —
  declared locally and Manager-prefixed per convention, checked *in addition to* `sm_team_staff`
  membership (holding the permission is necessary but not sufficient — it must also be for a team
  you actually staff). The portal-gate permission is named after the extension itself
  (`access-sports-management`, matching the `access-admin`/`access-mail` convention), while
  domain-action permissions (`manage-teams`) keep the domain word, matching `manage-tags`/
  `manage-people`.
- **Extension**: `kopling/sports-management` (`k-extensions/sports-management`,
  `Kopling\SportsManagement`), own Portal (id/path `sports-management`, i.e.
  `kopling-sports-management::sports-management` at URL `/sports-management`) — teams/rosters/
  matches are a large enough surface to warrant one, same reasoning as `moderation`'s own Portal.
  **Every table this extension owns is `sm_`-prefixed**, and every identifier naming the
  extension/Portal itself (package, namespace, table prefix, Portal id/path, portal-gate
  permission, route/view/lang file names) was renamed together — decided when renamed from the
  working name `kopling/team`, since this is meant for private use, where a distinct,
  collision-proof identity matters more than it would for something upstreamed. Domain vocabulary
  (the `Team`/`TeamMember` classes, "Teams" UI copy, the `manage-teams` permission) stays as
  "team" throughout — only the extension's own outward identity changed.

## Proposed data model (sketch)

All tables below are `kopling/sports-management`'s own, `sm_`-prefixed — nothing added to
`people` or any other core table, per the ownership pattern above. Sketch level (tables + key
columns), not migration code — confirm the shape before Phases 2-3 turn it into real migrations.

- **`sm_teams`** — `name`, `club` (string), `season` (string, e.g. `"2026/2027"`),
  `format_preset_id` (FK, nullable).
- **`sm_team_staff`** — pivot: `team_id`, `person_id`.
- **`sm_team_format_presets`** — `name` (e.g. `"JO11"`), `players_on_field`, `rules_url`. A small
  seeded set of KNVB categories, editable like any other admin-managed data (not a config file,
  per CLAUDE.md's "avoid config files").
- **`sm_team_members`** — one-to-one satellite on a `Person` (`person_id`, unique), `team_id`,
  `jersey_number`, `positions` (JSON array of the `Position` enum: K/D/M/F, zero or more),
  `guest` (bool, default false). No birth year. No contact/guardian
  columns — coach/staff manage the roster directly, no guardian-on-behalf-of-player concept yet.
- **`sm_matches`** (Phase 2, built) — `team_id`, `opponent_name`, `home_away` (enum),
  `location_address` (text), `format_preset_id` (nullable FK — defaults from
  `team.format_preset_id`, overridable per match), `scheduled_at`. No stored result/state column
  — final score and planned/in-progress/final state are both derived from
  `sm_match_goals`/`sm_match_periods` (one source of truth, can't drift out of sync with the
  event log).
- **`sm_match_availabilities`** (Phase 2, built) — `match_id`, `team_member_id`, `status`
  (available / absent / maybe). Coach/admin-entered for v1; becomes self-service once a roster
  member's `Person` gets real login credentials (no schema change needed for that later step).
- **`sm_match_periods`** (Phase 3, built) — `match_id`, `sequence`, `type` (play / break),
  `started_at` (nullable — only set when tracked live), `duration_seconds` (nullable while a live
  period is running). Replaces the sketch's `ended_at`: a period entered afterwards has a length,
  not wall-clock times. Freely correctable (edit type/length, add a missed break).
- **`sm_match_lineups`** (built) — `match_id`, `team_member_id`, `zone`: the planned starting
  lineup, arranged on the field before kick-off; turned into period 1's "on" events at kick-off.
- **`sm_match_substitutions`** (Phase 3, built) — `match_id`, `period_id`, `team_member_id`,
  `direction` (on / off), `zone` (K/D/M/F on "on" events), `offset_seconds` within that period (blank minute = "now" for a running
  period). Starters are plain `on` events at offset 0 of period 1. A substitution during a break
  takes effect from the next play period. Time played is replayed from these events per play
  period — unlimited/rolling subs, no sub-slot count.
- **`sm_match_goals`** (Phase 3, built) — `match_id`, `period_id`, `opponent` (bool),
  `scorer_team_member_id` (nullable — unknown scorer), `assist_team_member_id` (nullable),
  `offset_seconds`. An explicit `opponent` flag instead of "null scorer = opponent", so "our goal,
  scorer unknown" stays representable.

## Suggested phases

1. **Roster & teams — built.** `sm_teams`, `sm_team_staff`, `sm_team_format_presets` (seeded),
   `sm_team_members`, permissions (`access-sports-management`, `manage-teams`), Portal skeleton, CRUD UI.
2. **Match planning — built.** `sm_matches`, `sm_match_availabilities`, planning UI
   (opponent/location/home-away/format/availability marking), `manage-matches` permission.
3. **Match tracking — built.** `sm_match_periods`, `sm_match_substitutions`,
   `sm_match_goals`, live/after-the-fact tracking UI (period start/end, sub in/out, goal+assist
   logging), derived score/state display, `track-matches` permission.
