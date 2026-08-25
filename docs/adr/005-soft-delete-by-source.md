# 005: Soft-delete-by-source, tracked separately from the entity's own deletion

| Field | Value |
|-------|-------|
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-08-25 |
| **Decision Maker** | ITK Dev Economics team |
| **Stakeholders** | ITK Dev Economics team |
| **Status** | Accepted |

## Context

Economics holds a synchronised copy of tracker data, and that data can disappear upstream. A worklog
is deleted in the tracker, a ticket is removed, a project is archived away — and Economics has to
decide what to do with its copy.

The naive answer, deleting the local row, is not available. A worklog may already be bound to an
`InvoiceEntry`, and an issue may hold worklogs that are. An invoice that has been sent is a financial
record; the fact that its underlying worklog was later deleted at the source does not unmake it.

So there are two distinct facts about any synchronised row — whether it still exists at the source,
and whether it still exists in Economics — and they can differ.

### Drivers

- **Functional:** a row referenced by an invoice must survive its source disappearing.
- **Functional:** a row that no longer exists at the source must be recognisable as such, so it is not
  offered up for new invoicing.
- **Functional:** re-reading unchanged rows every hour must be cheap.
- **Non-functional:** the deletion state must be a column, queryable in DQL, not derived at runtime.

### Options Considered

1. **Hard delete on source deletion.** Simplest and keeps the copy honest. Breaks referential
   integrity with invoice entries, and destroys financial history.
2. **Reuse Gedmo `softdeleteable` for source deletions.** The bundle is already installed and its
   filter is enabled globally ([006](006-doctrine-orm-2-and-copenhagen-datetimes.md)), so rows would
   disappear from queries automatically. Conflates "deleted at the source" with "deleted in
   Economics" — and because the filter is global, an invoice would lose the worklogs it was built
   from.
3. **A distinct `sourceDeletedDate`, separate from the entity's own soft delete.** Three explicit
   columns on every synchronised entity, filtered by application code where it matters. More
   verbose, and nothing hides those rows automatically.

## Decision

Option 3. `App\Entity\Trait\SynchronizedEntityTrait` adds three nullable datetime columns to every
synchronised entity:

- `fetchDate` — when Economics last read this row.
- `sourceModifiedDate` — the source's own modification timestamp.
- `sourceDeletedDate` — when the source reported it deleted.

### Upserts skip unchanged rows

`DataProviderService` compares the incoming `sourceModifiedDate` against the stored one and returns
early when the timestamps match, logging the skip. Passing `$disableModifiedAtCheck` forces the write
through — used when the local shape has changed and a re-read is needed even though the source has
not moved. New entities never take this path; only updates do.

### Deletion is attempted, then recorded

On a source deletion, `DataProviderService` tries a hard delete and falls back to the mark:

- a **worklog** is removed unless it is bound to an `InvoiceEntry`;
- an **issue** is removed unless it still has worklogs;
- a **project** is removed unless it still has children.

When removal is refused, `sourceDeletedDate` is set instead. **Nothing revisits that mark.** A row
already carrying it returns early, so a parent reached before its children — which the deletion
ordering in [003](003-messenger-paged-synchronization.md) exists to prevent — stays half-deleted for
good.

## Consequences

### Positive

- Invoices keep the worklogs they were built from, whatever happens upstream.
- "Gone at the source" is a queryable column, so reports and invoicing can exclude those rows
  deliberately rather than by accident.
- The `sourceModifiedDate` check makes an hourly full sync cheap: unchanged rows cost a comparison and
  a log line, not a write.
- `fetchDate` makes sync staleness visible per row.

### Negative / Trade-offs

- No filter hides source-deleted rows. Every query that should exclude them must say so, and a new
  query that forgets will include deleted data silently. This is the main hazard of the decision.
- A row marked `sourceDeletedDate` is final: if the blocker is later removed — the invoice entry
  deleted, the worklogs detached — nothing retries the hard delete, and the row lingers.
- The mark depends on deletion ordering holding. Correctness here is coupled to a transport choice in
  another part of the system.
- Two soft-delete mechanisms coexist on the same entities: these columns and Gedmo's global
  `softdeleteable` filter. They mean different things and are easily confused.

### Follow-up Actions

- [ ] Consider a reaper that retries the hard delete for rows carrying `sourceDeletedDate` whose
      blocking references are gone.
- [ ] Audit report and invoicing queries for ones that should exclude `sourceDeletedDate` rows and do
      not.
