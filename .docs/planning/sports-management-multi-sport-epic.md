# Sports management — multi-sport epic (hockey, handball, basketball; korfbal later)

Extends `k-extensions/sports-management` (built for Dutch youth football) to more sports from
one engine, in the same extension. Rewritten 2026-10-03 against the actual code; the original
draft assumed a different baseline. Decisions go in `.docs/planning/decisions.md`.

**Progress is tracked in the checklist under §7 — tick items as they land.**

---

## 1. What exists today (the baseline)

- **Teams** (`sm_teams`: name, club, season, `format_preset_id`), staff with owners and
  invitations, roster `TeamMember`s (a `Person` without login, `jersey_number`, preferred
  `positions`, `guest`), per-match availability.
- **Format presets** (`sm_team_format_presets`): `name` (unique), `players_on_field`,
  `play_minutes`, `rules_url`. Seeded from KNVB by `kopling:sports-management:seed-knvb-presets`.
  No editing screen (out of scope here too).
- **Matches** (`sm_matches`) with an optional per-match `format_preset_id`/`play_minutes` override.
- **Clock:** `sm_match_periods` (play/break, `sequence`, `started_at`, `duration_seconds`). The
  coach starts and ends every period by hand; the match clock only runs during play periods.
  Nothing knows how many periods a match should have.
- **Events:** separate tables, not one event table — `sm_match_substitutions` (on/off + zone,
  `offset_seconds` within a period), `sm_match_goals` (`opponent`, `own_goal`, scorer, assist).
  `MatchTimeline` replays substitutions to derive who is on the field and seconds played.
- **Lineup / field:** `sm_match_lineups` before kick-off; four zone rows (`Position` enum:
  K/D/M/F) rendered as plain markup; in-zone order in `sm_match_slots`; drag or tap-to-move
  (`js/app.js`); swap by dropping on a player.
- **Rules ("validity"):** `FieldMove::limitError()` — at most one keeper, at most
  `players_on_field` on the field — mirrored in `exceedsLimits()` in `js/app.js`.
- **Fairness:** target play time (`fairShareSeconds`, whole minutes) and target bench time per
  player; badges count up and turn green at the target. No next-on/next-off suggestions.
- **Corrections:** 10-second undo of the last goal/field change; every event deletable from the
  report's event log; "enter afterwards" forms.
- **Report:** tab on the tracking page and its own page (`/report`): event log, goals and
  assists, time played.

**Not built, and not part of this epic:** offline queue/sync (left out on purpose), live updates
(`realtime` is a plan), a public page, season aggregation, next-on/next-off suggestions, an SVG
canvas, editable presets.

## 2. Decisions for this epic

1. **Sport is a property of the team and of each preset.** Creating a team asks for its sport;
   the format choices are that sport's presets. A match override only offers presets of the
   team's sport. Existing teams and presets become `football`. The sport is fixed once the team
   has matches.
2. **Code holds the behaviour, presets hold the numbers.** A `SportConfig` class per sport
   declares zones, the keeper zone, available sanctions, scoring values and rules; presets
   (seeded from the federations) carry `players_on_field`, `play_minutes`, `breaks` and a
   `rules` JSON (card durations, foul limit, …) that overrides the config's defaults.
