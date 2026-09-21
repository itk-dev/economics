# 007: Reports go form data in, report data out, through typed DTOs

| Field | Value |
| --- | --- |
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-08-25 |
| **Decision Maker** | ITK Dev Economics team |
| **Stakeholders** | ITK Dev Economics team |
| **Status** | Accepted |

## Context

The report suite is the largest read-only surface in the application: Hour, Workload, Forecast,
InvoicingRate, BillableUnbilledHours, Cybersecurity and Management. Each one takes a filter — a
project, a year, a date range, a view mode — and produces an aggregate that has no entity behind it.
A workload figure per worker per week is not a row in any table; it is a shape that exists only for
the report.

Handing entities to a report template makes that shape implicit. The template starts walking
associations, the query count grows invisibly, and there is nothing for static analysis to check
against, because Twig sees an object graph and PHPStan sees Twig. With seven reports and more likely
to come, the risk is that each one invents its own answer.

### Drivers

- **Functional:** a report's output shape is its own, unrelated to the entity graph it was aggregated
  from.
- **Non-functional:** the shape must be checkable — PHPStan runs at level 8
  ([009](009-phpstan-level-8-with-frozen-baseline.md)), and it can only check what is typed.
- **Non-functional:** report rendering must not trigger lazy loading, so query count stays a property
  of the service, not of the template.
- **Non-functional:** a new report should have an obvious shape to copy rather than a design decision
  to make.

### Options Considered

1. **Pass entities to Twig.** No new classes. Every template becomes a place where associations are
   traversed and queries are emitted, and PHPStan cannot see the contract at all.
2. **Array payloads.** Flexible and quick to write. Array shapes at level 8 mean either elaborate
   `array<string, mixed>` annotations that check nothing, or a growing baseline.
3. **A typed DTO per report, plus leaf DTOs for its rows.** More files. Every field is named and
   typed, the service's return type is the contract, and the template can only read what the DTO
   exposes.

## Decision

Option 3. Reports are a fixed pipeline, and the DTOs are in `src/Model/Reports`:

1. A `*ReportFormData` holds the filter, bound to a form type in `src/Form/*ReportType.php` — for
   example `HourReportFormData` with `HourReportType`.
2. A `*ReportService` in `src/Service` takes the filter values and returns a `*ReportData` —
   `HourReportService::getHourReport()` returns `HourReportData`.
3. The `*ReportData` composes leaf DTOs for its rows: `HourReportProjectTag`,
   `HourReportProjectTicket`, `HourReportWorklog`. View modes and period types are enums in the same
   namespace, e.g. `WorkloadReportViewModeEnum`, `WorkloadReportPeriodTypeEnum`.
4. The template renders the `*ReportData` and nothing else.

Report filter forms are built with `'csrf_protection' => false` and `'method' => 'GET'`, since they
filter rather than mutate — this keeps a report URL shareable and bookmarkable.

DQL stays in `src/Repository`. A report service composes repository queries and maps the results into
DTOs; it does not build queries itself.

### The three DTO families

`src/Model` holds DTOs, never entities, in three families. New code follows the family that matches
rather than inventing a shape:

| Family | Purpose |
| --- | --- |
| `Model/DataProvider/*Data` | Payloads decoded from the tracker during sync |
| `Model/Invoices/*FilterData` | Form-backed filter objects, bound to `src/Form/*FilterType.php` |
| `Model/Reports/*ReportFormData`, `*ReportData` | Report input and output |

## Consequences

### Positive

- A report's output is a typed contract, so level 8 checks the service, and the template can only read
  fields that exist.
- Templates cannot lazy-load. Query count is decided in the service, where it is visible.
- Aggregates are free to have a shape no entity has, which is what a report actually needs.
- A new report has a pattern to copy end to end, and the enum-per-view-mode convention keeps the
  options out of magic strings.

### Negative / Trade-offs

- Many small classes: seven reports contribute over twenty files in `src/Model/Reports` alone.
- Mapping is written by hand, so adding a field means touching the query, the DTO and the template.
- **The pipeline is not uniform at its input end.** `*ReportFormData` is what the form binds to, but
  the controllers pull individual fields off the form and pass them as arguments — the services take
  scalars, entities and enums rather than the form-data object. So the typed filter stops at the
  controller instead of reaching the service.
- `*ReportFormData` properties are non-nullable and unset until bound, which is workable for a form
  but makes them awkward to construct anywhere else.

### Follow-up Actions

- [ ] Consider passing `*ReportFormData` straight into the report services, so the filter is one typed
      object end to end rather than an argument list per report.
