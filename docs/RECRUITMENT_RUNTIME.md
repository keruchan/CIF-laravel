# Running the migrated recruitment application

Laravel's `public/index.php` serves the application. Compatibility URLs under
`/pages/` and `/ajax/` are Laravel routes, not copied executable endpoints.
Controllers use Laravel sessions, CSRF, responses and database connections.
The original validation, workflow, formatting, calculations and renderers live in
`app/Services/Recruitment`; page views are in `resources/views/recruitment`.
Public assets retain the reference design. Vite is not required to serve them.

## Current runtime (6 November 2026)

The current vanilla changes through `e292878` have been ported. Validation evidence
and remaining external acceptance are recorded in `MIGRATION_PROGRESS.md`.

HR's **Printables** navigation (`/pages/admin/#reports`) prepares Candidates,
MRF and KPI reports as a multi-worksheet Excel download or print preview.
Filters support date coverage, all/my assigned data and candidate archive scope;
reports include every matching row, with an explicit 10,000-row limit per report.
Export and print preparation are recorded under the Reports audit category.
Each HR MRF detail now shows its own candidate funnel; requestors do not receive it.
The HR dashboard starts on All dates and KPI on Last 12 months with Hired selected.
Facebook, height and weight are optional. Candidate tracking uses Screening and
application wording and displays implied prerequisites for released later stages.
Invitation codes expire after ten days only if unused; after first use they remain
valid for resuming the form and tracking, subject to account/code access controls.
MRF numbers remain generated and immutable; imports warn about existing numbers
and require confirmation for a different request before generating a new number.

Current work is localhost only. Earlier Hostinger preparation remains a future
reference in `DEPLOYMENT_HANDOFF.md`; nothing has been deployed. The migration
guard refuses schema bootstrap over existing tables without migration history.
The local `.env` was unchanged by the table-by-table migration conversion.

- Main entry: `/`, `/index.php` and `/index.html` open the applicant account at
  `/pages/candidate/`, matching the original candidate-first entry.
- Staff portal chooser: `/pages/portal/` (HR Recruitment and MRF Requestor).
- Requestor: `/pages/mrf-requestor/`. Old Department page URLs redirect while
  preserving query parameters; the old API and `portal=department` remain aliases.
- Superadmin: `/pages/superadmin/`. It has its own role and session. Existing
  Superadmin credentials from the PHP database remain valid. HR, Requestor and
  Superadmin logins can coexist in separate tabs; the chooser grants no access.
- Active fresh isolated copy: `cif_laravel_dev_20261104_90d68738` (43 recruitment
  tables, including security/audit tables). Previous copies are preserved.
- There are now 51 native table-creation migrations and one final security/audit
  migration. Together they create 52 tables (including Laravel's migration
  repository) and 68 audit triggers. Account/access fields are defined in their
  respective table migrations. The final migration installs frozen audit SQL
  and initializes security settings; it preserves the audit rollback guard.
  Existing local history was adopted after complete schema verification, without
  changing application records. Runtime requests do not execute migrations.
- For a genuinely new installation only, `php artisan recruitment:setup-superadmin`
  can create the first separate account, saving a random temporary password in a
  private file outside the application. `--credentials-file` chooses that file;
  `--password-stdin` accepts an operator-provided password without command-line
  exposure. Reruns preserve existing credentials. This was tested on disposable
  databases; do not run it against the original PHP database.
- Source audit behavior is retained, with Laravel request paths supplying role
  attribution and Laravel middleware supplying CSRF protection (HTTP 419).

Directory URLs retain their final slash so original relative links resolve
correctly, including after legacy redirects and entry through the root chooser.
Rejected CSRF requests retain the validated actor in the audit log and return
Laravel's HTTP 419 with the existing refresh instruction.

## Local development

Use PHP 8.3+ with PDO MySQL, mbstring, fileinfo, DOM/XML, OpenSSL and Phar/ZIP,
plus MySQL/MariaDB. The inspected runtime is PHP 8.4.27 and Laravel 13.38.0.

