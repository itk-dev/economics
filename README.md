# Economics

Integration with project/issue tracker to ease management.

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
   bin/console app:sync-worklogs
   ```
5. Run migrate-customer to migrate from invoice.customerAccountId to invoice.client
   ```shell
   bin/console app:migrate-customers
   ```

## APIs

This project uses issue tracker API's to create and get information about projects.

### Jira

[https://docs.atlassian.com/software/jira/docs/api/REST/9.3.0/](https://docs.atlassian.com/software/jira/docs/api/REST/9.3.0/)
[https://docs.atlassian.com/jira-software/REST/9.5.0/](https://docs.atlassian.com/jira-software/REST/9.5.0/)

### Tempo

[https://www.tempo.io/server-api-documentation](https://www.tempo.io/server-api-documentation)
