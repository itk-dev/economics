# 001: One data provider abstraction, Leantime as the only implementation

| Field | Value |
|-------|-------|
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-08-25 |
| **Decision Maker** | ITK Dev Economics team |
| **Stakeholders** | ITK Dev Economics team |
| **Status** | Accepted |

## Context

Economics owns no source data. Projects, issues, worklogs, accounts and milestones all originate in
an external project tracker, and everything the application does — invoices, project billing,
reports — is derived from that synchronised copy. Which tracker that is has changed once already:
the system was built against Jira and now runs against Leantime.

At the point Leantime replaced Jira there was exactly one implementation left, which raised the
question of whether the abstraction still pays for itself.

### Drivers

- **Functional:** synchronisation must be able to target a different tracker, and more than one
  instance of the same tracker, without the invoicing and reporting code being aware of it.
- **Functional:** the Jira era must remain migratable — existing installations carry Jira-shaped data.
- **Non-functional:** a tracker swap should be a contained change, not a rewrite of the sync layer.
- **Non-functional:** one indirection is acceptable; a plugin framework for a single provider is not.

### Options Considered

1. **Keep the interface with a single implementation.** `App\Interface\DataProviderInterface` stays,
   `LeantimeApiService` implements it, and `DataProviderService::IMPLEMENTATIONS` is the registry.
   Costs one level of indirection that no second implementation currently justifies; buys a seam that
   the Jira removal has already been pushed through once.
2. **Collapse the abstraction and call Leantime directly.** Removes the indirection and the interface
   drift described below. Makes the sync layer, the commands and the admin UI all name Leantime
   explicitly, so the next tracker change touches every one of them.
3. **Keep Jira alive alongside Leantime.** Preserves a real second implementation, and with it the
   proof that the abstraction works. Means maintaining an integration with a tracker no installation
   uses any more, including its custom-field configuration.

## Decision

Option 1. `DataProviderInterface` is retained, `LeantimeApiService` is its only implementation, and
`DataProviderService::IMPLEMENTATIONS` (`src/Service/DataProviderService.php`) is the registry — a
one-entry array today.

The abstraction is kept on evidence rather than on principle: removing Jira was an edit to that
registry plus the deletion of one service, not a change to any consumer of the synchronised data.
The `DataProvider` entity is what makes the seam load-bearing even with one implementation — several
rows can point at different Leantime instances, each enabled or disabled independently, which is a
multiple-provider case in production regardless of how many classes implement the interface.

Jira is retained only as a migration path: `src/Command/MigrateFromJiraEconomicsCommand.php` and
`docs/migration-from-jira-economics.md`. It is not a data provider and does not appear in the
registry.

## Consequences

### Positive

- Invoicing, billing and the report suite consume synchronised entities and never name a tracker.
- Adding or retiring a provider is a registry edit plus one service.
- Several tracker instances can be synchronised at once, and enabled or disabled per row, without a
  deploy — see [002](002-data-provider-credentials-in-database.md).

### Negative / Trade-offs

- An interface with one implementation is an abstraction the compiler cannot check against a second
  shape. It may have drifted towards Leantime's model in ways only a real second provider would
  reveal.
- **Known interface drift, deliberately left alone.** `DataProviderInterface` declares only
  `updateAll()` and `update()`, and neither declaration carries the `$disableModifiedAtCheck`
  argument that `LeantimeApiService` and `SyncCommand` both use. `deleteAll()` is not declared at
  all. The call sites are correct and the declaration is what lags. Do not "fix" this by dropping
  arguments at the call sites — that removes working behaviour to satisfy a stale signature.
- Reading the sync layer means following one more hop than a direct client would.

### Follow-up Actions

- [ ] Widen `DataProviderInterface` to match the implementation: add `$disableModifiedAtCheck` to
      `update()` and `updateAll()`, and declare `deleteAll()`.
- [ ] Reassess this ADR if a second tracker is ever added — that is the point at which the abstraction
      is tested rather than assumed.
