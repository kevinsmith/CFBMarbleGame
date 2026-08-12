# Greenfield Setup

Companion to `docs/architecture/README.md` for starting a new codebase on this architecture. For adopting it in an existing codebase, use `docs/architecture/migration.md` instead.

## Scaffold

The application root (`composer.json`, `src/`, `tests/`) is `app/`; paths below are relative to it.

```jsonc
// composer.json
{
  "autoload": { "psr-4": { "App\\": "src/" } },
  "autoload-dev": { "psr-4": { "Tests\\": "tests/" } }
}
```

```neon
# phpstan.neon
parameters:
    level: max
    paths: [src, tests]
services:
    - class: Tests\Architecture
      tags: [phpat.test]
```

- `tests/Architecture.php` — the PHPat rules (see docs/architecture/README.md, Enforcement); its `modules()` scan targets `__DIR__ . '/../src'`
- Module tests mirror module structure: `tests/Billing/...`
- `config/phinx/migrations/` (Phinx migrations; schema is a deployment artifact); each migration touches exactly one module's tables — data ownership applies to schema changes too
- Entry point (`public/index.php`) does nothing but invoke Bootstrap

**Greenfield day one:** create Bootstrap and your first module; run PHPat from the first commit. Don't create Kernel until a second module needs a contract (principle 8) — an empty or speculative Kernel invites junk.

