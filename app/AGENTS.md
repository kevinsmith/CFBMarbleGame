# App — PHP application conventions

Nested rules for the PHP app. The repo root `AGENTS.md` still applies; this file adds what is specific to `app/`.

## Architecture (modular monolith — enforced)

If this section conflicts with `tests/Architecture.php`, that file wins.

Three concepts:

- `App\Kernel` — shared contracts only: interfaces, cross-module events, value objects. Concrete classes are
  `final readonly`.
- Modules (e.g. `App\ModuleName`...) — vertical business or capability slices. Every top-level directory under
  `src/` except Kernel and Bootstrap is a module, automatically. Each module has four internal layers:
  `Presentation` → `Application` → `Domain` (dependencies point inward; Domain is pure — no I/O), plus
  `Infrastructure` (PDO repositories, adapters — depends on Domain/Application, never Presentation; visible only to
  Bootstrap bindings, never to the rest of the module).
- `App\Bootstrap` — composition root. ALL wiring: container config, bindings, route definitions, HTTP edge, `PDO`
  construction.

**PHPat (CI):** structural dependency and placement rules live only in `tests/Architecture.php`. Run
`make phpstan` before committing; a PHPat failure message states the rule that was broken.
Don't add entries to `phpstan-baseline.neon`; fix new violations instead. Don't restructure existing code to conform
unless that's the task.

**Review gates** (not PHPat — apply in code review):

- Cross-module communication goes through typed Kernel contracts only — no string event names, no reading another
  module's tables or session keys.
- Ambiguous placement? Put it in the module; promote to Kernel only when a second module needs it.
- Kernel events carry typed properties only — reject `array`/`mixed` payloads.
- Domain purity for PHP functions PHPat can't see (`file_get_contents`, `random_int`, `new DateTimeImmutable('now')`) —
  inject Kernel contracts instead.
- Right layer for the class (PHPat enforces the namespace exists under a layer, not that the class belongs there
  conceptually). Behavior enforcing an entity's invariants belongs on the entity (avoid anemic Domain).

## Unit vs integration tests

The split is by **external infrastructure**, not by whether a test touches I/O.

- Unit suite (`make tests-unit`): everything except the `integration` group. Isolated
  built-in state is a unit-test concern — a test
  that starts a real PHP session under a unique session id is a unit test.
- Integration suite (run as part of `make tests`): tests tagged `#[Group('integration')]`
  against the real database. Real database, network,
  or mail dependencies belong here.

## Test helpers

- Fixtures and other test helpers are colocated as close as possible to the tests that use
  them, not gathered in a dedicated namespace: keep a helper at the nearest common root of the
  tests that use it, and beside the test file itself when only one uses it.


## Code comments

- Write all comments in ASD-STE100 Simplified Technical English: short
  declarative sentences, active voice, imperative instructions, and one
  meaning per word. Do not use jargon, idioms, or ambiguous wording.


## Injection style

- Container-managed dependencies are **constructor-injected**: request-scoped
  singletons and stable services.
- Values that vary per invocation are **method parameters**.
- Don't thread per-call values through constructors, and don't make call sites
  obtain and forward singletons; the container wires stable services once.
