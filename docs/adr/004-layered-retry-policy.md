# 004: Retry policy is split across three layers on purpose

| Field | Value |
|-------|-------|
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-08-25 |
| **Decision Maker** | ITK Dev Economics team |
| **Stakeholders** | ITK Dev Economics team |
| **Status** | Accepted |

## Context

Synchronisation talks to a tracker over HTTP from inside a Messenger worker, so a failure can be
transient (the tracker restarting, a database failover, a rate-limit window) or permanent (a deleted
project, a revoked token, a malformed request). The two need opposite treatment, and there are three
places in the stack where a retry could be configured: the HTTP client, the message handler, and the
transport.

Retry logic spread across three files invites consolidation. This ADR exists to say why it must not
be consolidated: each layer answers a different question, and merging them loses one of the answers.

### Where the 429s came from

Worth recording, because nothing in this repository produced them and the answer is not in the
data-api plugin either. Leantime **core** rate-limits every request, in
`app/Core/Middleware/RequestRateLimiter.php` as of v3.9.7: the API bucket defaults to 100 requests per
60 seconds, keyed on client IP, and the 429 carries `Retry-After` plus three `X-RateLimit-*` headers.
It applies to `/APIData/API/…` because `IncomingRequest::isApiRequest()` lowercases the URI and
prefix-matches `/api`. It is disabled outright when `app.debug` is true, which is why development never
saw it.

**What resolved the 429s was raising `LEAN_RATELIMIT_API` to 10000 on the Leantime side**, not
anything in this repository — a full sync cannot approach that. The retry policy below is therefore
sized for outages in general, not for a rate limit in particular.

### Drivers

- **Functional:** a transient failure must be retried; a permanent one must not consume a retry budget
  it cannot benefit from.
- **Functional:** a failure that is genuinely final must reach the failed transport, where it is
  visible.
- **Non-functional:** retry spacing must outlast a real outage — a tracker restart, a failover, a 60s
  rate-limit window.
- **Non-functional:** retry spacing must **not** outlast the hourly cron interval, because a page
  waiting to be retried is that entity type's whole sync waiting with it
  ([003](003-messenger-paged-synchronization.md)).
- **Non-functional:** a single production worker must not be stopped by a transient error.

### Options Considered

1. **Retry in the HTTP client.** Wrap `app.leantime.http_client` in a `RetryableHttpClient`. Retries
   are invisible to the queue, so a message that eventually succeeds after several attempts looks
   clean, and one that fails has already burned its budget below the layer that tracks it. Also
   multiplies against the transport's own attempts.
2. **Retry only at the transport.** One place to configure, easy to reason about. Spends the full
   budget on a 404 or a 401 — outcomes no number of repeats can change — delaying the failure's
   arrival in the failed transport by minutes.
3. **Classify at the handler, retry at the transport, and never at the client.** Three files, one
   responsibility each. Requires the reasoning to be written down, or a later reader consolidates it.
4. **Throttle instead of retrying.** Wrap the client in a `ThrottlingHttpClient` over a rate limiter,
   so back-pressure is avoided rather than recovered from. Against the 10000/min now configured at the
   tracker it guards nothing, and it pauses *inside* the message handler — so the single worker stops
   draining the queue while it waits. Rejected. It does have the one property the chosen option lacks:
   a transport retry strategy never sees the response, so the ladder cannot read the `Retry-After`
   Leantime sends. That costs nothing here, because the ladder outlasts a 60s window by its third
   attempt anyway.

## Decision

Option 3. The policy lives in three places, deliberately, and is **not** to be consolidated.

### 1. The handler decides whether a failure is worth repeating

`src/MessageHandler/Trait/RethrowsTransientHttpFailuresTrait.php` rethrows only 408, 423, 425 and
429 — the 4xx codes that mean "later" rather than "no" — so the transport's retry strategy picks them
up. Every other 4xx becomes an `UnrecoverableMessageHandlingException`, because the retry budget
cannot change the request. The trait is shared by the handlers that call the tracker directly, so
both agree on the classification.

**For the delete sync this layer is the only cover there is.** `sync-deleted` dispatches on the `sync`
transport, which has no retry strategy at all, and it has to stay there — what keeps `DELETED_TYPES` in
child-before-parent order is the handlers running inline
([003](003-messenger-paged-synchronization.md)). So classification is not merely an optimisation that
saves the transport three wasted attempts; on that path it is the whole policy.

### 2. The transport decides the spacing

`config/packages/messenger.yaml`:

```yaml
retry_strategy:
    max_retries: 3
    delay: 10000
    multiplier: 3
```

Symfony's three attempts are the right number; its default 1s/2s/4s is the wrong spacing — over
before anything transient has had time to end. Widened to 10s/30s/90s, so the last attempt lands 130s
after the first failure, past a tracker restart, a database failover, or a 60s rate-limit window.

Not widened further, and this is the binding constraint: a page only queues its successor once it has
succeeded, so a page waiting to be retried is that entity type's entire sync waiting with it, against
an hourly cron. The spacing applies to every message on the transport, not only the tracker ones —
handlers mark a failure unrecoverable only when it describes the message itself, so anything
transient reaches the transport.

