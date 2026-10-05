# Plan: `sports-management`, a Dutch youth soccer team management extension

> Since 2026-10-03 the extension also covers hockey, handball and basketball (korfbal postponed);
> the multi-sport design, milestones and progress log live in `sports-management-multi-sport-epic.md`.

Status: renamed from `kopling/soccer-management` to `kopling/sports-management` (2026-09-29, see
decisions.md). All three phases built, plus a reworked tracking page (2026-09-29): a field with K/D/M/F
zones and a bench of avatars, a stored pre-kick-off lineup, and a single match clock driven by
Kick off / Break / Continue / End match / Resume. Since 2026-10-02 format presets carry total play
minutes, used to mark a player's fair share of play time, and a moderation/abuse pass added team
owners, staff invitations and moderation-portal tooling (see "Moderation & abuse" below). Since
2026-10-04 staff are coaches or referees ("spelbegeleider"), with duties delegated per match. Package `kopling/sports-management`
(`k-extensions/sports-management`), own Portal `sports-management`, every table `sm_`-prefixed.
See decisions.md, 2026-09-21, 2026-09-28 (×2), 2026-09-29 (×3), 2026-10-02 (×5), 2026-10-03 (×2) and 2026-10-04.

## Where we left off (2026-10-04)

Local database is migrated through `2026_10_04_000020`. Everything below is committed and pushed
(last: `c6819f1`, 2026-10-04).

**Access rule for new work:** a referee is staff too, so `Team::isStaffedBy()` only answers "may
open the team at all". Check `Team::isCoachedBy()` for coach-only actions,
`TeamMatch::isVisibleTo()` for viewing a match, and `TeamMatch::handles($person, RefereeDuty)` for
anything a referee can be delegated. A view on the match screen hides by the same rules
(`$isCoach`, `$duties[...]` from `TrackingController::matchData()` and `Ux\MatchControls`).

### Changes 2026-10-04
- **Referee ("spelbegeleider"):** staff carry a role (`sm_team_staff.role`: `coach` | `referee`,
  `StaffRole`); invitations carry the role they grant. Owners are always coaches. A match names one
  referee from the team's referee staff (`sm_matches.referee_person_id`) and the duties delegated to
  them (`referee_duties`, `RefereeDuty`: timing, scoring, sanctions where the sport has them), set in
  the match form. See decisions.md 2026-10-04.
  - A delegated duty is the referee's alone: hidden from coaches and refused server-side
    (`TeamMatch::handles()`); coaches take it back by editing the match. Lineup, field moves,
    substitutions, availability, roster and team settings stay coach-only, whatever site-wide
    permissions the referee holds.
  - A referee sees only matches assigned to them (team page, sidebar). On those: the field without
    bench or play-time badges (read-only, to pick scorer/assist/sanctioned player), and a report with
    periods, goals and scorers, plus sanctions only when they hold that duty — no substitutions or
    time played.
  - Removing a referee from staff unassigns them from upcoming matches. Deleting a person
    force-deletes teams they were the last *coach* of (was: last staff member).
- **Kick-off needs a complete lineup** (coach or referee): every field place filled, or nobody
  available left on the bench (`TrackingController::lineupComplete()`). Entering a first period
  afterwards isn't checked.
- **Repeat guard:** the same live action within 5 seconds is ignored — starting a period of the type
  that just started, or a live goal identical in side/scorer/assist/points. Goals entered afterwards
  with a minute aren't guarded.
- **Staff list:** name and badge over email; owners change a member's role, make them owner or
  remove them from one Edit modal (`StaffController::updateRole`, owners stay coaches; leaving the
  referee role unassigns upcoming matches).
- **Event timestamps keep microseconds** (`000020`, `$dateFormat` on substitutions, goals, sanctions):
  the replay breaks same-offset ties on `created_at`, which at second precision fell back to whatever
  order the database returned.

### Changes 2026-10-04 (UI pass, driven from the Veldwissel site)
- **Home/away badge** (`views/teams/matches.blade.php`, `views/matches/show.blade.php`): icon
  `kopling-sports-management::home` (`fas-house`) / `::away` (`fas-car-side`); in the match list the
  label is `sr-only` below `sm` (tooltip via `title`). Match rows no longer wrap: score + badge
  `shrink-0 whitespace-nowrap`, opponent/date `min-w-0`, date on its own line.
