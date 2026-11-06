# Plan: team tasks in `sports-management` (volunteer duties, sign-ups, balance)

Status: M1–M2 committed; M3–M7 built (2026-11-06), uncommitted; M8 postponed (see Backlog). Part of `kopling/sports-management`; every table
`sm_`-prefixed, same as the rest of the extension (`sports-management-extension-plan.md`).

## Goal

Staff define the recurring chores a team runs on (driving to away matches, setting up goals,
washing kit, coaching, team coordinator, bar duty) once, and families sign up for them. Everyone
sees what still needs people; staff see which players' families carry the load and which don't.

## Team settings (feature toggles)

Tasks are off until a coach switches them on. Per-team columns on `sm_teams`, same as the existing
per-team public-page default:

| Setting | Default | Effect |
|---|---|---|
| `tasks_enabled` | off | tasks card, tasks list, link(s) |
| `tasks_link_mode` (`TaskLinkMode`) | `team` | `team`: one link, pick your avatar; `per_player`: one link per player, no picker |
| `tasks_balance_public` | off | balance shown on the link page(s) too; staff always see it |
| `tasks_approve_completion` | off | staff confirm each past enlistment as done or no-show before it counts |

## Enlisting: links, no accounts (decided 2026-11-06)

Families enlist through secret URLs, not accounts, assuming good faith. This keeps "no guardian
accounts" (`sports-management-extension-plan.md`, "Decided (round 2)") intact. A player claiming
their roster `Person` with a real account is backlog (see "Backlog").

- **Team link** (`tasks_link_mode = team`): `sm_teams.tasks_token` (40 random chars, same as
  `TeamMatch::public_token`), route `/live/team/{token}/tasks`, on the token-only layout of the
  public match page (`layouts/live.blade.php`). Open link → tap your player's avatar → that
  player's tasks by date → tap a task to enlist (or leave). The chosen player is remembered in
  `localStorage` (per-device convenience only), with "not you?" to pick another.
- **Per-player link** (`per_player`): `sm_team_members.tasks_token`, route
  `/live/player/{token}/tasks`, opens straight on that player's view. For teams where an open
  avatar picker isn't wanted. Coach copies each link from the roster row. Switching modes leaves
  the other mode's tokens in place but refuses them.
- **Anyone with a link can act for the player(s) it reaches.** Accepted: good faith. Mitigations
  that cost nothing: every enlist/leave is logged (`sm_task_enlistment_events`), visible to staff
  on the task; staff can **regenerate** a link (unlike `public_token`, kept once made), which kills
  a leaked one.
- **Names:** the public match page shows initials only for privacy; this page can't — parents
  must recognise their own child. First name + last initial ("Daan K."), always, plus avatar.
  See "Privacy".
- **No `Person` for who actually does it.** An enlistment belongs to a roster member; an optional
  free-text `note` ("dad", "with the van, 4 seats", "my friend Sem") says who goes.
- **Several per player.** A family (or an older player bringing friends) can take more than one
  spot on the same occurrence; each spot is its own enlistment with its own note, left one by one.

## Concepts

- **Task**: what needs doing, for a team. Title, description, when, who, effort, weight, enrolment.
- **Occurrence**: one concrete date of a task. Stored, not computed, so enlistments have
  something stable to point at when a match moves.
- **Enlistment**: a roster member's family signing up (that's what makes the balance per
  player). Either for one occurrence, or for the whole task.
- **Period**: a stretch of the team's season it is active in (a KNVB "fase", the half before
  the winter break), defined by its coaches. Recurring tasks can be limited to periods. See
  "Season periods".

### When (`TaskSchedule`)

| Case | Occurrences come from |
|---|---|
| `all_matches` | every match of the team |
| `home_matches` / `away_matches` | matches with that `home_away` |
| `dates` | one or more date(+time)s entered on the task |
| `recurring` | a rule: weekly (weekday), monthly (day of month, or "nth weekday"), yearly (date); optional interval ("every 2 weeks") |
| `season` | a fixed role (team coordinator) from `starts_on` to `ends_on`: one hidden date per month end, always whole-task, so it earns its weight per month held; with `within_periods`, only months with a day in a period, dated on the last in-period day |

