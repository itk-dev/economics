<!-- markdownlint-disable MD024 -->
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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


* RELEASE NOTES:
  * Change name APP_INVOICE_RECEIVER_ACCOUNT to APP_INVOICE_SUPPLIER_ACCOUNT in `.env.local`
  * Set APP_INVOICE_DESCRIPTION_TEMPLATE in `.env.local`
  * Set APP_INVOICE_RECEIVER_DEFAULT_ACCOUNT in `.env.local`
  * Set APP_PROJECT_BILLING_DEFAULT_DESCRIPTION in `.env.local`
  * Migrate to new DataProvider model. The purpose of this is to couple the previous Jira data synchronizations to a
    data provider in the new model.
    - Add a dataProvider for current Jira implementation with the command
    ```sh
    bin/console app:project-tracker:create
    ```
    - Run the following commands to set `data_provider_id` field in the database for existing synced entities.

      Fill in the data from the `.env.local` values for the Jira connection:
      - Name: Jira
      - Url: JIRA_PROJECT_TRACKER_URL
      - Secret: JIRA_PROJECT_TRACKER_USER:JIRA_PROJECT_TRACKER_TOKEN

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

[Unreleased]: https://github.com/itk-dev/economics/compare/1.1.2...HEAD
[1.1.2]: https://github.com/itk-dev/economics/compare/1.1.1...1.1.2
[1.1.1]: https://github.com/itk-dev/economics/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/itk-dev/economics/compare/1.0.4...1.1.0
[1.0.4]: https://github.com/itk-dev/economics/compare/1.0.3...1.0.4
[1.0.3]: https://github.com/itk-dev/economics/compare/1.0.2...1.0.3
[1.0.2]: https://github.com/itk-dev/economics/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/itk-dev/economics/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/itk-dev/economics/releases/tag/1.0.0