- **Match page header** (`views/matches/show.blade.php`): back link + ⋮ menu (`<x-k::dropdown>`, coaches
  only) holding Edit (opens `modal-match-edit` via `data-modal-show`; the modal sits outside the
  dropdown with a hidden trigger) and Delete (was a button at the bottom); Report/Track buttons on
  their own row under the title. `MatchesControllerTest` asserts the new order.
- **Team page:** "Back to all teams" link above the title (`back_to_teams`), since phones have no
  sidebar.
- **New match defaults** to next Saturday 08:30 (`views/matches/form.blade.php`; app timezone, no
  conversion, same as stored times).
- **Modal forms** carry `<x-k::modal.cancel />` next to Save (team create/edit, player add/edit, match
  create/edit, period edit).
- Core, same pass: modals no longer close on a tap beside the box (✕ in the corner instead, plus
  Escape); `<x-k::modal.cancel />`; form hints wrap (`whitespace-normal` on `.label`, a long hint used
  to stretch the field past the modal); a click anywhere on a date/time input opens its picker
  (`resources/js/app.js`, deferred so a browser that already opened it isn't toggled shut); Dutch
  `lang/nl/ux.php` and `community.php`; Dutch admin/moderation menu labels.

### Changes 2026-10-03
- **Fairness:** target bench time (`TeamMatch::fairBenchSeconds()`) next to target play time;
  bench players carry a bottom badge with bench minutes ("benched 12'"), green at the target;
  field players who sat at least a minute keep theirs (no prefix, not ticking). Top badge on bench
  players reads "played 12'". Both badges centered. Targets are whole minutes, rounded down
  (`fairShareSeconds()`), so a badge turns green on the minute it shows and every player can
  reach the target; bench target = match minutes − play target. Labels: "Target play time: 42',
  target bench time: 8'" (NL "Speelstreeftijd", "reservestreeftijd"; NL "reserve" for bench).
- **Report:** goals and assists per player (points for basketball); own page at
  `/{team}/matches/{match}/report` (same partial as the tab, data from
  `TrackingController::matchData()`), linked from the match page and the team's match list once a
  match has ended; edits made there redirect back to it. "Back to match" sits left of the tabs.
- **Field order:** order within a zone is remembered (`sm_match_slots`, see decisions.md
  2026-10-03); dropping beside a player inserts left/right of their center.
- **Own goal by the opponent:** `own_goal` on goals — counts for us, no scorer/assist.
- **Multi-sport (epic M1–M8):** sport on team and preset, `SportConfig` per sport, break cue,
  sanctions (time penalties, red cards, foul-outs), zones per sport, KNHB/NHV/NBB seeders,
  basketball points. Details: the epic.
- **Navigation:** "All teams" at the top of the sidebar; "Sports management" in the user menu
  (`access-sports-management`, hidden on the portal itself). Upcoming matches read
  "<team> vs <opponent>" for staff of more than one team.
- **Team form:** sports listed alphabetically (football preselected); season prefilled
  (`Team::currentSeason()`, turns over in July); name, sport and season marked required (core
  `x-k::form.input`/`select` `required` option); club optional (`000018`), subtitles skip it
  (`Team::subtitle()`).
- **Per-sport wording:** `Sport::trans($key)` reads `by_sport.{sport}.{key}` in the current locale
  (`Lang::hasForLocale`, so Dutch never borrows an English override) and falls back to the generic
  key; `Position::label()` uses it too. Basketball says "Score"/"Points and assists"/"Basket
  recorded" (NL "Score"/"Punten en assists"/"Tik op de scorer"); English handball/basketball say
  "court". "Enter afterwards" goal forms offer +1/+2/+3 for basketball.
- **Deleting a person** (admin → People) force-deletes every team they were the last staff member
  of, roster included (`PersonObserver`, registered with `Extend\Model::observe()` on `Person`). A team can't
  lose its last staff member any other way (the last owner can't leave or be removed).
Migrations up to 2026-09-30 were edited in place (pre-production);
later ones are new: play minutes (`2026_10_02_000011`, then run
`kopling:sports-management:seed-knvb-presets`), team owners + invitations (`000012`, backfills
the earliest staff member of each existing team as owner) and team soft deletes (`000013`).

### Changes 2026-09-29
- **Roster:** positions are zero or more of K/D/M/F (`Position` enum, JSON `positions` column,
  `x-k::form.multi-select`); birth year removed. Roster sorted by name; its heading shows a count
  of permanent players and, separately, guests.
- **Team page:** Roster, Matches and Staff each in a daisyUI card (`card card-border`), in that
  order, team info + edit on top, a confirmed **Delete team** at the bottom (kept `Person` rows;
  reversed 2026-10-02, see "Moderation & abuse").
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
    button (Kick off / Break / Continue), a stop button for End match (since 2026-10-02), and,
    once ended, a ⋯ menu (core `x-k::dropdown`) holding Resume match. The match screen drops the left sidebar (`@extends(..., ['sidebar' => false])`).
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

### Changes 2026-10-02
- **End match** is an icon-only stop button (confirmed) next to Break/Continue; the ⋯ menu only
  remains after the match ended, for Resume match.
- **"Tap the scorer" prompt** sits over the "On field" row and the tabs instead of the field.
- **Play minutes:** format presets carry total play time (KNVB seed, upserted by name on every run),
  overridable per match. Still no round/break schedule.
- **Fair-share badge:** a minutes badge turns green once the player reached play minutes ×
  players-on-field ÷ squad shown (absent players excluded); switches live while ticking. No
  format or play minutes: no green.
- **Fair share on the bench card:** a faint "Fair share 33'" corner label (same style as the zone
  letters), whole minutes; hidden before kick-off and without play minutes.
- **Sidebar** (`Ux\TeamsNav` in the portal's `sidebar-panel` slot): the teams you staff, and, when
  that's at most 3, the next 3 not-yet-ended matches from today on (live ones marked, linking
  to tracking; team name shown only with more than one team). Hidden on phones (the core sidebar
  is `md:` and up) and on the match screen.
- **Team page order:** Matches above Roster; Matches hidden until the team has a roster (or
  already has matches), so a new team starts at its roster.
- **Time played** (Report tab) sorted by most minutes played, no longer by who is on the field.
- **Bench avatars** centered in the bench card.
- **KNVB seed** now also has JO14 and JO16; values per category are `[players on field, play minutes]`.
- **Dutch translations** (`lang/nl/messages.php`, `lang/nl/permissions.php`). Core, alongside:
  the portal layout's `<html lang>` follows the app locale, and `head.blade.php` takes a
  `@section('description')` override and a `@stack('head')`. "Track match" reads "Wedstrijd
  spelen" in Dutch.
- **Event log names the position:** a player moved onto the field is logged as "Jip to Midfield"
  ("Jip naar Middenveld") instead of "Jip on"; a substitution entered afterwards without a zone
  keeps "on".
- **Sidebar icons:** teams and upcoming matches carry an icon, declared as overridable
  `kopling-sports-management::team` (default `fas-user-group`) and `::match` (default
  `fas-futbol`). The requested `user-group-simple` / `court-sport` are Font Awesome Pro, so they
  can't be the defaults; see the Pro item under open points.
- **Report** moved from the team header to the bottom row, next to Delete team (see
  "Moderation & abuse").
- **Nightly deploy failure** after these migrations (`activitypub_actors` missing) was core's,
  not this extension's: only enabled extensions' migrations were loaded. Fixed in core
  (decisions.md, "Migrations load for every installed extension").

### Still to check visually
- 2026-10-04 UI pass: the date picker in a real desktop Firefox (on Veldwissel's pages Firefox's own
  calendar button did nothing while a bare `data:` page worked; root cause not found, the click
  handler works around it); whether the Android back gesture still closes a modal; the modal ✕
  against a long modal title; the ⋮ menu on the match page for a referee (should be absent).
- 2026-10-04 changes, on a phone:
  - Staff list: name and badge over email, one Edit button; the Edit modal (role, Make owner,
    Remove); the role select beside the email field in the invite row.
  - Match form: referee picker and duty checkboxes; "Referee: name (time, score)" on the match page.
  - As a referee: team page (only assigned matches, Leave team), match page without availability,
    match screen with field only (no bench or badges), Goal/Break buttons, report without time
    played or substitutions.
  - As a coach with duties delegated: top bar with timing delegated (falls back to the read-only
    clock layout), field without Goal buttons when scoring is delegated.
  - The "complete the lineup" error on kick-off (shown above the field).
- Dutch copy read-through on the match screen (longer words in the top bar and badges).
- Event log lines with the position, sidebar icons for teams and matches (alignment of the
  match icon beside the two-line entry).
- 2026-10-02 changes: stop button size next to Break, scorer prompt position over the tabs,
  green badge turning on live, play minutes field on the match form.
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

- 2026-10-03 changes: sanction mode (button, tap player, pick kind), countdown and "out" badges,
  the return prompt after a penalty, +1/+2/+3 buttons for basketball, a second action button next
  to Goal on a phone, sidebar "All teams" + user-menu portal link order, required asterisks.
  Confirmed working: break cue (tested with a 2-minute match).

### Next
- Visual pass on a phone (list above) and of the moderation & abuse screens (see that section),
  then decide the communication block.

### Open
- **Firefox date picker root cause:** something on Kopling pages stops Firefox's own calendar button
  (not reproducible in headless Firefox 155; a click reaches the input, nothing prevents it). The
  core click handler covers it; worth finding if it shows up elsewhere.
- **Other extensions' modals** (admin, tags, pages, moderation) have only the ✕ and Escape now; give
  their forms `<x-k::modal.cancel />` too.
- **Referees, later:** the moderation team preview shows owners but not referee roles; a coach
  can't correct delegated events afterwards without first taking the duty back (by design, see
  2026-10-04); a referee on more than three teams gets no sidebar match list
  (`TeamsNav::MATCHES_UP_TO_TEAMS`), only the team pages.
- **Required markers on the match form** (opponent, date/time) — offered, not done.
- **Federation values to confirm:** see the epic §9/§10 (small hockey formats, all NHV durations,
  NBB formats, whether the youngest age groups use cards/suspensions).
- **Korfbal:** postponed (epic §6).
- **Later:** a second person operating the phone; preparing substitutions ahead and applying
  them together.
- **Editing format presets:** out of scope for now (decided 2026-10-03): presets are seeded from
  the federations, one command each (`seed-knvb-presets`, `seed-knhb-presets`,
  `seed-nhv-presets`, `seed-nbb-presets`). The seeds leave `rules_url` empty, so the "Rules" link
  never shows.
- **No signal on the sideline:** an action sent without a connection fails; queuing offline
  actions was left out on purpose.
- **Avatar icons from extensions** (e.g. roles): a `RenderingAvatar` event, same shape as
  `RenderingCard`, deferred until a first real use.
- **Font Awesome Pro icons** (`user-group-simple`, `court-sport`) for the sidebar: a site can now
  install Pro with its own token (`kopling:icons:pro`, see decisions.md, 2026-10-03) and override
  these two icons; the defaults stay free, since most sites won't have Pro.
- **Cards in football:** not wanted for now (2026-10-03); `Football::sanctions()` is empty.
- **Roster members as accounts:** core now has a `/settings` page for a person's own name, with
  a slot for extension sections. Nothing here uses it yet; it becomes relevant once a roster
  member's `Person` can log in (the "real accounts later" path).

### Settled, no action needed
- **Edit team button** on the team page keeps the modal's default small trigger; looked fine.
- **Subsplit:** split to the read-only repository `kopl-ing/sports-management`
  (`.github/subsplit-config.json`), since 2026-09-29.

## Moderation & abuse (2026-10-02)

Team data is private to its staff and holds children's names, so a report-driven queue alone
catches little. Moderators get team metadata by default and roster/match detail only once a team
is reported. Everything sits in this extension on existing hooks; `moderation` is unchanged.

### Findings (fixed)
| Issue | Fix |
| --- | --- |
| Adding staff by email attached any account without consent, and the "no account found" error revealed whether an email had an account | Email invitations (`sm_team_invitations`), keyed by the typed email; same response either way; the invitee accepts or declines on their Teams page; staff can revoke |
| Any staff member could remove any other, including the creator; anyone with `manage-teams` could delete the team | `sm_team_staff.owner`: creator is owner, owners can promote others. Only owners delete the team or remove non-owner staff; anyone may leave, except the last owner |
| Roster members (children) had a public profile at `/p/{person}` | `profile` 404s unless `Gate::allows('view', $person)`; this extension denies `view` for roster members |
| Deleting a member or team kept its `Person` rows | A member's login-less `Person` (no email, password or identity) is deleted with it; team delete is permanent and goes per member |
| No rate limit on creating teams, roster members or invitations | `throttle` per account: teams 10/hour, members 60/hour, invitations 20/hour |

Checked and not an issue: ActivityPub only exposes a Person with an `activitypub_actors` row and a
handle, which roster members never get.

### Built
- **Team is a moderation target** (`RegistersModerationTargets`), with `SoftDeletes` +
  `deleted_by`/`deleted_reason`. Moderation's Hide freezes a team (its staff get a 404),
  Unhide restores it, Delete removes it for good. Required anyway: the queue treats a
  non-soft-deletable target as a Person and would render the sanction form for a Team.
- **Report** from the team page, next to Delete team: moderation's `ReportControlEntry` in this extension's own
  `kopling-sports-management::team.control` slot (`Extension::TEAM_CONTROL_SLOT`),
  `class_exists`-guarded.
- **Queue preview** (`moderation.team-preview`): team details, staff with owner badge and a
  Sanction button each, roster and matches in collapsed sections. The only place moderators see
  names from a roster.
- **Teams overview** in the Moderation portal (`/moderation/sports-management`, sidebar entry
  `Ux\ModerationNav`): every team including hidden ones, with club, season, format, staff,
  roster and match counts, created date, sortable by newest / largest roster / most matches,
  with Hide/Unhide/Delete. No roster names. Gated by the Moderation portal's own `moderate`
  permission (no raw cross-extension permission string in this extension).

### Open
- **Communication block:** a moderation sanction's communication block isn't read anywhere yet
  (core or here). Undecided whether it should stop writes in this extension, since only co-staff
  see them.
- **Invitations by mail:** the invitee only sees an invitation once signed in; no email is sent.
  Invitations don't expire.
- **Admin people list** still lists roster members (admins only); their profile link there 404s.
- **Visual check:** Report button next to Delete team at the bottom of the team page (its
  trigger is styled for a dropdown, full width and left-aligned, so it may not match Delete
  team), invitations card on the Teams page, the Teams
  overview and team preview in the Moderation portal.
- **Teams overview paging** drops the chosen sort on page 2 (core's pagination doesn't keep the
  query string).

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
- **Staff is many-to-many, per-team** (since 2026-10-02 with owners and invitations, see
  "Moderation & abuse"). An account (`Person`) can staff multiple teams; a team can
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
- **Format preset: KNVB categories, players-on-field and total play minutes.** A small fixed set
  of presets (JO7 through JO19 and Senioren) — each a name, a players-on-field count, total play
  minutes (added 2026-10-02, overridable per match), and a rules-link URL. The seed upserts by name,
  so re-running it applies changed KNVB values. **Round length and number of rounds are deliberately excluded from
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
  you actually staff, in the right role: since 2026-10-04 coach, or referee for duties delegated
  on that match). The portal-gate permission is named after the extension itself
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
  `format_preset_id` (FK, nullable), `deleted_at`/`deleted_by`/`deleted_reason` (moderation hide).