Recurring tasks have `starts_on`/`ends_on` (at most two years apart), defaulting to the team's
season (`Team::seasonBounds()`: July–June for a season like "2026/2027"), so they have an end and
get generated up front. Match-bound tasks follow the team's matches; set dates are explicit. A
recurring task can be
set to **only within periods** (`within_periods`): occurrences outside every period of the team
aren't generated, so a weekly trainer task skips the winter and summer breaks.

Match-bound tasks take **minutes before kick-off** (`lead_minutes`, e.g. 45 for "drive, leave at
7:45"), stored as the occurrence's own time. Without one, the occurrence takes the match time.
Occurrences carry a date and an optional time (set dates and repeating tasks needn't have one).

### Who

`min_people`, `max_people`, both nullable. Per occurrence:

- under `min` → **needs people** (warning)
- `min` reached, under `max` → **enough**, still open
- `max` reached → **full**, enlisting closed
- neither set → open, count only
- date passed → **done** (greyed, closed on the link page); with `tasks_approve_completion`,
  **to confirm** for staff until every enlistment on it has an outcome

### What / effort / weight

- `description`: plain text, multi-line.
- Effort, informational, both nullable, per occurrence: `effort_minutes`, `effort_cents` (money
  spent, e.g. fuel), entered in whole euros: core has no currency setting. No reimbursement (see "Out of scope").
- **`weight`**: what one occurrence counts for in the balance, set on the task (default 1). Staff
  decide what's fair ("driving away = 2, setting up goals = 1"), rather than the balance guessing
  it from effort. A whole-task enlistment earns `weight` per occurrence it covered, so a trainer
  on a weekly task limited to periods earns it per active week, not over the breaks. Each enlistment earns it, so a player who brings
  a friend to set up goals earns it twice.

### Enrolment (`TaskEnrolment`)

- `per_occurrence` — sign up date by date (set up goals, drive to an away match).
- `whole_task` — one sign-up covers every occurrence (trainer, team coordinator, kit washer for
  the season). Counts toward each occurrence's capacity; can be ended early (`ended_on`), after
  which later occurrences count it no more.

### Approving completion (`tasks_approve_completion`)

Off: a past occurrence counts for everyone enlisted. On: staff mark each enlistment on a past
occurrence **done** or **no-show** (`TaskOutcome`); only `done` counts, no-shows are listed on the
balance separately. "All done" per occurrence and per week keeps it a single tap in the normal
case. Whole-task enlistments follow the task's `confirm_each_occurrence` (whole-task only, off by
default): off, they count as done and staff only mark a no-show; on, they're confirmed like any
other enlistment.

## Season periods

`sm_team_periods`: `team_id`, `name` ("Fase 1", "Voorjaar"), `starts_on`, `ends_on`. A team is
already one season (`sm_teams.season`), so its periods cut that season up. **Coaches define them
themselves** on the team page (decided 2026-11-06); used by `within_periods` repeating tasks and
season roles, and shown next to the date of matches that fall in one (derived, not stored). Periods may not overlap and run at most two years; they may reach past the season on
either side (handing out or collecting kit before or after it).

Why not presets, checked 2026-11-06: federations publish periods as documents, not data. KNVB's
youth "fasenvoetbal" planning is a PDF per district per season and differs per age band (2026/27
Noord/Oost: O7–O12 phase 1 from 5 Sept to 3 Oct, phase 2 from 23 Jan to 20 Mar; O13–O19 phase 2
from 30 Jan to 29 May), and several districts move youth indoors over the winter. Seeding that is
districts × age bands × federations, re-entered every season. Sportlink Club.Dataservice exposes a
club's fixtures (backlog: match import), not periods.

School holidays do have real open data: Rijksoverheid
`opendata.rijksoverheid.nl/v1/infotypes/schoolholidays`, per school year and region
(noord/midden/zuid). Optional later step: "skip school holidays" on recurring tasks, region as a
site setting (`HasAdminSettings`), fetched yearly and cached.

## Data model (sketch)

```
sm_teams
  + tasks_enabled bool, tasks_link_mode string, tasks_balance_public bool,
    tasks_approve_completion bool, tasks_token string null unique

sm_team_members
  + tasks_token string null unique

sm_team_periods
  id uuid, team_id cascade, name, starts_on date, ends_on date

sm_tasks
  id uuid, team_id → sm_teams cascade
  title, description text null
  schedule (TaskSchedule), recurrence json null, time null, lead_minutes int null
  starts_on date null, ends_on date null     -- recurring only
  within_periods bool                               -- M5
  min_people int null, max_people int null
  effort_minutes int null, effort_cents int null, weight tinyint default 1 (1–10)
  enrolment (TaskEnrolment)
  undo_window (TaskUndoWindow)      -- M2, see "Additions"
  confirm_each_occurrence bool      -- M4, whole_task + approve completion only
  created_by → people null on delete, timestamps

sm_task_dates                        -- only for schedule = dates
  id, task_id cascade, date, time null, unique (task_id, date)

sm_task_occurrences
  id uuid, task_id cascade, match_id → sm_matches null on delete, date, time null
  cancelled_at null, timestamps
  unique (task_id, match_id)

sm_task_enlistments
  id uuid, task_id cascade, occurrence_id → sm_task_occurrences null (cascade)
  team_member_id → sm_team_members cascade
  note string null                   -- who goes, free text
  ended_on date null                 -- whole_task only
  created_by → people null on delete -- set when staff enlisted from the team page
  timestamps
  index (occurrence_id, team_member_id) -- several per player allowed

sm_task_outcomes                     -- only with tasks_approve_completion
  id, enlistment_id cascade, occurrence_id cascade, outcome (TaskOutcome)
  confirmed_by → people null on delete, timestamps
  unique (enlistment_id, occurrence_id)

sm_task_enlistment_events
  id, task_id cascade, occurrence_id null cascade, team_member_id cascade
  action (enlisted | left), created_at
```

Outcomes are their own table because a whole-task enlistment needs one per occurrence it covers.
`occurrence_id null` ⇔ whole-task enlistment. `max_people` is checked in a transaction with a
lock on the occurrence row, so two parents tapping the last slot at once can't both get it.

## Keeping occurrences in step

- **Match-bound tasks**: a `TeamMatch` observer (created / `scheduled_at` or `home_away`
  changed / deleted) creates, moves or removes occurrences for every match-bound task of the
  team. An occurrence that had enlistments is marked cancelled instead of disappearing, so the
  link page can show it.
- **Dates / recurring**: occurrences regenerate when the task's schedule changes, or the team's
  periods change for `within_periods` tasks. Existing occurrences on a date that still exists
  keep their enlistments; dropped dates are cancelled.
- **Task edited from match-bound to something else**: same rule — matching occurrences survive,
  the rest are cancelled.
- Generation lives in one class (`TaskOccurrences::sync(Task)`), called from the observers and
  the task/period controllers, so there's one place the rules live.

## Screens

- **Link page**: (team mode) the roster as a grid of avatars, permanent players only; then the
  player's view: occurrences by date with title, time, "3 / 4" badge (daisyUI `badge`,
  warning/success/neutral by state), names already enlisted, Enlist / Leave. Whole-task duties on
  top. Balance below when `tasks_balance_public`. Tapping swaps only the list (`hx-target`+
  `hx-select` pair, same as `page/pagination.blade.php`).
