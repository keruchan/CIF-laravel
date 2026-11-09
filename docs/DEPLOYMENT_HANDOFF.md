# Laravel deployment handoff

Prepared locally on 4 November 2026. No release was uploaded, no hosted database
was changed and no live email was sent. Hostinger support, in the reply supplied
by the user, identified the account as **Unlimited Web Hosting** for `ccsbsis.com`
and confirmed TRIGGER privilege for its database users. The user selected
**`https://recruitment.ccsbsis.com`** as the separate recruitment portal. Enabled
server access and the actual subdomain directory are still to be confirmed.
The commands below are an operator procedure for review, not a record of actions
already performed.

## Hostinger account confirmation

The current Superadmin audit implementation uses 68 SQL triggers. On 4 November,
the user supplied Hostinger support's account-specific confirmation that the
database users assigned to `ccsbsis.com` can create and run triggers. This resolves
the earlier permission question; keep the existing audit implementation intact.
Actual trigger installation and execution still need validation in the separate
hosted staging database using the database user assigned to this Laravel app.

Hostinger's generic
[database import instructions](https://www.hostinger.com/support/1864324-how-to-upload-and-set-up-your-database-at-hostinger/)
exclude trigger definitions. The later account-specific support response supplied
by the user takes precedence for this plan. Do not remove the triggers to follow
the generic import guidance. Trigger definitions use the target account's database
user rather than a hardcoded source DEFINER. No MySQL Event Scheduler is needed:
cleanup uses Laravel's PHP scheduler invoked by a hosting cron job.

The prepared environment template is `docs/deployment/hostinger.env.example`.
Copy it to the private hosted application's `.env`, then fill the actual hPanel
database settings and an appropriate application key privately. It is not the
local environment and contains no working passwords. Host-only session cookies
keep the portal's sessions separate from other sites on `ccsbsis.com`.

Hostinger documents [SSH-related access on Premium and above](https://www.hostinger.com/support/which-file-transfer-and-server-access-options-are-supported-at-hostinger/)
and [preinstalled Composer versions on those plans](https://www.hostinger.com/support/8727597-how-to-install-composer-locally-at-hostinger/).
Confirm access is enabled and select Composer 2. Set PHP 8.3 or later for the
website, then separately check CLI PHP; the versions can differ. This project was
tested on PHP 8.4.27. Hostinger's
[PHP version instructions](https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/)
explain the hPanel selector, and its
[PHP/SSH guide](https://www.hostinger.com/in/tutorials/how-to-check-your-php-version/)
explains the separate CLI version.

For PDF import, `proc_open` must be available and a suitable `pdftotext` binary
must exist. Hostinger states that `proc_open` is disabled on new accounts and can
be enabled in the PHP options; see
[disabled PHP functions](https://www.hostinger.com/support/3212034-how-to-enable-disabled-php-functions-in-hostinger/).
Confirm binary availability with support. Word import does not need that binary.

If this plan fixes the document root at `public_html`, use this private layout:

```text
CONFIRMED-SUBDOMAIN-PARENT/
  recruitment-app/       complete Laravel project and private .env
  public_html/           contents of recruitment-app/public only
```

Copy the public assets and corrected `.htaccess` to `public_html`. Use the prepared
`docs/deployment/hostinger-public-index.php.example` as that directory's `index.php`,
after confirming the sibling application directory name. It loads the private
application and points Laravel's public asset path at `public_html`. The actual
subdomain root may use another folder name; confirm it in hPanel before applying
the template. Keep both
public asset copies synchronized per release. No public storage link is needed
for the migrated private documents. The template is prepared locally; this layout
has not yet been exercised on the user's Hostinger account.

## Release and server layout

Keep the current vanilla website available while preparing a separate Laravel
release directory and a separate database. Set the selected domain's document
root to that release's **`public/` directory**. The project root, `.env`, database
exports, private storage, tests and migration documents stay outside the webroot.

The application uses root-relative routes and an origin-only candidate portal
URL. Use a domain or subdomain whose root points to `public/`; a shared subdirectory
installation needs its own URL/cookie review before cutover.

The inspected app requires PHP 8.3+, PDO MySQL, mbstring, fileinfo, DOM/XML,
OpenSSL and the other extensions required by `composer.lock`. Confirm MySQL or
MariaDB and permission to create/alter tables and install triggers. Native
table-by-table migrations use PDO MySQL; running them does not require the
`mysql` command-line client. Backup/restore tools are a separate requirement.

Word import uses the existing DOCX parser. PDF import needs a working `pdftotext`
binary and `proc_open`; configure `CIF_PDFTOTEXT` privately on the server. Allow
uploads of at least 2 MB and a larger POST envelope. Database packet limits must
also accommodate a saved document and its SQL payload. Test the actual hosting
limits with fictional uploads before accepting the release.

On Apache, use the included `public/.htaccess` with `mod_rewrite` and the required
override permission. Its portal exception preserves directory slashes and avoids
a redirect loop with Laravel. For Nginx or IIS, configure the equivalent front
controller behavior and preserve these directory URLs:
`/pages/admin/`, `/pages/mrf-requestor/`, `/pages/superadmin/`,
`/pages/candidate/` and `/pages/portal/`.

## Private configuration and dependencies

Install dependencies from the lock file in the new release directory:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
```

Set the private environment for this release. Use `APP_ENV=production`,
`APP_DEBUG=false`, the final HTTPS `APP_URL` and `CIF_PUBLIC_ORIGIN`, and the new
Laravel database connection. Use an appropriate unique session cookie name with
`SESSION_SECURE_COOKIE=true`. Preserve an existing Laravel `APP_KEY`; generate
one only for a genuinely new Laravel environment. Do not rotate it on each release.

Keep `CIF_MAIL_ENABLED=false` during restore, validation and smoke checks.
The app's current mail behavior and required SMTP settings are documented in
`RECRUITMENT_RUNTIME.md`. Credentials belong only in private server configuration.
Do not copy the local environment wholesale to a hosted system.

The web/PHP account needs write access to `storage/` and `bootstrap/cache/`.
Private import staging is in `storage/app/private`; do not publish it through a
storage symlink. The recruitment assets are already in `public/css`, `public/js`
and `public/assets`; Vite is not needed to serve the migrated portals.

Avoid the starter `composer setup` workflow for a migrated installation: it
generates a key and runs migrations as part of setup. Use explicit reviewed steps.

## Choose the correct database procedure

**Existing recruitment data:** prepare a verified, isolated full Laravel database
image from a current snapshot of the authoritative vanilla data. The image must
include the Laravel infrastructure tables and migration history, all recruitment
records, security tables and audit triggers. The local copy tool demonstrates
this sequence: it runs the native migrations in a new empty target first, copies
every source table and row, verifies them, then installs the source triggers. It never
replaces an existing database. It is restricted to local development and is not
a hosted cutover command.

Back up the authoritative source database, original website/files and private
configuration first. Keep exports private and include binary documents, routines,
events and triggers where present. Review trigger ownership/DEFINER compatibility
for the target host with its database operator. Restore the verified **full
Laravel image** into a new empty hosted target, preserving migration history.
Compare record/relationship counts, row and document hashes, IDs, password hashes,
audit history and trigger coverage against that same source snapshot.

Do not import raw vanilla tables into a database without migration history and
then run `artisan migrate`: the table-creation migrations require an empty target
or verified baseline adoption.
The application now refuses that sequence before changing tables. If the guard
fires, preserve the database and follow the full-image preparation procedure;
do not bypass it by inventing migration records. Never run `migrate:fresh`, reset,
rollback, demo seeders or the default `DatabaseSeeder` against existing records.

Once the restored target has its verified migration history, the release's
additive migrations can run:

```sh
php artisan migrate:status
php artisan migrate --force
```

**Genuinely empty installation only:** create its empty database first, configure
that target privately, then run:

```sh
php artisan migrate --force
php artisan db:seed --class=RecruitmentSeeder --force
php artisan recruitment:setup-superadmin --credentials-file=/PRIVATE/PATH/new-superadmin.txt
```

This creates 52 tables and 68 audit triggers, seeds the eight built-in templates
and creates a separate first Superadmin. It creates no demo HR/requestor/candidate
records. Setup repeats preserve credentials and customized templates. Store the
one-time credentials file outside both the application and the website, then
change the temporary password through the portal. Existing migrated Superadmin
accounts retain their original credentials and do not need to be recreated.

## Runtime and acceptance

After private configuration and the verified data restore, prepare caches in the
new release directory:

```sh
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Configure the host scheduler to run `php artisan schedule:run` every minute from
the project directory. This runs the hourly cleanup of expired private upload
reviews. Validate the PDF reader and scheduler under the actual server account.

On a separate staging target, verify sign-in and independent sessions for HR,
Requestor and Superadmin, candidate invitation/form/tracker access, account
revocation, protected records/downloads, requests/approvals, imports, pooling,
date filters, assessments/deployment locks, audit history and print/mobile views.
Use fictional records and keep mail disabled for these checks.

A real SMTP acceptance check requires an explicitly approved recipient and
message. Enable sending only for that controlled check, verify provider acceptance
and attempt history, and remember that acceptance does not prove inbox delivery.

## Cutover and rollback for operator approval

1. Record the original document root/release, authoritative database name,
   private environment and a tested restore location. Keep the original system
   available until the new release has passed staging acceptance.
2. Pause source writers and scheduled jobs for the final snapshot. Build and
   verify the final full Laravel image from that snapshot; earlier development
   comparisons do not validate a later production dataset.
3. Restore into a new target, run the approved additive migrations, then switch
   the document root/release and its private database setting as one cutover.
   Verify HTTPS, cookies, portal redirects, sign-in, records, imports and counts
   before reopening writes. Enable real mail only when separately accepted.
4. If rollback is needed before new writes are accepted, return the document root
   and configuration to the recorded original system. Keep the failed target and
   logs for analysis. Do not drop tables or run migration rollback commands.
5. If the new system has accepted writes, pause both systems and reconcile those
   records before switching back; an old backup alone would lose new activity.

No switch, restore into hosting or real email action is authorized by this
document. The hosting-specific commands and cutover window remain to be agreed.