- **`sm_team_staff`** — pivot: `team_id`, `person_id`, `owner` (bool), `role` (`coach` |
  `referee`; owners are always coaches).
- **`sm_team_invitations`** — `team_id`, `email` (lowercased, unique per team), `role`, `invited_by`.
- **`sm_team_format_presets`** — `name` (e.g. `"JO11"`), `players_on_field`, `play_minutes`
  (total, nullable), `rules_url`. A small
  seeded set of KNVB categories, editable like any other admin-managed data (not a config file,
  per CLAUDE.md's "avoid config files").
- **`sm_team_members`** — one-to-one satellite on a `Person` (`person_id`, unique), `team_id`,
  `jersey_number`, `positions` (JSON array of the `Position` enum: K/D/M/F, zero or more),
  `guest` (bool, default false). No birth year. No contact/guardian
  columns — coach/staff manage the roster directly, no guardian-on-behalf-of-player concept yet.
- **`sm_matches`** (Phase 2, built) — `team_id`, `opponent_name`, `home_away` (enum),
  `location_address` (text), `format_preset_id` (nullable FK — defaults from
  `team.format_preset_id`, overridable per match), `play_minutes` (nullable — falls back to the
  effective preset's), `scheduled_at`, `referee_person_id` (nullable, a referee on the team's
  staff) and `referee_duties` (JSON array of `RefereeDuty`). No stored result/state column
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
  period — unlimited/rolling subs, no sub-slot count. Events at the same offset replay in
  `created_at` order, so event tables keep microsecond timestamps (`$dateFormat`, since `000020`).
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