- **Team page → Tasks card** (staff): next open occurrences, to-confirm count, the link (copy +
  regenerate) or a note that links are per player, and enlist/remove on behalf of a player.
- **Team settings**: the four toggles above, in the team edit modal.
- **Roster row**: copy/regenerate the player's link, in `per_player` mode.
- **Periods**: list on the team page, add/edit/delete (coaches).
- **Tasks list** (`/{team}/tasks`, staff): occurrences by date, grouped by week, filter "open
  only" / "to confirm"; each task shows its enlist/leave log.
- **Task form** (staff, `x-k::modal` + `_form`, same as the match form): fields above; schedule
  fields toggle per `TaskSchedule`.
- **Balance** (`/{team}/tasks/balance`, staff): one row per permanent player (guests excluded):
  weight earned, done / upcoming count, no-shows (when approving), effort totals; sorted lowest
  first; drill into a player for the list.

## Permissions and access

- Define/edit/delete tasks and periods, team settings, links: team coaches (`Team::isCoachedBy()`).
  Referees: no.
- Confirm outcomes: team coaches.
- Enlist/leave: anyone holding a link, for the player(s) it reaches, on that team only. Staff also
  from the team page.
- Site-wide `manage-tasks` permission not needed; same team-local checks as the rest of the
  extension.
