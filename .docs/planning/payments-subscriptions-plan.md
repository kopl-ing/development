# Plan: Stripe-backed payments (`kopling/payments`)

Status: proposed, not started. No code written yet.

## Context

blomstra/payments (Flarum) proved the feature set is worth having — gate a permission group
behind a subscription, charge to post/reply in a tag, charge to view a tag's replies, charge to
bump a stale post, run bounties — but its own admin UX makes people learn its internal
vocabulary (PTA/PTP/PTR/PTV/PTB) before they can configure anything. That's the specific thing
to not repeat here. This plan mimics the *feature list*, not the *information architecture*.

## The naming/UX decision (read this before anything else)

There is no central "Payment Types" registry, and no acronyms anywhere in the admin UI or the
code. **The price field lives right next to the thing it gates, phrased in plain language, at
the screen you'd already visit to configure that thing:**

- Tag settings (`k-extensions/tags`' existing `/admin/tags` screen) gets a "Payments" section
  with four plain toggles + price fields: *"Charge to post in this tag"*, *"Charge to reply in
  this tag"*, *"Charge to view replies in this tag"*, *"Allow bounties in this tag"*.
- Group settings (admin's People/Groups screen) gets one toggle + recurring price: *"Charge to
  join this group"*.
- One global admin setting (via `HasAdminSettings`, same mechanism every other extension already
  uses): *"Charge to bump a moment"* — global, not per-tag, since bumping isn't naturally
  tag-scoped and a sixth per-tag price field would start recreating the acronym-soup problem.

Nobody configuring Kopling ever sees the words "subscription," "gateway," or a payment-type
dropdown. The person-facing side mirrors it: the paywall shows up exactly at the point of the
blocked action (the compose form, the reply box, the hidden replies, the bump button, the
join-group button) — never a separate "billing" page someone has to go find first.

## One-time vs. subscription — one rule, not per-feature bookkeeping

Learning carried over from blomstra/payments: every gate should offer both a one-time price and
a recurring price where it makes sense at all, and it makes sense everywhere **except bounties**
(a bounty is inherently a single pot of money attached to one moment — "subscribe to bounties"
isn't a coherent thing). One rule covers all five of the rest:

> **A one-time payment unlocks one instance of the action. A subscription unlocks the action for
> as long as it stays active, everywhere it applies.**

Concretely: pay once to publish *this* moment vs. subscribe to post in this tag without paying
per moment; pay once to bump *this* moment vs. subscribe to a bump pass; pay once to unlock *this*
thread's replies vs. subscribe to always see this tag's replies; pay once for lifetime access to
a group vs. subscribe to it monthly. Same rule, five features, nothing feature-specific to learn.

This also answers the schema question directly: **Kopling never stores a price or decides a mode
itself.** Each gate just points at a Stripe Product; the admin adds whichever Price objects they
want to that product in their own Stripe Dashboard (a one-time price, a recurring price, or both),
and Stripe's own hosted checkout surface shows whichever options exist — Kopling finds out which
one the person picked from the webhook, after the fact, rather than deciding in advance. This
removes an entire category of UI (no price/currency/interval form anywhere in Kopling's admin)
and keeps Stripe as the single source of truth for what something costs — exactly the
"mainstream tool inside, sovereign contract outside" treatment the charter already gives
Tiptap/htmx/daisyUI, just with less to wrap this time. **Verify before building** which exact
Stripe primitive does this (see Open Questions) — a Checkout Session/Payment Link's `mode` is
fixed at creation and can't mix a one-time and a recurring price in the same session, so "shows
both options" has to mean either a Pricing Table (built for exactly this) or two separate
Payment Links surfaced together; don't assume the API shape before checking it.

## Feature-to-mechanism map, and what each one actually needs

| Feature | One-time meaning | Subscription meaning | New mechanic needed |
|---|---|---|---|
| Pay to access a permission group | lifetime group membership, no expiry | membership synced to subscription status | subscription reconciliation |
| Pay to publish in a tag | this specific moment, paid for at submit time | post in this tag without paying per moment | held-submission interception |
| Pay to reply in a tag | this specific reply, paid for at submit time | reply in this tag without paying per reply | same interception, reused |
| Pay to view replies in a tag | unlocks this one thread's replies | always see this tag's replies | reply paywall rendering |
| Pay to bump a moment | unlocks bumping this one moment | a standing "bump pass" | `bumped_at` column + feed ordering |
| Bounties, per-tag opt-in | the only mode — one pot of money per moment | **not offered** | **accepted-answer marking** (doesn't exist yet) + Stripe Connect |

The bounty row is the one genuinely new piece of product mechanics (marking a reply as the
accepted answer) — everything else is a payment check bolted onto something that already exists.

## Scope for v1 (and what's deliberately out)

In scope: all six features above, real Stripe Checkout (redirect flow, no Stripe.js/Elements),
real webhook processing, group membership reconciliation, bounty collection + payout via Stripe
Connect Express.

Deliberately simplified, called out rather than silently narrowed:
- **Bounties don't use a true escrow hold.** A manual-capture PaymentIntent held until an answer
  is accepted is what "bounty" implies, but it also means building refund/expiry handling for
  held-but-never-captured funds. v1 collects the bounty into the platform's own Stripe balance at
  post time (a normal charge) and pays it out via a Stripe Transfer to the accepted reply's
  author once they've onboarded Connect. This is a real, deliberate simplification versus a true
  hold — flagged so it isn't mistaken for the real thing.
- **No self-serve subscription-management UI is built.** Stripe's own hosted Billing Portal
  already does cancel/upgrade/payment-method-update; "Charge to join this group" deep-links there
  instead of Kopling re-building it — same "mainstream tool inside" reasoning the charter already
  applies to Tiptap/htmx/daisyUI.
- **No per-tag override of the bump product.** One global Stripe product for v1 (with whichever
  one-time/recurring prices the admin attaches to it); if a real need for per-tag bump pricing
  shows up later, `payment_gates` (below) already has the shape to add it without a redesign.
- Refund policy beyond "bounty not accepted within its own window" is an open question, not
  designed here.

## Architecture / ownership map

Same ownership discipline as [activitypub-federation-plan.md](activitypub-federation-plan.md),
and the same precedent that plan and this one both lean on: **2026-07-18 in
[decisions.md](decisions.md)** — reactions' upvote/downvote config was moved off `tags`' own
table onto reactions' own, specifically because "reactions owns the concept" even though `tags`
had the convenient admin CRUD already. Payments follows the same rule: it never adds price
columns to `tags`/`groups`.

- **`k-extensions/payments`** (new) — owns all Stripe integration, all of its own tables
  (`payment_gates`, `payment_charges`, `payment_subscriptions`, `payment_bounties`,
  `payment_connect_accounts`, `payment_webhook_events`), the admin "Payments" section rendered
  into `tags`' and `groups`' existing settings screens via `ChangesUx`, and the global bump price
  via `HasAdminSettings`. Nothing outside this extension ever touches a `\Stripe\*` class
  directly — wrapped behind a `Payments` facade, per the charter's "mainstream tool inside,
  sovereign Kopling contract outside" rule (same treatment Tiptap/htmx/daisyUI already get).
- **`k-core`** — gains the one new cross-cutting hook payments needs and nothing else (see
  Phase 0): an `AuthorizesModels` contract, and a `bumped_at` column + feed-ordering hook on its
  own `Moment`.
- **`k-extensions/discussions`** — gains the accepted-answer mechanic on its own `moments`
  ownership boundary (`Moment::accepted_reply_id`, pointing at its own `Reply`) — a real feature
  in its own right (any Q&A-style thread benefits from marking an accepted answer), not a
  payments-only concept, so discussions owns it and payments listens for it.

## Phase 0 — Core/discussions prerequisites

**0a. `AuthorizesModels`** (new `k-core/src/Extension/Contract/AuthorizesModels.php`), mirrors
`ValidatesModels`'s exact shape but for pass/fail closures instead of cacheable rule strings:
```php
interface AuthorizesModels
{
    /** @return array<class-string, Closure(\Illuminate\Http\Request $request): bool> */
    public function modelAuthorizers(): array;
}
```
A closure returns `true` to allow, or **throws** to deny — deliberately not `true|string`: Laravel
treats any non-empty string as truthy, so a `FormRequest::authorize()` that naively returned a
denial *message* string would actually authorize the request by accident. Throwing sidesteps that
trap entirely and lets whatever's thrown carry more than a message when it needs to (Phase 4 has
`payments` throw its own exception carrying a checkout redirect, not just a 403). Aggregated the
same *uncached*, per-request way `Manager::models()` already is — not through `RegistrationCache`
— for the exact reason documented in the ActivityPub plan's Phase 0c: closures can't survive
`var_export()`, which is why `ValidatesModels`' own docblock requires its rules to stay
closure-free in the first place. `Manager::authorize(string $model, Request $request): void`
runs every registered closure for that model class, letting the first thrown exception propagate.

Two existing `FormRequest`s each need a **small, one-line change** to call it —
`k-extensions/composer/src/Requests/StoreMomentRequest.php` and
`k-extensions/discussions/src/Requests/StoreReplyRequest.php`, both of whose `authorize()`
currently just `return true`. Change to:
```php
public function authorize(Manager $manager): bool
{
    $manager->authorize(Moment::class, $this); // or Reply::class

    return true;
}
```
Whatever gets thrown renders via the app's normal exception-handling path — a plain denial can
just be Laravel's own `AuthorizationException` (renders 403, no new machinery); `payments`'
own payment-required case registers its own `renderable()` callback for its own exception type,
the same way core's `ServiceProvider` already does for the htmx auth-wall (decisions.md,
2026-07-09) — any package's provider can register one, not something core-exclusive.

This is the one place this plan reaches outside its own extension, same as the ActivityPub plan
needed a small `Portal` change in core — called out explicitly rather than left implicit.

**0b. `bumped_at` + feed ordering**: `k-core/migrations/..._add_bumped_at_to_moments_table.php`
adds a nullable `bumped_at` timestamp to `moments`. The feed's existing `QueryingMoments` event
(`k-core/src/Content/Event/QueryingMoments.php`, dispatched from `IndexController`/
`LatestMomentsController` right before the query runs, mutable `$query` — the exact mechanism the
2026-07-16 decision already establishes for feed-reorder extension points) is where `payments`
reorders by `COALESCE(bumped_at, created_at) desc` once it's installed — no new event needed,
this is precisely what `QueryingMoments` is already for.

**0c. Accepted answer** (`k-extensions/discussions`): its own migration adds nullable
`accepted_reply_id` (FK to `replies.id`) on `moments`. A small "mark as accepted" action, gated
to the moment's own author, dispatches a new `Discussions\Event\ReplyAccepted` (own event, wired
through the existing `ListensToEvents`/`Manager::listeners()` mechanism — not a new contract,
same reuse discipline as `QueryingMoments`/`AttemptLogin`) that `payments` listens for to trigger
a bounty payout.

## Phase 1 — `k-extensions/payments` scaffold and schema

New package, structured like every other extension (`composer.json` type `kopling-extension`,
`src/Extension.php`). Requires `stripe/stripe-php` — the one new external dependency this plan
introduces. Own migrations:

- `payment_gates`: polymorphic `subject_type`/`subject_id` (a `Tag` or a `Group`, or nothing for
  the single global bump gate), `action` (`publish` | `reply` | `view_replies` | `bounty_enabled`
  | `group_access` | `bump`), `stripe_product_id` (the only Stripe reference Kopling stores —
  whatever one-time/recurring prices exist on that product in the admin's own Stripe Dashboard
  are what Stripe presents at checkout; Kopling never stores an amount, currency, or mode). One
  row per `(subject, action)` — this is the single table every one of the "charge to..." toggles
  in the admin UX reads and writes.
- `payment_charges`: the ledger for one-time payments — `person_id`, `gate_id`,
  `subject_type`/`subject_id` (what got unlocked: for `bump`/`view_replies` the specific `Moment`,
  known when checkout starts; for `publish`/`reply` the `Moment`/`Reply` that gets created the
  moment payment clears — see Phase 4's "held submission," not a later, separate redemption step;
  for `group_access` the `Group`, granted permanently, no expiry), `stripe_checkout_session_id`,
  `status` (`pending`/`paid`/`refunded`/`failed`), `paid_at`. Always a 1:1 record of one real
  payment for one real thing — never a spendable balance. `Payments::hasPaidFor(Person $person,
  Model $subject, string $action): bool` checks this table for a paid charge on that exact subject
  **or** `payment_subscriptions` for an active subscription on the same gate — either satisfies
  it, which is the one method every gate check in this plan calls.
- `payment_subscriptions`: `person_id`, `gate_id` (which `payment_gates` row this subscription
  grants standing access to), `stripe_subscription_id`, `status`, `current_period_end` — drives
  group-membership reconciliation and every other gate's "standing access" check.
- `payment_pending_submissions`: exists only to carry a `publish`/`reply` attempt across the
  redirect to Stripe and back — `id` (uuid, used as the checkout session's `client_reference_id`),
  `person_id`, `gate_id`, `payload` (json — the validated moment/reply fields: `title`, `body`,
  `tags`, `moment_id` for a reply), `created_at`. Deleted once it's turned into a real `Moment`/
  `Reply` row (or left to expire — see Open Questions) — this table only ever holds an *attempt*,
  never anything a person could come back and redeem later on a different post.
- `payment_bounties`: `moment_id`, `person_id` (poster), `amount_cents`,
  `stripe_checkout_session_id`, `status` (`open`/`awarded`/`expired`), `awarded_reply_id`
  (nullable FK to `replies.id`), `awarded_at`.
- `payment_connect_accounts`: `person_id`, `stripe_account_id`, `payouts_enabled` — Connect
  Express onboarding state for anyone who might receive a bounty payout.
- `payment_webhook_events`: `stripe_event_id` (unique) — dedup log, since Stripe retries
  webhook delivery; every webhook handler checks-then-inserts here before doing anything else.

`HasPermissions`: `kopling-payments::manage-payments` (admin can edit gates/prices, view the
charge ledger and bounty list) — the existing `Extend\Permission` + Gate mechanism, no new
pattern.

## Phase 2 — Admin configuration surface

`ChangesUx` registrations into `tags`' existing admin screen (`k-extensions/tags/views/admin/
index.blade.php`'s own edit form) and `admin`'s Groups screen, each a small Blade partial reading
and writing `payment_gates` for that specific `Tag`/`Group` row — reusing the slot mechanism
exactly the way `tags` already registers `related-tags` into Community's rail, just a different
target slot. Every one of these toggles asks for exactly one thing when turned on: a Stripe
Product ID, pasted from the admin's own Stripe Dashboard where they've already set up whichever
prices they want that gate to offer. Global bump product via `HasAdminSettings`, same `Ux\Form\*`
components every other admin setting already uses (2026-07-14 decision) — a single text field,
not a settings framework of its own. **No price, currency, or interval field exists anywhere in
this admin surface** — that's the direct consequence of the one-time/subscription decision above.

## Phase 3 — Checkout: turning a gate into a real charge

No client-side Stripe.js/Elements, consistent with this codebase's no-build-step, no-JS-framework
extension philosophy — but unlike a plain single-price Checkout Session, the redirect target here
has to be whichever Stripe-hosted surface actually presents the gate's product's one-time *and*
recurring prices together (see the Open Questions item on confirming that primitive). A blocked
action (compose form submit, reply submit, bump button, join-group button) redirects to
`Payments::checkoutFor($person, $subject, $action)`, which resolves the gate's `stripe_product_id`
to that hosted surface, carrying `client_reference_id`/metadata identifying `(person, gate,
subject)` so the webhook can reconcile *whichever* price the person actually picked, plus a
`success_url` back to the original page. On success, htmx's own redirect-follow (the same
`hx-boost`-transparent redirect handling the login wall already relies on, per decisions.md
2026-07-09's `HX-Redirect` entry) lands the person back where they started; the actual unlock
only happens once the webhook confirms payment (Phase 5) and reports back *which* mode the person
chose — never decided or assumed by Kopling ahead of time, and never applied optimistically on
redirect alone.

`publish`/`reply` don't fit that shape at all, because the thing being paid for doesn't exist yet
— see Phase 4's "held submission" for how those two actually work; they never create a
`payment_charges` row up front the way every other gate does.

## Phase 4 — Enforcing the gates

- **Publish / reply — held submission, not a credit.** No prepaid balance, no "spend it on
  whatever you post next" — the payment is for *this* attempt, and the attempt's content is what
  waits, briefly, for the payment to clear. The `AuthorizesModels` closure (Phase 0a) checks, for
  each tag id in the submitted request, whether `payment_gates` has a `publish`/`reply` row for
  that tag and the person has an active subscription for it — if so, done, nothing further
  happens. If not, and no subscription covers it, the closure itself (not the controller, not a
  separate service) inserts a `payment_pending_submissions` row with the request's own validated
  payload, then throws `Payments\Exception\PaymentRequired` carrying that row's id. `payments`'
  own `renderable()` handler turns that into a redirect to Stripe checkout for the gate's product,
  `client_reference_id` set to the pending-submission id. On `checkout.session.completed`,
  `Jobs\ProcessStripeWebhook` (Phase 5) looks the pending submission up by that id, creates the
  real `Moment`/`Reply` from its stored payload right there, writes the resulting id onto a new
  `payment_charges` row in the same step, and deletes the pending submission — one attempt, one
  payment, one row, created exactly once, never a balance sitting around waiting to be spent on
  something else. A subscription-covered submission never touches `payment_pending_submissions`
  or `payment_charges` at all — it's simply allowed straight through.
- **View replies**: rendering-time, not submission-time, and — unlike publish/reply — the subject
  (the thread) is already known, so no credit indirection is needed: `Payments::hasPaidFor()`
  against that specific `Moment` (one-time) or the tag-wide gate (subscription) decides it
  directly. Recommended approach: `payments` registers a paywall component via `Ux::replace()`
  targeting discussions' own reply-body slot entry (`Reply::BODY_SLOT`,
  `k-extensions/discussions/src/Reply.php`) when gated and unpaid — replacing (not composing
  alongside) the real body with a "pay to view replies" CTA, since Ux slots normally compose and
  this needs to *suppress* the default content instead. **Verify before building**: confirm
  `UxEntry`'s `condition` (`Ux::when()`) only evaluates static Gate-ability strings (as `tags`'
  own `->when('kopling-tags::manage-tags')` usage suggests) before assuming it can carry a
  per-record "has this person paid" check — if it can't, the `replace()` component does the
  paid/unpaid branch itself in PHP rather than via a declarative `when()` condition.
- **Bump**: a button on the person's own Moment, visible only to its author, redirects to checkout
  for the global bump gate — either a fresh one-time charge for *this* moment, or, if the person
  already holds an active bump-pass subscription, skips checkout entirely and sets
  `bumped_at = now()` immediately. A one-time charge sets `bumped_at` on webhook confirmation, same
  as every other one-time unlock.
- **Group access**: not an `AuthorizesModels` check at all — it's the existing `hasPermission()`/
  Group-membership path, unchanged. A one-time `payment_charges` row for `group_access` inserts a
  permanent `group_person` row directly (no expiry — see Phase 5); a subscription keeps that row
  synced to the subscription's own status.

## Phase 5 — Webhooks and reconciliation

Stripe webhooks are machine-to-machine, same shape as the ActivityPub plan's inbox delivery: they
need to skip CSRF and read a raw body to verify the `Stripe-Signature` header. **This plan shares
the exact same prerequisite the ActivityPub plan already specified — `Portal::$middleware`,
letting a Portal declare `middleware: ['api']` instead of the hardcoded `['web']`.** Whichever of
the two plans lands first builds that capability; the other reuses it as-is, no duplicate work.

`POST /_stripe/webhook` (payments' own Portal, `middleware: ['api']`) verifies the signature,
checks-then-inserts into `payment_webhook_events` for idempotency, then dispatches a queued
`Jobs\ProcessStripeWebhook` — never doing the actual reconciliation synchronously in the request.
That job branches on `checkout.session.completed`'s own `mode` field — this is the one place
Kopling actually learns which price the person picked, since Phase 3 never decided it up front:
- `mode: payment`, ordinary gate (`bump`/`view_replies`/`group_access`) → marks the matching
  `payment_charges` row `paid` (matched via the session's `client_reference_id` from Phase 3). For
  `group_access` this immediately inserts a permanent `group_person` row; bump/view-replies are
  already unlocked as soon as the row is `paid`, since `subject_id` was known and set upfront.
- `mode: payment`, held submission (`publish`/`reply`) → the `client_reference_id` is a
  `payment_pending_submissions` id instead of an existing `payment_charges` row: look it up,
  create the real `Moment`/`Reply` from its stored payload, write a `payment_charges` row for it
  (already `paid`, `subject_id` set to the row just created), delete the pending submission.
- `mode: subscription` → creates/updates the matching `payment_subscriptions` row (`gate_id` from
  the same metadata) and, if the gate's action is `group_access`, syncs `group_person` membership
  to the subscription's status (`customer.subscription.updated`/`.deleted` keep it in sync after
  the initial checkout — add on `active`, remove on `canceled`/`past_due`).

A bounty's own `checkout.session.completed` (always `mode: payment` — bounties don't offer a
subscription, see Scope) marks the `payment_bounties` row `open` instead of touching
`payment_charges`.

`ReplyAccepted` (Phase 0c) triggers the bounty payout: a Stripe Transfer to the accepted reply
author's Connect account (`payment_connect_accounts`), guarded on `payouts_enabled` — if the
author hasn't onboarded Connect yet, the bounty stays `awarded` with payout pending and the
person sees a "connect a payout account to receive this" prompt, not a silent failure.

## Phase 6 — Hosting posture

Same reasoning as the ActivityPub plan's Phase 8, same underlying constraint
(`QUEUE_CONNECTION=sync` default, no scheduler wired up anywhere yet): webhooks may never arrive
on a host with no public URL or a misconfigured Stripe dashboard. `ProcessStripeWebhook` gets real
`tries`/`backoff`; the extension additionally ships a `payments:reconcile` console command
(`HasCommands`) that polls Stripe's API directly for subscription/charge status as the
degraded-host fallback a host operator can cron — not a new mechanism, the same posture
`federation:deliver-pending` already established.

## Config

New `.env` keys (root `.env.example`, env vars only — no application code, consistent with what
root already owns): `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`.

## Testing

Pest `Feature/` tests: gate checks per action type (paid/unpaid/guest), `AuthorizesModels`
denial surfaces the right message, webhook signature verification (valid/tampered/replayed —
the last one specifically exercising `payment_webhook_events` dedup), subscription→group-
membership sync in both directions (grant on `active`, revoke on `canceled`), bump reordering via
`QueryingMoments`, and the full bounty lifecycle (collected → accepted → transferred, and
collected → accepted → payout-pending when Connect isn't onboarded). Mock Stripe at the HTTP
boundary (`Http::fake()` against `api.stripe.com`, or `stripe-php`'s own test-mode fixtures) —
never hit real Stripe from the suite.

## Open questions worth a real answer before Phase 3/4/5 start

- **Which exact Stripe primitive shows one-time and recurring prices together**, since a single
  Checkout Session/Payment Link's `mode` can't mix them — most likely a Pricing Table (built for
  presenting several prices on one product at once), but confirm the current API/Dashboard
  capability directly before Phase 3 rather than assuming it from memory. This decides how
  `Payments::checkoutFor()` actually builds its redirect.
- Whether `Ux::when()`/`UxEntry` can express a per-record dynamic condition at all, or whether
  every payment-gated Ux registration has to do its own PHP check inside the component (Phase 4's
  "view replies" bullet already flags this — it affects more than just that one feature if the
  answer is no).
- Refund policy: does a bounty that's never accepted auto-refund after some window, or stay
  parked indefinitely awaiting a manual admin action?
- Abandoned pending submissions: a person who starts checkout for a paid publish/reply and never
  completes it leaves a `payment_pending_submissions` row behind forever. Low-stakes (it's just an
  unused draft, not money), but worth a cheap cleanup — e.g. a scheduled delete of rows older than
  a day — rather than leaving it unbounded.

Resolved by this revision, no longer open: multiple price tiers per gate (e.g. monthly vs. annual
group access) now falls out for free — a gate references one Stripe Product, and every Price
attached to it in Stripe's own Dashboard is what gets offered, however many there are. Also
resolved: publish/reply never needed a credit/balance system at all — holding the one pending
attempt across the checkout redirect and fulfilling it exactly once is enough.