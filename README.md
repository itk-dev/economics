<!-- ABOUT THE PROJECT -->

# Economics

Economics is designed to integrate with project trackers (Jira, Leantime, etc.) to ease project management and
billing processes.

It synchronizes projects, issues and worklogs from external project trackers into its own database.

```mermaid
graph TD
;
    ProjectBilling --> Invoice; ProjectBilling --> Project; Invoice --> Project; Invoice --> Client; Invoice --> InvoiceEntry; InvoiceEntry --> Worklog; Issue --> Worklog; Issue --> Project; Issue --> Account; Client --> Account;
```

<!-- GETTING STARTED -->

# Development

## Getting Started

1. Run initialization script

```sh
task start
```

2. Open website

```sh
itkdev-docker-compose open
```

3. Login with OIDC
4. Assign roles to your user

```sh
task user:set-roles
```

5. Reload page and you should have access to everything.

6. Set create .env.local with the following values set
```

###> Project tracker connection ###
LEANTIME_PROJECT_TRACKER_URL=<VALUE>
LEANTIME_PROJECT_TRACKER_TOKEN=<VALUE>
###< Project tracker connection ###

###> itk-dev/openid-connect-bundle ###
USER_OIDC_METADATA_URL=<VALUE>
USER_OIDC_CLIENT_ID=<VALUE>
USER_OIDC_CLIENT_SECRET=<VALUE>
USER_OIDC_REDIRECT_URI=https://economics.local.itkdev.dk/openid-connect/generic
USER_OIDC_ALLOW_HTTP=<VALUE>
USER_OIDC_LEEWAY=<VALUE>
###< itk-dev/openid-connect-bundle ###

APP_INVOICE_RECEIVER_ACCOUNT=<VALUE>
APP_INVOICE_DEFAULT_DESCRIPTION=<VALUE>

###> symfony/mailer ###
MAILER_DSN=smtp://mail:1025
###< symfony/mailer ###
```

## Data

You have two options to get some data into the database.

You can either:

- [Load in fixtures](#fixtures)
- [Synchronize data](#synchronize-data) from an actual project tracker.

### Fixtures

To load fixtures

```sh
task fixtures:load
```

### Synchronize Data

#### Data provider

To synchronize data, we need to setup a data provider.

1. Create data-provider

```sh
task data-provider:create
```

2. Enter name
2. Enter the base url of the project tracker(e.g. <https://leantime.whatever.com>)
3. Enter secret
4. Use up-down arrows to select an implementation class

- A class is implemented for each supported project tracker

#### Synchronize projects

Before issues and worklogs can be synchronized, projects need to be synchronized and "included".

1. Queue projects job and consume the job

```sh
task queue:projects
task queue:consume-one
```

2. Navigate to /admin/project/ and filter by "Not included"
3. Include the projects you want to synchronize data for.

#### Synchronize issues/worklogs

After including the projects you want to synchronize data for, you can synchronize issues and worklogs.

1. Queue issues and worklogs job and consume the jobs

- Note that this might take a while.

```sh
task queue:all
task queue:consume-all
```

2. All relevant data should now be synchronized to the database.

## Assets

The node container will watch for code changes in the `assets` folder and
recompile.

Use

```sh
task watch
```

to see the compilation log, e.g. to detect errors.

## Coding standards

Each PR is reviewed with Github Actions.

Check coding standards with:

```shell
task coding-standards:check
```

Apply coding standards with:

```shell
task coding-standards:apply
```

## Testing

The test setup follows the guidelines from: <https://symfony.com/doc/current/testing.html>.

To run tests:

```shell
task tests
```

DoctrineFixtures are load each time phpunit is run.
Between each test the initial state of the database is restored using DAMADoctrineTestBundle.