- The public match page's token grants nothing here; separate tokens, separate routes.
- Rate-limit the public enlist/leave endpoints (Laravel `throttle`), since they're unauthenticated.

## Privacy

The link page is unauthenticated and will be forwarded, so it shows first name + last initial
and avatars, no match addresses beyond what the task needs. `noindex` and no link to it from
anywhere public. The balance (reads as "who isn't pulling their weight") is **staff-only** unless
the team switches on `tasks_balance_public`; no-shows never appear on the link page. Everyone with
a link sees *who* enlisted per occurrence — needed to coordinate who's driving.

## Moderation

Tasks are team content: the existing team moderation target covers them (team preview lists
them); no own `ModerationTarget`.

## Additions (beyond the brief)

Recommended for the first version:

1. **Undo window** (`undo_window`, `TaskUndoWindow`), set per task by staff: "Allow undoing a
   sign-up" — *any time* / *until an hour before* / *until a day before* / *never*. Outside the
   window the link page can't leave the occurrence (staff still can), so a driver can't silently
   drop out the morning of an away match, nor can anyone wipe the weekend's drivers the night
   before. Whole-task sign-ups: the window applies to ending them (`ended_on`) before the next
   occurrence.
2. **"Needs people" surfacing**: occurrences under `min` within the next 7 days get a warning on
   the team page, the sidebar's upcoming list, and the top of the link page.
3. **Staff assign**: staff enlist a player's family on their behalf (shown as "assigned"), for
   when asking in the group chat beats waiting.
4. **Offset from kick-off** for match-bound tasks (included above).

## Backlog

- **"I am this person"**: a player (or later a guardian) with an account claims their roster
  `Person` and enlists signed in, instead of through a link. The upgrade path the main plan
  already describes ("real accounts later"); links keep working alongside.
- **Calendar feed** (iCal) behind the link token: a player's enlistments and matches. Without
  accounts this is the only reminder channel — a moved match updates in the parent's own calendar.
- **"Share to group chat" text**: staff copy a plain-text list of open spots for the coming week,
  with the link, to paste in the team's chat.
- **Swap request**: "looking for someone to take over" on an enlistment, shown on the link page.
- **Exemptions**: staff mark a player's family as exempt (hardship, a staff member's own kids)
  so the balance doesn't flag them.
- **Copy tasks** from another team or the previous season. A club-wide task list waits on a club
  entity, which v1 deliberately doesn't have.
- **Trainings** as a schedule source: `all_matches` only knows matches; `recurring` +
  `within_periods` covers trainings meanwhile.
- **Match import** from Sportlink Club.Dataservice (football, club `client_id`).
- **Self-service availability** on the same link page: tap your player, mark available/absent
  per match.
- **Skip school holidays** (was M8, postponed 2026-11-06 since periods already cover the breaks):
  Rijksoverheid feed, region as a site setting, cached yearly; see "Season periods".
- **Notifications**: core has no notification mechanism yet; a core gap, not this extension's.

## Out of scope

Paying out or reimbursing costs (`effort_cents` is an estimate only; `payments-subscriptions-plan.md`
is the place if this ever comes), club-wide tasks, guardian accounts, federation period presets.

## Open questions

1. **Undo window default** on a new task: built as *any time* (the proposal); confirm, or switch to *until a day before*.

## Decided (2026-11-06)

- Link page names: first name + last initial, always.
- Periods: coaches define their own per team (= per season); no suggestions, no presets.
- Several enlistments per player per occurrence, each earning the task's weight.
- Undo window is a per-task option, chosen by staff.
- Whole-task enlistments count as done by default when approving completion; per-task toggle to
  confirm each occurrence instead.

## Milestones (tick as completed)

