# 006: Doctrine ORM 2, Copenhagen-local datetimes, and a global soft-delete filter

| Field | Value |
| --- | --- |
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-08-25 |
| **Decision Maker** | ITK Dev Economics team |
| **Stakeholders** | ITK Dev Economics team |
| **Status** | Accepted |

## Context

Economics is an hour-accounting system for a single Danish municipality. Almost every number it
produces is a sum of worklog hours bucketed by a local calendar period — a week, a month, a quarter.
Which bucket an hour falls in is decided by its timestamp, so the timezone the persistence layer
works in is not a display concern; it decides the totals on an invoice.

Alongside that, the persistence layer carries several project-wide settings that are easy to trip
over: the ORM major version is pinned, a Doctrine filter is on globally, and one table is hidden from
schema management altogether.

### Drivers

- **Functional:** hour reports are defined in Danish local calendar weeks and months; the boundary
  between two weeks must be unambiguous.
- **Functional:** Messenger's own table must not appear in generated migrations or fail schema
  validation.
- **Non-functional:** date bucketing must be expressible in DQL, so aggregation happens in the
  database rather than in PHP.
- **Non-functional:** the ORM version must be stable — ORM 3 changes idioms the codebase relies on
  throughout.

### Options Considered

1. **Store UTC, convert at the presentation layer.** The portable answer, and the right one for a
   multi-timezone application. Means every aggregation either converts in SQL or buckets in PHP, and
   every new report is one more place a week boundary can slip by an hour.
2. **Remap the DBAL `datetime` types so every datetime is Copenhagen-local end to end.** One
   configuration line per type, and every layer — database, DQL, PHP, Twig — agrees without
   conversion. Forecloses multi-timezone use.

## Decision

Option 2, plus the surrounding persistence settings, all in `config/packages/doctrine.yaml`.

### Copenhagen-local datetimes

```yaml
types:
    datetime: App\Doctrine\Extensions\DBAL\Types\EuropeCopenhagenDateTimeType
    datetime_immutable: App\Doctrine\Extensions\DBAL\Types\EuropeCopenhagenDateTimeImmutableType
```

Both custom types live in `src/Doctrine/Extensions/DBAL/Types`. Because the built-in type **names**
are remapped rather than new names added, this applies to every entity field in the codebase — no
per-field annotation, and no way to opt one out by accident. A UTC round-trip is removed from the
path between a worklog's timestamp and the week it is counted in.

MySQL date functions are registered for DQL so the bucketing happens in the database:

```yaml
dql:
    datetime_functions:
        month: DoctrineExtensions\Query\Mysql\Month
        week: DoctrineExtensions\Query\Mysql\Week
        weekofyear: DoctrineExtensions\Query\Mysql\WeekOfYear
        year: DoctrineExtensions\Query\Mysql\Year
```

### Doctrine ORM 2, pinned

`doctrine/orm` is pinned at `^2.13`. **ORM 3 idioms will not work** — this is the single most common
source of code suggested against this project that does not run.

### Gedmo `softdeleteable` enabled globally

The filter is enabled for every query rather than switched on per request. Note that this is a
*different* mechanism from the `sourceDeletedDate` column in
[005](005-soft-delete-by-source.md): this one hides rows Economics deleted; that one records rows the
tracker deleted, and hides nothing.

### `messenger_messages` excluded from the schema

```yaml
schema_filter: ~^(?!messenger_messages$)~
```

The Doctrine transport manages its own table. Without the filter, every `doctrine:schema:validate`
and every generated migration would try to reconcile it.

Entities extend `AbstractBaseEntity`, which brings Blameable and Timestampable, so `createdAt` /
`updatedAt` / `createdBy` are not restated per entity.

## Consequences

### Positive

- One timezone from column to template. A week boundary is decided once, by the database, in the only
  timezone the business uses.
- Report aggregation is DQL, not PHP, so hour reports scale with the database rather than with memory.
- The remap is impossible to forget on a new entity, because it changes the meaning of the standard
  type names.
- Schema validation stays green in CI without excluding the Messenger transport by hand.

### Negative / Trade-offs

- **The application is not multi-timezone-ready.** Serving a second municipality in another timezone
  means unwinding this decision and every report built on it.
- `datetime` no longer means what the Doctrine documentation says it means. A developer reading an
  entity sees a standard type and gets a custom one.
- `schema_filter` means Doctrine schema validation has a blind spot; a real drift in
  `messenger_messages` will not be reported.
- The ORM 2 pin is standing upgrade debt, and it grows as the ecosystem moves to ORM 3.
- A globally enabled filter surprises anyone writing a query that legitimately needs deleted rows;
  it must be disabled explicitly.

### Follow-up Actions

- [ ] Plan the ORM 3 upgrade as its own piece of work, with a superseding ADR.
- [ ] Set `server_version` in `doctrine.yaml` or `DATABASE_URL` — it is still commented out, so
      Doctrine infers it from a connection.
