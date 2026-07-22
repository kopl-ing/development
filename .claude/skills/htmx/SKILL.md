---
name: htmx
description: Use when writing HTML with htmx, building htmx-powered pages, troubleshooting htmx behavior, migrating to/from htmx, or authoring an htmx extension. This project pins htmx 4 (a beta, node_modules/htmx.org) -- do not assume htmx 1/2 conventions.
---

# htmx (this project's vendored htmx 4)

This project pins **htmx 4** (`node_modules/htmx.org`, currently a beta) — not htmx 1/2. Its own
skill docs ship inside the npm package itself, at `node_modules/htmx.org/dist/skills/`. Read the
one relevant to the task directly from that path rather than trusting anything memorized about
older htmx versions — this router is deliberately kept thin (no content copied in) so an
`npm install`/version bump never leaves stale guidance sitting here instead.

| Task | Read |
|------|------|
| Writing htmx markup: attributes, events, swap strategies, general patterns | `node_modules/htmx.org/dist/skills/htmx-guidance.md` |
| Debugging: requests not firing, swaps not happening, unexpected behavior | `node_modules/htmx.org/dist/skills/htmx-debugging.md` |
| Migrating an SPA framework (React/Vue/Angular) to htmx, or the htmx 2 -> 4 concepts | `node_modules/htmx.org/dist/skills/htmx-migration.md` |
| Step-by-step htmx 2.x -> 4.x upgrade (attribute/event renames, config, headers) | `node_modules/htmx.org/dist/skills/htmx-upgrade-from-htmx2.md` |
| Writing or debugging an htmx 4 extension | `node_modules/htmx.org/dist/skills/htmx-extension-authoring.md` |

Two htmx 4 specifics worth remembering even before opening any of the above (see `CLAUDE.md`'s
own gotchas list for the full context):

- Attribute inheritance is explicit — `hx-target:inherited="..."` on a parent, never a plain
  `hx-target` cascading to children.
- `htmx:after:swap` fires on the element that triggered the request, not the swap target — for
  an `outerHTML` swap of an ancestor, that element is already detached by the time the event
  fires, so it never bubbles anywhere. Use `htmx:after:settle` (fires on the actual swapped-in
  element) to react to a swap from outside it.