The local `C:\php84\php.ini` now loads `php_opcache.dll` with
`opcache.enable=1`, `opcache.enable_cli=1`, `opcache.validate_timestamps=1` and
`opcache.revalidate_freq=0`. This avoids recompiling Laravel on each development
request while checking PHP files for edits every request. Restart `artisan serve`
after changing PHP runtime settings. The original INI is backed up outside the
workspace under `C:\LaravelProjects\cif-migration-backups\`.

Composer's optimized class map was rebuilt with `composer dump-autoload --optimize`.
Repeat that command after adding application classes when measuring performance.
Local config caching is not required for these improvements; leave it uncached
while editing `.env`, particularly mail settings. A first request after restarting
PHP is colder than subsequent requests. These INI changes are local-machine
settings and are not automatically installed by copying this repository.

The HR frontend starts the requested page's data load before background sidebar
counts, reducing the queue on PHP's local development server. Session validation,
CSRF, rate limits, request locking and audit checks remain active. Analytics still
reads current data rather than caching potentially stale dashboard responses.
For a repeatable isolated benchmark, run `node tests/Browser/benchmark-recruitment.cjs`.
It compares both runtimes on 1000 fictional candidates with email disabled, and
removes its generated database afterward. Results are under
`storage/app/migration-validation/20261106-performance/`.

```powershell
php artisan serve
```

- Portal chooser: `http://127.0.0.1:8000/pages/portal/`
- Candidate: `http://127.0.0.1:8000/pages/candidate/`
- HR: `http://127.0.0.1:8000/pages/admin/`
- MRF Requestor: `http://127.0.0.1:8000/pages/mrf-requestor/`
- Superadmin: `http://127.0.0.1:8000/pages/superadmin/`

Existing identities and password hashes are preserved. Requestor sign-in uses
email and does not rewrite legacy usernames. Use existing account credentials;
there is no new default password or seeded production account.

The user's choice of fresh current PHP data was carried forward on 4 November.
The private environment uses `cif_laravel_dev_20261104_90d68738`. The previous
`cif_laravel_dev_20261028_031cfcb6`, older `cif_laravel_dev` and original `cif`
remain intact. Only `DB_DATABASE` changed during the copy;
the prior private environment is backed up outside the webroot under
`C:\LaravelProjects\cif-migration-backups\`.

```powershell
php artisan recruitment:compare-data
```

This compares all 43 current recruitment tables read-only, including binary document
bytes and definitions. It excludes Laravel infrastructure tables. Differences
after actual local editing are expected; do not overwrite either dataset to make
this pass. To deliberately create another copy, this command creates a new random
database and activates it only after every row and definition matches:

```powershell
php artisan recruitment:copy-reference --source-database=cif --activate
```

Do not reset these datasets. For a genuinely empty MySQL/MariaDB database,
`php artisan migrate` creates each table through Laravel Schema Builder.
The MySQL command-line client and old schema dump are not needed for migration.
For an existing local copy needing baseline adoption, follow `database/README.md`.
Then, for an empty installation only,
`php artisan db:seed --class=RecruitmentSeeder` supplies the original built-in
templates without accounts or overwriting template edits. The starter
`DatabaseSeeder` is not used for the migrated dataset.

## Private mail and PDF configuration

No source mailbox password was copied. Sending requires `MAIL_MAILER=smtp` and
`CIF_MAIL_ENABLED=true`. Keep sending disabled during local validation.

For the user's Gmail setup, the private `.env` now uses `smtp.gmail.com`, port
587 and `MAIL_SCHEME=smtp`. Replace the commented `MAIL_USERNAME` and
`MAIL_PASSWORD` placeholders with the Gmail address and Google App Password,
keeping double quotes and removing spaces from the App Password. The sender
address uses `${MAIL_USERNAME}` automatically. Do not put credentials in this
document. Run `php artisan config:clear` after changing cached settings.
Placeholder App Passwords are refused before SMTP is contacted.

`CIF_PUBLIC_ORIGIN=http://127.0.0.1:8000` is permitted for local use. HTTP
localhost, IPv4 loopback and IPv6 loopback origins are allowed only when the app
environment is local/testing; production and other hosts still require HTTPS.
Recipients can open localhost links only on the computer running this app.
Gmail authentication and real delivery remain untested; setup did not send mail.

