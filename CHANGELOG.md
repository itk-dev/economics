# CHANGELOG

All notable changes to this project will be documented in this file.

See [keep a changelog](https://keepachangelog.com/en/1.0.0/) for information about writing changes to this log.

## [Unreleased]

* Updated api source to use Leantime
* Modified getPlanningData to work with Leantime data
* Changed amount and price field to NumberType instead of IntegerType.
* Added export more options to recorded invoices overview.

## 1.0.4

* Fixed command to recalculate sums for all invoices by first calculating invoice entries.

## 1.0.3

* Changed redirect after create a manual invoice entry.
* Removed export options when client is not set.
* Added create new buttons to top of invoices and project billing lists.
* Added command to recalculate sums for all invoices.
* Changed datetime form fields to date.
* Fixed select all on worklog list.

## 1.0.2

## Changed

* Updated package-lock.json.

## 1.0.1

### Changed

* Updated openid-connect to newest version.
* Updated docker-compose files to newest version.

## 1.0.0

### Added

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

### Changed

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
* Fixed issues with create project. Javascript has been changed to use stimulus. Changed how session is accessed.
* Updated to authorization code flow.
* Changed worklog save button styling to be sticky.
