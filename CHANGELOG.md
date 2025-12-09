<!-- markdownlint-disable MD024 -->
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

* [PR-255](https://github.com/itk-dev/economics/pull/255)
  Minor synchronization adjustments.
* [PR-252](https://github.com/itk-dev/economics/pull/252)
  Added support for new workers endpoint.
* [PR-253](https://github.com/itk-dev/economics/pull/253)
  Made worker optional on issue entity.
* [PR-242](https://github.com/itk-dev/economics/pull/242)
  Changed Leantime data provider to use apidata plugin instead of leantime api.

## [2.10.1] - 2025-11-24

* [PR-250](https://github.com/itk-dev/economics/pull/250)
  hotfix dataprovider reference

## [2.10.0] - 2025-11-19

* [PR-246](https://github.com/itk-dev/economics/pull/246)
  Minor api adjustments.
* [PR-243](https://github.com/itk-dev/economics/pull/243)
  Added CRUD for service agreements and cyber security agreements.
  Added JSON endpoint for retrieving agreements.
* [PR-240](https://github.com/itk-dev/economics/pull/240)
  Fixes and optimizations for app:sync command
* [PR-235](https://github.com/itk-dev/economics/pull/235)
  Increased timeout of nginx.
  Included archived issues in sync.
  Template files updated.

## [2.9.4] - 2025-07-10

* [PR-232](https://github.com/itk-dev/economics/pull/232)
  Leantime synchronization adjustments.

## [2.9.3] - 2025-07-08

* [PR-230](https://github.com/itk-dev/economics/pull/230)
  Switch to amqp message broker.

## [2.9.2] - 2025-06-30

* [PR-226](https://github.com/itk-dev/economics/pull/226)
  Upped memory limit for LT-sync.
  Fixed some composer-related issues.

## [2.9.1] - 2025-05-19

* [PR-225](https://github.com/itk-dev/economics/pull/225)
  Ensure current week and month shown on dashboard when no time is logged.
  Update composer dependencies, patch only.

## [2.9.0] - 2025-05-07

* [PR-223](https://github.com/itk-dev/economics/pull/223)
  Adjust cron sync interval from hourly to daily at midnight.
* [PR-222](https://github.com/itk-dev/economics/pull/222)
  Add quarter picker for unbilled billable worklogs report.
* [PR-221](https://github.com/itk-dev/economics/pull/221)
  Remove showKanban stuff from link to leantime to make it faster.

## [2.8.6] - 2025-04-07

* [PR-218](https://github.com/itk-dev/economics/pull/218)
  Included Done tasks in holiday planning overview.

## [2.8.5] - 2025-04-04

* [PR-216](https://github.com/itk-dev/economics/pull/216)
  Replaced literals with query parameters in worklog repo.

## [2.8.4] - 2025-04-03

* [PR-214](https://github.com/itk-dev/economics/pull/214)
  Explicitly set isBilled when synchronizing worklogs.
  Select isBilled=NULL when getting unbilled billable worklogs.

## [2.8.3] - 2025-03-26

* [PR-212](https://github.com/itk-dev/economics/pull/212)
  Setup auto deploy (woodpecker) for both prod sites.

## [2.8.2] - 2025-03-21

* [PR-209](https://github.com/itk-dev/economics/pull/209)
  Refactor dashboard calculations to do SUM in database and limit to days in current year.
* [PR-210](https://github.com/itk-dev/economics/pull/210)
  Increase php max execution time for supervisor container to allow for LeanTime API rate limit.
  Update github actions to use docker setup.
  Update to latest ITK docker setup.

## [2.8.1] - 2025-03-18

* [PR-208](https://github.com/itk-dev/economics/pull/208)
  Removed extra build.

## [2.8.0] - 2025-02-24

* [PR-206](https://github.com/itk-dev/economics/pull/206)
  3947: Added create release GitHub Actions workflow.
* [PR-205](https://github.com/itk-dev/economics/pull/205)
  3863: Added lock to synchronization job to avoid executing more than one sync at a time.
  3863: Moved queue monitoring to handler instead of command.
* [PR-202](https://github.com/itk-dev/economics/pull/202)
  3863: Added holiday planning.
* [PR-201](https://github.com/itk-dev/economics/pull/201)
  2299: Added project issue sync button to planning.
* [PR-200](https://github.com/itk-dev/economics/pull/200)
  3660: Adds user dashboard.
* [PR-199](https://github.com/itk-dev/economics/pull/199)
  2299: Fixed linting of javascript.
* [PR-202](https://github.com/itk-dev/economics/pull/202)
  Security updates.
* [PR-204](https://github.com/itk-dev/economics/pull/204)
  3907: Updating lastSent when running subscriptions.
* [PR-197](https://github.com/itk-dev/economics/pull/197)
  2299: Upgraded to php 8.3 and node 20.
* [PR-191](https://github.com/itk-dev/economics/pull/191)
  2299: Added project sync component to navigation.
* [PR-195](https://github.com/itk-dev/economics/pull/195)
  3602: Added billable unbilled hours report.
* [PR-196](https://github.com/itk-dev/economics/pull/196)
  3624: Correctly handling periods when viewing past workload reports.

## [2.7.0] - 2025-01-14

* [PR-194](https://github.com/itk-dev/economics/pull/194)
  2299: Added amount to invoices list. Removed data provider.
* [PR-193](https://github.com/itk-dev/economics/pull/193)
  2575: Added link to issue on hour report.
* [PR-188](https://github.com/itk-dev/economics/pull/188)
  2299: Removed sprint report.

## [2.6.1] - 2025-01-02

## [2.6.0] - 2025-01-02

* [PR-182](https://github.com/itk-dev/economics/pull/182)
  2597: Added invoicing rate report.
* [PR-185](https://github.com/itk-dev/economics/pull/186)
  2597: Added epic migration command.
* [PR-184](https://github.com/itk-dev/economics/pull/184)
  3489: Workload report period averages.
* [PR-183](https://github.com/itk-dev/economics/pull/183)
  2597: Added epic relations.
* [PR-187](https://github.com/itk-dev/economics/pull/187)
  Updated symfony bundles.
* [PR-189](https://github.com/itk-dev/economics/pull/189)
  Npm audit.
* [PR-187](https://github.com/itk-dev/economics/pull/187)
  Updated symfony bundles.
* [PR-175](https://github.com/itk-dev/economics/pull/175)
  2617: Added forecast report.

## [2.5.3] - 2025-03-17

* [PR-207](https://github.com/itk-dev/economics/pull/207)
  hotfix: Setup woodpecker workflows.

## [2.5.2] - 2025-03-17

* [PR-207](https://github.com/itk-dev/economics/pull/207)
  hotfix: Change from yarn to npm for build release

## [2.5.1] - 2024-11-26

* [PR-180](https://github.com/itk-dev/economics/pull/180)
  hotfix: Corrected from/to date check in hour report.

## [2.5.0] - 2024-10-23

* [PR-173](https://github.com/itk-dev/economics/pull/173)
  2663: Workload report loading speed improvement.
* [PR-167](https://github.com/itk-dev/economics/pull/167)
  2499: Added worker name in workload report.
* [PR-166](https://github.com/itk-dev/economics/pull/166)
  2545: Added total column for workload report.
  2545: Fixed average calculation.
* [PR-168](https://github.com/itk-dev/economics/pull/168)
  Added Game center
* [PR-164](https://github.com/itk-dev/economics/pull/164)
  3298: Added report notification subscription

## [2.4.3] - 2024-10-09

* [PR-174](https://github.com/itk-dev/economics/pull/174)
  Fixed status enum twig rendering.

## [2.4.2] - 2024-09-12

* [PR-163](https://github.com/itk-dev/economics/pull/163)
  2454: Hide done tasks in planning overview.
* [PR-159](https://github.com/itk-dev/economics/pull/159)
  2396: Added year select to planning overview.
* [PR-158](https://github.com/itk-dev/economics/pull/158)
  2299: Fixed isBillable filter for project list.
  2299: Removed unused code from planning overviews.
* [PR-157](https://github.com/itk-dev/economics/pull/157)
  2299: Npm audit fixes.
* [PR-156](https://github.com/itk-dev/economics/pull/156)
  2299: Composer update.
* [PR-155](https://github.com/itk-dev/economics/pull/155)
  2294: Added worker name field and added to planning overview.
* [PR-154](https://github.com/itk-dev/economics/pull/154)
  2265: Changed X column in external exported csv.

## [2.4.1] - 2024-09-04

* [PR-152](https://github.com/itk-dev/economics/pull/152)
  2244: Handling Leantime timestamps when importing.

## [2.4.0] - 2024-08-20

* [PR-149](https://github.com/itk-dev/economics/pull/149)
  2096: Set default dataprovider on hourReport.
* [PR-148](https://github.com/itk-dev/economics/pull/148)
  2031: Project overview standard settings.
* [PR-147](https://github.com/itk-dev/economics/pull/147)
  2033: Sync worklogs from invoice entry.
* [PR-146](https://github.com/itk-dev/economics/pull/146)
  2034: Invoice date select continuity.
* [PR-145](https://github.com/itk-dev/economics/pull/145)
  2059: Specify workload report week definition.
* [PR-143](https://github.com/itk-dev/economics/pull/143)
  2050: Hour-report issue duedate ignore.
* [PR-142](https://github.com/itk-dev/economics/pull/142)
  2041: Revise Leantime issue status sync.
* [PR-138](https://github.com/itk-dev/economics/pull/138)
  1867: Issue status as enum.
* [PR-135](https://github.com/itk-dev/economics/pull/135)
  1772: Removed views.
* [PR-136](https://github.com/itk-dev/economics/pull/136)
  1774: Planning view use service.
* [PR-137](https://github.com/itk-dev/economics/pull/137)
  1812: Minor hour report improvements.
* [PR-134](https://github.com/itk-dev/economics/pull/134)
  1632: Remove team report.
* [PR-133](https://github.com/itk-dev/economics/pull/133)
  1742: Simplified hour report form.
* [PR-132](https://github.com/itk-dev/economics/pull/132)
  1742: Fixed synchronization issues.
* [PR-128](https://github.com/itk-dev/economics/pull/128)
  1595: Added retryable http client decorator for handling rate limiting.
* [PR-117](https://github.com/itk-dev/economics/pull/117)
  1211: Added hour report
  NOTE: APP_DEFAULT_PLANNING_DATA_PROVIDER has been changed to APP_DEFAULT_DATA_PROVIDER. This has to be changed when releasing.
* [PR-124](https://github.com/itk-dev/economics/pull/124)
  710: Added workload report
* [PR-129](https://github.com/itk-dev/economics/pull/129)
  1632: Added invoicing rate view to workload report

## [2.3.3] - 2024-07-10

* [PR-141](https://github.com/itk-dev/economics/pull/141)
  Data provider stuff

## [2.3.2] - 2024-07-05

* [PR-140](https://github.com/itk-dev/economics/pull/140)
  1768: Added link to invoice entry that binds worklog.

## [2.3.1] - 2024-07-05

* [PR-139](https://github.com/itk-dev/economics/pull/139)
  1890: Added check that issue exists before adding worklog to database.

## [2.3.0] - 2024-06-03

* [PR-126](https://github.com/itk-dev/economics/pull/126)
  1590: Added worklog product as prefix on product invoice entries
* [PR-125](https://github.com/itk-dev/economics/pull/125)
  1547: Set account based on invoice entry type
* [PR-123](https://github.com/itk-dev/economics/pull/123)
  1544: Allowed invoicing issues with products and no worklogs
* [PR-122](https://github.com/itk-dev/economics/pull/122)
  1547: Added invoice entry account selector
* [PR-121](https://github.com/itk-dev/economics/pull/121)
  1485: Fixed floating number issues
* [PR-120](https://github.com/itk-dev/economics/pull/120)
  1484: Cleaned up worklog cleanup
* [PR-118](https://github.com/itk-dev/economics/pull/118)
  1485: Made product quantity floatable

## [2.2.0] - 2024-05-06

* [PR-114](https://github.com/itk-dev/economics/pull/114)
  1258: Clean up planning view ui and add scroll to active sprint.
* [PR-112](https://github.com/itk-dev/economics/pull/112)
  1280: Simplified planning form. Added default value.
* [PR-113](https://github.com/itk-dev/economics/pull/113)
  Worklog period filter
* [PR-110](https://github.com/itk-dev/economics/pull/110)
  1209: No cost invoices
* [PR-111](https://github.com/itk-dev/economics/pull/111)
  1208: Restored exported data column
* [PR-107](https://github.com/itk-dev/economics/pull/107)
  1213: Fixed handling of filter value
* [PR-109](https://github.com/itk-dev/economics/pull/109)
  1207: Added invoice query
* [PR-108](https://github.com/itk-dev/economics/pull/108)
  1208: Changed default sorting of recorded invoices
* [PR-106](https://github.com/itk-dev/economics/pull/106)
  1202: Handled worklog deletions
* [PR-115](https://github.com/itk-dev/economics/pull/115)
  1270: Planning hoursRemaining source change

## [2.1.2] - 2024-04-16

* [PR-104](https://github.com/itk-dev/economics/pull/104)
  1174: Fixed datetime format in Leantime API calls
* [PR-103](https://github.com/itk-dev/economics/pull/103)
  1169: Made sure that Leantime issues have at most one version (milestone)
* [PR-102](https://github.com/itk-dev/economics/pull/102)
  1157: Updated external billing export

## [2.1.1] - 2024-04-04

* [PR-100](https://github.com/itk-dev/economics/pull/100)
  1111: Fixed fetching timesheet data from Leantime

## [2.1.0] - 2024-03-27

* [PR-98](https://github.com/itk-dev/economics/pull/98)
  Replaced Tom Select with Stimulus
* [PR-97](https://github.com/itk-dev/economics/pull/97)
  Twig CS Fixer
* [PR-96](https://github.com/itk-dev/economics/pull/96)
  Miscellaneous fixes
* [PR-86](https://github.com/itk-dev/economics/pull/86)
  Added products.
* [PR-95](https://github.com/itk-dev/economics/pull/95)
  Updated bank holiday helper
* [PR-94](https://github.com/itk-dev/economics/pull/94)
  Updated data in external invoicing
* [PR-93](https://github.com/itk-dev/economics/pull/93)
  Made price on client optional
* [PR-87](https://github.com/itk-dev/economics/pull/87)
  Fixed Leantime API request
* [PR-91](https://github.com/itk-dev/economics/pull/91)
  Updated standard price on clients
* [PR-89](https://github.com/itk-dev/economics/pull/89)
  Cleaned up Twig templates.
* [PR-88](https://github.com/itk-dev/economics/pull/88)
  Miscellaneous clean-ups.

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

[Unreleased]: https://github.com/itk-dev/economics/compare/3.0.0...HEAD
[3.0.0]: https://github.com/itk-dev/economics/compare/2.9.4...3.0.0
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
