# 010: Worklog anonymisation scrubs the description and keeps the worker

| Field | Value |
| --- | --- |
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-09-18 |
| **Decision Maker** | ITK Dev Economics team |
| **Stakeholders** | ITK Dev Economics team |
| **Status** | Draft |

## Context

A worklog carries two things that say something about a person: `worker`, the e-mail address of
whoever logged the time, and `description`, free text they typed while logging it. The description
is the one that cannot be predicted. It is written in the middle of casework, and it can name a
citizen, a case number or a system that should not still be readable five years later.

`app:anonymize-worklogs` runs from cron and anonymises everything started more than five years ago.
The question this record settles is what "anonymise" means there, because the command's name implies
more than it does.

Worklogs are not free-standing rows. They are what invoice entries are built from, and they are what
the hour, workload, forecast and invoicing-rate reports aggregate. Whatever the command rewrites,
those two obligations survive it.

### Drivers

- **Functional:** free text older than the retention period must stop being readable.
- **Functional:** historical reports must keep aggregating by person after the scrub. "How much did
  this team bill in 2019" cannot become unanswerable because 2019 was anonymised.
- **Functional:** a sent invoice stays a financial record — see
  [005](005-soft-delete-by-source.md) — so the rows behind it cannot be emptied.
- **Non-functional:** the command runs unattended and repeatedly; a second run over the same rows
  must not corrupt the first run's work.

### Options Considered

1. **Scrub the description only.** Removes the unbounded text, leaves every aggregate intact. The
   worklog still records that this person worked these hours on this day.
2. **Scrub the description and blank the worker.** Closer to what "anonymised" sounds like. Collapses
   every anonymised row onto a single empty worker, so historical per-person and per-team reporting
   silently loses its grouping key — silently, because the reports keep rendering, just wrong.
3. **Scrub the description and pseudonymise the worker.** Replace the address with a stable synthetic
   key, keeping grouping while dropping the identity. Correct in principle, but it needs a mapping
   with its own storage, its own retention question and a migration over existing rows, and the
   reports would need to resolve the key for display.

## Decision

Option 1. `WorklogRepository::anonymizeWorklogs()` issues one bulk update
(`src/Repository/WorklogRepository.php`) that sets exactly two columns on every worklog started
before the cutoff:

- `description` becomes `"worklog " . id` — not blank, so the row still reads as a worklog rather
  than as missing data.
- `anonymizedDate` is stamped with the run time.

The update is guarded by `anonymizedDate IS NULL`, so a row is rewritten once and later runs skip
it. That guard is what makes the command safe to run from cron forever.

`worker` is deliberately untouched. The treatment is minimisation of the free text, not
de-identification of the worklog.

## Consequences

### Positive

- The unbounded field — the one that can carry case detail nobody predicted — is gone after five
  years, which is the actual exposure.
- Reports over historical periods keep working unchanged. Nothing downstream needs to know the
  command exists.
- `anonymizedDate` makes the state queryable and the operation idempotent, so the cron job needs no
  bookkeeping of its own.

### Negative / Trade-offs

- **An anonymised worklog still says who did the work and when.** This is the whole of the
  trade-off, and it is worth stating in those words rather than relying on the column list: anyone
  reading the table can still attribute five-year-old hours to a named employee.
- The command is called `app:anonymize-worklogs`, which oversells what it does. The name will keep
  inviting the assumption that the worker went too.
- Because the scrub is one bulk UPDATE rather than per-entity, it bypasses Doctrine events. Nothing
  currently listens for them on `Worklog`, but a future listener would not fire here.

### Follow-up Actions

- [ ] If a retention requirement ever demands the worker go as well, implement option 3, not option
      2. Blanking the column keeps the reports rendering while quietly emptying them, which is worse
      than either leaving it or replacing it properly.
- [ ] Confirm with the data-protection contact that a five-year-old worklog attributed to a named
      employee is acceptable, and record the answer here.
