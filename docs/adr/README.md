# Architecture Decision Records

This directory contains Architecture Decision Records (ADRs) for Economics. An ADR captures one
architectural decision together with the context that forced it, the options that were rejected, and
the consequences the project now lives with. See [adr.github.io](https://adr.github.io/) for
background on the practice.

| Number | Title | Status | Date |
|--------|-------|--------|------|
| [001](001-single-data-provider-abstraction.md) | One data provider abstraction, Leantime as the only implementation | Accepted | 2026-08-25 |
| [002](002-data-provider-credentials-in-database.md) | Data provider credentials live in the database | Accepted | 2026-08-25 |
| [003](003-messenger-paged-synchronization.md) | Synchronisation is Messenger-paged, and the transport choice is semantic | Accepted | 2026-08-25 |
| [004](004-layered-retry-policy.md) | Retry policy is split across three layers on purpose | Accepted | 2026-08-25 |
| [005](005-soft-delete-by-source.md) | Soft-delete-by-source, tracked separately from the entity's own deletion | Accepted | 2026-08-25 |
| [006](006-doctrine-orm-2-and-copenhagen-datetimes.md) | Doctrine ORM 2, Copenhagen-local datetimes, and a global soft-delete filter | Accepted | 2026-08-25 |
| [007](007-report-form-data-to-report-data-dtos.md) | Reports go form data in, report data out, through typed DTOs | Accepted | 2026-08-25 |
| [008](008-self-rebuilding-test-database.md) | The test bootstrap rebuilds the database; no DAMADoctrineTestBundle | Accepted | 2026-08-25 |
| [009](009-phpstan-level-8-with-frozen-baseline.md) | PHPStan level 8, with a baseline that is never regenerated | Accepted | 2026-08-25 |

## Writing a new ADR

1. Take the next free number and name the file `NNN-brief-title.md`.
2. Follow the structure of an existing record: metadata table, `## Context` with `### Drivers` and
   `### Options Considered`, `## Decision`, then `## Consequences` split into positive, negative and
   follow-up actions.
3. Start at status `Draft` and add a row to the table above.
4. Move to `Accepted` once the stakeholders have reviewed it.

A decision that replaces an earlier one does not edit that record: the new ADR says
`Supersedes NNN`, the old one becomes `Deprecated by NNN`, and both rows are updated here.

## Status values

| Status | Meaning |
|--------|---------|
| `Draft` | Under discussion, not yet binding |
| `Accepted` | Approved and to be followed |
| `Rejected` | Considered and not adopted |
| `Deprecated by NNN` | Replaced by a later record |
| `Supersedes NNN` | Replaces an earlier record |