| Reference setting | Laravel environment setting |
| --- | --- |
| SMTP host / port | `MAIL_HOST` / `MAIL_PORT` |
| Username / password | `MAIL_USERNAME` / `MAIL_PASSWORD` |
| Sender email / name | `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` |
| SSL on port 465 | `MAIL_SCHEME=smtps`, `MAIL_PORT=465` |
| STARTTLS on port 587 | `MAIL_SCHEME=smtp`, `MAIL_PORT=587` |
| Candidate website origin | `CIF_PUBLIC_ORIGIN` (live HTTPS origin) |
| Sending enablement | `CIF_MAIL_ENABLED=true` |

The sender matches the mailbox username, as in the reference. Laravel/Symfony
Mailer retains snapshots, attempts, actors, retry safeguards and embedded branding.
Sent means service acceptance. Tracker release remains independent of acceptance,
matching the current PHP code. Replaying a release does not automatically retry mail.

PDF recognition uses `CIF_PDFTOTEXT`, otherwise the source's platform discovery
paths. This machine has `C:\Program Files\Git\ucrt64\bin\pdftotext.exe`.
DOCX recognition retains the source parser. Uploads remain private and downloads
require authorization. Staging keeps binary files on Laravel's private local disk
and only small metadata in JSON sessions, then saves unchanged original bytes.
Saved/discarded reviews and logout remove temporary files. Expired files are
pruned on imports and by `recruitment:prune-imports`, scheduled hourly through
Laravel's scheduler. Configure the scheduler as part of hosting setup.

## Validation

```powershell
php vendor/phpunit/phpunit/phpunit --no-progress
npm.cmd run test:visual
npm.cmd run test:reference -- check-dashboard-pairs
npm.cmd run test:reference -- check-requestor-edit
npm.cmd run test:reference -- check-deployed-readonly
npm.cmd run test:reference -- check-archived-invitations
npm.cmd run test:reference -- check-mrf-import
npm.cmd run test:reference -- check-superadmin
npm.cmd run test:reference -- check-department-dashboard
npm.cmd run test:reference -- check-candidate-list-stages
npm.cmd run test:reference -- check-dashboard-coverage
npm.cmd run test:reference -- check-assessment-locks
```

PHP checks include standalone rules and disposable MySQL integration tests. They
need local MySQL and permission to create/drop `cif_mrf_test_<16 hexadecimal
characters>` databases. Fixture lifecycle code rejects other names and never resets
the configured development database. Mail checks use in-memory transport or
explicit synthetic callbacks.

Browser checks use installed Microsoft Edge and `@playwright/test`. They need the
current reference (default `C:\xampp\htdocs\cif`, override with `CIF_REFERENCE_ROOT`)
for comparison scenarios/fixtures. The adapter serves Laravel's public directory,
uses its full schema, gives legacy fictional Department accounts email addresses,
and accounts for Laravel's HTTP 419 CSRF response. Rate-limit/cache data stays in
each fixture database. PDF fixtures use the installed reader.

Visual checks compare original/Laravel responses and desktop/mobile screens with
fictional records. External fonts are blocked identically and mail availability
is normalized to disabled in both browsers; private mail settings are not read or
changed. Screenshots are under `storage/app/migration-validation/20261104/visual`.
Aggregate evidence is in `docs/validation/20261104`; the original 28 October
evidence remains in `docs/validation/20261028`. The three-link chooser is an
explicit requested difference from the current source's two-link chooser.

Some older reference checks assume pre-screening saves, retired exams, pending-MRF
selection, legacy username login or superseded filters. Use the current source,
translated rules and native integration checks to resolve those obsolete assertions.

Local verification does not deploy, replace hosted data, validate production
configuration, or authorize a real email test.
