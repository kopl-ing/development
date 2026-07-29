I've got a full, grounded picture now. Here's what's actually there today, the gap, and a concrete proposal.

Current state

k-core has a real pipeline; extensions don't.

- vite.core-dist.config.js compiles k-core/src/Ux/{css,js}/* into k-core/dist/* with fixed, unhashed filenames, Tailwind-processed via @tailwindcss/vite, npm dependencies bundled (Alpine, htmx, TipTap, emoji-mart, tagify). release.yml runs npm run build:core-dist,
  commits k-core/dist, then tags — the tag is what triggers subsplit.yml's create: tags: handler to mirror it into kopl.ing/core. Subsplit itself has no build step — it just mirrors whatever's already committed in a directory at that commit.
- Extensions get exactly one thing: PortalExtension::css(string $path) / ->js(string $path) (k-core/src/Portal/PortalExtension.php) — one hand-written, unprocessed CSS file and one already-browser-ready JS module per (extension, portal) pair, served at runtime through
  a hashed-key asset route (Manager::extensionAssets()) and injected via plain <link>/<script type="module"> in head.blade.php, only on requests that resolve to that specific portal. No Tailwind processing, no bundling, no npm dependencies, no code-splitting.
- The reason extensions can use Tailwind/daisyUI classes at all today is k-core/src/Ux/css/app.css's own explicit source globs:
  @source "../../../../k-extensions/**/*.blade.php";
- k-core's own compiled bundle scans every extension's Blade files in this monorepo and bundles whatever utility classes they use into k-core/dist/app.css. That's the entire mechanism today — there's no per-extension registration of anything; it's a hardcoded glob.
- .github/subsplit-config.json currently only splits core and reactions — most extensions aren't even wired into subsplit yet.

The actual gap

That @source glob is why this works inside this monorepo and why it quietly won't for the stated goal ("admins selectively choose extensions"):

1. Third-party/external extensions (installed via Composer into a real site, never part of this monorepo) never get scanned by anything — any Tailwind class they use beyond what's already used somewhere in this tree's k-core+extensions silently produces no CSS. There's
   no way for an extension author outside this repo to register their own classes into anyone's build.
2. JS is worse — there's no path at all today for an extension needing a real bundled dependency (not just Alpine/htmx, which core already loads). PortalExtension::js() requires a single pre-bundled file the author built by hand.
3. It's an implicit, one-directional coupling — k-core's release has to already know about extensions' markup; extensions have no mechanism of their own.

This is precisely the gap CLAUDE.md already flags ("Extensions don't get their own compiled bundle yet... a per-extension resources/+dist/+release workflow (mirroring k-core's) is the documented path") — just not built.

Proposed design

Mirror k-core's own pipeline, generalized, reusing conventions already established elsewhere in this codebase:

1. Source layout — an extension that needs real Tailwind/bundling gets its own resources/css/app.css + resources/js/app.js (distinct from the existing flat css//js/, which stays exactly what it is today: hand-written, unprocessed, portal-scoped passthrough files — no
   collision, two clearly different contracts). Each extension's own app.css does its own @import "tailwindcss" + daisyUI plugin + a shared preset/import for Kopling's theme tokens (so colors resolve identically), with @source scoped to only its own src//views/ — never
   the rest of the monorepo. This is the part that actually fixes the coupling: an extension's compiled bundle becomes self-contained and correct in isolation, which is the only way a third-party extension could ever plug into this.
2. Build — one generic Vite config (not a hand-copied file per extension) that discovers every extension with a resources/js/app.js present (directory-convention, same philosophy migrations//views//lang/ already use — per Manager::conventions()'s own docblock, no
   interface required) and builds each into its own k-extensions/{name}/dist/, fixed unhashed filenames, same as core's.
3. Release — extend release.yml (or a sibling job in the same workflow) to loop over extensions declaring compiled assets, build+commit each dist/ before the tag. Subsplit needs zero changes — it already mirrors whatever's committed in each configured directory at tag
   time.
4. Registration/loading — this is the one real design fork, worth deciding explicitly:
   - (a) Extend PortalExtension with ->compiledCss()/->compiledJs() (or auto-detect dist/ presence the same way), keeping compiled assets portal-scoped like the existing hand-written ones — consistent mental model, only loads where relevant.

Proposed design


Mirror k-core's own pipeline, generalized, reusing conventions already established elsewhere in this codebase:

