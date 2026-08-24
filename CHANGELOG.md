<!-- markdownlint-disable MD024 -->
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

* [PR-331](https://github.com/itk-dev/economics/pull/331)
  Raised test coverage from 64% to 90% and the threshold from 62 to 85
* [PR-339](https://github.com/itk-dev/economics/pull/339)
  * Recorded why dropping `--failure-limit=1` in PR-327 does not reopen what it was originally there for. The
    flag guarded a real failure mode: a Doctrine error closes the `EntityManager`, and a worker holding a closed
    one fails every message after it, which is where the pile of failed jobs came from. It cannot span two
    messages here. `framework.messenger.reset_on_message` defaulted to false until Symfony 6.0 and has been
    true-only since 6.1, and on this stack `messenger:consume` registers `ResetServicesListener` itself unless
    `--no-reset` is passed, which the supervisor does not. That resets the `doctrine` registry after every
    message, and `Registry::resetOrClearManager()` branches on `isOpen()` — an open manager is cleared, a closed
    one is replaced outright. The handlers cooperate by catching narrowly: none of them catches `\Throwable`, so
    a Doctrine error leaves the handler, the message reaches the retry ladder, and the next attempt runs against
    a manager that was rebuilt in between. What the flag was needed for in 2025 the framework now does per
    message, and unlike the flag it does not cost the queue a worker to do it.
  * Added the `doctrine_ping_connection` middleware, covering the one thing that reset genuinely cannot: it
    replaces a *closed* manager, but a manager sitting over a dead socket still looks open, and only issuing a
    query tells the two apart. A worker that lives longer than its connection is the normal case rather than the
    exceptional one — an idle reap, a database restart, a failover and a proxy discarding an idle socket all end
    the same way — so the handling is written to survive a dead connection outright rather than to fit whatever
    the server is configured for. Deliberately so, because that configuration is not knowable from here: the
    local `itkdev/mariadb` image sets `wait_timeout` to 300s, MySQL and MariaDB both document 28800s, and
    production runs a database this repository does not describe. Without the ping the first message after the
    connection dies fails with "server has gone away" and is recovered by the retry ladder — nothing is lost,
    but it costs a failed job and a delay for a fault a single query would have caught. Cheap to avoid:
    `DoctrinePingConnectionMiddleware` pings only when the envelope carries a `ConsumedByWorkerStamp`, and
    `SyncTransport` adds only a `ReceivedStamp`, so this is one dummy `SELECT` per consumed message and nothing
    at all on the `sync` transport or the inline billing dispatches. `doctrine_transaction` was not added with
    it — the handlers each flush once, and wrapping every message in a transaction is a change of behaviour, not
    a fix.
* [PR-337](https://github.com/itk-dev/economics/pull/337)
  * Added `CLAUDE.md`, so an agent starts from the Taskfile and the container rather than reaching for
    host `php`, and does not have to rediscover the decisions it would otherwise undo — the split
    retry policy, the hand-built Leantime HTTP client, soft-delete-by-source, ORM 2.
  * Recorded that `coding-standards:js:check` covers `assets/` only, since its name reads as though it
    covers Markdown too, and gave the Markdown lint its own entry.
* [PR-327](https://github.com/itk-dev/economics/pull/327)
  * Recorded where the 429s came from, since nothing here did and the answer is not in the data-api plugin.
    Leantime core rate-limits every request in `app/Core/Middleware/RequestRateLimiter.php` (v3.9.7): the API
    bucket defaults to **100 requests per 60 seconds, keyed on client IP**, and the 429 carries `Retry-After`
    plus three `X-RateLimit-*` headers. It reaches `/APIData/API/…` because `IncomingRequest::isApiRequest()`
    lowercases the URI and prefix-matches `/api`. It is disabled outright when `app.debug` is true, which is
    why development never saw it. **What resolved the 429s was raising `LEAN_RATELIMIT_API` to 10000 on the
    Leantime side**, not anything in this repository — a full sync cannot approach that.
  * Respaced the `async` transport's `retry_strategy`. Three attempts was never the problem; 1s/2s/4s was, being
    over before anything transient has had time to end. Now 10s, 30s, 90s, so the last attempt lands 130s after
    the first failure — past a Leantime restart, a database failover, or a 60s rate-limit window. No wider than
    that, because a page only queues its successor once it succeeds: a page waiting to be retried is the whole
    entity type's sync waiting with it, against an hourly cron. This applies to every message on the transport,
    not only the Leantime ones. PR-325 and PR-326 already narrowed the handlers so only a failure describing the
    message itself is unrecoverable, which is what lets a transient failure reach the queue at all.
  * A 4xx other than 408/423/425/429 now fails the message immediately instead of being retried three times to
    arrive at the same answer — the endpoint returns 400 for a missing `type` or the retired `deleted`
    parameter, and the retry budget cannot rewrite the request. This is the delete sync's only cover:
    `sync-deleted` dispatches on the `sync` transport, which has no retry strategy, and it has to stay there —
    what keeps `DELETED_TYPES` in child-before-parent order is the handlers running inline.
  * Bounded Leantime requests with `timeout: 5` and `max_duration: 30` on a client of their own rather than on
    `framework.http_client.default_options`, so nothing else inherits them. Symfony caps neither by default and
    an uncapped request holds the worker forever, since `messenger:consume` only checks `--time-limit` between
    messages. A scoped client cannot express this: scopes key on `base_uri`, and the Leantime one comes from the
    `DataProvider` entity at runtime.
  * Dropped `--failure-limit=1` from the supervisor's `messenger:consume`. `StopWorkerOnFailureLimitListener`
    counts every `WorkerMessageFailedEvent`, which the worker dispatches for retryable failures too, so with one
    worker configured the first transient error stopped it — now that a retry ladder exists, that is the wrong
    thing to count.
  * Considered and rejected throttling the client with a `ThrottlingHttpClient` over a rate limiter. Against the
    configured 10000/min it would guard nothing, and it pauses inside the message handler, so the single worker
    stops draining the queue while it waits. Retrying is also the layer that cannot read the `Retry-After`
    Leantime sends — a transport retry strategy never sees the response — but the ladder above outlasts a 60s
    window by its third attempt, so the header would not change the outcome.
* [PR-335](https://github.com/itk-dev/economics/pull/335)
  * Stopped `projectRemovedFromDataProvider()` hard-deleting a project that a version, a project billing or a
    service agreement still points at. Each of those points back with a non-nullable, non-cascading foreign key,
    so `remove()` raised a database error instead of the soft delete the invoice, issue and worklog checks give.
    Only the delete sync's type ordering — milestones before projects — kept it out of reach, and any milestone
    deletion the source never reported exposed it.
* [PR-334](https://github.com/itk-dev/economics/pull/334)
  * Paginated the Leantime delete sync, following [data-api#21](https://github.com/ITK-Leantime/data-api/pull/21):
    `/deleted` now serves one type per request with `start`/`limit`, so `delete()` queues a message per type and
    `deleteAsJob()` pages through them the way `updateAsJob()` already does. The whole deletion history no longer
    has to arrive in a single response — which is what the 300s `max_duration` in `config/packages/framework.yaml`
    was sized for, though it stays as it is for the entity endpoints.
  * The delete request now sends its timestamp as `deletedAfter`, the endpoint's new name for it, matching
    `modifiedAfter` on the entity endpoints. The old `deleted` answers 400 rather than being ignored, so the key
    cannot go missing unnoticed again.
  * The delete cursor is the endpoint's new `deletionId`, not the deleted entity's `id`: deletions are ordered by
    when they happened. It advances past a deletion that names no entity, since a skipped row still has to be paged
    past, and a full page with no usable `deletionId` stops with an error rather than re-queueing itself.
* [PR-326](https://github.com/itk-dev/economics/pull/326)
  * Stopped the pagination cursor in `updateAsJob()` looping on a page it cannot advance past. Skipping null ids
    left the cursor where it started, so a full page of them re-queued the same page forever and starved the
    single worker of every other sync. Such a page now stops with an error instead. Ids that are not numeric are
    skipped for the same reason, and the cursor follows the highest usable id on the page rather than the last.
  * Added `Unit\Service\LeantimeApiServiceTest`, covering the cursor directly — the integration tests only ever
    use partial pages, so no next page was queued and the cursor was never exercised.
* [PR-332](https://github.com/itk-dev/economics/pull/332)
  * CI: Stopped every workflow job starting RabbitMQ. `phpfpm` depends on `rabbit` being healthy, and
    `docker compose` loads `docker-compose.override.yml` automatically, so all eight jobs that run
    `phpfpm` booted a broker none of them use — `when@test` in `config/packages/messenger.yaml` routes
    the only AMQP transport to Doctrine. The jobs now pass `--no-deps` and start `mariadb` explicitly
    where they need it, which removes the intermittent `dependency failed to start: container
    economics_v2-rabbit-1 exited (1)` failures and cuts a container off every job.
    Also gave the `rabbit` healthcheck a `start_period`: `rabbitmq-diagnostics` boots an Erlang VM per
    invocation, so probing every second spawned a dozen of them alongside the broker's own ~12s boot,
    and without a start period each failure counted against the retry budget.
* [PR-325](https://github.com/itk-dev/economics/pull/325)
  * Fixed the Leantime sync halting silently on a single bad row. A row that cannot be mapped, or that a handler
    rejects, logs `Skipping <class> id <id>: <reason>` and the sync moves on. The catches are deliberately narrow —
    a `TypeError` from a null field and a handler's `UnrecoverableMessageHandlingException` are skippable, while a
    dead database or an unreachable Leantime still halts the run loudly instead of being logged away as a bad row.
  * Made the Leantime result mappers null-safe ahead of
    [data-api#18](https://github.com/ITK-Leantime/data-api/pull/18): a deleted user is attributed to
    `deleted-user-<userId>`, a missing name becomes `(no name) <id>`, and rows with no `ticketId`/`projectId`/`id`
    are skipped. The tracker id is part of the name placeholder because names are used as lookup keys elsewhere —
    `ProjectBillingService` resolves a client by version name.
  * A `deleted-user-<userId>` attribution no longer overwrites a worker name an earlier sync already stored.
  * Fixed the `/deleted` request sending its timestamp as `deletedAfter` rather than `deleted`, which made every
    delete-sync pull the entire unpaginated deletion history. Deletion entries with no id are now skipped and logged,
    and a single failing entry no longer drops every deletion after it — with the timestamp now applied, a dropped
    entry would never come round again.
  * Added `LeantimeApiServiceTest::testUpdateWithNullValues()` and `::testDeletedUserFallbackKeepsStoredWorker()`,
    and pinned the `/deleted` request body so the parameter name cannot regress.

## [3.7.0] - 2026-06-26

* [PR-324](https://github.com/itk-dev/economics/pull/324)
  * Added game center with snake
* [PR-303](https://github.com/itk-dev/economics/pull/303)
  * Added nightly safety-net sync cron jobs to `.woodpecker/prod_economics.yml`
    and `.woodpecker/prod_itk_economics.yml`. Five staggered jobs run at
    02:00/02:10/02:20/02:30/02:40 invoking
    `app:data-providers:sync -j -d` for projects (`-p`),
    workers (`-r`), versions (`-s`), issues (`-i`), and worklogs (`-w`) —
    re-syncing everything touched within the past week and bypassing the local
    `modifiedAt` short-circuit (`-d`), since the upstream source isn't fully
    trusted to update `modifiedAt` on every change. A sixth job at 02:50 runs
    `app:data-providers:sync-deleted --interval=P1W` to widen the deletion
    window to the past week (vs. the default `PT1H` used by the 25-minute
    cron).
* [PR-322](https://github.com/itk-dev/economics/pull/322)
  * Autoselect external receiver account from client.

## [3.6.1] - 2026-06-23

* DevOps: Added docker compose dependency between phpfpm and rabbit.

## [3.6.0] - 2026-06-23

* [PR-321](https://github.com/itk-dev/economics/pull/321)
  * Removed the invoice description project name check.
* [PR-318](https://github.com/itk-dev/economics/pull/318)
  * Quality-of-life improvements for external invoices.

## [3.5.0] - 2026-05-26

* [PR-315](https://github.com/itk-dev/economics/pull/315)
  * Add tidy-feedback collector module.
* [PR-310](https://github.com/itk-dev/economics/pull/310)
  * Remove dataProvider scoping.
* [PR-264](https://github.com/itk-dev/economics/pull/264)
  Added cybersecurity report.
* [PR-309](https://github.com/itk-dev/economics/pull/309)
  * Consolidated project and service-agreement fields.
  * Moved the Leantime project link from service agreement to project and rendered it via a Twig helper.
  * Moved git repos from service agreement to project.
  * Added codeowners on project, editable as a multiselect on the project edit form.
  * Added a project API endpoint returning each project with its codeowners and most recent service agreement.
  * Removed the service agreement API endpoint, superseded by the project API endpoint.
  * Removed `LEANTIME_PROJECT_TRACKER_URL`; `leantimeUrl` is now resolved from each project's data provider.

## [3.4.1] - 2026-05-23

* [PR-313](https://github.com/itk-dev/economics/pull/313)
  * Changed supervisor to php 8.4 version.

## [3.4.0] - 2026-05-20

* [PR-311](https://github.com/itk-dev/economics/pull/311)
  * Updated bundles.
* [PR-307](https://github.com/itk-dev/economics/pull/307)
  * Migrated static analysis from Psalm to PHPStan (level 8). Analysis now also covers `tests/`.
* [PR-306](https://github.com/itk-dev/economics/pull/306)
  * Excluded `messenger_messages` from Doctrine schema diffing via `doctrine.dbal.schema_filter`, so the Messenger
    Doctrine transport can manage its own table without generating noisy migrations.
  * Upgraded Symfony from 6.4 to 7.4 LTS. Dropped `symfony/proxy-manager-bridge`
    (removed in Symfony 7.0), removed the obsolete `enable_authenticator_manager`
    security option, and tightened `User::eraseCredentials()` to the new
    `: void` return type required by `UserInterface`.
* [PR-305](https://github.com/itk-dev/economics/pull/305)
  * npm audit fix: bumped `@symfony/webpack-encore` to `^6` and aligned
    `postcss-loader`, `sass-loader`, `webpack-cli` with its peer requirements.
    Removed the now-redundant `markdownlint-cli` npm dep — markdownlint runs
    via the `markdownlint` docker compose service in CI.
* [PR-304](https://github.com/itk-dev/economics/pull/304)
  * Applied itk-dev templates.
  * Removed Game Center.
  * Removed `prettier-plugin-jsdoc`.
* [PR-302](https://github.com/itk-dev/economics/pull/302)
  * Excluded `messenger_messages` from Doctrine schema diffing via `doctrine.dbal.schema_filter`.
  * Added `task test:coverage:set-threshold -- <value>` to update the coverage threshold across `Taskfile.yml`,
    `composer.json`, and `.github/workflows/pr.yml`.
  * Fixed `ForecastReportService::getForecastReport()` crashing on worklogs whose issue had epics
    (`PersistentCollection` passed to `array_map`, and `Epic::getName()` → `getTitle()`).
  * Added integration tests for `HourReportService`, `InvoicingRateReportService`, `WorkloadReportService`,
    `BillableUnbilledHoursReportService`, and `ForecastReportService` under `tests/Integration/Service/`.
  * Added `InvoiceEntryFlowTest` covering edit, delete, recorded-invoice guards, and invalid-CSRF fall-through for
    `InvoiceEntryController`.
  * Added a minimum test-coverage gate (62%) using a clover report and `rregeer/phpunit-coverage-check`;
    removed the unused Codecov upload step.
  * Added `Taskfile.yml` (go-task) wrapping common dev commands as `task <name>`; composer scripts unchanged.
  * Sped up `composer fixtures:load` by disabling DBAL debug middlewares, batching worklog inserts in groups
    of 500, and caching entity references across `clear()`.
  * Added controller smoke-matrix and flow tests under `tests/Integration/Controller/` covering admin index
    routes and invoice/project-billing/hour-report flows.
  * Fixed `/admin/invoices/{id}/edit` crashing on entries with no account by letting
    `InvoiceEntryHelper::getAccountLabel()` accept null.
  * Fixed `/admin/reports` landing page crash by replacing the stub with a minimal page linking to each report.
  * Aligned `config/packages/security.yaml` with the report controllers: `/admin/reports/*` now requires
    `ROLE_REPORT` instead of `ROLE_ADMIN`.
* [PR-265](https://github.com/itk-dev/economics/pull/265)
  Add version to issue during sync.

## [3.3.0] - 2026-05-12

* [PR-292](https://github.com/itk-dev/economics/pull/292)
  * Workload report: right-align numbers and round percentages to 1 decimal.
* [PR-290](https://github.com/itk-dev/economics/pull/290)
  * Sort reports alphabetically - unbilled projects report, hour report, invoicing rate report, workload report.

## [3.1.0] - 2026-04-30

* [PR-288](https://github.com/itk-dev/economics/pull/288)
  * Composer.lock cleanup.
* [PR-286](https://github.com/itk-dev/economics/pull/286)
  * Security updates.
* [PR-284](https://github.com/itk-dev/economics/pull/284)
  * Security updates.
* [PR-283](https://github.com/itk-dev/economics/pull/283)
  * Revert supercronic and templates update.
* [PR-282](https://github.com/itk-dev/economics/pull/282)
  * Fixed worklog project not updating when ticket is moved to another project in Leantime.
* [PR-281](https://github.com/itk-dev/economics/pull/281)
  * Service agreements: Styling adjustments, index fields, QoL.
* [PR-280](https://github.com/itk-dev/economics/pull/280)
  * Additional fields for service agreements.

## [3.0.2] - 2026-03-04

* [PR-274](https://github.com/itk-dev/economics/pull/274)
  * Added cron to itk-economics woodpecker setup.

## [3.0.1] - 2026-02-02

* [PR-266](https://github.com/itk-dev/economics/pull/266)
  * Replace symfony scheduler with cron.
  * Fixed issue version and epic synchronization.
  * Fixed issue epic use.
  * Added option to disable modifiedAt check for sync command.
  * Changed invoice epic selector to multiple.

## [3.0.0] - 2026-01-20

* [PR-262](https://github.com/itk-dev/economics/pull/262)
  * Fixed includedProjects query.
* [PR-261](https://github.com/itk-dev/economics/pull/261)
  * In hourReport, get epics on issue via join.
* [PR-259](https://github.com/itk-dev/economics/pull/259)
  * Attach epics to issues during sync.
* [PR-258](https://github.com/itk-dev/economics/pull/258)
  * Skip excluded workers from workload report.

## [2.10.2] - 2025-12-11

* [PR-265](https://github.com/itk-dev/economics/pull/256)
  * Add next year as an option for the planning overview.
* [PR-252](https://github.com/itk-dev/economics/pull/252)
  * Added support for new workers endpoint.
* [PR-253](https://github.com/itk-dev/economics/pull/253)
  * Made worker optional on issue entity.
* [PR-242](https://github.com/itk-dev/economics/pull/242)
  * Changed Leantime data provider to use apidata plugin instead of leantime api.

## [2.10.1] - 2025-11-24

* [PR-250](https://github.com/itk-dev/economics/pull/250)
  * hotfix dataprovider reference

## [2.10.0] - 2025-11-19

* [PR-246](https://github.com/itk-dev/economics/pull/246)
  * Minor api adjustments.
* [PR-243](https://github.com/itk-dev/economics/pull/243)
  * Added CRUD for service agreements and cyber security agreements.
  * Added JSON endpoint for retrieving agreements.
* [PR-240](https://github.com/itk-dev/economics/pull/240)
  * Fixes and optimizations for app:sync command
* [PR-235](https://github.com/itk-dev/economics/pull/235)
  * Increased timeout of nginx.
  * Included archived issues in sync.
  * Template files updated.

## [2.9.4] - 2025-07-10

* [PR-232](https://github.com/itk-dev/economics/pull/232)
  * Leantime synchronization adjustments.

## [2.9.3] - 2025-07-08

* [PR-230](https://github.com/itk-dev/economics/pull/230)
  * Switch to amqp message broker.

## [2.9.2] - 2025-06-30

* [PR-226](https://github.com/itk-dev/economics/pull/226)
  * Upped memory limit for LT-sync.
  * Fixed some composer-related issues.

## [2.9.1] - 2025-05-19

* [PR-225](https://github.com/itk-dev/economics/pull/225)
  * Ensure current week and month shown on dashboard when no time is logged.
  * Update composer dependencies, patch only.

## [2.9.0] - 2025-05-07

* [PR-223](https://github.com/itk-dev/economics/pull/223)
  * Adjust cron sync interval from hourly to daily at midnight.
* [PR-222](https://github.com/itk-dev/economics/pull/222)
  * Add quarter picker for unbilled billable worklogs report.
* [PR-221](https://github.com/itk-dev/economics/pull/221)
  * Remove showKanban stuff from link to leantime to make it faster.

## [2.8.6] - 2025-04-07

* [PR-218](https://github.com/itk-dev/economics/pull/218)
  * Included Done tasks in holiday planning overview.

## [2.8.5] - 2025-04-04

* [PR-216](https://github.com/itk-dev/economics/pull/216)
  * Replaced literals with query parameters in worklog repo.

## [2.8.4] - 2025-04-03

* [PR-214](https://github.com/itk-dev/economics/pull/214)
  * Explicitly set isBilled when synchronizing worklogs.
  * Select isBilled=NULL when getting unbilled billable worklogs.

## [2.8.3] - 2025-03-26

* [PR-212](https://github.com/itk-dev/economics/pull/212)
  * Setup auto deploy (woodpecker) for both prod sites.

## [2.8.2] - 2025-03-21

* [PR-209](https://github.com/itk-dev/economics/pull/209)
  * Refactor dashboard calculations to do SUM in database and limit to days in current year.
* [PR-210](https://github.com/itk-dev/economics/pull/210)
  * Increase php max execution time for supervisor container to allow for LeanTime API rate limit.
  * Update github actions to use docker setup.
  * Update to latest ITK docker setup.

## [2.8.1] - 2025-03-18

* [PR-208](https://github.com/itk-dev/economics/pull/208)
  * Removed extra build.

## [2.8.0] - 2025-02-24

* [PR-206](https://github.com/itk-dev/economics/pull/206)
  * 3947: Added create release GitHub Actions workflow.
* [PR-205](https://github.com/itk-dev/economics/pull/205)
  * 3863: Added lock to synchronization job to avoid executing more than one sync at a time.
  * 3863: Moved queue monitoring to handler instead of command.
* [PR-202](https://github.com/itk-dev/economics/pull/202)
  * 3863: Added holiday planning.
* [PR-201](https://github.com/itk-dev/economics/pull/201)
  * 2299: Added project issue sync button to planning.
* [PR-200](https://github.com/itk-dev/economics/pull/200)
  * 3660: Adds user dashboard.
* [PR-199](https://github.com/itk-dev/economics/pull/199)
  * 2299: Fixed linting of javascript.
* [PR-202](https://github.com/itk-dev/economics/pull/202)
  * Security updates.
* [PR-204](https://github.com/itk-dev/economics/pull/204)
  * 3907: Updating lastSent when running subscriptions.
* [PR-197](https://github.com/itk-dev/economics/pull/197)
  * 2299: Upgraded to php 8.3 and node 20.
* [PR-191](https://github.com/itk-dev/economics/pull/191)
  * 2299: Added project sync component to navigation.
* [PR-195](https://github.com/itk-dev/economics/pull/195)
  * 3602: Added billable unbilled hours report.
* [PR-196](https://github.com/itk-dev/economics/pull/196)
  * 3624: Correctly handling periods when viewing past workload reports.

## [2.7.0] - 2025-01-14

* [PR-194](https://github.com/itk-dev/economics/pull/194)
  * 2299: Added amount to invoices list. Removed data provider.
* [PR-193](https://github.com/itk-dev/economics/pull/193)
  * 2575: Added link to issue on hour report.
* [PR-188](https://github.com/itk-dev/economics/pull/188)
  * 2299: Removed sprint report.

## [2.6.1] - 2025-01-02

## [2.6.0] - 2025-01-02

* [PR-182](https://github.com/itk-dev/economics/pull/182)
  * 2597: Added invoicing rate report.
* [PR-185](https://github.com/itk-dev/economics/pull/186)
  * 2597: Added epic migration command.
* [PR-184](https://github.com/itk-dev/economics/pull/184)
  * 3489: Workload report period averages.
* [PR-183](https://github.com/itk-dev/economics/pull/183)
  * 2597: Added epic relations.
* [PR-187](https://github.com/itk-dev/economics/pull/187)
  * Updated symfony bundles.
* [PR-189](https://github.com/itk-dev/economics/pull/189)
  * Npm audit.
* [PR-187](https://github.com/itk-dev/economics/pull/187)
  * Updated symfony bundles.
* [PR-175](https://github.com/itk-dev/economics/pull/175)
  * 2617: Added forecast report.

## [2.5.3] - 2025-03-17

* [PR-207](https://github.com/itk-dev/economics/pull/207)
  * hotfix: Setup woodpecker workflows.

## [2.5.2] - 2025-03-17

* [PR-207](https://github.com/itk-dev/economics/pull/207)
  * hotfix: Change from yarn to npm for build release

## [2.5.1] - 2024-11-26

* [PR-180](https://github.com/itk-dev/economics/pull/180)
  * hotfix: Corrected from/to date check in hour report.

## [2.5.0] - 2024-10-23

* [PR-173](https://github.com/itk-dev/economics/pull/173)
  * 2663: Workload report loading speed improvement.
* [PR-167](https://github.com/itk-dev/economics/pull/167)
  * 2499: Added worker name in workload report.
* [PR-166](https://github.com/itk-dev/economics/pull/166)
  * 2545: Added total column for workload report.
  * 2545: Fixed average calculation.
* [PR-168](https://github.com/itk-dev/economics/pull/168)
  * Added Game center
* [PR-164](https://github.com/itk-dev/economics/pull/164)
  * 3298: Added report notification subscription

## [2.4.3] - 2024-10-09

* [PR-174](https://github.com/itk-dev/economics/pull/174)
  * Fixed status enum twig rendering.

## [2.4.2] - 2024-09-12

* [PR-163](https://github.com/itk-dev/economics/pull/163)
  * 2454: Hide done tasks in planning overview.
* [PR-159](https://github.com/itk-dev/economics/pull/159)
  * 2396: Added year select to planning overview.
* [PR-158](https://github.com/itk-dev/economics/pull/158)
  * 2299: Fixed isBillable filter for project list.
  * 2299: Removed unused code from planning overviews.
* [PR-157](https://github.com/itk-dev/economics/pull/157)
  * 2299: Npm audit fixes.
* [PR-156](https://github.com/itk-dev/economics/pull/156)
  * 2299: Composer update.
* [PR-155](https://github.com/itk-dev/economics/pull/155)
  * 2294: Added worker name field and added to planning overview.
* [PR-154](https://github.com/itk-dev/economics/pull/154)
  * 2265: Changed X column in external exported csv.

## [2.4.1] - 2024-09-04

* [PR-152](https://github.com/itk-dev/economics/pull/152)
  * 2244: Handling Leantime timestamps when importing.

## [2.4.0] - 2024-08-20

* [PR-149](https://github.com/itk-dev/economics/pull/149)
  * 2096: Set default dataprovider on hourReport.
* [PR-148](https://github.com/itk-dev/economics/pull/148)
  * 2031: Project overview standard settings.
* [PR-147](https://github.com/itk-dev/economics/pull/147)
  * 2033: Sync worklogs from invoice entry.
* [PR-146](https://github.com/itk-dev/economics/pull/146)
  * 2034: Invoice date select continuity.
* [PR-145](https://github.com/itk-dev/economics/pull/145)
  * 2059: Specify workload report week definition.
* [PR-143](https://github.com/itk-dev/economics/pull/143)
  * 2050: Hour-report issue duedate ignore.
* [PR-142](https://github.com/itk-dev/economics/pull/142)
  * 2041: Revise Leantime issue status sync.
* [PR-138](https://github.com/itk-dev/economics/pull/138)
  * 1867: Issue status as enum.
* [PR-135](https://github.com/itk-dev/economics/pull/135)
  * 1772: Removed views.
* [PR-136](https://github.com/itk-dev/economics/pull/136)
  * 1774: Planning view use service.
* [PR-137](https://github.com/itk-dev/economics/pull/137)
  * 1812: Minor hour report improvements.
* [PR-134](https://github.com/itk-dev/economics/pull/134)
  * 1632: Remove team report.
* [PR-133](https://github.com/itk-dev/economics/pull/133)
  * 1742: Simplified hour report form.
* [PR-132](https://github.com/itk-dev/economics/pull/132)
  * 1742: Fixed synchronization issues.
* [PR-128](https://github.com/itk-dev/economics/pull/128)
  * 1595: Added retryable http client decorator for handling rate limiting.
* [PR-117](https://github.com/itk-dev/economics/pull/117)
  * 1211: Added hour report
  * NOTE: APP_DEFAULT_PLANNING_DATA_PROVIDER has been changed to APP_DEFAULT_DATA_PROVIDER. This has to be changed when
    releasing.
* [PR-124](https://github.com/itk-dev/economics/pull/124)
  * 710: Added workload report
* [PR-129](https://github.com/itk-dev/economics/pull/129)
  * 1632: Added invoicing rate view to workload report

## [2.3.3] - 2024-07-10

* [PR-141](https://github.com/itk-dev/economics/pull/141)
  * Data provider stuff

## [2.3.2] - 2024-07-05

* [PR-140](https://github.com/itk-dev/economics/pull/140)
  * 1768: Added link to invoice entry that binds worklog.

## [2.3.1] - 2024-07-05

* [PR-139](https://github.com/itk-dev/economics/pull/139)
  * 1890: Added check that issue exists before adding worklog to database.

## [2.3.0] - 2024-06-03

* [PR-126](https://github.com/itk-dev/economics/pull/126)
  * 1590: Added worklog product as prefix on product invoice entries
* [PR-125](https://github.com/itk-dev/economics/pull/125)
  * 1547: Set account based on invoice entry type
* [PR-123](https://github.com/itk-dev/economics/pull/123)
  * 1544: Allowed invoicing issues with products and no worklogs
* [PR-122](https://github.com/itk-dev/economics/pull/122)
  * 1547: Added invoice entry account selector
* [PR-121](https://github.com/itk-dev/economics/pull/121)
  * 1485: Fixed floating number issues
* [PR-120](https://github.com/itk-dev/economics/pull/120)
  * 1484: Cleaned up worklog cleanup
* [PR-118](https://github.com/itk-dev/economics/pull/118)
  * 1485: Made product quantity floatable

## [2.2.0] - 2024-05-06

* [PR-114](https://github.com/itk-dev/economics/pull/114)
  * 1258: Clean up planning view ui and add scroll to active sprint.
* [PR-112](https://github.com/itk-dev/economics/pull/112)
  * 1280: Simplified planning form. Added default value.
* [PR-113](https://github.com/itk-dev/economics/pull/113)
  * Worklog period filter
* [PR-110](https://github.com/itk-dev/economics/pull/110)
  * 1209: No cost invoices
* [PR-111](https://github.com/itk-dev/economics/pull/111)
  * 1208: Restored exported data column
* [PR-107](https://github.com/itk-dev/economics/pull/107)
  * 1213: Fixed handling of filter value
* [PR-109](https://github.com/itk-dev/economics/pull/109)
  * 1207: Added invoice query
* [PR-108](https://github.com/itk-dev/economics/pull/108)
  * 1208: Changed default sorting of recorded invoices
* [PR-106](https://github.com/itk-dev/economics/pull/106)
  * 1202: Handled worklog deletions
* [PR-115](https://github.com/itk-dev/economics/pull/115)
  * 1270: Planning hoursRemaining source change

## [2.1.2] - 2024-04-16

* [PR-104](https://github.com/itk-dev/economics/pull/104)
  * 1174: Fixed datetime format in Leantime API calls
* [PR-103](https://github.com/itk-dev/economics/pull/103)
  * 1169: Made sure that Leantime issues have at most one version (milestone)
* [PR-102](https://github.com/itk-dev/economics/pull/102)
  * 1157: Updated external billing export

## [2.1.1] - 2024-04-04

* [PR-100](https://github.com/itk-dev/economics/pull/100)
  * 1111: Fixed fetching timesheet data from Leantime

## [2.1.0] - 2024-03-27

* [PR-98](https://github.com/itk-dev/economics/pull/98)
  * Replaced Tom Select with Stimulus
* [PR-97](https://github.com/itk-dev/economics/pull/97)
  * Twig CS Fixer
* [PR-96](https://github.com/itk-dev/economics/pull/96)
  * Miscellaneous fixes
* [PR-86](https://github.com/itk-dev/economics/pull/86)
  * Added products.
* [PR-95](https://github.com/itk-dev/economics/pull/95)
  * Updated bank holiday helper
* [PR-94](https://github.com/itk-dev/economics/pull/94)
  * Updated data in external invoicing
* [PR-93](https://github.com/itk-dev/economics/pull/93)
  * Made price on client optional
* [PR-87](https://github.com/itk-dev/economics/pull/87)
  * Fixed Leantime API request
* [PR-91](https://github.com/itk-dev/economics/pull/91)
  * Updated standard price on clients
* [PR-89](https://github.com/itk-dev/economics/pull/89)
  * Cleaned up Twig templates.
* [PR-88](https://github.com/itk-dev/economics/pull/88)
  * Miscellaneous clean-ups.

## [2.0.0]

* Adds phpunit.
* Adds fixtures.
* Adds project billing tests.
* Changed to using client->versionName to issue->version mapping for project billing.
* Added project lead/mail to project.
* Removed usused fields from account and client.
* Add team report export
* Add open spout extension
* Add choices to views
* Add team report
* Add workers to views
* Add view filtering to management reports
* Add csv export to management reports
* Added view delete protection
* Adds views filtering.
* Adds user administration.
* Added view and related form
* Default to work id worker not longer exists in Leantime worklog sync.
* Added commands to manage data providers.
* Changed how errors are handled in Leantime api calls.
* Modified getSprintReportData to work with Leantime data
* Added project lead to client when syncing projects.
* Remove description from create invoice page.
* Added generate description button to invoice when client is set.
* Fixed texts.
* Fixed classes for choices.js fields and disabled state.
* Added project lead to invoice edit page.
* Changed InvoiceEntry material number and account to be set only at the invoice level.
* Added default account to invoices from environment variable.
* Added check for invoice entries with amount 0 when putting invoice on record.
* Fixed issue with receiver account for project billing.
* Fixed invoices overview sorting. Changed default sorting for invoices on record as by exportedDate.
* Changed monolog config to ignore deprecations.
* Added Leantime specific header to api service.
* Added option to only export internal or external invoices from project billing.
* Added checks for errors before allowing putting project billing on record
* Added error check for invoice entries with 0 amounts
* Make sure all issues are selected in project billing period.
* Refactored error handling.
* Added support for multiple data providers
* Removed project creator for Jira.
* Added client view.
* Added account view.
* Added leantime support for projects and project sync.
* Added week-based planning view, based on issue duedates.
* Fixed minor leantime integration issues.
* Added dataprovider as column in project list.
* Added nested menus and current page highlight in menu.
* Added filters and sorting to client and account viewws.
* Added display names when hiding rows in Planning overview.
* Added a security voter for handling access to Invoice, InvoiceEntry and ProjectBilling.
* Added javascript style linting.
* Removed static from stimulus controller.
* Added data provider to account, client, project_billing and invoice index views.

* RELEASE NOTES:
  * Change name APP_INVOICE_RECEIVER_ACCOUNT to APP_INVOICE_SUPPLIER_ACCOUNT in `.env.local`
  * Set APP_INVOICE_DESCRIPTION_TEMPLATE in `.env.local`
  * Set APP_INVOICE_RECEIVER_DEFAULT_ACCOUNT in `.env.local`
  * Set APP_PROJECT_BILLING_DEFAULT_DESCRIPTION in `.env.local`
  * Migrate to new DataProvider model. The purpose of this is to couple the previous Jira data synchronizations to a
    data provider in the new model.
    * Add a dataProvider for current Jira implementation with the command

    ```sh
    bin/console app:project-tracker:create
    ```

    * Run the following commands to set `data_provider_id` field in the database for existing synced entities.

      Fill in the data from the `.env.local` values for the Jira connection:
      * Name: Jira
      * Url: JIRA_PROJECT_TRACKER_URL
      * Secret: JIRA_PROJECT_TRACKER_USER:JIRA_PROJECT_TRACKER_TOKEN

      NB! Replace 1 with the relevant DataProvider.id if it differs from 1.

    ```sh
    bin/console doctrine:query:sql 'UPDATE account SET data_provider_id = 1';
    bin/console doctrine:query:sql 'UPDATE client SET data_provider_id = 1';
    bin/console doctrine:query:sql 'UPDATE issue SET data_provider_id = 1';
    bin/console doctrine:query:sql 'UPDATE project SET data_provider_id = 1';
    bin/console doctrine:query:sql 'UPDATE version SET data_provider_id = 1';
    bin/console doctrine:query:sql 'UPDATE worklog SET data_provider_id = 1';
    ```

## [1.1.2]

* Changed how project billing is put on record, to allow for finishing a partially
complete process.
* Added exported date to invoices overview.
* Changed project billing period to date fields.
* Aligned date formats.
* Added total amount to invoice.

## [1.1.1]

* Added choices.js to dropdowns with many options.
* Added epic filter to worklog selection page.
* Removed time from period selections on worklog selection page.
* Optimized sync memory usage.
* Composer update to Symfony 6.4.

## [1.1.0]

* Updated api source to use Leantime
* Modified getPlanningData to work with Leantime data
* Changed amount and price field to NumberType instead of IntegerType.
* Added export more options to recorded invoices overview.
* Fixed issue with issue version sync.

## [1.0.4]

* Fixed command to recalculate sums for all invoices by first calculating
  invoice entries.

## [1.0.3]

* Changed redirect after create a manual invoice entry.
* Removed export options when client is not set.
* Added create new buttons to top of invoices and project billing lists.
* Added command to recalculate sums for all invoices.
* Changed datetime form fields to date.
* Fixed select all on worklog list.

## [1.0.2]

* Updated package-lock.json.

## [1.0.1]

* Updated openid-connect to newest version.
* Updated docker-compose files to newest version.

## [1.0.0]

* Added Billing.
* Added migration path from JiraEconomics.
* Added Sprint Report.
* Added Planning.
* Added OIDC login.
* Added Project Billing.
* Added list of issues not included because they lack account in project billing.
* Added Project Billing exported date.
* Added Project sync action.
* Added help text to invoice entry (worklog) type.
* Added publiccode.yml
* Added OpenID Connect Controller
* Updated docker files to the newest version.
* Fixed path bugs.
* Added filtering to lists.
* Added entity model section to readme.
* Fixed sprint report.
* Fixed planning js.
* Cleaned up config files.
* Changed add worklogs button style.
* Fixed filter function for worklogs.
* Updated to latest ITK logo
* Upgraded to latest bundles.
* Fixed budget path.
* Fixed planning js.
* Fixed filtering issue with project overview.
* Changed sprint report form to GET method.
* Fixed worklog select path.
* Optimized worklog select javascript.
* Fixed issues with create project. Javascript has been changed to use stimulus.
  Changed how session is accessed.
* Updated to authorization code flow.
* Changed worklog save button styling to be sticky.

[Unreleased]: https://github.com/itk-dev/economics/compare/3.6.0...HEAD
[3.6.0]: https://github.com/itk-dev/economics/compare/3.5.0...3.6.0
[3.5.0]: https://github.com/itk-dev/economics/compare/3.3.0...3.5.0
[3.3.0]: https://github.com/itk-dev/economics/compare/3.1.0...3.3.0
[3.1.0]: https://github.com/itk-dev/economics/compare/3.0.1...3.1.0
[3.0.1]: https://github.com/itk-dev/economics/compare/3.0.0...3.0.1
[3.0.0]: https://github.com/itk-dev/economics/compare/2.10.2...3.0.0
[2.10.2]: https://github.com/itk-dev/economics/compare/2.10.1...2.10.2
[2.10.1]: https://github.com/itk-dev/economics/compare/2.10.0...2.10.1
[2.10.0]: https://github.com/itk-dev/economics/compare/2.9.4...2.10.0
[2.9.4]: https://github.com/itk-dev/economics/compare/2.9.3...2.9.4
[2.9.3]: https://github.com/itk-dev/economics/compare/2.9.2...2.9.3
[2.9.2]: https://github.com/itk-dev/economics/compare/2.9.0...2.9.2
[2.9.0]: https://github.com/itk-dev/economics/compare/2.8.6...2.9.0
[2.8.6]: https://github.com/itk-dev/economics/compare/2.8.5...2.8.6
[2.8.5]: https://github.com/itk-dev/economics/compare/2.8.4...2.8.5
[2.8.4]: https://github.com/itk-dev/economics/releases/tag/2.8.4
[2.8.3]: https://github.com/itk-dev/economics/releases/tag/2.8.3
[2.8.2]: https://github.com/itk-dev/economics/releases/tag/2.8.2
[2.8.1]: https://github.com/itk-dev/economics/releases/tag/2.8.1
[2.8.0]: https://github.com/itk-dev/economics/releases/tag/2.8.0
[2.7.0]: https://github.com/itk-dev/economics/releases/tag/2.7.0
[2.6.1]: https://github.com/itk-dev/economics/releases/tag/2.6.1
[2.6.0]: https://github.com/itk-dev/economics/releases/tag/2.6.0
[2.5.3]: https://github.com/itk-dev/economics/releases/tag/2.5.3
[2.5.2]: https://github.com/itk-dev/economics/compare/2.5.1...2.5.2
[2.5.1]: https://github.com/itk-dev/economics/compare/2.5.0...2.5.1
[2.5.0]: https://github.com/itk-dev/economics/compare/2.4.2...2.5.0
[2.4.3]: https://github.com/itk-dev/economics/compare/2.4.2...2.4.3
[2.4.2]: https://github.com/itk-dev/economics/compare/2.4.1...2.4.2
[2.4.1]: https://github.com/itk-dev/economics/compare/2.4.0...2.4.1
[2.4.0]: https://github.com/itk-dev/economics/compare/2.3.3...2.4.0
[2.3.3]: https://github.com/itk-dev/economics/compare/2.3.2...2.3.3
[2.3.2]: https://github.com/itk-dev/economics/compare/2.3.1...2.3.2
[2.3.1]: https://github.com/itk-dev/economics/compare/2.3.0...2.3.2
[2.3.0]: https://github.com/itk-dev/economics/compare/2.2.0...2.3.0
[2.2.0]: https://github.com/itk-dev/economics/compare/2.1.2...2.2.0
[2.1.2]: https://github.com/itk-dev/economics/compare/2.1.1...2.1.2
[2.1.1]: https://github.com/itk-dev/economics/compare/2.1.0...2.1.1
[2.1.0]: https://github.com/itk-dev/economics/compare/2.0.0...2.1.0
[2.0.0]: https://github.com/itk-dev/economics/compare/1.1.2...2.0.0
[1.1.2]: https://github.com/itk-dev/economics/compare/1.1.1...1.1.2
[1.1.1]: https://github.com/itk-dev/economics/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/itk-dev/economics/compare/1.0.4...1.1.0
[1.0.4]: https://github.com/itk-dev/economics/compare/1.0.3...1.0.4
[1.0.3]: https://github.com/itk-dev/economics/compare/1.0.2...1.0.3
[1.0.2]: https://github.com/itk-dev/economics/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/itk-dev/economics/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/itk-dev/economics/releases/tag/1.0.0
