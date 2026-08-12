# Migrating an Existing Codebase

Companion to `docs/architecture/README.md` for bringing an existing codebase under this architecture. For new codebases, use `docs/architecture/greenfield.md` instead.

## The Governing Principle: Converge Deliberately

`docs/architecture/README.md` describes the destination, and full conformance is the goal — eventually all code shares one shape. The discipline is about pace and packaging, not about whether to converge:

- **Conformance work is always its own task, never a rider.** Changing legacy code for a feature or fix does not include restructuring it "while in there" — scope creep disguised as tidiness hides behavior changes inside shape changes and makes the diff unreviewable. Restructure in dedicated, separately reviewed changes.
- **The baseline is a backlog with no deadline.** Frozen violations are real, ranked work — below feature work by default. Promote one when it blocks something, when its area is slated for deliberate cleanup, or when there's slack.
- **New code is held to the full standard from day one.** The rules fully constrain what's written now; legacy converges at backlog pace.
- **No big bangs.** Converge module by module, violation by violation, each move reviewable on its own. A baselined violation sitting for a year is the prioritization working, not a failure.

## The Procedure

Order matters — each step makes the next one checkable:

1. **Install the rules first, baselined.** Add PHPat + the full rule set, then run `docker compose run --rm -T --entrypoint vendor/bin/phpstan web analyse --no-interaction --generate-baseline` to freeze existing violations. New code is fully constrained from day one; legacy debt is visible and burned down incrementally.
2. **Inventory hidden coupling before moving anything.** The static tools can't see it, so list it manually, with the target resolution for each type:
   - Shared DB table → assign one owning module; others access via a Kernel contract the owner implements
   - Shared session keys → replace with a domain-shaped Kernel contract (see the Current User example in docs/architecture/identity-and-access.md)
   - String service IDs → constructor-injected interfaces
   - String event names → typed Kernel event classes
3. **Identify module seams.** Apply the boundary tests (see docs/architecture/README.md, Drawing Module Boundaries); legacy-specific signals: route groups that change together, DB table clusters, code the same people always touch. Aim for a handful of coarse modules first (principle 9).
4. **Establish Bootstrap.** Pull the entry point, container config, routing, and error handling to `App\Bootstrap`. Mostly mechanical; immediately clarifies the edge.
5. **Carve modules one at a time.** Move each slice into its namespace. The boundary rule will light up with cross-module violations — that's the point. Resolve each with this procedure:
   - Is the dependency *notification*? ("X happened, others may care") → typed Kernel event; provider dispatches, consumer listens
   - Is it a *capability request*? ("do this for me") → Kernel interface; provider implements, Bootstrap binds, consumer injects
   - Is it *shared data shape*? → Kernel value object
   - Is it *misplaced cohesion*? (the two pieces always change together) → move the code into one module; the boundary was drawn wrong, not the dependency
6. **Extract Kernel contracts only as step 5 demands them.** Don't design Kernel up front (principle 8). A speculatively built Kernel will be wrong.
7. **Ratchet the baseline to zero.** Track the violation count in CI; fail if it grows, celebrate when it shrinks.

