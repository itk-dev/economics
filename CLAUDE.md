# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Environment

All commands run inside Docker containers. Common workflows are wrapped in
`Taskfile.yml` and invoked via [go-task](https://taskfile.dev) (`task <name>`).
Run `task` with no arguments to list everything. The underlying composer/npm
scripts still work for CI.

**All PHP and Node commands must run through their containers** — never on the
host. Use the `task` wrappers (`task composer -- …`, `task phpfpm -- …`,
`task node -- …`) or one of the higher-level tasks listed below. Bare
`composer`, `php`, `phpunit`, `bin/console`, `node`, `npm`, `npx`, `yarn`, and
`pnpm` invocations are routed to a permission prompt by `.claude/settings.json`
to catch accidents.

One-time stack setup (network, pull, install deps, run migrations):

```bash
task setup
```

Day-to-day:

```bash
task compose -- up -d          # start the stack in the background
task compose -- down           # stop the stack
task phpfpm -- sh              # shell inside the phpfpm container
task compose -- <args>         # any docker compose command
task composer -- <args>        # composer inside phpfpm
task node -- <args>            # one-off node container command
```

## Common Commands

### PHP

```bash
task test                              # run the PHP test suite
task test:coverage                     # tests + HTML coverage in coverage/
task test:file -- tests/Service/X.php  # run a single test file
task test:coverage:check               # enforce coverage threshold (currently 62%)
task code-analysis                     # PHPStan static analysis
task coding-standards:apply            # auto-fix PHP + Twig + JS + Markdown
task coding-standards:check            # check PHP + Twig + JS + Markdown
task coding-standards:php:apply        # auto-fix PHP + Twig only
task coding-standards:php:check        # check PHP + Twig only
task prepare-code                      # all pre-commit checks (cs + analysis + tests)
task fixtures:load                     # load data fixtures (prompts)
task db:migrate                        # apply Doctrine migrations (prompts)
```

### JavaScript / Assets

```bash
task assets:dev                  # build assets for development
task assets:watch                # watch and rebuild on change
task assets:build                # production asset build
task coding-standards:js:apply   # auto-fix JS + Markdown coding standards
task coding-standards:js:check   # check JS + Markdown coding standards
```

### Messenger (async jobs)

```bash
task messenger     # consume messages from the `async` transport
```

## CI Requirements

PRs are validated by GitHub Actions workflows under `.github/workflows/` (entry points include `pr.yml`, `composer.yaml`, `php.yaml`, `twig.yaml`, `javascript.yaml`, `markdown.yaml`, `changelog.yaml`). All of the following must pass:

- Composer validate, normalize, and audit
- Doctrine schema validation
- PHPStan static analysis
- PHP-CS-Fixer coding standards
- Twig coding standards
- ESLint (zero warnings)
- Markdownlint
- PHPUnit test suite
- **CHANGELOG.md must be updated in every PR**

## Architecture

**Symfony 7.4 application** (PHP 8.3+) for project economics management — invoicing, project billing, planning, and reporting. Integrates with external project trackers (Leantime, legacy Jira).

### Key layers

- **Controllers** (`src/Controller/`) — thin HTTP handlers with attribute-based routing. All routes under `/admin/` require authentication.
- **Services** (`src/Service/`) — business logic (billing, reports, planning, data sync).
- **Entities** (`src/Entity/`) — Doctrine ORM entities. Most extend `AbstractBaseEntity` which provides id, timestamps (Gedmo), and blameable fields. Entities synced from external trackers use `SynchronizedEntityTrait` and `DataProviderTrait`. Traits live under `src/Entity/Trait/`.
- **Repositories** (`src/Repository/`) — Doctrine data access layer.
- **Models** (`src/Model/`) — DTOs and view models, organized by domain (invoices, planning, reports). Data-provider DTOs are under `src/Model/DataProvider/`.
- **Forms** (`src/Form/`) — Symfony Form types for entities and report filters.
- **Enums** (`src/Enum/`) — PHP 8.1 backed enums for roles, statuses, types.
- **Commands** (`src/Command/`) — CLI commands for data sync (`SyncCommand`, `SyncModifiedCommand`, `SyncDeletedCommand`), imports, and admin tasks.
- **EventListeners** (`src/EventListener/`) — Doctrine and kernel event hooks.
- **Security** (`src/Security/`) — auth voters and security-related services.
- **Twig extensions** (`src/Twig/`) — custom filters/functions.

### Async processing

Symfony Messenger with AMQP/RabbitMQ. Messages in `src/Message/` with handlers in `src/MessageHandler/`. Used primarily for data synchronization from external project trackers (upsert projects, issues, worklogs, workers, versions).

### Data providers

`DataProviderInterface` (`src/Interface/`) defines the contract; `LeantimeApiService` (`src/Service/`) is the active implementation, communicating through the [ITK-Leantime/data-api](https://github.com/ITK-Leantime/data-api) plugin. DTOs live under `src/Model/DataProvider/`.

### Authentication

Azure OpenID Connect via `itk-dev/openid-connect-bundle`. Role hierarchy: `ROLE_ADMIN` > `ROLE_USER`, with specialized roles (`ROLE_INVOICE`, `ROLE_PROJECT_BILLING`, `ROLE_PLANNING`, `ROLE_REPORT`, `ROLE_PRODUCT_MANAGER`).

### Frontend

Webpack Encore with TailwindCSS, Stimulus controllers (`assets/controllers/`), and FontAwesome icons. Sass for additional styling.

### Database

MariaDB with Doctrine ORM. Custom Europe/Copenhagen DateTime types in `src/Doctrine/`. Uses custom DQL functions: `MONTH()`, `WEEK()`, `WEEKOFYEAR()`, `YEAR()`.

## Coding Standards

- **PHP:** `@Symfony` rules via php-cs-fixer (`.php-cs-fixer.dist.php`)
- **Static analysis:** PHPStan level 8 (`phpstan.dist.neon`)
- **JavaScript:** Airbnb + Prettier via ESLint (`.eslintrc`)
- **Twig:** twig-cs-fixer
- **Markdown:** markdownlint-cli
- **Editor:** 4 spaces, UTF-8, LF line endings (`.editorconfig`)