3. **Zones stay simple rows**, as today; each sport defines its own rows. No SVG, no fixed
   position slots, no formations in v1. The `Position` enum stays the zone vocabulary (the union
   of every sport's codes); a sport picks its subset and order, labels can differ per sport.
4. **Breaks work like the targets:** a preset has a number of `breaks`; the expected play-period
   length is `play_minutes / (breaks + 1)`. When the running play period reaches it, the screen
   cues a break (visual, plus vibration where the device supports it). Period labels follow the
   structure: 1 break → halves, 3 breaks → quarters, otherwise numbered periods.
5. **Football gets no cards** for now.
6. **Fouls and penalties are events** in the event log, deletable and undoable like goals.
7. **Presets are seeded from the official bodies** (KNVB done; KNHB, NHV, NBB here), not edited.
8. **One extension.** Split later only if it becomes unmanageable.
9. **Korfbal is postponed** to a fast-follow (see §6): it is the long pole and the only sport
   that bends the model (zones that rotate on goals, a boys/girls spot rule).

### Playing short (sanctions)

| Case | On-field count | Back-fill? | Player |
|---|---|---|---|
| Time penalty (hockey green/yellow, handball 2 min) | N − 1 for the penalty, stacking | No | off, returns after the timer |
| Red card (hockey, handball) | N − 1 for the rest of the match (hockey) or a set time (handball: 2 min) | No while short | ineligible for the rest of the match |
| Foul-out (basketball) | stays N | Yes | ineligible |
| Suspension limit (handball: 3rd 2-min) | as the time penalty | No while short | ineligible afterwards |

- Recording a penalty, red card or the limit-reaching foul takes the player off the field at
  that moment (an off-substitution linked to the sanction), so playing time stays correct with
  the existing replay. Deleting the sanction removes that substitution too.
- While short, the effective maximum is `N − active short spells`; the field never shows
  "below N" as a problem then.
- On expiry the maximum returns to N and the screen prompts to bring the player back (default
  the suspended player; any eligible player may come on instead). A prompt, never an automatic
  swap.
- Target play/bench times keep the planned full-strength values during short spells.
- Only our own team's sanctions are tracked; the opponent's aren't.

## 3. Architecture

- `src/Sport.php` — enum `football | hockey | handball | basketball` (korfbal later), `config()`.
- `src/Sport/SportConfig.php` + one class per sport (`Football`, `Hockey`, `Handball`,
  `Basketball`): `zones()` (rows top to bottom), `keeperZone()`, `defaultZone()`, `sanctions()`,
  `pointValues()`, `rule($preset, $key)` (preset `rules` value or the config default).
- `src/SanctionKind.php` — `foul | green_card | yellow_card | suspension | red_card`, with what
  each does (time penalty, ineligible, counted toward a limit).
- `sm_match_sanctions` — `match_id`, `period_id`, `team_member_id`, `kind`, `offset_seconds`,
  `duration_seconds` (time penalties/short spells; null = rest of match), `substitution_id`
  (the linked off-substitution, nullable).
- `MatchTimeline` gains sanctions: active short spells, remaining penalty seconds, ineligible
  players, foul counts.
- `FieldMove::limitError()` becomes sport-aware: keeper rule only where the sport has a keeper
  zone, effective maximum, ineligible players refused. `js/app.js` reads the keeper zone, the
  effective maximum and ineligible players from data attributes instead of hardcoding `K`.
- Scoring: `sm_match_goals.points` (default 1). Football/hockey/handball score 1; basketball
  offers 1, 2 or 3 points per basket. The score sums points.

## 4. Per sport

- **Football** — zones F/M/D/K, keeper K; no sanctions; 1 point. KNVB presets get `breaks`.
- **Hockey** — zones F/M/D/K (aanval/middenveld/verdediging/keeper), keeper K (formats without
  keepers set none in their preset rules); green card (2'), yellow card (5'), red card (rest of
  match, short for the rest). KNHB presets.
- **Handball** — zones A (hoek & cirkel)/B (opbouw)/K, keeper K; 2-minute suspension, third
  suspension makes the player ineligible; red card (ineligible, short for 2'). NHV presets.
- **Basketball** — zones C (center)/F (forwards)/G (guards), no keeper; personal fouls with
  foul-out at the preset limit (FIBA: 5), back-fill allowed; 1/2/3 points. NBB presets. The clock
  is the coach-driven clock we already have (start/stop at quarters and long stoppages).

## 5. Data changes

- `sm_team_format_presets`: `sport` (default `football`), unique (`sport`, `name`) instead of
  `name`; `breaks` (nullable); `rules` (JSON, nullable).
- `sm_teams`: `sport` (default `football`).
- `sm_match_goals`: `points` (default 1).
- `sm_match_sanctions`: new (see §3).
- Seeders: one command per federation, same shape as the KNVB one.

## 6. Korfbal (postponed — fast-follow)

Needs, beyond the above: two zones (attack/defence) of four, with the zones swapping roles every
two goals (derived from the goal count); a rule of two boys' spots and two girls' spots per zone;
a two-zone layout. Store a korfbal-only, nullable "plays in the boys'/girls' spots" field on the
team member — not gender, never on core's `Person`, never shown outside korfbal teams. KNKV rules
(team sizes, switch rule, youth variants) to confirm first.

## 7. Milestones (tick as completed)

- [x] **M1 — Sport spine + football through `SportConfig`.** Sport on teams and presets (team
  form, match override filtered by sport), `SportConfig` classes, zones/keeper rule/max from the
  config (server + `js/app.js`). *AC: football behaves exactly as before; full suite green.*
- [x] **M2 — Breaks.** Preset `breaks`, KNVB values, period labels (halves/quarters), break cue +
  vibration. *AC: a cue appears once the running play period reaches its expected length.*
- [x] **M3 — Sanctions + time penalty.** `sm_match_sanctions`, recording (live + afterwards),
  linked off-substitution, short-handed maximum, countdown badge, expiry prompt, event log,
  undo. *AC: a suspended player accrues no minutes, the team plays short, the return is
  prompted.*
- [x] **M4 — Red cards and counters.** Red card (ineligible + short spell), counted kinds with a
  limit (fouls, handball suspensions) → ineligible; foul-out allows back-fill. *AC: reaching the
  limit refuses the player on the field.*
- [x] **M5 — Zones per sport.** Rows, labels and preferred positions per sport. *AC: a sport's
  rows come from its config alone.*
- [x] **M6 — Hockey.** Config + KNHB presets. *AC: a youth match tracks with quarters and cards.*
- [x] **M7 — Handball.** Config + NHV presets. *AC: two halves, 2-minute suspensions, third one
  rules the player out.*
- [x] **M8 — Basketball.** Config + NBB presets, 1/2/3 points. *AC: quarters, foul-out with
  back-fill, score sums points.*
- [ ] **M9 — Korfbal.** Postponed (§6) — not started.

## 8. Testing (Pest)

- Football regression after M1: the existing `tests/Feature/SportsManagement` suite passes
  unchanged (apart from fixtures needing a sport).
- No minutes during a penalty; short spells don't change targets; stacking penalties.
- Limits: the foul/suspension limit refuses the player; foul-out allows back-fill; red card
  refuses for the rest of the match.
- Break cue rendered with the expected period length.
- Each sport's zones and presets.

## 9. Open points

- Federation values in the seeders are taken from the published rules where found; anything
  uncertain is marked in the seeder and here, to confirm.
- Basketball team fouls / bonus free throws: out of v1.
- Vibration isn't available on iOS Safari; the visual cue is the baseline.

## 10. Progress log

- 2026-10-03 — Epic rewritten against the code.
- 2026-10-03 — M1 done: migration `2026_10_03_000016` (preset `sport`/`breaks`/`rules`, unique
  sport+name; team `sport`; goal `points`), `Sport` enum, `src/Sport/{SportConfig,Football,Hockey}`,
  keeper/default zone/zone validation from the config (server + `data-sm-keeper` in `js/app.js`),
  team form loads the sport's presets via `teams/sport-fields.blade.php` (htmx), sport locked once
  the team has matches, match presets scoped to the team's sport, score sums `points`.
- 2026-10-03 — M2 done: `TeamMatch::playPeriodSeconds()`, break cue on the Break button
  (`data-sm-break-due`, pulse + `navigator.vibrate` once on the transition), period labels
  (`period_half`/`period_quarter`), KNVB breaks per knvb.h5mag.com/pupillenvoetbal: O8–O12 play
  halves with a time-out in each (3 breaks), JO13+ 1 break, JO7 none (advice only).
- 2026-10-03 — M3 done: `SanctionKind`, `sm_match_sanctions` (migration `000017`), `MatchSanction`,
  `MatchTimeline::{onFieldAt,shortSpells,penaltySecondsLeft,awaitingReturn,sanctionCounts}`,
  `TeamMatch::{unavailableMemberIds,effectiveMaxOnField}`, `storeSanction`/`destroySanction`
  (linked off-substitution, undo), sanction mode + button + return prompt on the field, countdown
  badge (`clock` partial `countdown`), event log + "enter afterwards" form. Hockey config got its
  cards early to test against. M4's rules (red card, counted limits, foul-out back-fill) are in the
  same code; their tests land with handball/basketball.
- 2026-10-03 — M5 done: `Position` gained `B`/`A`/`G`/`C`; `label(?Sport)` checks
  `positions_{sport}.{code}` first; `Position::options(Sport)` and member positions validated per
  sport; `Handball` and `Basketball` configs (rows, sanctions, rule defaults, basketball 1/2/3
  points) added to `Sport`.
- 2026-10-03 — M6 done: seeders share `Command/SeedsFormatPresets`; `kopling:sports-management:seed-knhb-presets`
  (O8 3-tal 2×15, O9 6-tal 2×25, O10 8-tal 2×30 — all without keepers; O11 9-tal and O12+ 11-tal
  4×17.5). O12+ confirmed on hockey.nl/veldhockey; O8–O11 from secondary sources (club/hockey
  sites) — to confirm against KNHB's jongste-jeugd rulebook. Cards: green 2', yellow 5', red rest of
  match (config defaults); whether O8–O10 use cards at all is to confirm.
- 2026-10-03 — M7 done: `kopling:sports-management:seed-nhv-presets` (F 3+keeper 2×15, E 5+keeper
  2×20, D 2×20, C/B 2×25, A/senioren 2×30, 7 players from D up). From club sources; handbal.nl's
  own page lists only the age bands — to confirm against NHV's competition rules, as is whether
  F/E-jeugd use suspensions. Test covers M4's counted limit (third suspension) and the 2-minute
  red-card short spell.
- 2026-10-03 — M8 done: `kopling:sports-management:seed-nbb-presets` (U8–U12 eight periods of 4',
  U14+ 4×10', 3x3 one period of 10' without foul-out), per a club summary of NBB's competition
  handbook — to confirm against the handbook itself. Goal `points` (1/2/3) with +1/+2/+3 buttons on
  the field and in the top bar, score and scorers sum points, own goal only for 1-point sports.
  Test covers foul-out with back-fill (M4). M4 ticked: red card, counted limits and foul-out are
  all covered by the M3/M7/M8 tests.
- **Release state:** M1–M8 done, korfbal postponed. On deploy: `php artisan migrate`, then the
  four seeders (`seed-knvb-presets`, `seed-knhb-presets`, `seed-nhv-presets`, `seed-nbb-presets`).
