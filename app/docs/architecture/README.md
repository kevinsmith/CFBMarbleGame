# Modular Monolith Architecture Principles

A reference for structuring a PHP application with enforced module boundaries. This file lives at `app/docs/architecture/README.md`; code-side pointers (the PHPat `->because()` messages and `app/` AGENTS.md files) use the app-relative path `docs/architecture/README.md` — the whole directory travels as a unit with the app. Companions: `docs/architecture/identity-and-access.md` (identity deep-dive), `docs/architecture/greenfield.md`, `docs/architecture/migration.md` (adoption paths), and the embedded diagram `docs/architecture/modular-monolith-dependency-diagram.svg`.

**Contents:** [Layer Structure](#layer-structure) · [Terminology](#terminology) · [Naming](#naming) · [The Dependency Rules](#the-dependency-rules) · [What Goes Where](#what-goes-where) · [Guiding Principles](#guiding-principles) · [Drawing Module Boundaries](#drawing-module-boundaries) · [Inside a Module: Layers](#inside-a-module-layers) · [Worked Examples](#worked-example-cache) · [Enforcement with PHPat](#enforcement-with-phpat) · [Adopting the Architecture](#adopting-the-architecture) · [Common Violations → Fixes](#common-violations--fixes) · [Code Review Checklist](#code-review-checklist) · [Failure Modes & Mitigations](#failure-modes--mitigations) · [Health Signals](#health-signals)

## Layer Structure

```
App\Kernel\        ← shared contracts (interfaces, events, value objects)
App\Billing\       ← business module
App\Orders\        ← business module
App\Tax\           ← capability module (shared service behind a Kernel contract)
App\Bootstrap\     ← composition root (container, routes, HTTP edge)
```

## Terminology

- **Contract** — anything a module depends on as a stable agreement: interfaces, cross-module events, shared value objects. Contracts live in Kernel or vendor.
- **Interface** — the PHP construct. Every cross-module interface is a contract; not every contract is an interface (events and value objects are contracts too), and not every interface is a contract (module-internal interfaces are just module code).
- **Vertical slice** — everything one business capability needs, top to bottom: its actions, use cases, entities, and persistence together in one module. The anti-pattern is *horizontal* organization — grouping by technical role across all capabilities (`app/Controllers`, `app/Services`, `app/Models`), where one feature is smeared across every folder. Here it's inverted: cut by capability first (modules), by technical role second (the four layers *inside* each module). "Where's the invoicing code?" has a one-word answer: `Billing`.

## Naming

Architecture-relevant naming rules borrowed from Doctrine Coding Standard:

- **No `Interface` suffix or prefix.** The contract takes the natural name; implementations carry the qualifier: `PdoInvoiceRepository implements InvoiceRepository`, `SystemClock implements Clock`. This synergizes with the architecture: the unadorned name belongs to the abstraction, nudging every type-hint toward the contract, and implementation names become self-describing.
- **No `Exception` suffix on concrete exceptions** (`CannotVoidInvoice`, not `CannotVoidInvoiceException`); abstract exception classes and exception *interfaces* do take the suffix. Name exceptions for the violation, which also keeps the Outcomes convention honest — an exception that's hard to name as a violation is probably an expected outcome.
- **No `Abstract` prefix, no `Trait` suffix.** Name abstract classes for what's common (`PdoRepository`, extended by concrete repositories), not for being abstract.
- **Vendor and PSR names keep their own conventions** (`Psr\SimpleCache\CacheInterface`, `Psr\Container\ContainerInterface`) — don't rename what you don't own. This is why doc examples mix styles: unsuffixed = ours, suffixed = PSR/vendor.

## The Dependency Rules

| → means "depends on" | Allowed? | Rationale |
|---|---|---|
| Module → Kernel | ✅ | Kernel exists to be shared |
| Module → same module | ✅ | Internal cohesion |
| Module → other Module | ❌ | Communicate via Kernel contracts |
| Kernel → anything (except vendor) | ❌ | Kernel must depend on nothing in the app |
| Bootstrap → anything | ✅ | Composition root wires everything |
| Anything → Bootstrap | ❌ | The edge is invisible to the inside |

Enforced with **PHPat** (as PHPStan rules) from day one — see the Enforcement section. Rules that don't fail CI are documentation, not enforcement.

![Dependency structure at both scales: Bootstrap on top as the only entry from the outside world; modules below it with one opened to show Presentation and Infrastructure adapters, Application, and Domain; Kernel beneath everything as the terminus of every in-app chain; vendor and the PHP library as the ground under it all. Red edges mark forbidden dependencies.](modular-monolith-dependency-diagram.svg)

## What Goes Where

### Kernel — things modules depend on
All dependency arrows in the app point one direction — inward, toward Kernel — and here they **terminate**: everything may depend on Kernel; Kernel depends on nothing in the app.

- Shared contracts: interfaces (`Clock`), cross-module events (`OrderPlaced`), value objects (`Money`, `EmailAddress`, `OrderId`)
- Tiny stable implementations: `SystemClock`
- **Everything here must earn its keep.** Kernel is the most expensive real estate in the codebase: everything may depend on it, so each addition widens the universal surface, each change ripples system-wide, and retraction is the costliest refactor there is. Nothing enters speculatively — a contract is admitted by a second module's demonstrated need (principle 8), and that bar is the maintenance budget's main defense.

### Modules — vertical slices (business domains *or* shared capabilities)
- Internally structured as four layers — `Presentation`, `Application`, `Domain`, `Infrastructure` (see Inside a Module: Layers)
- **Capability modules** (`App\Tax\`, `App\Notifications\`) are modules too: shared non-trivial services live here, exposed via a Kernel contract, consumed by other modules through that contract only. Structurally identical to business modules.
- May define **internal interfaces** freely (`Billing\Domain\InvoiceRepository`) for testability/abstraction — an interface only moves to Kernel when *another module* needs to depend on it
- Implement Kernel interfaces and dispatch/listen to Kernel events — no module-level public surface
- Contain no route or container declarations — all wiring lives in Bootstrap

### Bootstrap — things that depend on modules
The same dependency arrows **originate** here (`Bootstrap → Module → Kernel`): Bootstrap depends on everything; nothing depends on Bootstrap. Outermost ring of the onion, not an opposite direction.

- Container configuration and bindings (all of it — modules declare none)
- Route definitions for every module (centralized; the single map of the app's HTTP surface)
- Front controller, middleware pipeline, error handler
- Cache configuration (backend choice, TTL defaults, key prefixes)
- Internal namespaces: flat until size demands, then by concern (`Bootstrap\Http`, `Bootstrap\Persistence`, `Bootstrap\Events`) — Bootstrap has no layers; it's all wiring
- Anything reading env vars, DSNs, or naming multiple modules

## Guiding Principles

### 1. Contracts sink, decisions rise
Contracts sink toward Kernel or vendor packages.
Decisions (config, I/O targets, wiring) rise toward Bootstrap.

**Test:** pick any implementation choice (Redis vs. file cache, sessions vs. JWT) — could you reverse it by editing only Bootstrap (one binding, one new adapter)? If a module must change too, that module knows *which* implementation instead of just *what* capability: it has imported a decision instead of a contract.

**Fix:** put a contract in front of the decision (principle 2), move the concrete reference behind it into an Infrastructure adapter, bind the pair in Bootstrap, and have the module type-hint only the contract. Then rerun the test: the swap should now be one binding.

### 2. Prefer vendor contracts over homegrown ones
`Psr\SimpleCache\CacheInterface` beats `App\Kernel\Cache` — stable, framework-agnostic, maintained by someone else. Kernel-quality for free.

Note: PSR-first is a *default*, not absolute. When a PSR contract lacks functionality you genuinely need, the package's own interface is next in line (per principle 7's preference order) — still a contract, still keeps concrete classes out of modules.

### 3. Explicit shared space beats implicit
`App\Kernel\Clock` over `App\Clock`. Promoting a class to Kernel is a deliberate act; that friction is the scaling mechanism. The root namespace as shared space becomes a junk drawer.

### 4. Modules stay ignorant, Bootstrap wires
- **Decision:** all route definitions and container config live in Bootstrap — modules declare nothing for aggregation
- Boundary enforcement of shared backends with flat namespaces (session storage, queue names, log channels): Bootstrap partitions them per module at binding time. For example, a module type-hints `CacheInterface`, and Bootstrap contextually binds a cache pool with that module's key prefix. Unprefixed, two modules writing `customer:42` silently overwrite each other — string coupling through the shared keyspace. Prefixing in Bootstrap keeps modules ignorant of the sharing and makes collision impossible rather than avoided by convention.
- Modules never touch the container directly (constructor injection only)
- Mitigation as Bootstrap grows: split its config files *by module* (`Bootstrap/routes/billing.php`, `Bootstrap/container/billing.php`)

### 5. Sideways communication goes through Kernel contracts
- **Decision:** all cross-module contracts live in Kernel — no module `Contracts\` namespaces
- Billing needs to know an order was placed? Kernel defines `OrderPlaced`; `Orders` dispatches it; `Billing` listens. Neither knows the other exists.
- Sub-namespace Kernel contracts by owning module — the module that implements or dispatches them (`App\Kernel\Orders\OrderPlaced`, `App\Kernel\Billing\InvoiceCreator`); use a concern name only when no module owns the contract (`App\Kernel\Persistence\Transaction`, implemented by Bootstrap glue). Default to the owner — concern names are the exception, not a style choice.
- Never concrete class → concrete class across modules — PHPat enforces it, so it's never a debate at crunch time
- Reconsider module-level contracts only if Kernel churn becomes painful — not before

### 6. Where to put shared services
1. **Trivial, deterministic, nothing to wire** (`Money::add()`, `Slugifier`) → Kernel concrete class, used directly. (`SystemClock` is trivial but reads the wall clock — not deterministic — so it's consumed via the `Clock` contract instead; see the Clock example.)
2. **Infrastructural glue** — config/vendor dependencies, no real logic of its own (HTTP client, cache adapter) → interface in Kernel (or PSR), wiring in Bootstrap
3. **Genuine behavior** — testable logic, own dependencies, likely to evolve → a **capability module**: contract in Kernel (`App\Kernel\Tax\TaxCalculator`), implementation in `App\Tax\`, binding in Bootstrap

Caution: no module-per-helper-class. A lone `SlugGenerator` stays in Kernel (case 1) until a real capability cluster emerges.

The payoff: "shared service" is not a special case. The architecture has exactly three concepts — Kernel (contracts), modules (behavior), Bootstrap (wiring). A cross-cutting service is just a module whose consumers span domains.

### 7. Vendor dependencies: abstract behavior, not values
Modules may depend on vendor code directly, split by what kind of thing it is:

**Services/capabilities** (things that *do* — I/O, side effects, swappable behavior):
- Depend on an interface: PSR if one exists → the package's own interface → a Kernel-defined interface as last resort
- Bootstrap binds the concrete implementation; modules never touch it (vendor concretes are deny-by-default everywhere outside Bootstrap)
- One exception: when the vendor class can't satisfy the contract as-is (`PDO`, a Guzzle client behind a Kernel interface), the module's Infrastructure adapter wraps it — the concrete is named there and nowhere else in the module. Such an adapter is often a capability module in waiting: when a second module needs the same capability, the port promotes to Kernel and the adapter graduates into the capability module's Infrastructure (principle 8). Persistence never takes this ride — each module keeps its own `PDO` repositories, permanently.
- **Kernel-defined interface (last resort, when the package offers none):** shape the contract by what modules need, not by the vendor's API. An adapter in a capability module's Infrastructure implements it (a few lines of pure glue may live in Bootstrap). Keep vendor types out of the interface's signature: Kernel value objects, scalars, or PSR types only, or the abstraction leaks.

**Value types** (things that *are* — `DateTimeImmutable`, `Brick\Money\Money`, `Ramsey\Uuid\Uuid`):
- Depend on the concrete class directly — fine and usually better
- An interface over a value object rarely means anything (`MoneyInterface` promises what, to whom?), the type permeates signatures anyway so an interface wouldn't save a migration, and interfaces sacrifice finality/immutability/named constructors
- ✅ in modules: PSR interfaces, `DateTimeImmutable`, `brick/money`, `ramsey/uuid` values, validation libraries — effectively language extensions

**When a package contains both** (e.g. `ramsey/uuid`): abstract the producer, use the product directly — inject `UuidFactoryInterface` behind an abstraction, pass `Uuid` values around concretely.

**Test** (same as principle 1): would you ever swap it? If swapping is plausible, modules see only the contract. If inconceivable, direct use is fine.

**The "wrap everything" objection:** the familiar rule — never depend on vendor code directly — is right about services and kept in full above. Extended to value types it inverts: the wrapper is a shadow API you now maintain (less documented and less tested than what it hides, minus finality, immutability, and named constructors), and it still wouldn't make a migration cheap, since the type permeates every signature either way. That's cost paid up front for protection never delivered. "Everything" is not a risk assessment; the swap test is.

### 8. When placement is ambiguous, default to the module
Put it in the most relevant module and promote later. Moving a class *into* Kernel when a second module needs it is a cheap, mechanical refactor; retracting something *from* Kernel means touching every consumer. The same asymmetry applies to contracts: keep an interface module-internal until another module actually needs it (see "internal interfaces"). Premature promotion is the failure mode; late promotion is a rename.

### 9. Prefer few coarse modules
Start with a handful of coarse modules; split only when a module demonstrably contains two things — different owners, different change cadences, or internal boundary friction. The asymmetry mirrors principle 8: splitting a coarse module later is mechanical, while merging finely-shattered modules means unwinding Kernel contracts that never needed to exist. Module count grows with the team, not with ambition. Two or three modules is a fully valid instantiation of this architecture.

## Drawing Module Boundaries

A module is a business capability that owns its data and vocabulary. The business-vs-capability label is informational, not structural (principle 6) — but when the label feels ambiguous, the test is **whose rules are they?** Rules a product manager would describe (eligibility, trial terms, approval flows) belong to a business module. Rules an engineer would describe (password hashing, token rotation) belong to a capability module.

**Name modules for the lifecycle they own, not a moment in it.** Modules are nouns owning things (`Orders`, `Billing`, `Catalog`); events name moments (`OrderPlaced`, `InvoiceVoided`). A module named after an event — Checkout, Registration, Signup — is a naming smell: data ownership means whoever creates the `orders` row also owns amendment, cancellation, and status transitions, so the module's real scope is the whole order lifecycle, and checkout is just its create-moment. (A purchase funnel rich enough to have its own change cadence — A/B-tested steps, promo codes, growth-team ownership — can later earn a separate `Cart` module owning the pre-order journey, which calls an Orders Kernel contract to place the order; Orders still owns the table. Split-on-evidence, per principle 9, not a starting point.)

Identity, authentication, and access control are the most confusion-prone modeling territory in any application and get their own deep-dive: `docs/architecture/identity-and-access.md`. The same rules apply there; the design reasoning just needs more room.

Four tests for whether a proposed boundary is correct:

- **Change test.** Things that change together belong together. If a typical feature PR would touch both sides of the boundary, it isn't a boundary — it's a line through the middle of one thing.
- **Data ownership test.** Every table has exactly one owning module, and a module owns all writes to its tables. If you can't name the owner of a table under a proposed split, the split is wrong or the table needs decomposing.
- **Vocabulary test.** The same word meaning different things marks a real boundary: "Customer" as payment-method-holder (Billing) vs. ticket-opener (Support) are two concepts that deserve two homes, related only by a shared `CustomerId` in Kernel. Conversely, two modules constantly translating between near-identical concepts are probably one module.
- **Interaction-surface test.** Describe the interaction between the two proposed modules. If it fits in a sentence and compiles down to a handful of Kernel contracts ("Billing reacts when an order is placed" — one event), the boundary is good. If it takes a paragraph, or the split would mint five interfaces consumed only by each other, it's one module.

**For readers who know DDD:** a business module approximates a *bounded context* — the vocabulary test above is the bounded-context test, and one ubiquitous language holds per module. The mapping has two edges: capability modules aren't bounded contexts (they wrap machinery, not a domain model), and despite the name, `App\Kernel` plays the role of DDD's *Published Language* between contexts (events, shared value objects), not its *Shared Kernel* pattern — nothing here implies a co-owned domain model. With those two caveats, the DDD literature on finding context boundaries applies directly to drawing module boundaries.

**What belongs in a single module:** the complete vertical slice for its capability — all four layers (see Inside a Module: Layers). A module should be able to answer every question about the data it owns without leaving home.

**What belongs in separate modules:** distinct capabilities that pass the interaction-surface test; shared technical capabilities with real behavior (capability modules, principle 6); code with structurally different change drivers (principle 9's split criteria).

**Signals a boundary is wrong — too fine:**
- Feature work routinely spans the same pair of modules
- Chatty contracts: fulfilling one request crosses the boundary multiple times
- Kernel accumulating contract pairs consumed only by one specific A→B relationship

**Signals a boundary is wrong — too coarse:**
- Two internal clusters with a thin bridge between them — that bridge is a natural seam
- Vocabulary collisions inside the module (one `Customer` class serving two meanings, sprouting conditional fields)
- Different people consistently editing disjoint halves; merge conflicts at the shared edges

When the signals conflict, principle 9 breaks the tie: stay coarse, split when the evidence accumulates.

## Inside a Module: Layers

Every module has the same four sub-namespaces. Uniformity is mandated up front — the one deliberate exception to principle 8's promote-later asymmetry, because here the asymmetry points the other way: creating four directories is trivial, retrofitting layers into a grown flat module is a full internal reorganization. A missing directory is fine while empty (PHPat rules on absent namespaces match nothing).

| Layer | Contains | May depend on (within the module) |
|---|---|---|
| `Domain` | Entities, domain services, repository *interfaces*, module-internal value objects | Nothing — pure, no I/O |
| `Application` | Use cases, Kernel event listeners, and implementations of Kernel interfaces whose answer is computed from the module’s own Domain (vs. read from an external mechanism — see `Infrastructure`) | `Domain` |
| `Presentation` | Actions (one per route), responders, request validation — the wire-format boundary (typed Kernel events skip it; those event listeners live in Application) | `Application`, `Domain` (value objects and enums referenced in outcomes — never entities) |
| `Infrastructure` | PDO repositories implementing Domain interfaces, vendor glue — home of every implementation of a Domain, Application, or Kernel interface whose job is reading or writing an external mechanism (database, session, network, filesystem) | `Domain`, `Application` — never `Presentation` |

The table governs intra-module dependencies only. **Every layer may additionally depend on Kernel** and on vendor code per principle 7 — with one tightening: Domain gets value types and pure functions only, and that applies to PHP itself as much as to vendor (`DateTimeImmutable` yes; `file_get_contents`, `random_int`, `new DateTimeImmutable('now')` no — time and randomness enter Domain as injected Kernel contracts like `Clock`). PHPat enforces the structural rules and bans `PDO` outside Infrastructure; PHP's I/O is mostly functions, which PHPat can't see, so the rest of Domain purity is a review gate.

Tie-breaker for a Kernel-interface implementation’s layer: how would you test it? If in-memory fakes of Domain interfaces suffice, it belongs in Application (Tax’s `CalculateTax`). If it needs the real session, database, or network, it belongs in Infrastructure (`PhpSessionCurrentUser` and `$_SESSION`). See Testing by layer.

**Infrastructure is invisible inside the module:** nothing in the module sees it; only Bootstrap references Infrastructure classes (to bind them to Domain/Application interfaces). Note the direction of that exclusivity — Bootstrap references *every* layer (routes reference Presentation actions, the listener map references Application listeners, bindings reference Infrastructure); what's special about Infrastructure is that Bootstrap is its only referrer. Unlike Bootstrap, Infrastructure does not see everything — it depends inward on Domain and Application only. The `PDO` instance is constructed in Bootstrap (DSN is a decision) and injected into Infrastructure repositories; `PDO` never appears outside Infrastructure.

**Presentation and Infrastructure are sibling adapters** (hexagonal terms: *driving* vs. *driven*). Presentation drives the module — wire-format input arrives (routed there by Bootstrap, the system's only entry point) and is translated into typed calls on Application use cases. Infrastructure is driven by it — Domain, Application, or Kernel define interfaces; Infrastructure fulfills them. Both point inward toward the core from opposite sides and never see each other: a repository has no business knowing how requests arrive. If both seem to need a shared shape, that shape belongs in Domain.

**Placement quick answers:**
- Presentation follows ADR (Action–Domain–Responder): one action class per route invokes an Application use case; a responder maps the outcome union to the response (see Outcomes). "Domain" in ADR terms is the Application + Domain layers together.
- The command a use case accepts and the DTOs it returns are Application classes, written in the app's own language (`CreateInvoiceCommand`, outcome objects). Shapes that mirror a transport — request payload structures, JSON response bodies — are Presentation classes. Actions translate between the two families, which is what lets HTTP, CLI, and queue adapters all drive the same use case.
- Validation splits: request-shape validation ("is this well-formed input") → Presentation; business invariants ("can this invoice be voided") → Domain, on the entity.
- Presentation is the adapter for every stimulus that crosses a *representation* boundary, not just HTTP: CLI commands and queue-consumer actions live there too, invoking the same Application use cases. Stimuli already typed in the app’s own language (Kernel events) have nothing to adapt and enter via Application directly.
- Read paths may bypass Domain: a list or report screen can use a query-service interface (Application) with a PDO implementation (Infrastructure) returning plain DTOs — hydrating full entities to render a table is ceremony without payoff. Writes always go through Domain.

**Transactions:** deciding what forms one atomic unit — which writes succeed or fail together — is business knowledge, so the Application use case owns the transaction boundary. It can't call `$pdo->beginTransaction()` itself (`PDO` is Infrastructure-only), so it injects a Kernel contract instead: `App\Kernel\Persistence\Transaction { run(callable $fn): mixed }` — begin, execute the callable, commit on return, roll back on throw. The implementation is a few lines over `PDO`, wired in Bootstrap on the same connection the repositories use, so every write inside the callable shares one database transaction (infrastructural glue, principle 6 case 2). Dispatch Kernel events inside `run()` and the default event semantics (listener failure rolls back — see Failure Modes) become mechanically true: the listener executes while the transaction is still open, and its exception unwinds the whole unit.

**Outcomes:** a use case's return type is a union of small, immutable outcome classes — `PostUpdated|PostNotFound|NotAuthorized|ValidationFailed` — living in Application. Each is `final readonly` with its data required in the constructor, so illegal states — a "success" with no data — are unrepresentable; add a private constructor and named factories only where construction has invariants beyond field presence (`ValidationFailed::fromViolations()` rejecting an empty list) or distinct paths worth naming. The responder handles the union with an exhaustive `match`, and PHPStan verifies every case is covered: the exhaustiveness that makes the output-port pattern attractive, without inverted control flow, stateful responders, or void-everywhere composability problems. (Skip handler/output-port interfaces except niche cases: streaming outcomes, or one use case driving several very different presenters.)

- **Exceptions are for the exceptional only** — DB down, invariant violated, programmer error. Domain throws on invariant breaches; Infrastructure failures bubble; a Bootstrap-level error handler maps uncaught exceptions to 500s. Never catch-all and convert to an error result: that flattens bugs into business outcomes and blinds error monitoring. Never exceptions-as-control-flow for validation.
- **Results are semantic, not presentational.** Outcome classes carry codes and structures (`EmailInvalid`), never user-facing strings; each driving adapter (HTTP, CLI, queue consumer) phrases and maps them (e.g. to status codes) itself.
- **Results live at the boundary.** They carry primitives and DTOs, never domain entities, and they don't tunnel through inner layers — `Result<Result<...>>` means the boundary is misplaced.
- **Transaction interplay: throw to abort, return to commit.** `Transaction::run()` rolls back on exceptions only, so a use case returning a failure outcome must do so before any writes, or throw instead.

**Event mechanics:** dispatch via a dispatcher interface injected into Application use cases — PSR-14's `Psr\EventDispatcher\EventDispatcherInterface` is the example throughout, per principle 2's PSR-first ladder, but the requirement is the injected contract, not that particular standard. Listener registrations are wired in Bootstrap's event-to-listener map. Listeners live in Application, not Presentation: a Kernel event arrives already typed in the app's own language — no wire format to adapt, no response to map — and synchronous listeners run inside the dispatcher's transaction, Application's territory. (If events later move to a queue, the consumer action deserializing the message is Presentation; the typed listener it invokes stays Application.)

**Within a layer:** flat until size demands grouping — `Billing\Application\CreateInvoice`, not `Billing\Application\UseCases\CreateInvoice`. When a layer does need grouping, group by feature or aggregate (`Application\Invoicing\`, `Domain\Subscription\`), never by technical kind (`Application\Commands\`, `Domain\Entities\`, `Application\DTOs\`) — kind-folders recreate horizontal organization in miniature inside every layer. A use case's outcome classes sit beside it.

**Testing by layer:** Domain gets pure unit tests, no doubles needed. Application gets unit tests with in-memory fakes of Domain interfaces. Infrastructure gets integration tests against a real database. Presentation gets request/response tests through the front controller. All test doubles — `FrozenClock`, in-memory repositories — live under `Tests\` (autoload-dev), mirroring the module they serve; tests depend on `src/`, never the reverse — PHPat-enforced (`testSrcNeverReferencesTests`), with runtime as backstop: a `src/` reference to `Tests\` fatals wherever dev autoloading is absent.

**Caution — anemic domain drift:** layers invite a failure mode where Application "services" do all the work by calling getters on hollow Domain objects. The structure can't prevent it; review can. Behavior that reads or enforces an entity's invariants belongs *on the entity*.

## Worked Example: Cache

| Piece | Location | Why |
|---|---|---|
| `Psr\SimpleCache\CacheInterface` (PSR-16) | vendor | The contract modules type-hint |
| `$cache->get(...)` / `$cache->set(...)` calls | Modules | Using the contract |
| Cache configuration: implementation choice, Redis backend, TTL defaults | Bootstrap | Implementation decision |
| Per-module key prefixes / pools | Bootstrap (contextual binding) | Edge decides; module stays ignorant |

## Worked Example: Clock

| Piece | Location | Why |
|---|---|---|
| `Clock` | `App\Kernel\Clock` | Shared contract (or `psr/clock`) |
| `SystemClock` | `App\Kernel\Clock` | Tiny and stable; its wall-clock read is why the contract exists |
| `FrozenClock` | `Tests\...` (autoload-dev) | Test double — no production consumer, so no place in `src/`, let alone Kernel |
| Binding interface → implementation | Bootstrap | Wiring |

## Worked Example: Tax (capability module)

Callers push: `calculate(LineItems $items, Jurisdiction $j): TaxBreakdown` — all Kernel value objects. Tax never fetches from any domain.

| Piece | Location | Why |
|---|---|---|
| `TaxCalculator`, `LineItems`, `TaxBreakdown` | `App\Kernel\Tax` | Contract other modules type-hint, plus its value objects |
| Rate rules, exemption logic, rounding policy | `App\Tax\Domain` | Genuine evolving business logic — pure, heavily unit-tested |
| `CalculateTax` use case | `App\Tax\Application` | Orchestrates: load rules, apply, return breakdown |
| `Tax\Domain\TaxRateRepository` | `App\Tax\Domain` | Internal interface — no other module needs it |
| `PdoTaxRateRepository` | `App\Tax\Infrastructure` | Rates live in tables Tax owns |
| Binding | Bootstrap | Wiring |

## Enforcement with PHPat

PHPat runs as PHPStan rules — one toolchain, fails CI on violations. Each rule's `->because()` message names the doc section that resolves it, so an agent (or human) hitting a violation is routed to guidance at exactly the moment it's needed, with zero standing context cost.

**Authoritative rule set:** [`tests/Architecture.php`](../tests/Architecture.php).

### Fully enforceable

Covered by `Architecture.php` (module boundaries, Kernel as leaf, Bootstrap invisible, no container outside Bootstrap, layer placement and dependencies, Infrastructure invisibility, PDO only in Infrastructure, vendor deny-by-default with allowlists, Kernel `final`/`readonly`, `src` never references `Tests\`).

Unit tests live in `tests/` mirroring the module structure
(`tests/ModuleName/...`); the architecture rules apply to `src/` production code
only, so tests may freely use PHPUnit and test-only helpers. `tests/Architecture.php` itself lives there too, as do
any integration test helpers.


### Approximable

- **"Kernel = contracts only"**: largely enforced. Assert every Kernel class is an interface, enum, or `final readonly` class (`shouldBeFinal`, `shouldBeReadonly`); combined with `testKernelIsLeaf`, Kernel is forced to be immutable, stateless, and free of in-app class dependencies — which is the real invariant (value objects like `Money::add()` legitimately contain logic). The residual gap is the first item under "Not enforceable."
- **Orphan detection**: solved by dynamic discovery — any new top-level directory under `src/` automatically becomes a module and inherits the full rule set. Nothing under `App\` can exist uncovered.
- **Layer placement**: `testModuleClassesLiveInLayers` requires every module class under one of the four layer namespaces. Which *conceptual* layer a class belongs in (e.g. anemic Domain vs. Application) remains a review judgment.

### Not enforceable — culture and review

- Whether pure logic in Kernel is substantial enough to deserve its own capability module (the case 1 vs. case 3 boundary in principle 6) — a judgment call for review
- Kernel sub-namespace *ownership* (naming shape ≠ ownership truth)
- String-based coupling invisible to static analysis: string service IDs, shared session keys, shared DB tables, event-name strings. Two modules writing the same table is a Module → Module dependency no tool sees.
- "No module-per-helper-class"
- Domain purity for PHP functions (`file_get_contents`, `random_int`, `new DateTimeImmutable('now')`) — PHPat sees class dependencies only
- Kernel event payloads: typed properties only — reject `array`/`mixed` (see Failure Modes)

**Corollary:** contracts-as-classes are what make boundaries visible to tooling. Prefer typed events and interfaces over string identifiers everywhere — it's not just cleaner, it's what keeps the architecture checkable.

### Maintenance

- **No module registry.** Modules are discovered from `src/` at analysis time (see orphan detection above). Tradeoff accepted: no deliberate-registration friction, so watch for module sprawl in review.
- **Vendor concretes are deny-by-default everywhere outside Bootstrap.** Presentation, Application, and Domain may reference vendor interfaces and the listed PHP/vendor value types in `vendorContractsAndValueTypes()` — nothing else (no global-namespace catch-all); Infrastructure adds the wrapped-packages allowlist for the concretes its adapters wrap (`PDO`, Guzzle, …); Kernel gets contracts and value types only. Adopting a new value-type package or PHP value class fails CI until it is allowlisted, deliberately: classification is forced, loudly, at adoption time. A package Bootstrap binds as-is needs no entry anywhere — absent from both allowlists, it is denied outside Bootstrap automatically.

## Adopting the Architecture

Two entry paths, each with its own doc — read the one matching your situation, not both:

- **Greenfield** → `docs/architecture/greenfield.md`: repo scaffold, day-one order of operations.
- **Existing codebase** → `docs/architecture/migration.md`: the ratchet procedure. Its governing principle: this document describes the *destination* and full conformance is the goal, but conformance work is deliberate, scheduled, and separately reviewed — never a side effect of other changes.

## Common Violations → Fixes

| Violation | Fix |
|---|---|
| `Billing\InvoiceService` imports `Orders\Order` | Kernel value object (`OrderId`) if only the reference is needed; Kernel interface implemented by Orders if behavior is needed |
| Orders wants Billing to create an invoice when an order is placed | Orders dispatches Kernel event `OrderPlaced`; Billing listens. Orders never calls Billing. |
| A class in Kernel with mutable state or I/O | Move to a capability module; leave an interface in Kernel if multiple modules consume it |
| Module constructor takes `ContainerInterface` | Inject the actual dependencies; delete the container parameter |
| `$this->events->dispatch('order.placed', $data)` | Typed Kernel event class — string names are invisible to tooling |
| Module instantiates `new GuzzleHttp\Client(...)` | Type-hint PSR-18 `ClientInterface`; Bootstrap configures and binds the client |
| Two modules read/write the same DB table | One module owns the table; the other goes through a Kernel contract the owner implements |
| `Billing\Domain\Invoice` runs a query / takes `PDO` | Domain defines `InvoiceRepository`; `Billing\Infrastructure\PdoInvoiceRepository` implements it with injected `PDO` |
| `Billing\Application\CreateInvoice` news up `Infrastructure\PdoInvoiceRepository` | Type-hint the Domain interface; Bootstrap binds the Infrastructure implementation |
| `Billing\InvoiceService` (class not under a layer namespace) | Move into `Domain`, `Application`, `Presentation`, or `Infrastructure` |
| Use case throws an exception for an expected miss ("post not found") | Add `PostNotFound` to the outcome union; exceptions are for the exceptional (see Outcomes) |
| Outcome class carries a domain entity or a user-facing message | Primitives/DTOs and semantic codes only; the driving adapter phrases and maps |
| Kernel interface with exactly one consuming module | Demote to a module-internal interface (principle 8) |

## Code Review Checklist

PHPat catches structural violations automatically; this checklist is the layer above it — judgment calls and tooling-invisible coupling. For any diff, check what's new:

- **New class in Kernel?** Must be an interface, enum, or `final readonly` value object (PHPat enforces shape). Human judgment: is this pure logic big enough to be a capability module (principle 6)?
- **New cross-module interaction?** Must go through a typed Kernel contract — reject string service IDs, string event names, or reading another module's tables/session keys (invisible to tooling; review is the only gate).
- **New vendor usage in a module?** Value type → concrete is fine. Service/capability → interface only (PSR → package's own → Kernel-defined). Vendor types must not appear in Kernel interface signatures.
- **New top-level directory under `src/`?** It's automatically a module — is it a real capability cluster or a helper class that belongs in Kernel or an existing module?
- **New interface in Kernel?** Does a *second* module actually consume it? If not, it should be module-internal (principle 8).
- **Anything touching the container outside Bootstrap?** Reject — constructor injection only.
- **Ambiguous placement debated in review?** Default: module now, promote later (principle 8).
- **New module class?** Must live under one of the four layer namespaces (PHPat enforces placement). Human judgment: is it in the *right* layer, and is Domain accumulating hollow getters while Application does the work (anemic drift)? Behavior enforcing an entity's invariants belongs on the entity. And is Domain calling I/O or nondeterministic PHP functions (`file_get_contents`, `new DateTimeImmutable('now')`)? Inject Kernel contracts instead — PHPat can't see function calls, so review is this gate.
- **New or changed Kernel event?** Typed properties only — reject `array`/`mixed` payloads (the escape hatch that defeats enforcement; see Failure Modes).
- **PHPStan baseline touched?** (Migrating codebases only.) The count may only shrink — reject additions.

## Failure Modes & Mitigations

Known pressure points, each with its decision or mitigation. None changes the core structure; two required decisions are marked.

### Cross-module reporting (decision: sanctioned exception)
"One module owns each table" breaks down at reports joining orders, invoices, and shipments — N+1 across Kernel contracts is not an answer. **Decision:** a read-only reporting path (a Reporting capability module or Bootstrap-level query layer) may join across any tables. Fence it: a separate DB connection with read-only credentials, so the exception is structurally incapable of becoming write coupling. Writes remain owned; this exception is explicit, not a violation.

### Event transaction semantics (decision: synchronous by default)
Does a listener run inside the emitter's transaction? Left undefined, every developer assumes differently. **Decision:** events are synchronous, in-process, and transactional by default — a listener failure rolls back the emitting transaction. This is honest coupling for one process on one database. A listener that must not fail the emitter's transaction defers explicitly (queue/job), accepting eventual consistency deliberately, per listener, not by accident. (Mechanics — who opens the transaction and how — in "Inside a Module: Layers".)

### Capability modules as dependency magnets
A Notifications module "just" needs to look up customer emails, then invoice details, then... and Kernel accumulates a shadow domain model of ever-fatter DTOs. To be clear: a capability module is the *right* home for Notifications — the risk isn't the placement, it's the contract shape. Shaped as pull ("give me a way to look up the user"), it becomes the archetypal magnet; shaped as push, it's as clean as any module. **Mitigation — callers push, capabilities never pull:** capability contracts take data as parameters. Billing assembles the invoice-email data it already owns and passes it to `Notifier`; Notifications never learns any domain. (Compare the Tax worked example, whose contract couldn't be shaped as pull even by accident.) A quick smell test: if a capability contract needs a cross-domain DTO — a Kernel type gluing together fields from multiple modules (shipping address from Orders + invoice lines from Billing + locale from Settings) — the contract is shaped wrong. No single caller owns all that data, so someone must aggregate across modules to build it, meaning the pull just moved from the capability to its callers or to Kernel. Correctly push-shaped contracts take parameters each caller can fill from data it already owns; if a parameter can't be filled that way, the capability is implicitly demanding a fetch.

### The payload-array escape hatch
Under deadline pressure, `array $payload` on a Kernel event is string coupling with extra steps — untyped, invisible to PHPat. **Mitigation:** review gate (see checklist): Kernel events carry typed properties only, no `array`/`mixed` payloads. Adding a typed property is the cheap path; keep it cheaper than the workaround.

### Event indirection vs. traceability
"Neither module knows the other exists" makes causal chains hard to follow — a direct call is one ctrl-click, an event is a search. Partially mitigable, not eliminable: listeners must be order-independent (if ordering matters, one listener orchestrates the steps); registration in Bootstrap is grouped by event so the fan-out is readable in one place. The residual cost is the price of decoupling — pay it only where decoupling earns it (principle 8).

### Ceremony vs. team size
The benefits are chiefly organizational — parallel work, ownership, blast radius. **Mitigation — granularity is the dial (principle 9):** two or three coarse modules is a fully valid instantiation with near-zero ceremony; module count grows with the team, not with ambition.

### Enforcement blind spots
Beyond the known string coupling: templates, raw SQL (table and column names are strings), serialization groups, and config arrays all reference code and schema in ways PHPStan can't see. **Mitigation:** prefer `::class` constants and PHP attributes over string references in config; for SQL, the data-ownership review check is the gate — a module's Infrastructure may only name its own tables (plus the fenced reporting exception).

### `final readonly` vs. the ecosystem
Occasional library friction (some hydrators/serializers fight readonly). Narrower still with pure-PDO persistence: hydration is code you write in Infrastructure repositories (named constructors, `fromRow()` mappers), so nothing fights the shape. Anything IO-shaped in Kernel sits behind an interface, so test doubles implement the interface — mocking final classes never comes up. If a specific library genuinely fights a Kernel value object's shape, drop the modifier for that class with a documented inline exception — the invariant is conceptual immutability; enforcement is best-effort, not dogma.

### Extraction cost (honest framing)
A module's class-dependency surface being exactly Kernel is *necessary but not sufficient* for extraction to a service: the shared database must split, synchronous transactional events must become a message bus with delivery semantics, and Kernel becomes a versioned shared library. The clean surface is real; it's perhaps a third of extraction cost.

## Health Signals

- ✅ Kernel holds only contracts (plus trivial implementations) — growth in contracts *each backed by a second consumer* is fine; speculative contracts or behavior/logic accumulating there mean something is misplaced
- ✅ Extracting a module = its class-dependency surface is exactly Kernel (necessary for extraction, not sufficient — see Failure Modes)
- ⚠️ A module imports the container → service-locator smell
- ⚠️ Kernel references a module class → cycle in spirit; move it to Bootstrap
