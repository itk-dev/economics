# Migration path from JiraEconomics

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
