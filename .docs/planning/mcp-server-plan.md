# Plan: MCP server for Kopling (`kopling/mcp`, `kopling/oauth`), first consumer `sports-management`

Status: M1–M5 built (2026-11-06), uncommitted.

Since 2026-11-07 `sports-management` is no longer part of this repo (see decisions.md 2026-11-07).
Its parts of this plan are kept as written.

## Goal

Let a person use an AI assistant (Claude, or any MCP client) on a Kopling site with their own
account and their own permissions. The first use case is team staff working with their teams
through `sports-management`, for example: "plan Saturday's away match at 09:30 against FC Rivals",
"who hasn't driven yet this season?", "assign Jip to set up the goals next week", "Daan is ill
on the 14th, take him off as trainer that day". Admins get the same, within what their own
permissions already allow.

The server is a capability of the site, not of one extension: `kopling/mcp` runs it, any
extension can contribute tools to it, and nothing about it lives in core except a small,
dependency-free declaration (see "The extender").

## Shape: three packages, one responsibility each

| Package | Owns | Depends on |
|---|---|---|
| `kopling/oauth` (`k-extensions/oauth`) | OAuth 2.1 authorization server (Laravel Passport): clients, tokens, scopes, consent screen, "connected apps" per person, admin overview | `laravel/passport` |
| `kopling/mcp` (`k-extensions/mcp`) | The MCP endpoint (Laravel MCP), tool discovery, permission/scope filtering, audit log, admin settings, base classes for tool authors | `laravel/mcp`, `kopling/oauth` |
| `kopling/sports-management` | Its own tools under `src/Mcp/`, declared through the extender | nothing new (`suggest`s `kopling/mcp`) |

**Why OAuth is its own extension rather than part of `mcp`:** an OAuth server is a general
capability (a mobile app or a public API would need exactly the same clients, tokens and consent
screen); bundling it into `mcp` would make the next consumer depend on MCP to get login. `mcp`
requires `oauth` in its `composer.json`; `oauth` knows nothing about MCP. Same ownership test as
CLAUDE.md, "Feature ownership across extensions".

**Why not core:** neither Passport nor Laravel MCP belongs in every Kopling install. A site that
doesn't want AI access never installs these two packages and carries none of their dependencies,
tables or routes. Same reasoning as `realtime-extension-plan.md` and `activitypub`.

## The extender: how other extensions hook in

### Declaration in core, runtime in `mcp` (the federation precedent; decided 2026-11-06)

Follow exactly what federation does: `Extend\Federation` + `HasFederatedModels` live in core and
are protocol-free; `activitypub` reads them (decisions.md, 2026-08-10). For MCP:

- **Core** gets `Kopling\Core\Extension\Contract\HasMcpTools` and a plain value object
  `Kopling\Core\Extend\Mcp`:

  ```php
  public function mcpTools(): array
  {
      return [
          (new Mcp\Toolset('teams'))
              ->label('Teams')
              ->tools([ListMyTeams::class, GetTeam::class, PlanMatch::class])
              ->scope('read', [ListMyTeams::class, GetTeam::class])
              ->scope('write', [PlanMatch::class]),
      ];
  }
  ```

  It holds only class-strings and strings, no Laravel MCP types, so core has no new dependency
  and `sports-management` can implement the contract whether or not `mcp` is installed.
- **`kopling/mcp`** reads every enabled extension's `mcpTools()` through `Manager`, registers the
  tool classes with Laravel MCP, and offers the base classes tool authors extend
  (`Kopling\Mcp\Tool`, see below).
- **Tool classes** live in the owning extension (`k-extensions/sports-management/src/Mcp/Tools/`)
  and extend `Kopling\Mcp\Tool`. They are only ever autoloaded by `mcp`, so a site without
  `kopling/mcp` never loads them and never needs `laravel/mcp`.

### Alternative considered: the contract inside `kopling/mcp`

Matches the brief literally ("the extension carries the extender"), but `sports-management`'s
`Extension` would then `implements \Kopling\Mcp\...`, which fatals when `mcp` isn't installed.
The workaround is the `class_exists()` guard `sports-management` already uses for moderation's
`ReportControlEntry`. That works for one UX entry; for a whole contract on the extension class it
doesn't. Rejected for that reason.

