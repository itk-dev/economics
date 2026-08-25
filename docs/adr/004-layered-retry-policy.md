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

## Decision

Option 3. The policy lives in three places, deliberately, and is **not** to be consolidated.

### 1. The handler decides whether a failure is worth repeating

`src/MessageHandler/Trait/RethrowsTransientHttpFailuresTrait.php` rethrows only 408, 423, 425 and
429 — the 4xx codes that mean "later" rather than "no" — so the transport's retry strategy picks them
up. Every other 4xx becomes an `UnrecoverableMessageHandlingException`, because the retry budget
cannot change the request. The trait is shared by the handlers that call the tracker directly, so
both agree on the classification.

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

## Consequences

### Positive

- A permanent failure fails immediately and is visible in the failed transport within seconds, not
  minutes.
- A transient failure gets 130 seconds of coverage, which spans the outages actually observed.
- Attempts are counted in exactly one place, so the queue's view of a message is the truth.
- The single production worker survives transient tracker unavailability.

### Negative / Trade-offs

- The policy has to be read in four files to be understood, and none of them is obviously the entry
  point. This ADR and the comments at each site are the mitigation.
- The classification is a fixed status-code list. A tracker that signals back-pressure with an
  undocumented code, or with a 5xx that is really a rate limit, is classified wrong.
- The 130s ceiling is set by the paging design, not by what an outage needs. An outage longer than that
  costs the run, and recovery waits for the next hourly cron.
- Without `--failure-limit`, a worker in a pathological failure loop is bounded only by
  `--time-limit=900`.

### Follow-up Actions

- [ ] Before consolidating any part of this, read this ADR. If consolidation still looks right, record
      the reasoning as a superseding ADR rather than editing the three sites.
