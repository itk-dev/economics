# Economics

Integration with project/issue tracker to ease management. The worklogs
and projects are synced from a project tracker (e.g. Jira).

The project consists of the following parts:

* Invoices: Create invoices for projects and clients. These can consist of manual
invoice entries and invoice entries created from worklogs.
* Project Billing: Automatically create invoices from a project for a given period.
* Planning: Overview of planned work for the coming sprints.
* Sprint Report: Detailed overview of the work for a given project/version.
* Projects: Overview of which projects to work with in the system.
* Project Creator: Create a new project in the Project Tracker.

## Development

Getting started:
```shell
docker compose run node npm install
docker compose up -d
docker compose bin/console doctrine:migrations:migrate
```

Set create .env.local with the following values set
```shell
###> Project tracker connection ###
PROJECT_TRACKER_URL=<VALUE>
PROJECT_TRACKER_USER=<VALUE>
PROJECT_TRACKER_TOKEN=<VALUE>
###< Project tracker connection ###

###> itk-dev/openid-connect-bundle ###
USER_OIDC_METADATA_URL=<VALUE>
USER_OIDC_CLIENT_ID=<VALUE>
USER_OIDC_CLIENT_SECRET=<VALUE>
USER_OIDC_REDIRECT_URI=https://economics.local.itkdev.dk/openid-connect/generic
###< itk-dev/openid-connect-bundle ###

APP_INVOICE_RECEIVER_ACCOUNT=<VALUE>
APP_INVOICE_DEFAULT_DESCRIPTION=<VALUE>

JIRA_API_SERVICE_CUSTOM_FIELD_EPIC_LINK=<VALUE>
JIRA_API_SERVICE_CUSTOM_FIELD_ACCOUNT=<VALUE>
JIRA_API_SERVICE_CUSTOM_FIELD_SPRINT=<VALUE>
JIRA_API_SERVICE_DEFAULT_BOARD=<VALUE>
```

Sync projects and accounts.

```shell
docker compose bin/console app:sync-projects
docker compose bin/console app:sync-accounts
```

Visit /admin/project and "include" the projects that should be synchronized in the installation.

Then sync issues and worklogs

```shell
docker compose bin/console app:sync-issues
docker compose bin/console app:sync-worklogs
```

### Assets

The node container will watch for code changes in assets folder and recompile.

## Migration path from JiraEconomics

1. Copy database from JiraEconomics.
2. Run migrate-from-jira-economics:
   ```shell
   bin/console app:migrate-from-jira-economics
   ```
   to prepare the database. This will remove a couple of tables and add the doctrine_migration_versions table
   with the Version20230101000000 migration marked as already run.
3. Execute the remaining migrations:
   ```shell
   bin/console doctrine:migrations:migrate
   ```
4. Run synchronizations:
   ```shell
   bin/console app:sync-projects
   bin/console app:sync-accounts
   ```
5. Run migrate-customer to migrate from invoice.customerAccountId to invoice.client
   ```shell
   bin/console app:migrate-customers
   ```
6. Visit /admin/project and "include" the projects that should be synchronized in the installation.
7. Synchronize issues and worklogs
   ```shell
   bin/console app:sync-issues
   bin/console app:sync-worklogs
   ```

## Production

### Deploy

```shell
composer install --no-dev -o
bin/console doctrine:migrations:migrate
```

Build the assets locally
```shell
docker compose run --rm node npm run build
```

Copy the `/public/build` folder to the server.

### Sync

Run synchronization with a cron process with a given interval to synchronize with the project tracker:
 ```shell
   bin/console app:sync
```

## APIs

This project uses issue tracker API's to create and get information about projects.

### Jira

[https://docs.atlassian.com/software/jira/docs/api/REST/9.3.0/](https://docs.atlassian.com/software/jira/docs/api/REST/9.3.0/)
[https://docs.atlassian.com/jira-software/REST/9.5.0/](https://docs.atlassian.com/jira-software/REST/9.5.0/)

### Tempo

[https://www.tempo.io/server-api-documentation](https://www.tempo.io/server-api-documentation)
