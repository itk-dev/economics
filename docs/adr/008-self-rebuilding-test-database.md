# 008: The test bootstrap rebuilds the database; no DAMADoctrineTestBundle

| Field | Value |
| --- | --- |
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-08-25 |
| **Decision Maker** | ITK Dev Economics team |
| **Stakeholders** | ITK Dev Economics team |
| **Status** | Accepted |

## Context

Most of what is worth testing here touches the database. Invoicing, project billing and the report
suite are aggregations over synchronised data, and a test that mocks the repository away tests the
mock. So the integration suite needs a populated database, and it needs one in a known state.

The usual Symfony answer is DAMADoctrineTestBundle: wrap every test in a transaction and roll it back
afterwards, so each test starts from the fixture state regardless of what the previous one did. That
bundle is **not** installed here, and the substitute is a bootstrap script that rebuilds the database
once per run. Nothing else sets the test database up — no separate script, no CI step, no make
target.

That is surprising enough, and consequential enough, that it needs recording rather than discovering.

### Drivers

- **Functional:** the integration suite needs migrations applied and `AppFixtures` loaded.
- **Functional:** running the suite must require no manual setup, locally or in CI.
- **Non-functional:** the unit suite must not need a database at all, so it stays fast.
- **Non-functional:** migrations are themselves under test — running them from empty on every suite
  execution catches a broken migration chain immediately.

### Options Considered

1. **DAMADoctrineTestBundle.** Per-test transaction rollback, so tests are isolated by construction
   and cross-test coupling is impossible. Costs a dependency, and puts every test inside a transaction
   — which changes behaviour for anything that manages transactions itself or relies on
   autocommit-visible state.
2. **Rebuild once per run; each test cleans up after itself.** No dependency and no transaction
   wrapper, so tests see the database as production code does. Isolation becomes a convention rather
   than a guarantee.
3. **Rebuild per test class.** A middle ground with much better isolation. Drop-create-migrate-load
   per class makes the suite far slower, on a codebase with 50-plus migrations.

## Decision

Option 2. `tests/bootstrap.php` — the bootstrap named by `phpunit.xml.dist` — does the whole setup on
every run, in order:

1. `cache:clear`
2. `doctrine:database:drop --force --if-exists`
3. `doctrine:database:create`
4. `doctrine:migrations:migrate`
5. `doctrine:fixtures:load`

There is **no DAMADoctrineTestBundle**, so tests are not wrapped in transactions and are not isolated
from each other. **A test that writes must clean up after itself.** This is the rule that makes the
choice workable, and the one thing to remember when adding a test.

`phpunit.xml.dist` defines two suites:

- **`unit`** over `tests/Unit` (`Command`, `Enum`, `EventListener`, `MessageHandler`, `Service`,
  `Twig`). `composer tests-unit` runs it against the database-free `tests/bootstrap_unit.php`.
- **`integration`** over `tests/Integration` (`Controller`, `Repository`, `Service`), which needs the
  full bootstrap above.

Fixture users for each role — `admin@test.local` and friends — and the login helpers live in
`tests/Integration/Controller/AbstractControllerTestCase.php`. Coverage is gated in CI at **62**
(`vendor/bin/coverage-check coverage/clover.xml 62`, `.github/workflows/pr.yml`).

## Consequences

### Positive

- `task test` is the whole contract; there is no setup step to document or forget.
- Migrations are exercised from empty on every run, so a broken chain fails the suite rather than a
  deploy.
- Tests observe the database exactly as production code does, with no ambient transaction changing
  flush and visibility behaviour.
- The unit suite is genuinely database-free and can run on its own, fast, via `composer tests-unit`.

### Negative / Trade-offs

- **Isolation is a convention, and conventions get broken.** A test that writes and does not clean up
  leaves state for whatever runs next; the resulting failure surfaces in the *other* test, which is
  the most expensive kind of failure to diagnose.
- The suite is order-dependent in principle, so it cannot be parallelised as it stands, and a failure
  may not reproduce when the test is run alone.
- Every run pays a full drop-create-migrate-load, which grows with the migration count.
- The bootstrap shells out with `passthru` and does not check exit codes, so a failed migration shows
  up as confusing test failures rather than as a setup error.

### Follow-up Actions

- [ ] Revisit DAMADoctrineTestBundle. `README.md` already claims it is in use — "Between each test the
      initial state of the database is restored using DAMADoctrineTestBundle" — which is stale, and
      either the bundle or the sentence should go.
- [ ] Check `passthru` exit codes in `tests/bootstrap.php` and fail loudly on a setup error.
- [ ] Correct the stale parts of `README.md` more broadly: it still documents Jira environment
      variables and pre-`task` docker commands. Out of scope here, but it should not be inherited as
      fact.
