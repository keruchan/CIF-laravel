# Recruitment database migrations

Updated 4 November 2026. Current work is localhost only.

## Native Laravel structure

`migrations/` contains 51 table-creation migrations: 43 recruitment tables and
eight Laravel infrastructure tables. Each table has its own anonymous migration
class, `Schema::create()` definition and `Schema::dropIfExists()` rollback.
Laravel creates its own `migrations` bookkeeping table, giving 52 tables total.
The files are ordered so referenced tables exist before their foreign keys.
Original column types, defaults, collations, indexes and relationship rules are
preserved, including the MEDIUMBLOB used for original uploaded documents.

The 52nd migration, `2026_11_04_000000_add_recruitment_security_and_audit.php`,
initializes security settings and installs the 68 audit triggers from the frozen
`triggers/2026_11_04_recruitment_audit.php` snapshot. Trigger definitions use SQL
because Laravel's Schema Builder has no trigger API. Migrations no longer call
runtime security/audit installers or load a SQL dump.

The final audit migration deliberately refuses rollback to preserve audit history.
Do not run `migrate:fresh`, `migrate:reset` or rollback against existing records.
Add separate, dated migrations for future schema changes.

## Empty installation

Create an empty MySQL/MariaDB database and configure its connection privately:

```sh
php artisan migrate
```

This runs all 52 migrations. Repeating it reports nothing to migrate. PDO MySQL
and database permissions for tables and triggers are required; the `mysql`
command-line client is no longer required to run these migrations. The same
Artisan command can run in a PHP container configured for MySQL/MariaDB. No Docker
configuration was added or container execution tested in this task.

On a genuinely new install, `php artisan db:seed --class=RecruitmentSeeder` adds
eight built-in email templates without overwriting edits. The separate
`recruitment:setup-superadmin` command creates the first administrator if none
exists. There are no seeded demo accounts. Recruitment integration tests require
an isolated MySQL/MariaDB database; the recruitment schema is not SQLite compatible.

## Existing local data

The active isolated copy is `cif_laravel_dev_20261104_90d68738`. Its original four
migration records were preserved and 48 native baseline records adopted after
verification. No existing application rows, table definitions or triggers were
changed. The original `cif` database and earlier development copies remain intact.

For another existing isolated local copy with this exact schema, preview first:

```sh
php artisan recruitment:adopt-migrations
php artisan recruitment:adopt-migrations --apply
php artisan migrate
```

Adoption verifies all 52 managed table definitions and 68 expected audit triggers
against `schema/recruitment-schema.json`. Mismatches are refused without writing
history. Apply records only the frozen baseline migrations, leaving future
migrations pending. It is limited to local/testing isolated database names and
preserves the existing rollback guard. It is not a hosted deployment command.

An existing database with tables but no Laravel migration history is refused by
the migration guard before bootstrap. Never invent history or import data over
a populated database to bypass this check. `recruitment:copy-reference` creates
a unique new database with native migrations, copies and verifies the source
records read-only, then restores its audit triggers. Existing databases are not
replaced; activation requires its explicit `--activate` option.

## Legacy reference and recovery

The former auto-loaded dump was moved to `reference/legacy-mysql-schema.sql`.
It is a schema-only historical fixture, not a data backup. Only the legacy test
fixture loader uses it, allowing independent comparison with native installations.
Laravel does not auto-load files from that directory.

Validation and the migration checklist are in `../docs/MIGRATION_PROGRESS.md`.
Before this conversion, database code, tests and documentation were preserved at
`C:\LaravelProjects\cif-migration-backups\20261104-native-migrations-131623`.
That directory also contains the active database's original migration history.

Historical full data backups from the October 27 transfer remain outside both
webroots at `C:\LaravelProjects\cif-migration-backups\20261027-143119\`:
`cif-before.sql`, `cif_laravel_dev-before.sql` and `verification.json`.
They contain private application data; keep them outside public assets and source
control. Restore only into a separate empty recovery database.