### What `Kopling\Mcp\Tool` adds on top of Laravel MCP's own `Tool`

- `person()`: the authenticated `Person` (never a raw user id).
- `authorize($ability, ...$args)`: the same `Gate` the web controllers use, failing as an MCP
  tool error rather than an HTTP 403.
- `scope()`: the OAuth scope this tool needs, from the toolset declaration; checked before
  `handle()`.
- Annotations by default: read tools `readOnlyHint`, delete tools `destructiveHint`, so clients
  ask the person before calling them.
- Every call written to the audit log (see "Audit log").

## Tool names

Same rule as everything else an extension contributes: the extension declares **local** names and
`kopling/mcp` prefixes them with `Manager::id()` (`kopling/sports-management` →
`kopling-sports-management`), the way `AggregatesPermissions`, `AggregatesPortals`,
`AggregatesSettings` and route names do. Authors never write the prefix, and two extensions can
both have a `list-teams` without colliding.

- **Local name:** declared on the tool class (`protected string $name = 'plan-match';`), kebab
  case, `[a-z0-9-]+`, like permission and setting ids.
- **Full name:** `{extension-id}__{local-name}`, e.g. `kopling-sports-management__plan-match`.
  Not `{id}::{name}` like the other aggregators: MCP clients and the model APIs behind them only
  accept `A–Z a–z 0–9 _ -` in tool names, at most 64 characters, so `::` becomes `__`. The
  extension id itself never contains `_`, so the split stays unambiguous.
- **Checked at registration:** an invalid local name, or a full name over 64 characters, throws
  when `kopling/mcp` builds the server, naming the extension and tool, the same way a broken
  declaration fails anywhere else. Nothing is silently truncated.
- **Toolset and scope names** are local too (`teams`, `read`, `write`) and prefixed with `{id}::`;
  see "Scopes".

## Endpoint

- **One server per site, at `/mcp`**, not one per extension. A person adds one connector to their
  client; the tools they see are the union of every extension's toolsets, filtered (below).
  Every tool name is prefixed with its extension's id (see "Tool names").
- **Transport: streamable HTTP**, stateless per request. This fits the PHP request model and the
  shared-hosting profile in `realtime-extension-plan.md`: no long-running process, no queue, no
  Redis. Laravel MCP's web transport does this; verify streaming responses stay optional.
- **Filtering per person:** `tools/list` returns only tools whose scope the token carries **and**
  whose permission the person has. A coach without `manage-matches` doesn't see `plan-match`
  at all. Team-level rules (coach vs. referee, `Team::isCoachedBy()`) are checked per call, inside
  the tool.