- [x] M1 Tasks + occurrences: tables, `TaskSchedule`/`TaskEnrolment`/`TaskFrequency`,
      `TaskRecurrence`, weight, `TaskOccurrences::sync()`, `TeamMatch` hooks, task form, Tasks
      card on the team page, `tasks_enabled` toggle (staff). The other team toggles land with the
      milestone that gives them an effect.
- [x] M2 Link pages: team + per-player tokens, toggle/regenerate, avatar picker, per-player list,
      enlist/leave (throttled) with event log, capacity lock, undo window; staff Tasks card + list
      with staff assign.
- [x] M3 Balance page (weight-based), `tasks_balance_public`.
- [x] M4 Approve completion: outcomes, to-confirm filter, "all done", no-shows on the balance.
- [x] M5 Season periods: table + UI (coaches), `within_periods` generation.
- [x] M6 "Needs people" surfacing on team page, sidebar and link page.
- [x] M7 Translations (nl/en), moderation team preview lists tasks.
- [ ] ~~M8 Skip school holidays~~ postponed 2026-11-06: periods cover the breaks; moved to Backlog.

## Testing (Pest)

Feature tests in `tests/Feature/SportsManagement/TasksTest.php` (same folder, so the shared helpers load): occurrence generation per schedule
(incl. monthly "nth weekday", year boundaries, `within_periods`), match moved/deleted/home↔away
flip, period edits regenerating occurrences, overlapping or over-long periods refused, several
enlistments per player counting toward capacity and balance, capacity at the last slot,
undo window per option for link page vs staff, whole-task
`confirm_each_occurrence` on/off, team link reaches only its own team, per-player link reaches only
its player, the inactive mode's tokens refused, regenerated token refuses the old one, tasks off
refuses all tokens, balance weight with whole-task enlistments across periods, outcomes (only
`done` counts, whole-task default), staff access (referee, unrelated person).

## Progress log

- 2026-11-06 — plan drafted.
- 2026-11-06 — enlisting through links (pick avatar, then task), good faith; guardian accounts
  dropped from the plan.
- 2026-11-06 — per-team toggles (link mode, public balance, approve completion), weight on the
  task, season periods suggested from matches; account claiming moved to backlog.
- 2026-11-06 — names as first name + last initial; coaches define periods themselves; several
  enlistments per player.
- 2026-11-06 — undo window as a per-task option; whole-task confirmation as a per-task toggle.
- 2026-11-06 — M1 built: migration `2026_11_06_000022` (not yet run on the local database),
  models, recurrence rules, occurrence sync, task form with schedule fields swapped via
  `tasks.schedule-fields` (same pattern as `teams.sport-fields`), Tasks card for coaches.
  Notes for M2: `date` columns are compared with `whereDate()` (SQLite stores a date cast with a
  time); occurrences are deleted, not cancelled, until enlistments exist — M2 switches sync and
  the match `deleted` hook to cancelling occurrences that have enlistments.
- 2026-11-06 — M2 built: migration `2026_11_06_000023` (link mode + tokens, `undo_window`,
  enlistments, event log; backfills tokens for existing members and task-enabled teams).
  `TaskEnlistments` holds enlist/leave (capacity lock, undo window, log); `TaskBoard` loads what
  both the link page and the staff planning page (`/{team}/tasks`) show, 8 weeks at a time.
  Occurrences with sign-ups are cancelled instead of deleted. Leaving a whole-task sign-up sets
  `ended_on` to the day before the next date, or drops it if it covered nothing yet. Notes for
  M3: a whole-task sign-up covers dates from its `created_at` day; guests can't use links or be
  assigned.
- 2026-11-06 — M2 testing round: link page wording is "Oppakken" / "I'll do it" and "Toch niet" /
  "Can't make it" (no "aanmelden": it reads as an account action); sign-up details, undo rule and
  "Wie doet het?" moved into a modal; duplicate task; page titles across the extension.
- 2026-11-06 — M3 built: migration `2026_11_06_000024` (`tasks_balance_public`), `TaskBalance`
  computes per permanent player: weight per passed, non-cancelled date covered, per sign-up.
  Staff page `/{team}/tasks/balance` lowest first, a collapse per player listing what they took
  on. The link page shows points and done/to-come counts only (no effort or costs), sorted by
  name rather than ranked. UI calls earned weight "punten" / "points". Notes for M4: "passed"
  is all that counts as done now; outcomes replace that test in `TaskBalance`.
