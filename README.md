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

## Entity model

```mermaid
graph TD;
   ProjectBilling-->Invoice;
   ProjectBilling-->Project;
   Invoice-->Project;
   Invoice-->Client;
   Invoice-->InvoiceEntry;
   InvoiceEntry-->Worklog;
   Issue-->Worklog;
   Issue-->Project;
   Issue-->Account;
   Client-->Account;
```

The system is build around Invoices.
Each invoice is connected to a project and consists of invoice entries.
The output is a .csv file.

Projects, clients, accounts, issues and worklogs are synchronized from a project tracker.

Invoice entries can be manual entries or connected to a number of worklogs from 
a project tracker.

Project billing can create a number of invoices for a given project for a given project.
Only issues from the project tracker that have a connected account will be included. This
is used to create invoices for the Support project, where issues are billed to different
accounts.

## Development

Getting started:
```shell
docker compose run node npm install
docker compose up -d
```

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