- **Rate limit** per token (Laravel `throttle`), configurable through admin settings.
- **Resources and prompts** (MCP's other two primitives) are out of scope for v1; the toolset
  declaration leaves room for `->resources()` / `->prompts()` later.

## Authentication (`kopling/oauth`)

### What MCP clients expect

Remote MCP servers authorize with OAuth 2.1: authorization code + PKCE, protected-resource
metadata (`/.well-known/oauth-protected-resource`), authorization-server metadata
(`/.well-known/oauth-authorization-server`) and dynamic client registration, so a client like
Claude.ai can connect with only the server URL. Laravel MCP has first-party support for this on
top of Passport (`Mcp::oauthRoutes()`); confirm against the installed version before building.

### How it fits Kopling

- **Who logs in:** the person's normal Kopling account, through whatever login extension the site
  uses (`auth-email-password` today). The OAuth authorize page sits behind the existing login
  route (`AuthSettings::loginPath()`), so it never has its own password form.
- **Consent screen:** a Kopling page (`x-k::*` components, site theme) listing the client's name,
  the scopes it asks for in plain language ("See your teams and matches", "Plan matches and tasks
  for your teams") and Allow / Deny.
- **`Person` and Passport:** Passport's token guard needs `HasApiTokens` on the user model.
  Rather than putting a Passport trait on core's `Person`, `oauth` registers its own auth provider
  whose model is `Kopling\OAuth\Person extends Kopling\Core\People\Person` with the trait, used
  only by the `api` guard. It's still a `Person` for every `Gate` and `instanceof` check. Verify
  `Passport::$userModel` / provider resolution accepts this; fallback is a narrow core change.
- **UUIDs:** `people.id` is a UUIDv7 string. Passport's stock migrations assume integer user ids,
  so `oauth` ships its own copies of the Passport tables with `uuid('user_id')`
  (`Passport::ignoreMigrations()`), loaded through the extension's `migrations/` like any other.
- **No config files** (feedback memory: Settings over `config/*.php`): token lifetimes, whether
  dynamic client registration is open, and key storage location are `HasAdminSettings` fields;
  `oauth` applies them to Passport at boot.
- **Keys:** `kopling:oauth:keys` (an `HasCommands` command wrapping `passport:keys`), run once on
  install. There's no admin command runner yet; for shared hosting without a shell, `oauth` could
  generate missing keys on first use instead.
- **Personal access tokens** for CLI clients that can't do the browser flow (e.g. a header in
  Claude Code's config): generated from the person's "Connected apps" section, scoped the same
  way, revocable the same way.

### Scopes

Every token used against `/mcp` must carry the **`kopling-mcp::use-mcp` scope** (owned by
`kopling/mcp`, same id as the permission): a client
that didn't ask for it, or a token issued for something else later (an app, an API), is refused on
every call, whatever other scopes it has. The consent screen only offers `mcp` to a person who has
the `kopling-mcp::use-mcp` permission.

Beyond that, scopes are declared by toolsets with a local name (`read`, `write`) and prefixed by
`kopling/mcp` exactly the way `AggregatesPermissions` prefixes permissions:
`kopling-sports-management::read`, `kopling-sports-management::write`. OAuth scope strings allow
`:`, so scopes keep the `{id}::` form unchanged. `oauth` exposes the aggregated list to Passport
(`Passport::tokensCan()`). A token's scopes can only narrow what the person may do, never widen
it: permissions and team roles always apply on top.

### Where people and admins manage this

- **Person:** a "Connected apps" section on `/settings` through
  `AccountSettingsController::SECTIONS_SLOT`: each client, its scopes, last used, Revoke; plus
  "Create a personal token".
- **Admin:** an admin-portal page listing clients and active tokens per person, with revoke; and
  the settings above.

## Permissions

- `kopling-mcp::use-mcp`: may connect an MCP client at all. Not granted by default; an admin
  gives it to a group (e.g. coaches). Required twice, both checked on every call: the person must
  have the permission **and** the token must carry the `kopling-mcp::use-mcp` scope. Losing the permission
  disables existing tokens immediately; nothing waits for them to expire.
- Tool permissions are the owning extension's own, unchanged:
  `kopling-sports-management::manage-matches` gates match tools exactly as it gates the web
  routes. No MCP-specific copies of existing permissions.
- Team rules stay in the domain: tools call `Team::roleOf()`, `isCoachedBy()`,
  `TeamMatch::handles()` like the controllers do.
- `kopling-sports-management::administer-teams-via-mcp` (new, owned by `sports-management`): site admins
  for teams. See "Admins".

## Privacy

Roster data is about children, and an MCP client sends what it reads to the AI provider the
person chose. That is a real change in where data goes, so:

- **Off by default, twice:** the site admin enables MCP (`mcp` admin setting), and each team's
  owner allows it per team (`sm_teams.mcp_enabled`, team edit form, help text naming what leaves
  the site; decided 2026-11-06). A coach's token sees no data of a team that hasn't opted in.
  The one exception is site admins (see "Admins"); the help text says so.
- **Data minimization in tool output:** roster members as name + jersey number + positions; no
  `people` fields beyond that, no account emails of other staff, no public tokens/links (a task
  link in an assistant transcript is a leaked link).
- **Audit log** (below) visible to the site admin and, for their own team, to the team owner.
- The privacy page / charter should say that MCP access exists when enabled; propose the charter
  diff when this is built (CLAUDE.md, "Recording decisions").

## Audit log

`mcp_tool_calls`: `id`, `person_id` (nullOnDelete), `client_id`, `tool`, `team_id` nullable
(tools set it when the call is about one team), `arguments` (JSON, with free-text fields
truncated), `outcome` (ok / denied / error), `created_at`. Written by `Kopling\Mcp\Tool` for every
call; pruned after a retention period set in admin settings (default 90 days, scheduled command,
or on write when the site has no scheduler).

## First consumer: `sports-management` tools

All tools resolve "my teams" as teams the person is staff of (`Team::roleOf()`), and take ids,
not names, once the assistant has listed them. Every write tool reuses the same domain code the
web controller uses (see "Shared actions").

Names below are local; on the wire each is prefixed, e.g. `kopling-sports-management__plan-match`.

### Read (`kopling-sports-management::read`)

| Tool | Returns |
|---|---|
| `list-my-teams` | teams with role (coach/referee), sport, season, format preset |
| `get-team` | roster (current players; former players flagged), staff, periods, settings relevant to planning |
| `list-matches` | upcoming/past matches with opponent, home/away, period, score if played; filters: from/to, period |
| `get-match` | lineup, availability, referee, location, score and goal list if played |
| `live-match` | clock, running period, score, field and bench with player ids and zones, latest events with ids; coach or the match's referee |
| `list-tasks` | tasks with schedule summary, min/max, weight, staff-only flag |
| `task-planning` | dates in a window with who's on them, state (needs people / enough / full), skips |
| `task-balance` | balance per player (points, planned, no-shows); staff view, so former players and no-shows included |
| `list-all-teams` | admins only: every team with staff counts and moderation state |
| `team-stats` | admins only: aggregates per team or season (matches, roster size, task coverage, balance spread) |

### Write (`kopling-sports-management::write`)

| Tool | Does | Needs |
|---|---|---|
| `plan-match` / `update-match` | create / change a match (opponent, time, home/away, location, public) | coach + `manage-matches` |
| `cancel-match` | delete a match (destructive hint) | coach + `manage-matches` |
| `set-availability` | availability for one or more players for a match | coach + `manage-matches` |
| `add-player` / `update-player` | roster changes, including `left_on` | coach + `manage-teams` |
| `create-task` / `update-task` | task definition (schedule, min/max, weight, staff-only, ...) | coach + `manage-teams` |
| `assign-task` / `unassign-task` | staff assign / remove, per date or season-long, incl. "off this date" | coach |
| `record-outcome` | done / no-show on a passed date | coach, team confirms completion |

Live tracking (M6), so a coach can say "John scored, assist Kyle" or "John off, Charlie on" to an
assistant on their phone. Same checks as the tracking screen: `track-matches`, substitutions
coach-only, timing/scoring/sanctions through `TeamMatch::handles()` (a delegated duty is the
referee's alone; no admin bypass). All go through `Actions\TrackMatch`, shared with
`TrackingController`, and answer with the `live-match` state.

| Tool | Does | Needs |
|---|---|---|
| `start-period` | kick-off, break start, restart after a break (ends the running period) | timing duty |
| `end-period` | stop the running period, e.g. final whistle | timing duty |
| `record-goal` | goal for us (scorer, assist, own goal, points) or the opponent | scoring duty |
| `substitute` | swap (incoming takes the zone), off only, on in a zone; optional earlier `minute` | coach |
| `record-sanction` | card / suspension / foul, taking the player off when it rules them out | sanctions duty |
| `delete-match-event` | takes back events by id; the stateless stand-in for the screen's session undo | per event type |

### Admins (decided 2026-11-06)

Site admins can reach **every team, regardless of team roles and of a team's MCP opt-in**, to
work through moderation reports and do analytics across teams.

- **Who:** persons with `kopling-sports-management::administer-teams-via-mcp`. Core has no "superuser"
  (no `Gate::before` granting everything), and `sports-management` must not depend on `admin`'s
  `access-admin` or `moderation`'s `moderate` (ownership rule), so the extension declares its own
  permission and the site assigns it to its admin group.
- **What:** every read and write tool, on any team. Tools resolve the team through one helper,
  `Kopling\SportsManagement\Mcp\TeamAccess`: team staff → their role's rules; `administer-teams-via-mcp`
  → full access, opt-in ignored; otherwise not found.
- **Cross-team read tools**, only for `administer-teams-via-mcp`: `list-all-teams` (all teams,
  including hidden and soft-deleted, with staff counts and moderation state) and
  `team-stats` (aggregates per team or season: matches played, roster size, task coverage
  and balance spread).
- **Accountability:** every admin call is in the audit log with `team_id`. The team owner's view
  of the log shows admin access to their team, so the opt-in exception is visible, not silent.
- **Web UI unchanged:** `TeamsController::show` still requires a team role. Whether
  `administer-teams-via-mcp` should open the web pages too is a separate decision, not part of this plan.
- Instance-level tools (moderation queue, extension settings) remain a separate toolset for
  `moderation` / `admin` later.

### Shared actions: no second copy of validation

Controllers hold the validation and save logic today (e.g. `TasksController::validated()`,
`MatchesController::validated()`). Tools must not re-implement it. Each write tool's milestone
first extracts the controller's logic into an action class in the owning extension
(`src/Actions/PlanMatch.php`, taking validated data and a `Person`) used by both the controller
and the tool, with the existing Feature tests as the safety net. Only the code a tool actually
needs is extracted; no blanket refactor.

## Data model (sketch)

```
oauth (Passport tables, own copies, uuid user ids)
  oauth_clients, oauth_access_tokens, oauth_refresh_tokens, oauth_auth_codes,
  oauth_device_codes (only if Passport version requires it)

mcp_tool_calls
  id uuid, person_id → people null on delete, client_id string null, tool string,
  team_id uuid null (no FK: a tool's team can belong to any extension), arguments json,
  outcome string, created_at (microseconds, same as match event tables)

sm_teams
  + mcp_enabled bool default false
```

## Testing (Pest)

- `tests/Feature/OAuth/`: authorization code + PKCE flow, consent required, deny path, scopes
  narrowed to what the person may grant, revoke from settings, personal tokens, `use-mcp`
  missing → `kopling-mcp::use-mcp` scope not offered.
- `tests/Feature/Mcp/`: token without the `kopling-mcp::use-mcp` scope refused, tool names
  prefixed with the fixture extension's id, a local name that's invalid or too long refused at
  registration, person who lost `use-mcp` refused
  with a still-valid token, `tools/list` filtered by scope and permission, unknown/unauthorized tool
  errors, audit row per call, disabled extension's tools disappear, rate limit; fixture extension
  under `tests/Fixtures/Extensions/` declaring a toolset (`fakeManager()`), so `mcp` is tested
  without `sports-management`.
- `tests/Feature/SportsManagement/Mcp*Test.php`: every tool, including referee vs. coach,
  team without `mcp_enabled` (refused for staff, allowed for `administer-teams-via-mcp`, audited),
  admin-only tools hidden from staff, former players, and the "Daan is ill on the 14th" skip flow. Use
  Laravel MCP's own test helpers for calling tools; check what the installed version offers.

## Milestones (tick as completed)

- [x] M1 `kopling/oauth`: Passport with own uuid migrations, `Person` subclass provider, consent
      screen, scopes registry (empty), connected apps section, admin overview, keys command,
      settings. Tests.
- [x] M2 `kopling/mcp`: `/mcp` endpoint, OAuth discovery + dynamic client registration, core
      `HasMcpTools` + `Extend\Mcp\Toolset`, `Kopling\Mcp\Tool` base class, filtering, audit log
      + pruning, `use-mcp` permission, admin settings, fixture-extension tests.
- [x] M3 `sports-management` read tools, `sm_teams.mcp_enabled`, `administer-teams-via-mcp` permission,
      `Mcp\TeamAccess`, admin-only cross-team tools.
- [x] M4 `sports-management` write tools, with shared actions extracted per tool.
- [x] M5 Docs: `extend.html` "Adding MCP tools" section and a toolset in `k-extensions/example`
      (kept in sync, per CLAUDE.md), charter diff for MCP access, decisions.md entries for the
      decisions below once confirmed.
- [x] M6 Live match tracking tools (`live-match` + six write tools), `Actions\TrackMatch` extracted
      from `TrackingController`.

## Decided (2026-11-06)

1. **Extender in core:** `HasMcpTools` + `Extend\Mcp\Toolset`, protocol-free, read by
   `kopling/mcp`.
2. **Teams opt in** for `sports-management` (`sm_teams.mcp_enabled`, owner decides).
3. **Site admins reach everything**, regardless of team roles and opt-in, for moderation and
   analytics, through `kopling-sports-management::administer-teams-via-mcp`; every such call audited and
   visible to the team owner.
4. **`use-mcp` is mandatory** as both permission and token scope (`kopling-mcp::use-mcp`) on
   every call. Dynamic
   client registration stays open (clients like Claude.ai need it), since a registered client
   gets nothing until a person with `use-mcp` consents.
5. **Everything an extension contributes is prefixed with its id:** tools as
   `{id}__{local}` (MCP tool names allow no `:`), toolsets and scopes as `{id}::{local}`, the
   same as permissions, portals, settings and routes. See "Tool names".

## Open questions

None at the moment.

## Progress log

- 2026-11-06 — plan drafted.
- 2026-11-06 — decided: extender in core, teams opt in, site admins reach every team through
  `administer-teams-via-mcp` (audited), `use-mcp` required as permission and `mcp` scope.
- 2026-11-06 — tool names and scopes prefixed with the extension id like every other aggregated
  contribution: tools `{id}__{local}` (MCP's character set has no `:`), scopes `{id}::{local}`;
  the use-mcp scope is `kopling-mcp::use-mcp`.
- 2026-11-06 — M1 built: `k-extensions/oauth` (Passport 13.10, phpseclib 4). Wired by its own
  package `ServiceProvider` (decisions.md, same date), active only while the extension is enabled;
  Passport's routes off, `/oauth/token` registered outside `web`, authorize/approve/deny on the
  `oauth` Portal with a Kopling consent screen. `api` guard → provider `kopling-oauth-people`
  (`PeopleProvider`: sanctioned people resolve to no one) → `Kopling\OAuth\Person` (core `Person` +
  `HasApiTokens`, same morph class, own `getProviderName()`). Own Passport tables with uuid
  `user_id` (cascade on person delete), no device-code grant. `Kopling\OAuth\Token` adds a
  `person()` relation (Passport's `user()` can't be eager-loaded). Scope registry `Scopes::add()`
  with an optional permission per scope; `Bridge\ScopeRepository` drops scopes the person can't
  grant and `*`. "Connected apps" on `/settings` (revoke per app, personal tokens), admin page
  "Gekoppelde apps" (`manage-oauth`): apps, active tokens, revoke, create signing keys (generated
  in PHP, since `passport:keys` only exists on the console), lifetimes as admin settings. Core:
  `RedirectUnauthenticated` returns a 401 JSON for API requests (decisions.md, same date).
  Notes for M2: register the `kopling-mcp::use-mcp` scope through `Scopes::add()` with that
  permission; the 401 still needs MCP's `WWW-Authenticate` header pointing at the
  protected-resource metadata; dynamic client registration isn't in Passport itself.
- 2026-11-06 — M2 built: core `HasMcpTools` + `Extend\Mcp\Toolset` (`scope($id, $description,
  [tools])`) + `Manager::mcpToolsets()` (uncached). `k-extensions/mcp` (laravel/mcp 1.1.2), own
  package `ServiceProvider`, active only while both `mcp` and `oauth` are enabled (the site
  admin "enables MCP" by enabling the extension; no separate setting). `POST /mcp` behind
  `auth:api` → `RequireMcpAccess` (`use-mcp` permission and scope, 403 otherwise) → throttle
  (admin setting). `Registry` validates tool names when the server builds (kebab local name,
  full name ≤ 64, unique, must extend `Kopling\Mcp\Tool`). `Kopling\Mcp\Tool`: prefixed
  `name()`, `shouldRegister()` = `use-mcp` + tool scope + `allows($person)`, `person()`,
  `authorize()`, `aboutTeam()`. Audited `tools/call` writes `mcp_tool_calls` (ok / denied /
  error, free text cut at 200 chars), pruned on 1 in 100 writes after the retention setting.
  Own OAuth discovery (`/.well-known/oauth-protected-resource[/…]`,
  `/.well-known/oauth-authorization-server`) because Laravel MCP's assumes Passport's default
  route names and the `mcp:use` scope; the nested protected-resource route keeps Laravel MCP's
  name so its `WWW-Authenticate` middleware points at it. Dynamic client registration reuses
  Laravel MCP's controller (answering with Kopling's scopes); redirect domains are an admin
  setting, default `*`, and the consent screen now shows the domain a person returns to.
  Every MCP scope requires `use-mcp` to grant; Passport's default scope (no `scope` requested)
  is all MCP scopes. Admin page "AI-assistenten (MCP)" (`view-tool-calls`): tools per toolset
  and the call log. Notes for M3: the team owner's view of the log is still to build; Passport's
  default scope applies to every OAuth client, revisit when a non-MCP client appears.
- 2026-11-06 — M3 built: migration `2026_11_06_000030` (`sm_teams.mcp_enabled`, switch in the team
  edit form, owner only, shown when `kopling/mcp` is installed). Permission `administer-teams-via-mcp`.
  `Mcp\TeamAccess` (staff: teams they're on that opted in; admins: every team incl. hidden) and
  `Mcp\TeamTool` (`team()`, `tasksTeam()`, `player()`: name, number, positions, guest, left_on).
  Read tools, all in `kopling-sports-management::read`: `list-my-teams`, `get-team` (roster for
  coaches only), `list-matches` / `get-match` (referees: only their own matches), `list-tasks`,
  `task-planning`, `task-balance` (coaches), `list-all-teams`, `team-stats` (admins). Owner's view
  of the log: `Kopling\Mcp\Ux\TeamCalls` in sports-management's new owner-only slot
  `TEAM_OWNER_SLOT`, added behind `class_exists()` like moderation's report button. Notes for M4:
  a refused tool (not found, coaches only) is logged as `error`, not `denied`, since tool errors
  all come back the same way; write tools need the shared actions extracted first.
- 2026-11-06 — M4 built: actions `SaveMatch`, `SetAvailability` (replace for the form, partial
  for tools), `SaveTeamMember`, `SaveTask` (+ `formValues()`), `TaskEnlistments::recordOutcome()`;
  the controllers call them. Write tools in `kopling-sports-management::write`: `plan-match`,
  `update-match`, `cancel-match` (not after kick-off), `set-availability`, `add-player`,
  `update-player` (incl. `left_on`), `create-task`, `update-task`, `assign-task` (per date,
  whole task with `starts_on`, restores a regular taken off that date), `unassign-task` (with
  `date_id` on a whole-task sign-up: off that date only), `record-outcome`. Match tools need
  `manage-matches`, player and task definitions `manage-teams` (as the web routes); site admins
  pass. `task-planning` now returns `enlistment_id`s and, with `past_weeks`, passed dates with
  outcomes. A tool someone may not use answers "tool not found", like any unknown tool.
- 2026-11-06 — M5 done: `extend.html` Section 10 "MCP tools" (toolset, tool class, names, who sees
  a tool, base-class helpers, rules; Icon/Reference renumbered to 11/12). `k-extensions/example`
  implements `HasMcpTools`: toolset `things` with `list-things` (read) and `add-thing` (write,
  needs `manage-things`), `suggest`s `kopling/mcp`; `tests/Feature/Mcp/ExampleToolsetTest.php`.
  Charter: Section 9 row "AI assistants (MCP)" and D34. decisions.md: `use-mcp` on every call,
  team opt-in with audited admin reach. No privacy page exists yet; the charter row is where
  "MCP access exists when enabled" is stated for now.
- 2026-11-06 — M6 done: live tracking over MCP, reversing the v1 "out of scope" call now that
  there's a use case (a coach on the sideline talking to an assistant). Write logic moved from
  `TrackingController` into `Actions\TrackMatch`; tools in `src/Mcp/Tools/` on a `Mcp\MatchTool`
  base; `tests/Feature/SportsManagement/McpLiveMatchTest.php`. Write-scope consent wording updated.
- 2026-11-06 — Admin permission renamed from `administer-teams` to `administer-teams-via-mcp`
  (migration `2026_11_06_000031` moves existing grants): it only ever granted the AI-assistant
  reach, while admins reach teams on the web through moderation anyway. A site can now withhold
  AI reach to team data without touching moderation.
