# Economics

Integration with project/issue tracker to ease management.

## Migration from previous

1. Copy database
2. Add `doctrine_migration_versions` with the first migration set as already executed.
   Can be done with the following command:
   ```
   bin/console app:migrate-from-jira-economics
   ```
3. Execute the remaining migrations:
   ```
   bin/console doctrine:migrations:migrate
   ```

## APIs

This project uses issue tracker API's to create and get information about projects.

### Jira

[https://docs.atlassian.com/software/jira/docs/api/REST/9.3.0/](https://docs.atlassian.com/software/jira/docs/api/REST/9.3.0/)
[https://docs.atlassian.com/jira-software/REST/9.5.0/](https://docs.atlassian.com/jira-software/REST/9.5.0/)

### Tempo

[https://www.tempo.io/server-api-documentation](https://www.tempo.io/server-api-documentation)
