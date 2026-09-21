# 002: Data provider credentials live in the database

| Field | Value |
| --- | --- |
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-08-25 |
| **Decision Maker** | ITK Dev Economics team |
| **Stakeholders** | ITK Dev Economics team |
| **Status** | Accepted |

## Context

A data provider needs a base URL and an API token. The Symfony default is to put both in the
environment and to configure a scoped HTTP client against them, so that every request to that host
carries the right headers automatically.

That does not work here, because a scoped client keys on `base_uri`, and Economics does not know its
base URIs at container-build time: they are rows in a table. The consequence shows up as a hand-built
client in `config/services.yaml`, which reads like an oversight unless the reason is written down.

### Drivers

- **Functional:** more than one provider must be configurable, and each enabled or disabled
  independently, by an administrator rather than by a deploy.
- **Functional:** a token has to be replaceable when it is rotated at the tracker, without a release.
- **Non-functional:** one request must never hold a Messenger worker indefinitely.
- **Non-functional:** credentials should stay out of the codebase and out of version control.

### Options Considered

1. **Environment variables plus a scoped HTTP client.** The framework default. Gives per-host
   defaults, headers and retry configuration for free, and puts the token in the usual place for a
   secret. Fixes the set of providers at deploy time, and makes a second Leantime instance a
   configuration release.
2. **Database rows plus one hand-built client.** A `DataProvider` row holds the URL and token; a
   single client is defined with timeout options only, and the URL and token are applied per request
   from the row. Providers become runtime data. Loses the scoped-client conveniences.
3. **Database rows plus a scoped client built per provider at runtime.** Keeps the scoped-client
   behaviour by constructing one client per row on boot. Means a service graph that depends on the
   database being readable before the container is usable, and a cache invalidation problem whenever a
   row changes.

## Decision

Option 2. Credentials live in the `DataProvider` entity, and providers are created and managed
through the console — `app:data-provider:create`, `app:data-provider:list`,
`app:data-provider:set-enable` — or through the admin interface.

Because the base URI is only known at runtime, `app.leantime.http_client`
(`config/services.yaml`) is hand-built from `@http_client` with `withOptions`, carrying nothing but
the two timeout bounds, and is injected into `LeantimeApiService` as `$httpClient`:

```yaml
app.leantime.http_client:
    class: Symfony\Contracts\HttpClient\HttpClientInterface
    factory: ["@http_client", withOptions]
    arguments:
        - { timeout: 5, max_duration: 30 }
```

Both numbers are deliberate. `timeout: 5` is the idle gap between response chunks, not the whole
request, which is generous for an API that answers in milliseconds. `max_duration: 30` bounds one
page; Symfony caps neither by default, and an uncapped request holds a worker forever, because
`messenger:consume` only checks its `--time-limit` between messages and never during one.

**If a page ever needs longer than 30s, lower `LeantimeApiService::LIMIT` instead of raising the
cap.** The cap is what guarantees a worker comes back; the page size is the adjustable part. See
[003](003-messenger-paged-synchronization.md).

### The stored URL is normalized where it is read

Because the URL is data rather than configuration, nothing validates its shape on the way in, and a
provider stored as `https://leantime.example.com/` is as legitimate a row as one stored without the
trailing slash. `LeantimeApiService::API_PATH_DATA` carries its own leading slash, so that row was
asked for `//APIData/API/projects`, and the deep links written onto issues and projects came out as
`//errorpage/…` and `//projects/showProject/…`.

The slash is stripped where the URL is **read**, not where it is written. `LeantimeUrlGenerator::baseUrl()`
— already used by the `leantime_url` Twig function and by `ProjectRepository` — is injected into
`LeantimeApiService` and applied at each of the three places it concatenates the URL.

Normalizing on read is what covers the rows already stored with a trailing slash. A setter or a form
constraint would only cover rows written after it, because Doctrine hydrates properties directly and
never calls the setter when loading an entity.

## Consequences

### Positive

- Providers are runtime data: an administrator can add a tracker instance, rotate a token, or disable
  a provider without a deploy.
- Several providers are synchronised in the same run, each with its own credentials — the sync methods
  iterate `getEnabledLeantimeDataProviders()`.
- No credential is in the repository or in a compiled container.
- The timeout pair gives a hard bound on how long one message can occupy a worker.

### Negative / Trade-offs

- Tokens sit in application table rows, outside whatever secret management the platform offers, and
  are readable by anyone with database access or the admin role.
- The client forgoes the scoped-client conveniences: per-host default headers, `base_uri` resolution
  and framework-level retry configuration must all be handled in `LeantimeApiService` instead.
- The definition looks unmotivated in `config/services.yaml` without the comment that sits above it —
  which is why this ADR exists.
- URL normalization is applied per call site rather than once at the boundary, so a fourth place that
  concatenates a provider URL can silently forget it and reintroduce the double slash.

### Follow-up Actions

- [ ] Consider encrypting the token column at rest, or moving it behind a secrets backend while
      keeping the URL in the row.
