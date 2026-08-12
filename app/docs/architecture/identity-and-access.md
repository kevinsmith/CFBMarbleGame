# Identity & Access — Design Supplement

Companion to `docs/architecture/README.md`. Identity follows all the same rules — this supplement exists because user/identity modeling is the most confusion-prone territory in any application, and the design reasoning deserves room the main doc shouldn't spend.

## Three concerns, package by evidence

Identity bundles three concerns with different owners and change cadences:

| Concern | Owns | Cadence |
|---|---|---|
| **Identity** (`Users`) | The `users` row, core profile, lifecycle (creation, email change, closure), `UserRegistered` | Churns with product features |
| **Authentication** (`Auth`) | Credentials, sessions, MFA, token rotation | Stable security code — touched rarely, reviewed hard |
| **Access management** | Role assignments, permission checks | Churns with organizational policy |

Two valid packagings:

- **Split modules** (`Users` + `Auth`; access management lives with either, or stands alone): boundaries as above.
- **One coarse `Identity` module** owning all three (principle 9) — the seams above are the pre-identified split lines. Split on evidence, not up front.

Either way: identity is the most-consumed module in any system — everything wants `CurrentUser`, permission checks, display data — so its Kernel surface will be the codebase's largest. Keeping the module to identity + credentials + role assignments and *nothing else* is what keeps that surface from metastasizing.

## Naming decisions (and rejected alternatives)

- **`Users`** (split) or **`Identity`** (coarse) — nouns owning lifecycles.
- **Not `Registration`/`Signup`**: modules are named for the lifecycle they own, not a moment in it. Whoever creates the `users` row also owns profile updates, email changes, and closure; signup is just the create-moment. `UserRegistered` is a well-named *event* for the same reason.
- **Not `UserManagement`/`AccountManagement`**: "Management" is an activity, not an owned thing, and is infinitely elastic — nothing is obviously out of scope, which invites god-module accretion. "Account" additionally reads financial next to Billing.
- **Not `UserProfiles`**: too narrow — the module owns the lifecycle, not just the profile; a profile isn't what registered.
- **Not `UserIdentity`**: mildly redundant; `Identity` suffices and stays accurate if the module's scope ever generalizes.

A signup *funnel* rich enough to have its own change cadence — A/B-tested steps, referral codes, growth-team ownership — can later earn a separate `Onboarding` module that drives the flow and calls a Users Kernel contract to create the user; Users still owns the table. Split-on-evidence, per principle 9, not a starting point.

## The god-entity risk: keep the core entity thin

"User" is the classic god-entity, and a Users module risks being the god-entity with a namespace. The gravitational failure is every feature adding one more column to `users` — locale, plan, avatar, notification settings — until the module is a dumping ground whose boundary fails every test.

The discipline: **other modules never extend the core user table.** They own their *own* user-keyed tables (`billing_customers` keyed by `UserId`) and their own projection of what a user *is* to them. The vocabulary test predicts this: "User" means payer to Billing and ticket-opener to Support, so each meaning lives in its owning module, related through the Kernel `UserId`.

**Review signal:** a migration adding a capability-specific column to `users` belongs in that capability's module as a new table.

A narrow `users` table is also all the future-proofing the schema needs — it stays cheap to restructure if requirements change. Don't add type columns or abstractions for principal kinds that don't exist yet.

## Authorization splits in half

The example rule "an invoice can be voided only while unsettled, by its org's admin" fuses two different rules, and they live in different places — that's the point:

- **Access machinery** — *who is allowed to* — centralizes as a Kernel contract the identity-owning module implements: `can(UserId, Permission): bool`. The check is per capability; roles are the grouping abstraction behind it — a user is assigned roles, each role bundles capabilities, and `can()` resolves user → roles → capabilities. The call takes only *who* and *which capability*, never the target's state, which is what keeps it push-shaped and thin. It answers the example's "by its org's admin": does the user's role grant the capability to void invoices in that org?
- **Domain authorization** — *whether the action is legal on this particular aggregate right now* — is a Domain invariant of the owning module (Billing), *never* centralized. It answers the example's "only while unsettled": the `Invoice` aggregate's own method refuses to void once settled. Pooling such rules would rebuild every domain inside the access module (the dependency-magnet failure mode from the main doc).

A void request runs through both halves in order: Billing calls `can($userId, Permission::VoidInvoice)` — the access module says yes or no with zero knowledge of invoices — and, if yes, Billing asks the `Invoice` aggregate whether it is unsettled, a state check the access module must never see. `can()` is the door policy — who may try; the aggregate is the safe — whether the try succeeds.

The identity side answers *who has the permission* (resolved through roles); each domain answers *what the permitted user may do to its aggregates*.

## Worked Example: Current User (PHP session)

Shape the contract around *identity*, not *storage* — a generic `Session` interface in Kernel invites modules to share a mutable grab-bag, coupling them through session keys (a hidden Module → Module dependency no tool sees).

| Piece | Location | Why |
|---|---|---|
| `CurrentUser { id(): ?UserId; isAuthenticated(): bool }` | `App\Kernel\Identity` | Domain-shaped contract modules type-hint; Identity owns it |
| `PhpSessionCurrentUser` (reads `$_SESSION`) | `App\Identity\Infrastructure` (Identity owns login/session semantics) | Infrastructure with decisions baked in |
| Binding interface → implementation | Bootstrap | Wiring |

**Swap test:** moving from PHP sessions to JWT touches one binding, zero modules.
