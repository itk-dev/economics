# 003: Synchronisation is Messenger-paged, and the transport choice is semantic

| Field | Value |
|-------|-------|
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-08-25 |
| **Decision Maker** | ITK Dev Economics team |
| **Stakeholders** | ITK Dev Economics team |
| **Status** | Accepted |

## Context

A full synchronisation reads every project, milestone, ticket and timesheet the tracker holds for the
included projects. That is far more than one HTTP request can carry and far more than one PHP process
should attempt: a single long request has no progress, no partial success, and no way to resume after
a failure halfway through.

The tracker's API answers a page at a time. The question is how those pages are driven, and — less
obviously — what the choice between the `sync` and `async` Messenger transports actually means, since
in this codebase it is not a performance knob but an ordering guarantee.

### Drivers

- **Functional:** a full sync must complete across many requests without any single request being
  long-running.
- **Functional:** deletions must be applied children-before-parents, or a parent removal orphans rows
  that are still being read.
- **Functional:** a failed page must be retried without re-reading the pages that already succeeded.
- **Non-functional:** sync runs hourly by cron, so the whole run must finish comfortably inside an hour.
- **Non-functional:** one message must never hold a worker indefinitely — see
  [002](002-data-provider-credentials-in-database.md).

### Options Considered

1. **One request per entity type.** Simplest to read. Needs an unbounded timeout, gives no partial
   progress, and loses the entire type's work on any failure.
2. **Paged, with all pages queued up front.** Maximum parallelism. Requires knowing the total count
   before starting, and queues work for pages that a failure means should never run. Pages of the same
   type interleave, so ordering is gone.
3. **Paged, with each page dispatching its successor only once it has succeeded.** A self-propagating
   chain: the handler does its work and, at the end, queues the next page. A failure stops the chain at
   the failed page rather than skipping past it.

## Decision

Option 3, with the transport chosen per dispatch, not per application.

`LeantimeApiService::LIMIT = 100`. Each `LeantimeUpdateMessage` / `LeantimeDeleteMessage` handler
reads its page, and only at the end of the handler — after the page's own work is done — dispatches
the successor, when `resultsCount === $limit` indicates a page was full. The cursor is the highest id
seen on the page, not an offset, so pages cannot be skipped or repeated as rows shift underneath.
A full page whose cursor cannot advance stops the chain and logs, rather than re-reading the same page
until the queue starves.

Every dispatch carries an explicit `TransportNamesStamp`, selecting `async` or `sync` from the
`$asyncJobQueue` flag threaded through the whole call chain.

### The transport is an ordering guarantee, not a speed setting

`delete()` fans all four `DELETED_TYPES` out up front, in the order timesheets → tickets →
milestones → projects. That fan-out is only safe because `deleteAll()` passes `$asyncJobQueue` as
`false`: on the `sync` transport every handler runs inline, so a type's every page — removals and
next-page dispatch alike — completes before the next type is dispatched at all. Children are gone
before their parents are touched.

**`deleteAll()` must therefore stay on `sync`.** On the `async` queue the four types would interleave,
and a project could be reached while its timesheets were still a page behind. That path is not a
matter of being slower; it is wrong.

## Consequences

### Positive

- No request is long-running, and no page's failure discards a page that already succeeded.
- Progress is durable: a run interrupted mid-chain has committed everything up to the failed page.
- Deletion ordering is guaranteed by the transport rather than by hope, and the guarantee is one line
  (`$asyncJobQueue` false) rather than a scheduler.
- Page size is the tuning knob for a slow tracker, which keeps the HTTP timeout cap intact.

### Negative / Trade-offs

- A type's pages are strictly sequential, so a full sync is latency-bound on page count × round trip
  rather than parallelised. This is why the retry spacing cannot be widened without bound — see
  [004](004-layered-retry-policy.md).
- The chain is invisible in the code: nothing in `delete()` shows that the four dispatches are
  ordered. The guarantee lives in the transport argument, which is why the reasoning is commented at
  the dispatch site as well as recorded here.
- A page that fails past its retry budget silently ends that type's sync for the run; the next hourly
  run is what heals it.

### Follow-up Actions

- [ ] If `deleteAll()` ever needs the `async` queue, the four types must be **chained** — each
      dispatched only once the previous is exhausted — not fanned out. Do not simply flip the flag.