1. Source layout — an extension that needs real Tailwind/bundling gets its own resources/css/app.css + resources/js/app.js (distinct from the existing flat css//js/, which stays exactly what it is today: hand-written, unprocessed, portal-scoped passthrough files — no
1. Source layout — an extension that needs real Tailwind/bundling gets its own resources/css/app.css + resources/js/app.js (distinct from the existing flat css//js/, which stays exactly what it is today: hand-written, unprocessed, portal-scoped passthrough files — no
   collision, two clearly different contracts). Each extension's own app.css does its own @import "tailwindcss" + daisyUI plugin + a shared preset/import for Kopling's theme tokens (so colors resolve identically), with @source scoped to only its own src//views/ — never
   the rest of the monorepo. This is the part that actually fixes the coupling: an extension's compiled bundle becomes self-contained and correct in isolation, which is the only way a third-party extension could ever plug into this.
2. Build — one generic Vite config (not a hand-copied file per extension) that discovers every extension with a resources/js/app.js present (directory-convention, same philosophy migrations//views//lang/ already use — per Manager::conventions()'s own docblock, no
   interface required) and builds each into its own k-extensions/{name}/dist/, fixed unhashed filenames, same as core's.
3. Release — extend release.yml (or a sibling job in the same workflow) to loop over extensions declaring compiled assets, build+commit each dist/ before the tag. Subsplit needs zero changes — it already mirrors whatever's committed in each configured directory at tag
4. Registration/loading — this is the one real design fork, worth deciding explicitly:
   - (a) Extend PortalExtension with ->compiledCss()/->compiledJs() (or auto-detect dist/ presence the same way), keeping compiled assets portal-scoped like the existing hand-written ones — consistent mental model, only loads where relevant.
   - (b) Load unconditionally on every page, mirroring how k-core loads its own assets in head.blade.php today (no portal-scoping there at all) — simpler, but ships every installed extension's CSS/JS on every page regardless of relevance.

I'd lean (a) for consistency with the mechanism extensions already have, but it's a real tradeoff worth your call.
5. Bonus, closes an existing TODO for free — head.blade.php currently calls @vite() for k-core's own assets unconditionally, which is flagged in CLAUDE.md as broken for any standalone install (ViteManifestNotFoundException, no public/build/manifest.json outside this
   monorepo). A shared helper (Manager::viteOrDist(...), say) — "use @vite() if the monorepo dev manifest exists, otherwise reference the committed dist/ output" — would be needed for extensions' compiled assets regardless, and using it for k-core's own head-assets too
   would close that standing TODO in the same pass instead of building two dual-mode fallbacks.

This is a project-wide build-system convention, so once you've picked a direction it's worth a charter/decisions.md entry before landing it, per this repo's own recording rules. Want me to go ahead and implement this — and if so, which way on point 4?

✻ Churned for 3m 34s

❯ let's do a, i also think we need to move the js and css directories from core out of Ux, into the root of k-core similar to extensions (resources/(css|js)?
3. Release — extend release.yml (or a sibling job in the same workflow) to loop over extensions declaring compiled assets, build+commit each dist/ before the tag. Subsplit needs zero changes — it already mirrors whatever's committed in each configured directory at tag
   time.
4. Registration/loading — this is the one real design fork, worth deciding explicitly:
   - (a) Extend PortalExtension with ->compiledCss()/->compiledJs() (or auto-detect dist/ presence the same way), keeping compiled assets portal-scoped like the existing hand-written ones — consistent mental model, only loads where relevant.
   - (b) Load unconditionally on every page, mirroring how k-core loads its own assets in head.blade.php today (no portal-scoping there at all) — simpler, but ships every installed extension's CSS/JS on every page regardless of relevance.

I'd lean (a) for consistency with the mechanism extensions already have, but it's a real tradeoff worth your call.
5. Bonus, closes an existing TODO for free — head.blade.php currently calls @vite() for k-core's own assets unconditionally, which is flagged in CLAUDE.md as broken for any standalone install (ViteManifestNotFoundException, no public/build/manifest.json outside this
   monorepo). A shared helper (Manager::viteOrDist(...), say) — "use @vite() if the monorepo dev manifest exists, otherwise reference the committed dist/ output" — would be needed for extensions' compiled assets regardless, and using it for k-core's own head-assets too
   would close that standing TODO in the same pass instead of building two dual-mode fallbacks.

This is a project-wide build-system convention, so once you've picked a direction it's worth a charter/decisions.md entry before landing it, per this repo's own recording rules. Want me to go ahead and implement this — and if so, which way on point 4?