- 2026-11-06 — M4 built: migration `2026_11_06_000025` (`tasks_approve_completion`,
  `confirm_each_occurrence`, `sm_task_outcomes`). The rule lives in one place,
  `TaskEnlistment::outcomeFor()`: team not confirming → done (recorded outcomes ignored);
  otherwise the recorded outcome, else done for a whole-task sign-up without
  `confirm_each_occurrence`, else waiting. `TaskReview` lists passed dates for the planning page's
  new "Afgelopen" / "Te bevestigen" tabs (the latter over the whole season), with done/no-show per
  person and "Alles gedaan" per date and per week. Balance shows no-shows and waiting counts to
  staff only.
- 2026-11-06 — Leaving the team: migration `2026_11_06_000026` adds `sm_team_members.left_on`
  (first day off the team; set or cleared in the member's edit modal; delete still wipes
  everything). `TeamMember::leaveOn()` drops sign-ups from that date (season-long ones end the
  day before, logged as left by the coach) and the planning of not-yet-started matches. Task
  pages use `onTeamAt(now())`, match pages `onTeamAt(match date)`, so past reports keep the
  player. Former players: own collapsed roster section, own section on the staff balance, not on
  the parents' balance.
- 2026-11-06 — M5 built: migration `2026_11_06_000027` (`sm_team_periods`, `within_periods`).
  Periods are managed at the bottom of the Tasks card (coaches, tasks on); refused when they
  overlap or run longer than two years, not bound to the season. `TaskOccurrences` filters a `within_periods`
  repeating task's dates through the team's periods; any period change re-syncs those tasks
  (`syncPeriodTasks()`), with the usual keep/cancel rules. A task can only be limited to periods
  once the team has one. Summary reads "…, binnen periodes".
- 2026-11-06 — M6 built: `TaskBoard::shortHanded()` (dates within `SOON_DAYS` = 7 still under
  `min_people`, whole-task sign-ups counted). Shown as a warning on the team page's Tasks card
  and a "Deze week nog personen nodig" section in the sidebar (coached teams, first 3), both
  linking to the date on the planning page; and at the top of the link page, jumping to the row.
- 2026-11-06 — Public match page lists the match-bound tasks still vacant for that match (under
  their minimum, or no minimum and nobody yet) until kick-off: titles and counts only, never the
  task link (`TaskBoard::vacantFor()`).
- 2026-11-06 — M7 built: all task strings already existed in nl and en. Added the Dutch
  framework messages the task/period forms hit (`after_or_equal`, `between`, `date_format` in
  `k-core/lang/framework/nl/validation.php`) and readable `:attribute` names from the form labels
  (`Controllers/FieldNames`). Moderation team preview lists tasks with descriptions, the notes
  left through task links (with player and date), and periods.
- 2026-11-06 — `season` schedule ("Hele seizoen (vaste rol)") for fixed roles: hidden month-end
  occurrences (last one on `ends_on`), always taken on for all dates; points per month held, end
  and leave rules as for other whole-task sign-ups. Left out of the weekly "needs people"
  warning; the team page shows no next date for it.
- 2026-11-06 — Migration `2026_11_06_000028`: `sm_tasks.staff_only` (shown on the link page as
  "Staf deelt in", no taking on or backing out through the link) and `sm_task_enlistments.starts_on`
  (defaults to the creation day). Period backfill ("Terugwerkend toekennen", toggle on add, button
  on edit, idempotent) moves the start of the team's current season-long sign-ups back to the
  period's start; single-date sign-ups can't be backfilled. Balance bar stacks earned points with
  planned ones (upcoming sign-ups × weight) in a lighter shade.
- 2026-11-06 — Season roles can be limited to periods (month counts when any of its days is in
  one); `Team::periodOn()` labels matches with their period on the team page and match page.
- 2026-11-06 — M8 (school holidays) postponed to the backlog: coaches' own periods already skip
  the breaks.
- 2026-11-06 — Planning "Afgelopen" tab: assign and remove on past dates (backfill, replacements);
  staff assigning a passed date records it as done when the team confirms. Match-tied rows show
  the opponent in both tabs.
