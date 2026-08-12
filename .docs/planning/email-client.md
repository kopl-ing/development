# Kopling Mail Portal — Project Brief

## What we're building
A self-hosted webmail client as a "Portal" module inside **Kopling** (https://kopl.ing), a Laravel-based community platform (Laravel, Tailwind/daisyUI, htmx, Tiptap). The portal connects to any existing IMAP/POP3 mailbox — not a new mail provider, a better client for mailboxes people already have.

A person can connect **multiple mailboxes** (unlimited on self-host — see Business model below) and reads them through a single **unified inbox**: one merged view across every connected account, not a per-account switcher. Folder/message-list views and reading pane always operate across the person's full set of connected accounts unless a specific account/folder is chosen.

Ships as `kopling/mail-client` — named for the client specifically (not just `kopling/mail`) since other, differently-licensed mail-related extensions (a mailing-list feature, a notifications-via-email extension, etc.) may exist under Kopling later and shouldn't be implied as covered by this one package or its license.

## Why
- Existing open-source webmail (Roundcube, SnappyMail, Cypht, Mailpile, SOGo) is functional but dated in UX, and none combine modern UX with genuine privacy focus.
- Privacy-leading providers (Proton, Tuta) have huge validated demand (Proton: 100M+ users) but deliberately break standard protocol compatibility — Tuta has no IMAP/SMTP support at all, Proton requires a Bridge app — creating vendor lock-in.
- The gap: standards-compliant (works with any IMAP/POP3 box) + genuinely modern UX + real privacy/data-sovereignty engineering, all three at once. Nobody currently offers all three.
- Fits Kopling's existing positioning ("honors data sovereignty," "runs anywhere") and reuses its existing stack (queues, htmx, Tiptap, daisyUI) rather than requiring new infrastructure.

## v1 technical architecture

**Sync model**
- Polling-based sync via Laravel queue/scheduler (every 1–2 min per mailbox) — not IMAP IDLE. Must work on shared hosting, not just environments with persistent workers, consistent with Kopling's "runs anywhere" goal.
- Store only headers, flags, and folder structure locally. Fetch message bodies from the IMAP server on demand with a short-lived Redis/file cache. Do not mirror full mailboxes locally.
- Each connected mailbox = its own isolated queue job; one broken mailbox must not stall others.

**Data model**
- `mail_accounts` — one person to many accounts (no cap on self-host); connection details (host/port/encryption/protocol), credentials encrypted at rest (Laravel encrypter), never plaintext. One account per person flagged as the default "From" for new compositions (not replies — see UI below).
- `mail_folders`, `mail_messages` (headers/metadata + IMAP UID + folder + owning `mail_account_id`, for re-fetching bodies and for the unified inbox to attribute/badge each message to its source account), `mail_message_flags` (bidirectional sync of read/flagged/etc).
- Kept as its own linked module, not entangled into Kopling core tables — must be removable/optional.

**Protocol layer**
- IMAP via an actively maintained PHP library (e.g. `webklex/laravel-imap`) rather than raw `ext-imap`.
- SMTP sending via Laravel's existing Mailer (Symfony Mailer), but routed per-mailbox using the user's own SMTP credentials — not through Kopling's system mailer.
- Auth: support both plain username/password (app passwords, self-hosted IMAP) and OAuth2 (Gmail/Outlook) — OAuth2 is more work but covers the largest share of real-world mailboxes.

**UI**
- htmx for folder/message-list navigation and inline actions (archive, flag, move) — partial-swap model.
- Tiptap for compose/reply (already used elsewhere in Kopling).
- daisyUI components for the standard three-pane layout (folders / message list / reading pane).
- Unified inbox by default: message list pools every connected account, each row carries a small per-account indicator (initial/color chip) so provenance stays visible in the merged view. Folder tree in the sidebar still lets someone drill into one account's own folders when they want that (see UX & Portal plan, step 4).
- Compose "From" is an explicit account selector — defaults to the account being replied to (when replying) or the person's flagged default account (when composing new); never silently guessed beyond that.

**Explicitly out of scope for v1**
- Federation (ActivityPub-style cross-instance interoperability) — a protocol-design problem, not a feature; revisit only once there's a validated user base and a clear definition of what "federation" should actually do here.
- Full-text search across message bodies — v1 searches headers/subjects only; body search (via Meilisearch/Typesense) is a later addition once real demand is confirmed.
- Calendar/contacts — resist scope creep; a genuinely good mail experience alone is the whole v1.

## Business model & licensing

**Monetization principle**: charge for hosting infrastructure and convenience (storage, deliverability, spam handling, backups, extra accounts on a hosted SaaS), not for self-hosted software features. Self-hosters — including businesses and nonprofits — get the complete, unrestricted mail portal for free.

**License: Elastic License 2.0 (ELv2)**
- SPDX / Composer identifier: `"license": "Elastic-2.0"`
- Not OSI-approved open source — some automated license-checking tooling may flag it and require manual allowlisting; expect this, not just human pushback.
- Not parameterized like BUSL (no Licensor/Additional-Use-Grant template blanks) — the restriction is worded directly into ELv2's own "Limitations" section, which already reads almost exactly as: *"You may not provide the software to third parties as a hosted or managed service, where the service provides users with access to any substantial set of the features or functionality of the software."* Self-hosting and internal/nonprofit use are unrestricted by omission — ELv2 only restricts hosting it *as a service to others*.
- Intended framing for docs/marketing (unchanged from the original BSL wording): *"You may self-host and use the Licensed Work for any purpose, including internal business or nonprofit use. Offering the Licensed Work to third parties as a hosted or managed mail service requires a commercial license from Kopling."*
- No automatic conversion to an open license after N years — unlike BUSL's Change Date mechanism, ELv2 has no built-in time-limited expiry. Restriction is permanent unless Kopling relicenses it manually later. Worth being explicit about this given the "Key constraint" section below originally leaned on BUSL's "time-limited by design" framing.
- Scope the license to the mail client module specifically (`k-extensions/mail-client`), not all of Kopling core or other Kopling extensions.
- ELv2's own text includes a "Limitations" clause about not circumventing "license key functionality" — this only has teeth if such a mechanism actually exists in the software. Not built for v1 (no enforcement infrastructure required to ship), but this clause is what a future free/internal-use registration or license-key gate could hang off later, if that direction is pursued.

## Key constraint to hold onto
Every architectural decision should keep the self-hosted, zero-cost path for individuals and nonprofits completely full-featured. The subscription/licensing lever is about who else can resell this as a hosted service, not about withholding functionality from people running it themselves.

## UX & Portal integration plan
Mail gets its own Portal — not a page bolted onto Community — but must reuse Kopling's existing shared chrome/nav rather than hand-rolling its own, same as Admin and Docs already do.

1. **Declare the Portal, gated by its own permission.** In the extension's `Extension` class (`k-extensions/mail-client/src/Extension.php`), `implements HasPortals, HasPermissions`:
   ```php
   public function permissions(): array
   {
       return [
           new Permission(
               id: 'access-mail',
               label: __('kopling-mail-client::permissions.access-mail.label'),
               description: __('kopling-mail-client::permissions.access-mail.description'),
           ),
       ];
   }

   public function portals(): array
   {
       return [
           new Portal(id: 'mail', label: 'Mail', path: 'mail', layout: 'kopling-mail-client::layouts.mail', permission: 'access-mail'),
       ];
   }
   ```
   Registers as `kopling-mail-client::mail`, gated by `kopling-mail-client::access-mail`. Every route inside the Portal's `Route::group()` rides behind ordinary `can:kopling-mail-client::access-mail` middleware (see `k-core/routes/web.php`) — not a bespoke check. Unlike Admin's `access-admin`, this permission isn't meant to be operator-only: it exists so a community can choose whether Mail is available at all (e.g. keep it off during early rollout), granted broadly once enabled, same mechanism either way.

2. **Reuse `Community\Chrome` for layout — do not build a new topbar/sidebar/rail.** `k-extensions/admin/views/layouts/admin.blade.php` and `k-extensions/docs/views/layouts/docs.blade.php` are the precedent: both wrap `<x-k::community.chrome>` with their own slot names instead of copying its markup. `k-extensions/mail-client/views/layouts/mail.blade.php`:
   ```blade
   <x-k::community.chrome
       portal-id="kopling-mail-client::mail"
       topbar-slot="kopling-mail-client::mail.topbar"
       sidebar-slot="kopling-mail-client::mail.sidebar-panel"
       :rail-slot="null"
       :composer-slot="null"
       :mobile-dock="false"
       main-class=""
   >
       @yield('content')
   </x-k::community.chrome>
   ```
   `main-class=""` (not Community's default `max-w-2xl mx-auto`) since the message-list/reading-pane split needs full width, same reasoning as Admin's own override.

3. **Reuse the existing user menu / theme switcher, don't rebuild them.** In `Extension::ux()`, register Core's own `Community\UserMenu` and `Community\ThemeSwitcher` into Mail's topbar slot — the same components Admin re-registers into its own topbar rather than copying. Confirm the exact registration Admin uses in `k-extensions/admin/src/Extension.php` (`ux()` method) and mirror it 1:1 targeting `kopling-mail-client::mail.topbar`.

4. **Sidebar = unified smart views on top, per-account folder trees below; main = message list + reading pane.** Map the mail panes onto Chrome's two content columns (`sidebarSlot`, `main`), following the Docs precedent (sidebar = tree/nav, main = content) — but the sidebar itself has two tiers, not one flat list, since the default view spans every connected account:
   - `sidebar-panel` slot, top section → unified smart views that pool across every one of the person's `mail_accounts` ("All Inboxes" — the default landing view, "Flagged", "Sent"). These are queries over `mail_messages` filtered by the person's account set, not real IMAP folders.
   - `sidebar-panel` slot, below that → one collapsible section per connected `mail_accounts` row, each expanding to that account's own real folder tree (`k-extensions/docs/views/ux/sidebar.blade.php`'s `<ul class="menu">` pattern is the closest existing analog for the tree itself) — this is how someone drills into one account specifically, or reaches a folder (e.g. a provider-specific one) that has no place in the unified smart views.
   - `main` (the `@yield('content')` region) → an inner two-column split for message list + reading pane. Use a daisyUI `drawer` (collapsing to the message list on mobile, list+reading-pane side-by-side on desktop) rather than hand-rolled grid/flex CSS — check daisyUI's `drawer` component first per the project's "prefer daisyUI over hand-rolled" rule before reaching for custom breakpoints. Each row in the message list carries a small account indicator (initial/color chip) per the UI section above, since rows in "All Inboxes" mix accounts.
   - Selecting a message swaps only the reading pane via `hx-target`+`hx-select` on a shared content region (same pattern as `page/pagination.blade.php`/`card/title.blade.php`), not a full Chrome/topbar/sidebar re-render.

5. **Attach routes/assets via `ExtendsPortals`,** same mechanism every extension uses, including ones that declare their own Portal (declaring it grants no shortcut):
   ```php
   public function extendsPortals(): array
   {
       return [
           new PortalExtension('kopling-mail-client::mail')
               ->routes(__DIR__.'/../routes/web.php')
               ->css(__DIR__.'/../resources/css/app.css')
               ->js(__DIR__.'/../resources/js/app.js')
               ->compiledAssets(__DIR__.'/..'),
       ];
   }
   ```

6. **Compose UI: reuse Core's `<x-k::editor>` (Tiptap), don't re-bundle Tiptap.** `Editor` is already wired into `Core::ux()` and ships as one of the five precompiled Core bundles (`k-core/dist/editor.{css,js}`, loaded via `Manager::viteOrDist(base_path('k-core'), 'editor')`). Compose/reply should consume that existing component and its compiled bundle directly — the brief's "Tiptap for compose/reply (already used elsewhere)" should mean literally reusing Core's editor, not vendoring a second Tiptap install into `k-extensions/mail-client`'s own `resources/js`.

7. **Add a Mail entry point via the user menu, not Community's nav.** Admin's actual mechanism (`k-extensions/admin/src/Extension.php:175-182`), registers a `Portal\Navigation\Item` into `UserMenu::SLOT` (not Community's primary navigation), gated by permission, self-hiding while already on that portal:
   ```php
   ->add(Item::class, [
       'label' => __('kopling-mail-client::messages.mail'),
       'route' => 'kopling-mail-client::mail/index',
       'icon' => 'kopling-mail-client::mail',
       'hideOnPortal' => 'kopling-mail-client::mail',
   ])
   ->in(UserMenu::SLOT)
   ->as('mail-link')
   ->when('access-mail')
   ```
   Same `access-mail` permission declared in step 1 gates this entry too — mirrors Admin's `admin-link`/`access-admin` pairing exactly, just reusing one permission for both the Portal's routes and its own menu entry instead of two. `hideOnPortal` is read by `Item` (`k-core/src/Ux/Portal/Navigation/Item.php`) off `$context->isPortal(...)`, so it only self-hides correctly if the entry is rendered with Portal context — same as Admin's own entry.

8. **Verify against `k-extensions/example`** (the canonical reference implementation for both `ExtendsPortals` + `compiledAssets()`) before treating any of the above as final — it demonstrates the exact two-attachment pattern (own Portal + Community) and the auto-derived vs. explicit `$name` cases for `compiledAssets()`.