### 3. The HTTP client does not retry at all

`app.leantime.http_client` is **not** a `RetryableHttpClient`. Retrying underneath the queue only
hides the failure from it: the queue is where attempts are counted, where a failure lands in the
failed transport, and where the spacing above is enforced. The client caps `timeout: 5` and
`max_duration: 30` and nothing more — see [002](002-data-provider-credentials-in-database.md).

### And: the production worker runs without `--failure-limit`

`docker-compose.server.override.yml` runs `messenger:consume --time-limit=900 async` with no
`--failure-limit`. `StopWorkerOnFailureLimitListener` counts every `WorkerMessageFailedEvent`, which
the worker dispatches for retryable failures too — so a limit of 1 stopped the only worker on the
first transient error rather than on anything final. `--time-limit` still recycles the process, and a
message that genuinely fails ends up in the failed transport either way.

### The worker survives its database connection

Removing `--failure-limit=1` needs one more thing to be true, because the flag was guarding a real
failure mode: a Doctrine error closes the `EntityManager`, and a worker holding a closed one fails
every message after it. That is where the pile of failed jobs came from. Stopping the worker was a
blunt fix for it.

It cannot span two messages on this stack. `framework.messenger.reset_on_message` defaulted to false
until Symfony 6.0 and has been true-only since 6.1, and `messenger:consume` registers
`ResetServicesListener` itself unless `--no-reset` is passed, which the supervisor does not. So the
`doctrine` registry is reset after every message, and `Registry::resetOrClearManager()` branches on
`isOpen()` — an open manager is cleared, a closed one is replaced outright.

The handlers cooperate by catching narrowly: none of them catches `\Throwable`, so a Doctrine error
leaves the handler, the message reaches the retry ladder, and the next attempt runs against a manager
that was rebuilt in between. What the flag was needed for the framework now does per message, and
unlike the flag it does not cost the queue its only worker to do it.

#### A dead socket still looks open, so the queue pings

Reset covers a *closed* manager. It cannot cover a manager sitting over a dead socket, which still
reports itself open — only issuing a query tells the two apart. Hence `doctrine_ping_connection` in
`config/packages/messenger.yaml`.

A worker outliving its connection is the normal case rather than the exceptional one: an idle reap, a
database restart, a failover and a proxy discarding an idle socket all end the same way. The handling
is therefore written to survive a dead connection outright rather than to fit whatever the server is
configured for — deliberately, because that configuration is not knowable from here. The local
`itkdev/mariadb` image sets `wait_timeout` to 300s, MySQL and MariaDB both document 28800s, and
production runs a database this repository does not describe.

Without the ping, the first message after the connection dies fails with "server has gone away" and is
recovered by the retry ladder. Nothing is lost, but it costs a failed job and a delay for a fault a
single query would have caught. Cheap to avoid: `DoctrinePingConnectionMiddleware` pings only when the
envelope carries a `ConsumedByWorkerStamp`, and `SyncTransport` adds only a `ReceivedStamp` — so this
is one dummy `SELECT` per consumed message, and nothing at all on the `sync` transport or the inline
billing dispatches.

`doctrine_transaction` was **not** added alongside it. The handlers each flush once, and wrapping every
message in a transaction is a change of behaviour, not a fix.

## Consequences

### Positive

- A permanent failure fails immediately and is visible in the failed transport within seconds, not
  minutes.
- A transient failure gets 130 seconds of coverage, which spans the outages actually observed.
- Attempts are counted in exactly one place, so the queue's view of a message is the truth.
- The single production worker survives transient tracker unavailability.
- A worker also survives its database connection dying under it, whether the manager was closed by an
  error or the socket was reaped while idle, and recovers without spending a failed job on it.

### Negative / Trade-offs

- The policy has to be read in four files to be understood, and none of them is obviously the entry
  point. This ADR and the comments at each site are the mitigation.
- The classification is a fixed status-code list. A tracker that signals back-pressure with an
  undocumented code, or with a 5xx that is really a rate limit, is classified wrong.
- The 130s ceiling is set by the paging design, not by what an outage needs. An outage longer than that
  costs the run, and recovery waits for the next hourly cron.
- Without `--failure-limit`, a worker in a pathological failure loop is bounded only by
  `--time-limit=900`.
- Dropping the flag rests on two framework behaviours rather than on anything this repository states:
  `reset_on_message` being true, and `messenger:consume` registering `ResetServicesListener`. Adding
  `--no-reset` to the supervisor command, or a future Symfony changing either default, silently
  reopens the closed-manager failure mode.
- The handlers must keep catching narrowly for that to hold. A handler that grows a `\Throwable` catch
  swallows the Doctrine error, so the message never reaches the retry ladder and never benefits from
  the rebuilt manager.

### Follow-up Actions

- [ ] Before consolidating any part of this, read this ADR. If consolidation still looks right, record
      the reasoning as a superseding ADR rather than editing the three sites.
- [ ] If the supervisor command ever gains `--no-reset`, or a handler gains a `\Throwable` catch,
      revisit the closed-manager reasoning above rather than assuming it still holds.
