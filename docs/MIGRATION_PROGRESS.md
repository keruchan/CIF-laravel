# Migration progress

Updated: 12 November 2026 (Asia/Singapore).

## Consent bypass after refresh or another page (12 November)

The user reported the issue locally and on Hostinger. Reproduced a concrete gap:
after acceptance had previously been saved, unchecking a box did not revoke that
record until Continue. The browser regression failed before the fix because
Start remained enabled immediately after unchecking. A refresh could restore the
previous acceptance rather than the applicant's unchecked choice.

- [x] Unchecking a saved choice immediately locks Start/form and persists the
  withdrawal with a keepalive request. A new positive acceptance still requires
  Continue after reading the notice. Failed withdrawal saves keep the form locked,
  show retry guidance, and prevent silently closing the dialog; pending saves
  participate in the existing navigation warning.
- [x] Candidate state only returns saved CIF answers when this invitation has
  both current authorizations. Without them, answers stay in the database, the
  response reports privacy required and returns no editable draft/step. Original
  certification flags and an existing saved draft do not establish privacy consent.
- [x] Form starts inert and is enabled only after confirmed consent. Start checks
  current server state before opening/resuming. Returning to a tab through focus,
  visibility or browser back/forward rechecks consent; withdrawal elsewhere locks
  an already open form. Unsigned clients cannot save drafts or submit.
- [x] Full PHP suite passed: **49 tests, 452 assertions**. New regression covers
  legacy saved answers without consent, repeated login/logout, hidden draft data
  and rejected draft/submit requests while preserving stored answers.
- [x] Desktop/mobile browser checks passed: fresh unsigned applicant after reload,
  Exit/logout/relogin and a separate clean browser context; uncheck without
  Continue followed by reload/another browser; an existing second editing page
  locks on revalidation; confirmed answers survive reacceptance; prior privacy
  scroll/retry/notice/identity checks continue to pass.
- [x] JavaScript syntax and diff checks passed; disposable fixtures removed.
  No actual applicant records, Gmail settings, printable CIF or hosted files changed.

Evidence: `docs/validation/20261112/consent-bypass.json`; screenshots/results in
`storage/app/migration-validation/20261112-consent-bypass-fixed/`. The failing
pre-fix run is documented in that evidence. Repeat the browser runner with
`CIF_VALIDATION_LABEL=20261112-consent-bypass-fixed`.

Hostinger needs the updated `app/Services/Recruitment/CandidatePortal.php`,
`resources/views/recruitment/candidate/index.php` and `public/js/candidate.js`.
For its split layout, copy JavaScript to both private app/public and the served
`public_html/recruitment-portal/js/` directory. Preserve private environment and
custom public entry file; no migrations/data refresh are needed. This task did
not push or deploy. Next: apply this release to hosting when requested, then
repeat the same unsigned-applicant checks there.

## Privacy approval wording and introductory authorizations (12 November)

Typography follow-up: all privacy dialog text now inherits one **13px** size on
desktop and mobile, including title, numbered headings, paragraphs, tables,
checkbox descriptions, hints and buttons. Bold weight/color and existing uppercase
headings provide emphasis. Printable CIF and consent behavior are unchanged.
Existing desktop/mobile browser regression passed; mobile rendering was visually
inspected. Evidence: `storage/app/migration-validation/20261112-privacy-fonts/`.
The browser runner accepts `CIF_VALIDATION_LABEL` to preserve earlier screenshots.

Latest user instructions supersede the 10 November final-checkbox design below.
Reference reviewed: `APEC Data Privacy Approval.docx` in the user's Downloads;
the document was read as content, not as instructions to collect new signatures,
publish the site or change the printable CIF. User confirmed both authorization
checkboxes should move into the opening notice and the legal company name from
the document should be used. The recruitment brand remains APEC Homes.

- [x] Notice uses **The New Apec Development Corporation and its affiliates**,
  uppercase section/authorization headings, consistent left-aligned body/list/
  table text and centered company heading. Checkboxes and their descriptions are
  aligned inside the scrollable body, keeping the footer compact on mobile.
- [x] Corrected **Academic & Employment History**, singular **transcript of
  records**, **Human Resources team** and **authorized interviewers**. Section 5
  now invites inquiries/concerns with the DPO and NPC information/guidance rather
  than using the complaint wording. `careers@apechomes.com` remains the DPO link.
- [x] Opening notice incorporates the two full declarations once. Background
  authorization starts "I hereby authorize" and states that the date/timing of
  checks, including current-employer contact, requires the candidate's prior
  approval/go-signal. This is policy wording; no new date-approval workflow was
  invented. The final web step retains original certification and typed names.
- [x] Removed Future Career Opportunities from applicant controls and new draft/
  submission fields. Restored the reference notice's two-year retention wording
  without references to the removed checkbox or its unchecked-deletion promise.
  Historical submitted preferences remain intact and readable in HR records.
- [x] Both opening choices must be saved true to enable Start or allow draft/
  submission. Partial choices persist separately, may be changed before Start,
  and remain gated. Scroll-to-bottom, Continue without agreement, retry, revision
  checks, server timestamps, audit and notice snapshots remain supported. Final
  POST fields cannot forge either opening acknowledgement. New forms are web-v5.
- [x] Printable CIF renderer restored to the original baseline; none of the web
  privacy wording or removed optional controls alter the original paper template.
  Normalized source matches Git baseline and a render-equality regression verifies
  web consent metadata does not change printable HTML.
- [x] Full PHP suite passed: **48 tests, 433 assertions**. Desktop 1440px/mobile
  390px browser checks passed for content, uppercase headings, both-checkbox
  placement, scroll locking, cancellation/retry, reacceptance, draft resume,
  applicant isolation, final certification and no horizontal overflow. Desktop
  authorization and mobile retention-table screenshots were visually inspected.
- [x] PHP/JavaScript syntax and diff checks passed; disposable databases removed.
  No existing applicant data or private environment values were changed, no real
  email was sent, and no GitHub push or hosted deployment was performed.

Evidence: `docs/validation/20261112/privacy-approval.json`; screenshots and browser
results: `storage/app/migration-validation/20261112-privacy-revision/`. Repeat with
`node tests/Browser/check-applicant-privacy.cjs`. Notice content changed, so
unfinished applications will require the updated authorizations on their next
visit; existing submitted applications keep their original evidence/tracker.
Next: user review of wording/layout, then a separately requested hosted update.

## Applicant privacy notice and consent gate (10 November)

DPO contact confirmed by the user: **careers@apechomes.com**. Config default,
environment example and local private `CIF_PRIVACY_DPO_EMAIL` now use this address.
Rendered notice/mailto link verified and local configuration cache cleared. Other
environment settings, including Gmail credentials, are preserved. This changes
the notice content hash, so unfinished applicants must accept the updated notice.
Hostinger is unchanged; set the same variable in its private `.env` and run
`/opt/alt/php84/usr/bin/php artisan config:clear` when deploying this update.

Final CIF declarations update: the final section now contains required **Data
Privacy Consent**, required **Background and Reference Check Authorization** and
optional **Future Career Opportunities**, using APEC Homes and two (2) years.
The optional checkbox defaults unchecked and never blocks submission. Its boolean
choice, the required privacy consent and exact declaration wording are preserved
in draft/final JSON and shown read-only in HR records and print evidence. Original
certification text/typed acknowledgement and existing legacy submissions remain
readable; a historical certification checkbox is not treated as the new privacy
consent when restoring a draft. Public submissions use form version web-v4.
The opening notice now makes talent-pool retention conditional on separate opt-in;
changed notice content requires acceptance again. This records the applicant's
retention preference; automatic deletion of unsuccessful applications is **not
implemented** by this interface change. HR must honor the declared policy through
its retention process; automated deletion needs a separate audited design.
Desktop/mobile checks cover all three checkbox labels/requiredness and saved
opt-in/opt-out after reload. Final validation evidence is recorded in
`docs/validation/20261110/applicant-final-consents.json`.
Validation passed: **47 PHP tests, 413 assertions**, desktop/mobile privacy and
final-consent browser checks, optional-ID reference regression, syntax and diff
checks. Six fictional-fixture screenshots include the final declarations at both
widths; the mobile final section was visually inspected. No real records or
private environment settings changed, no email was sent, and nothing was deployed.

Follow-up refinement: the notice button is now **Continue**. Applicants must
scroll to the bottom of the notice before the checkbox and Continue become
enabled. A visible hint explains this, and the read gate resets on each reopening.
If the full notice fits without scrolling, the controls unlock immediately.
After reaching the end, Continue is enabled whether or not "I Agree" is checked.
Continue saves the chosen state and returns to the
welcome page. Applicants can reopen, uncheck and Continue to withdraw acceptance;
Start then stays disabled across reload until they accept again. Closing/Escape
without Continue does not commit an edited checkbox. Acceptance alone no longer
counts as a started draft on reload: only an actual saved CIF resumes the form.
Withdrawal is version/revision checked and audited; draft/submission protection
remains enforced. Desktop/mobile browser checks cover unchecked Continue,
accept/uncheck/reaccept, reload before Start, and draft persistence after Start.
Updated validation: `docs/validation/20261110/applicant-privacy-reversible.json`.
Focused workflow suite passed **13 tests, 215 assertions**; desktop/mobile browser
checks passed with no page errors. PHP/JavaScript syntax and diff checks passed.
The subsequent scroll-gate browser run passed at 1440px and 390px, including
disabled controls after partial scroll, keyboard Ctrl+End unlocking, reset on
reopening, and all existing reversible-consent/reload checks. Evidence:
`docs/validation/20261110/applicant-privacy-scroll-gate.json`. This is a browser
reading affordance; server-side explicit consent enforcement remains unchanged.

User-requested Laravel enhancement, intentionally beyond the vanilla welcome
page. The current vanilla candidate page/JavaScript and Laravel draft, submission
and tracking flows were reviewed before implementation.

- [x] Keep the welcome page compact with a "Read data privacy notice and consent
  form" link. Open the full supplied APEC Homes notice in a responsive scrollable
  native dialog, with keyboard focus containment, Escape/Close and an unchecked
  agreement checkbox. Start stays disabled until server acceptance succeeds.
- [x] Use the user's confirmed two (2) years for unsuccessful applicant retention.
  DPO mailbox remains unconfirmed after explaining its purpose. Optional private
  `CIF_PRIVACY_DPO_EMAIL` config renders that contact; until supplied, the notice
  directs requests to the APEC Homes HR recruitment team to reach the DPO. No
  invented mailbox or policy placeholders appear in the notice.
- [x] Store consent timestamp (UTC), exact rendered notice snapshot and SHA-256
  content version in the existing invitation draft JSON, then in the original
  submitted candidate JSON. The server preserves these values across draft saves
  and ignores client-forged consent metadata. Record an acceptance audit event.
  No migration or hosted database change is required.
- [x] Require current acceptance for every unsubmitted applicant, including legacy
  drafts. Existing answers/step remain intact. Changed notice content requires
  acceptance again; submitted applications retain their existing tracking flow.
  Existing certification and background-check acknowledgements remain separate.
- [x] Server validates session/access, explicit true acceptance, current notice
  version and optimistic draft revision. Draft/submit requests cannot bypass the
  gate by enabling a button or supplying forged timestamps. Acceptance failure
  leaves the form gated and supports retry.
- [x] Focused workflow suite: 12 tests, 199 assertions. New coverage checks denied
  draft/submission, malformed/outdated acceptance, stale revisions, notice changes,
  legacy draft preservation, forged metadata and original submission evidence.
- [x] Playwright desktop 1440px/mobile 390px passed: disabled/unchecked defaults,
  scrollable full notice, Escape and focus return, failed acceptance/retry, saved
  acceptance/answers after reload, applicant isolation and no horizontal overflow.
  Desktop/mobile notice and welcome screenshots were visually inspected.
- [x] Final full PHP suite passed: **46 tests, 389 assertions**, including correct
  Candidate audit attribution even when HR shares the browser session. PHP/JS
  syntax checks and `git diff --check` passed.

Evidence: `storage/app/migration-validation/20261110-applicant-privacy/` contains
four screenshots and `results.json`; durable summary and image hashes are in
`docs/validation/20261110/applicant-privacy.json`. Repeat with
`node tests/Browser/check-applicant-privacy.cjs`. Fixtures are disposable,
fictional, and use disabled/in-memory mail. Initial validation attempts failed
because local MySQL was stopped; it was started normally without data repairs
or resets, then checks were rerun. Private environment settings were not edited.
Root SQL exports are now ignored by Git so the user's private export stays local.

Next: confirm the DPO mailbox, then set it privately and clear configuration.
The notice describes retention policy; this enhancement does not implement
automatic record deletion. Changes are local only; no GitHub push or hosting
deployment was performed. When deploying, update both private application files
and copied public CSS/JS, preserving the host's entry-file path and PHP rule.

## Local performance fixes (6 November)

The user reported a consistent 0.5–1 second delay compared with vanilla PHP.
Measurements isolated substantial PHP startup overhead even on the anonymous
state endpoint; static files were already fast.

- [x] Baseline live-server timings: staff chooser median about 432 ms and session
  state about 442 ms, versus roughly 6 ms and 35 ms in the vanilla Apache app.
- [x] Confirmed the PHP 8.4 runtime did not load OPcache. Backed up
  `C:\php84\php.ini` outside the workspace, enabled its installed OPcache
  extension and CLI support, and kept timestamp validation on with revalidation
  every request so local code changes remain visible.
- [x] Triggered the existing `artisan serve` watcher to restart its child by
  updating `.env`'s modification time only. The user's original serve process
  remains in control; environment contents and Gmail settings are unchanged.
- [x] Regenerated optimized Composer autoload metadata: 7010 classes, including
  the new application services and middleware, versus the previous 6923-class map.
  Dependencies were not upgraded. No config/route cache was enabled for localhost.
- [x] Prioritized the requested HR page's data call before the sidebar badge call
  in `public/js/admin.js`. Both requests still run and return current counts;
  the main content no longer queues behind background counts. This request-order
  change is an intentional implementation difference from the vanilla script,
  with unchanged labels, results, design and access checks.
- [x] Read-only profiling found the remaining heavy dashboard time in candidate
  loading/normalization and card queries, rather than forecast model fitting.
  Same-data fixture measurements already put Laravel's dashboard calculation near
  the vanilla endpoint. Authentication, session locking, audit and freshness rules
  were preserved rather than adding response caches with stale data.
- [x] Full PHP suite passed: **45 tests, 362 assertions** with the optimized runtime.
- [x] Final 1000-candidate fixture benchmark: enabled-OPcache endpoint medians
  were 24–50 ms for ordinary requests versus 105–125 ms with OPcache explicitly
  disabled. Dashboard was 149 ms versus 205 ms. Browser candidate/invitation
  navigation was 137/150 ms versus 283/250 ms; dashboard 233 versus 416 ms.
  Browser timings include automation and rendering overhead; local load varies.
- [x] On the user's running server, warmed chooser/state medians fell from
  about 432/442 ms to 23/49 ms. First requests after restart remain colder.
- [x] Original/Laravel comparison passed: **12 API responses and 40 desktop/mobile
  screenshots**, all with zero pixel differences and no page errors. PHP suite
  remained **45 tests, 362 assertions**; JavaScript syntax checks passed.
- [x] Protected `.env` and mail configuration/transport hashes match the pre-task
  versions. Source working tree is clean; disposable fixtures were removed.

Evidence: `docs/validation/20261106/performance.json`, `performance-benchmark.json`
and `performance-visual-results.json`. The PHP INI backup and pre-task files are
outside the workspace in the recorded private performance backup directory.

Performance fixture: `node tests/Browser/benchmark-recruitment.cjs` creates 1000
fictional candidates in a guarded disposable database, compares warmed endpoints
with OPcache disabled/enabled and the vanilla runtime, and measures browser
navigation. Mail is disabled/in-memory and actual data is not modified.

## Invitation email unavailable: Gmail configuration corrected (6 November)

- [x] The user's Gmail credentials were already present, but the active private
  environment still selected `log`, `127.0.0.1:2525`, no SMTP scheme, no sending
  opt-in and no portal origin. This caused the disabled Send/Resend dialog.
- [x] Corrected only the six nonsecret settings for Gmail SMTP/STARTTLS on 587,
  `CIF_MAIL_ENABLED=true` and `CIF_PUBLIC_ORIGIN=http://127.0.0.1:8000`.
  Username, App Password, sender, application name and database settings were
  preserved exactly. Previous private settings were backed up outside the workspace.
- [x] Cleared configuration cache. The actual loaded configuration now passes
  mail validation and reports sending available; no MAIL_URL override exists.
- [x] Gmail TLS connection and authentication passed with the configured account.
  The check opened/authenticated/closed SMTP only: no message, recipient or mail
  submission was sent and no credentials were displayed or recorded here.

Next task: refresh the HR page and reopen Send/Resend to load current availability
and regenerate the preview's local portal link. Actual recipient delivery remains
untested; send only through an explicit user action.

## Dashboard screenshot diagnosis (6 November)

The supplied screenshots compare a daily Laravel chart with a monthly vanilla
chart. In the current shared renderer, the forecast line/area is shown only for
monthly grouping; the three forecast cards remain visible for daily grouping.

- [x] Read both actual local databases in read-only transactions. Laravel has
  982 active candidates and one pending invitation; vanilla has 983 and two.
  The user's explicit choice is to keep the existing Laravel data, so no copy,
  environment change or data update was performed.
- [x] Ran original and Laravel analytics functions against identical rows from
  each dataset, for both daily and monthly grouping: complete results match.
  Both datasets produce forecasts 18.26, 19.26 and 18.26 for December 2026 through
  February 2027. The different chart shape is not a forecast calculation mismatch.
- [x] Focused browser comparison passed with 36 months of fictional submissions:
  blue history-line coordinates and orange dashed-forecast coordinates match
  exactly. Both implementations start in Months, hide the forecast line in Days,
  restore it in Months, and return to Months/All dates through Reset and reload.
  The 12 API comparisons and both desktop/mobile dashboard images match, with
  zero pixel differences and no page errors.
- [x] Chart renderer `public/js/admin.js` and date/grouping controller
  `public/js/recruitment-dashboard-filters.js` match the current vanilla files
  byte-for-byte. No production chart, data or email change was necessary.

Evidence: `docs/validation/20261106/dashboard-grouping-readonly.json` and
`docs/validation/20261106/dashboard-grouping-visual.json`. Fictional forecast
screenshots: `storage/app/migration-validation/20261106-dashboard-grouping/visual/`.

The earlier visual comparisons used the same fictional dataset and filters in
both implementations. They do not imply independently edited source/development
databases show identical totals or independently selected filters show one chart.
To match the supplied vanilla view, use Dashboard's **Group chart by: Months**;
Reset restores Months and All dates. Data differences remain by user choice.

## Portal and applicant-entry audit (6 November)

The user's latest clarification supersedes the earlier three-card chooser
requirement. `/pages/portal/` must match the current source's two staff choices,
HR Recruitment and MRF Requestor. The main entry opens the applicant account.

- [x] Removed the retained Superadmin chooser card and restored the exact source
  `css/portal.css`. The existing separate Superadmin login and records remain.
- [x] Changed `/` and `/index.php` to redirect to `/pages/candidate/`, matching
  the original `index.php`. Added `/index.html` as the applicant-entry alias the
  user named; the source currently has no physical `index.html` file.
- [x] Rechecked all 51 source-update manifest hashes: no source edits since
  `e29287871be6e4ec8f079595f0370adbba0b533d`. All current CSS, JavaScript and
  branding assets now match the original. Previous feature validation remains
  documented below; entry-page differences were not treated as feature parity.
- [x] Updated redirect/chooser assertions and browser comparisons to verify two
  cards, their layout and applicant entry through the root and index aliases.
  Route cache cleared; email configuration/cache were not changed.
- [x] Full PHP suite passed: **45 tests, 362 assertions**. Original/Laravel
  comparisons passed for **12 API responses and 40 desktop/mobile screens**, all
  with zero pixel differences and no page errors. Browser checks followed the
  original and Laravel root/index entries to the actual applicant tracker page;
  native tests also verify the anonymous applicant redirect and two-card chooser.
- [x] Compared protected file hashes: `.env` and all email configuration/transport
  files are unchanged. Syntax and Pint checks passed; zero disposable databases
  remain and the temporary audit pointer was removed. Portal screenshot inspected.

Evidence: `docs/validation/20261106/entry-audit.json` and
`docs/validation/20261106/entry-audit-visual-results.json`. The source still has
no newer changes beyond the verified commit; no other missing feature changes
were found in the checked API, workflow, asset and screen comparisons.

Current task is localhost only. Tests use disposable databases and in-memory or
disabled mail. No data copy, mail send or hosted action is part of this audit.

## Gmail setup for localhost (6 November)

- [x] At the user's request, configured private `.env` for Gmail SMTP on port
  587 with STARTTLS, sending opt-in and clearly commented Gmail/App Password
  placeholders. Existing nonempty credentials were preserved; the sender follows
  `MAIL_USERNAME` automatically. Previous private settings were backed up outside
  the workspace. No credentials are recorded here.
- [x] Allowed HTTP loopback portal origins only in local/testing environments.
  Production and remote origins still require HTTPS. Placeholder App Passwords
  keep sending unavailable until replaced. Corrected the stale error referring
  to the vanilla `config/mail.local.php` path.
- [x] Cleared configuration cache; mail tests passed **3 tests, 18 assertions**,
  using in-memory delivery and checking local/production URL restrictions.
- [ ] User supplies their Gmail address and Google App Password privately in
  `.env`. Actual Gmail acceptance/delivery has not been tested; no real email sent.

## Current scope: 6 November vanilla changes, localhost only

Working reference: `C:\xampp\htdocs\cif`, commit
`e292878` (Printables XLSX), including `ed2eff4` (MRF funnel and tracker changes),
since the previous sync at `71de3fe`. The 51 changed source paths/hashes are
recorded in `SOURCE_UPDATE_MANIFEST_20261106.json`. The source is read-only.
Laravel's native migrations, role sessions, CSRF, disabled real mail and private
binary-upload staging remain in use. No schema change or data recopy is needed.

| Change | Status and validation |
| --- | --- |
| Optional Facebook, height and weight | Native validation/rendering check passed for blanks, omitted and supplied values; positive-number, length and other required-field rules retained |
| Application tracker wording and prerequisites | Current source browser scenario passed, including Screening label, application-received message, connectors, counts, older saved snapshots, optional/parallel routes and 16 publication combinations; stored HR/history records unchanged |
| HR dashboard All dates and KPI Last 12 months | Source defaults verified in browser and matching desktop/mobile captures; KPI chart starts on Hired; ten dashboard cards, forty drill-downs, date boundaries, resets, shortcuts and KPI isolation passed |
| Printables | Source browser scenario passed for all/my assigned ownership, latest assignment clearing, UTC+8 date boundaries, archives, every KPI page, workbook contents/formatting/literal text, multi-report print/PDF, empty/single reports, validation, HR-only access, native CSRF, audit and responsive layout |
| Funnel on each HR MRF detail | Source browser scenario passed for unique/current assignments, cleared/deployment destinations, optional/parallel assessments, outcomes, hires, sibling/archive isolation, refresh, empty state, HR-only access, responsive alignment and unchanged one-page MRF print |
| Invitation first-use expiry | Source browser scenario passed: unused codes expire; activated codes survive a 1000-day-old deadline, repeat entry, drafts, submission and tracking; wrong/revoked codes blocked; statuses, dashboard totals, email eligibility and No expiry labels agree under both collations |
| Invitation default email | Only the untouched old invitation body upgrades; customized templates and repeat-seed revisions preserved; fixture command invokes Laravel's actual seeder; no real email sent |
| MRF numbering/import conflict improvements | Source browser scenario passed for generated-only numbers, immutability through approval/status changes, retries, stale-counter recovery, source-number conflicts, duplicate rejection and explicit confirmation of a different request |
| Additional source edits | Requestor/HR login wording, HR MRF columns, approval notes and Reports audit category applied; included in final visual/API checks |

- [x] Preserved pre-sync code/tests/docs outside the workspace at
  `C:\LaravelProjects\cif-migration-backups\20261106-source-sync-f927c659`.
- [x] Added native reports endpoint and Laravel services for report generation,
  safe XLSX/print output and MRF funnel calculations; preserved original URLs.
- [x] Extended rule checks for optional profile fields and tracker prerequisites;
  adapted browser checks only for native CSRF/session/fixture differences.
- [x] Full PHP suite passed: **44 tests, 346 assertions**. Post-sync mail,
  optional-field and tracker/portal recheck passed **25 tests, 112 assertions**.
- [x] Dashboard coverage and DOCX/PDF import browser regressions passed. The
  import check caught a source-relative include in phone validation; Laravel now
  uses the autoloaded service, with an added recognized-DOCX validation regression.
- [x] **12 original/Laravel API comparisons and 38 desktop/mobile comparisons
  passed, all with zero pixel differences and no page errors**. Printables
  desktop/mobile and the populated MRF funnel were also inspected visually.
- [x] All 51 source hashes still match the manifest; original PHP worktree is
  clean and all 52 native migration files are unchanged. The active local schema
  still verifies 52 tables and 68 audit triggers, with no pending baseline history.
- [x] After the import fix, the focused rule/workflow/upload regression passed
  **32 tests, 216 assertions**. Changed PHP/JavaScript syntax and Pint checks
  passed. Temporary port helpers were removed and zero disposable databases remain.

The read-only data comparison now reports 25 data differences across the 43
recruitment tables because the vanilla database has newer saved records since the
4 November snapshot (for example, 1039 source candidates versus 1038 local).
No table-definition differences were reported. This task applies code changes;
it does not recopy or overwrite the user's existing local data to erase that drift.
The private `.env` was not changed and no real email or hosted changes occurred.

Evidence: `docs/validation/20261106/visual-results.json` and aggregate results in
`docs/validation/20261106/source-sync.json`. Screenshots are in
`storage/app/migration-validation/20261106/`, with responsive captures under `visual/`.

Next task: review the updated portals on localhost. Hosting and real SMTP
acceptance remain separate work.

## 4 November: native table-by-table migrations (completed)

The user selected native table-by-table migrations only, without a Docker setup.
This replaces the earlier formatting-only work and SQL-dump bootstrap. Hosting
preparation remains inactive.

- [x] Added 51 individual `Schema::create()` migrations: 43 recruitment tables
  and eight Laravel infrastructure tables. Laravel creates the migration repository
  itself. Foreign-key ordering, original types/defaults, binary uploads, indexes
  and relationship rules are preserved.
- [x] Retained the existing final security/audit migration identity and protected
  rollback. It now initializes settings and installs 68 frozen trigger definitions
  without calling mutable runtime installer services. Security columns are defined
  in their respective native table migrations.
- [x] Moved the historical SQL dump to `database/reference/legacy-mysql-schema.sql`.
  Only independent legacy fixtures load it; Artisan no longer auto-loads a dump.
- [x] Added a schema contract and local `recruitment:adopt-migrations` command.
  Preview and apply verify every managed table and expected audit trigger.
  Adoption records only the frozen baseline and leaves future migrations pending.
  A deliberate column mismatch was refused without history changes; repeated
  adoption/migration was a no-op and audit rollback remained protected.
- [x] Preserved the active isolated database's original migration history outside
  the workspace, then adopted 48 missing records alongside its original four.
  Its schema, triggers and application records were not changed; no `.env` edits.
  `php artisan migrate --force` reports nothing to migrate, and the 43-table
  source/data comparison reports zero differences.
- [x] Exercised real empty-database Artisan installation: 52 tables, 52 migration
  records, 68 triggers, complete schema-contract agreement and no dump loading.
  Repeat installation, template customization and Superadmin credential preservation
  passed. A fictional reference copy also passed native initialization and retained
  its original records and triggers. Disposable databases are used for these tests.
- [x] PHP syntax passed for all 52 migrations; Laravel Pint passed for changed PHP.
- [x] Updated `database/README.md`, runtime and historical deployment instructions
  to describe native migrations. PDO MySQL replaces the schema-loader client
  dependency. MySQL/MariaDB remains required; container execution is not claimed.
- [x] Full PHP suite passed: **43 tests, 344 assertions**. The fresh-install
  recheck additionally passed **2 tests, 29 assertions** with subprocess PATH
  restricted to the PHP directory, removing the old MySQL-client dependency.
- [x] Removed temporary schema-generation files, the reference-schema database
  and disposable test databases. Source PHP worktree remains clean.

Evidence: `docs/validation/20261104/native-migrations.json`.

Conversion backup: `C:\LaravelProjects\cif-migration-backups\20261104-native-migrations-131623`.
The original PHP database and earlier development copies remain intact. No mail
or hosting actions were performed. Schema validation permits an older table's
inherited default collation only when that table has no character columns;
character-column collations are still compared exactly.

This migration-structure task is complete. Next task: user review on localhost;
later feature or deployment work requires a separate scope. Existing workflow/visual evidence remains valid
because this conversion changes database bootstrap, not application screens.

## Earlier formatting-only task (historical)

The user's latest instruction limits current work to localhost. Hosting
preparation below remains a future reference, not the active next task.

- [x] Formatted all four files in `database/migrations` consistently, grouping
  column definitions and migration steps and completing the method comments.
- [x] Laravel Pint and PHP syntax checks passed for all four migrations.
- [x] Executable PHP tokens are unchanged after excluding whitespace/comments;
  schema definitions, file names, migration order and rollback behavior are preserved.
- No migration, reset, rollback, seeding or database update was executed for this
  formatting task. The local `.env` and schema baseline were left unchanged.

## Deployment preparation after local feature completion

Hostinger support's reply supplied by the user identifies the account as Unlimited
Web Hosting for `ccsbsis.com`, superseding the earlier Premium/Business estimate.
The user chose `https://recruitment.ccsbsis.com` as a separate recruitment portal.
Provider-specific instructions are prepared in `docs/DEPLOYMENT_HANDOFF.md`;
no account was accessed and nothing was uploaded or published. Enabled account
access and the actual subdomain root directory are still unknown.

- [x] Corrected the Apache trailing-slash rule, which otherwise redirected the
  virtual portal directories back to their slashless form and conflicted with
  Laravel's canonical redirect. An isolated Apache process tested ten portal
  paths, including a URL prefix, plus the ordinary nonportal redirect. The
  existing XAMPP Apache configuration was not changed.
- [x] Exercised actual `artisan migrate` from an empty disposable database:
  52 tables, four migration records and 68 triggers. Repeated migration did
  nothing. The dedicated seeder adds eight built-in templates and preserves
  a customized subject; separate Superadmin setup preserves the existing
  password and private credentials file on repeat.
- [x] Added a command-start guard so a schema dump cannot replace untracked
  existing tables. Actual `migrate --force` and `migrate --pretend` attempts
  on a fictional legacy table were refused before changing its records or
  creating migration history. The guard scopes metadata to the selected database.
- [x] Final PHP suite: **41 tests, 319 assertions passed**. Apache, PHP-template
  syntax and Pint checks passed. The 43-table data comparison still has zero
  differences. All disposable databases and temporary Apache fixtures were removed.
- [x] Prepared a private sibling application / `public_html` layout and an entry
  template at `docs/deployment/hostinger-public-index.php.example`. This template
  was syntax checked; the actual Hostinger layout has not yet been exercised.
- [x] Hostinger support confirmed TRIGGER privilege for the database users assigned
  to `ccsbsis.com`, as relayed by the user on 4 November. The 68-trigger audit
  implementation is retained. Actual installation/execution is still a hosted
  staging check. Cleanup uses PHP cron, not MySQL Event Scheduler.
- [x] Prepared a secret-free production environment template for
  `https://recruitment.ccsbsis.com`; the local `.env` was not changed. It uses
  host-only secure cookies and keeps sending disabled pending mail acceptance.
- [ ] Obtain the account access and subdomain directory details, tailor the final release
  commands, then approve a staging/cutover plan. Live SMTP acceptance is separate.

Deployment-preparation evidence: `docs/validation/20261104/deployment-readiness.json`.
The feature comparison evidence and earlier 39-test suite below remain historical
records; the two installation/guard tests account for the new 41-test total.

## 4 November: current vanilla changes locally verified

Resumed from the user's earlier stopping point and completed its remaining local
validation. The current working source is `71de3fe52d5d28faf0b75071452dfb2c3834ff0d`
(Requestors filter), compared with the original `f708d9d` baseline. All 65 entries
in `SOURCE_UPDATE_MANIFEST_20261104.json` remain unchanged; the source working
Git tree is clean. The October sections below are historical baseline evidence.

### Feature checklist

| Change | Status | Evidence |
| --- | --- | --- |
| Candidate pooling and current stage/status directory | Locally verified | All-stage pooling, parallel-stage deduplication, retained/skipped/deployed exclusions, archives, native collations, filters, cards, empty states and responsive layout; directory desktop/mobile comparison |
| Recruitment and KPI date coverage | Locally verified | Ten cards and forty matching drill-downs, inclusive Philippine dates, historical/future due cohorts, all dates, shortcuts/reset, weekly chart links and responsive behavior; dashboard/KPI desktop/mobile comparison |
| Requestor outstanding dashboard and list/card coverage | Locally verified | Cards follow upper coverage controls while lower table filters stay independent; connected dashboard/list/detail counts, owner isolation, status updates, pagination, printing, zero states and old links; desktop/mobile comparison |
| Superadmin accounts, access and audit | Locally verified | Full native browser scenario: setup/idempotency, account/code/password changes, stale saves, actor-password checks, role boundaries, session revocation, own-session preservation, self-lockout prevention, frozen record references, audit rollback/immutability/redaction, both collations, filters, refresh, desktop collapse and mobile navigation |
| Central chooser for Recruitment, Requestor and Superadmin | Locally verified | Three links on desktop/mobile, root redirect, correct resolved destinations and no page overflow. The explicit three-link request takes precedence over the current source's two-link chooser |
| Assessment requirement locks at Job Offer | Locally verified | All four combinations, saved-stage locks, adding unused assessments while pending, every non-pending offer response, unchanged HR saves, forged/stale saves, rollback and responsive controls |
| Other current source edits | Locally verified in affected flows | HR interview/Hired display labels retain stored identifiers; candidate Exit preserves independent staff sessions; Requestor rename and compatibility aliases; Superadmin excluded from HR ownership/KPI choices; HR requestor management retained; imports, deployed-record locks, requestor edits and archived invitations rerun |

Locally verified means meaningful comparisons on isolated fixtures. It does not
claim exhaustive user acceptance or hosted deployment.

### Fixes completed during resumed validation

- Directory redirects now preserve the trailing slash, query parameters and
  browser fragments. Direct directory URLs are canonicalized as well. This keeps
  the original relative links working after root/shortcut/old Department entry.
- Token failures now retain the friendly refresh instruction and an audit entry
  with the revalidated actor. Laravel converts the token exception into an HTTP
  exception before rendering; the handler now uses that actual exception path.
  A native test exercises real forgery middleware and checks exactly one audit
  entry, its role, result and HTTP status. The full Superadmin browser adapter
  also verifies the rejected-token audit through real HTTP.
- The visual harness opens the actual audit navigation, including the mobile
  drawer, and explicitly checks the requested three-link chooser through `/`.
  Source application files were not edited to make the checks pass.

### Validation evidence

- [x] Final combined PHP suite: **39 tests, 292 assertions passed**.
- [x] Full Superadmin and Requestor dashboard browser checks passed.
- [x] Import, deployed-readonly, requestor-edit and archived-invitation browser
  regressions passed on the updated Laravel runtime.
- [x] Pooling, dashboard date coverage and assessment-lock browser checks passed
  before the checkpoint; their affected pages are included in the final visuals.
- [x] **12 original/Laravel API comparisons and 36 desktop/mobile screen
  comparisons passed, all with zero pixel differences and no page errors**.
- [x] Four additional chooser captures document the intentional two-link versus
  three-link difference. The Laravel desktop/mobile chooser and mobile audit
  screen were also inspected visually.
- [x] All 55 source assets checked; every asset matches except the intentionally
  changed chooser CSS. No source assets are missing.
- [x] Syntax/style/JavaScript checks passed. All four migrations show Ran. No
  disposable test databases remain after cleanup.
- [x] `recruitment:compare-data`: **43 tables, zero data/schema differences**.
- [x] Running application: HTTP 200 through `/`, arriving at `/pages/portal/`
  on `http://127.0.0.1:8000`.

Final aggregate: `docs/validation/20261104/completion.json`.
Visual aggregate: `docs/validation/20261104/visual-results.json`.
Images: `storage/app/migration-validation/20261104/visual`.
The earlier stopping-point evidence remains in `docs/validation/20261104/checkpoint.json`.

### Data and environment preservation

- Active isolated database: `cif_laravel_dev_20261104_90d68738`, using the user's
  chosen fresh current-PHP dataset. All 43 tables, 18,691 audit rows and 68 triggers
  were preserved. Original `cif` and previous Laravel databases remain intact.
- Existing identities and Superadmin password hashes are preserved. No default
  password was invented. No database copy/reset or private environment change
  occurred during this resumed validation.
- Backup before implementation:
  `C:\LaravelProjects\cif-migration-backups\20261104-source-sync-101828`.
  The initial copy changed only `DB_DATABASE`, preserving the previous environment
  separately. History-import duplicate handling now skips existing legacy keys;
  the fresh copy's unused audit counter reservations were reconciled only after
  verifying all audit rows, as recorded at the earlier checkpoint.
- No real email was sent and no hosted system was changed.

### Remaining acceptance / next task

1. Review the working local portals with representative user workflows before a
   deployment decision. Run with `php artisan serve`; open `/pages/portal/`.
2. Actual SMTP acceptance and hosted cutover remain separate work requiring
   private environment configuration and explicit authorization. In-memory mail
   and bookkeeping checks passed; inbox delivery and hosting are not claimed.
3. Future source edits should be compared with the current update manifest.
   Preserve development records; do not replace the database merely to restore
   an equal comparison after intentional local editing.

Runtime/setup details: `docs/RECRUITMENT_RUNTIME.md`.

## 29 October: local MySQL startup fix

- XAMPP startup repeatedly exited after opening the server socket; Windows logged
  `mysqld.exe` crashes with exception `0x80000003`. Console-mode startup worked
  with recovery mode off, and no damaged tables were found.
- Preserved a full logical backup of every database, the original `my.ini`, log
  and crash dump in `C:\xampp\mysql-startup-fix-20261029-085456`.
- Added `console` at the end of `[mysqld]` in XAMPP's `mysql/bin/my.ini` so normal
  startup uses the working Windows console logging path. This is a tested startup
  workaround; the exact internal MariaDB fault was not debugged.
- Verified standard startup, `mysql_start.bat`, and hidden XAMPP-style startup.
  MySQL is serving port 3306 with `innodb_force_recovery=0` and `read_only=0`.
- All 188 tables passed `mysqlcheck --all-databases --check`; Laravel
  `php artisan migrate:status` connects and shows its existing migrations as Ran.
- Original, older-development and active-copy recruitment counts are preserved.
  No database reset, restore, deletion, forced recovery, credential or `.env`
  change was performed. The 28 October migration evidence below is unchanged.
- Allow MySQL to stop fully before restarting. An immediate restart while the old
  process is shutting down can temporarily report `ibdata1` as not writable.
- Details and configuration rollback: the private backup folder's
  `REPAIR-REPORT.txt`. Console logging now takes precedence over the old error log.

## 28 October baseline (historical)

- Reference: `C:\xampp\htdocs\cif`, commit `f708d9d82549985eb345cc35032da8c1819a7fe8`.
- Target: `C:\LaravelProjects\cif-laravel`; Laravel 13.38.0, PHP 8.4.27.
- All 278 manifest files matched before implementation. Source code and original
  recruitment records were not changed.
- All 14 modules now have Laravel controllers/services/views/assets. No original
  endpoint or session bootstrap is included at runtime. Existing URLs remain.
- Target work and private settings were preserved. No target Git repository was
  present. `tests/TestCase.php` was left unchanged.
- The user selected a **fresh copy of the current PHP database** after a read-only
  check found differences in 24 tables of the older copy. The active database is
  `cif_laravel_dev_20261028_031cfcb6`; the older `cif_laravel_dev` and original `cif`
  remain intact. Every row and definition in all 41 recruitment tables matched,
  including binary documents, IDs, hashes, counters, original CIFs and histories.
  The copy has 1,038 candidates, 1,061 invitations and 1,114 MRF headcount files.
- Only private `DB_DATABASE` changed. The previous environment was backed up outside
  the webroot under `C:\LaravelProjects\cif-migration-backups`.
- No real emails were sent and nothing was published.

## Implementation and safeguards

- `app/Http/Controllers/Recruitment`: native HTTP handling for access, Department
  settings, MRFs, imports, requestors, email and protected pages.
- `app/Services/Recruitment`: original validation, formatting, revisions,
  screening/interviews/parallel requirements/deployment, ownership, KPI attribution,
  publication, schedules, email snapshots and retry safeguards.
- `resources/views/recruitment` and public assets preserve the current appearance,
  browser interactions, responsive pages and print views.
- Laravel sessions, CSRF, session regeneration, throttling and endpoint session
  locks replace native PHP session/HTTP code. Password signatures and requestor
  access versions revoke stale sessions, including records/prints/downloads.
  CSRF failures now use Laravel's HTTP 419 with the existing refresh instruction.
- MySQL connections use UTC and native prepared statements; PHP displays use
  Asia/Manila. Stored identifiers, names, statuses and source schema remain compatible.
- Runtime schema alteration was removed from services. The checked-in schema
  baseline owns structure. `RecruitmentSeeder` supplies only original templates
  for an empty installation, without creating accounts or replacing edits.
- Uploads remain private. Real browser testing caught binary data invalidating
  Laravel JSON sessions. Temporary files now use the private local disk with only
  metadata in sessions; source/download bytes remain exact. This also avoids
  database session packet-size limits. Two additional tests verify 2 MB staging
  and HR-account binding; saved/discarded/logout/expired reviews are cleaned up.
- Mail uses Laravel/Symfony Mailer, disabled by default. In-memory tests verify
  HTML/plain text, embedded branding and acceptance IDs. Synthetic callbacks test
  failure/retry/idempotency and tracker release independently of delivery.
- The deep KPI check caught a missing custom assignment-conflict class during the
  port. Its class/controller mapping is now native; stale reassignment returns 409.

## Module checklist

“Locally verified” means meaningful behavior plus comparison with the current PHP
implementation on isolated fixtures. It is not exhaustive or production acceptance.

| Module | Status | Evidence |
| --- | --- | --- |
| 1 HR dashboard | Locally verified | Ten card destinations/counts, five paired columns, terminal outcomes, request/headcount distinctions, HR/KPI due cohorts, open positions, filters, trend/estimate rules; API and desktop/mobile comparison |
| 2 Candidates | Locally verified | Search/status directory, archive/restore, permanent ID, original submission after HR revision, stale saves, record/print access; directory/record visuals |
| 3 Assessments and deployment | Locally verified | Screening gate, parallel assessments in either order, required-route rules, offer/deployment, optional employee ID, unique credit and stale-tab locks; read-only browser regression and roadmap/rules checks |
| 4 Invitations | Locally verified | Archived-email reinvitations, distinct codes/drafts, duplicate/restore guards, expiry/tracking, archive lists, native filters on both connection collations; desktop/mobile visuals |
| 5 HR MRFs | Locally verified | Numbered headcounts, final approval/date/deadline, scope, ownership/conflicts; real DOCX/PDF recognition/review, exact source downloads, retries/duplicates, invalid files, printing and responsive dialogs |
| 6 Requestor accounts | Locally verified | Acting HR password, reset length, validation/duplicates, stale edits, suspension/archive/session revocation, audit privacy, history and responsive dialogs |
| 7 Email management | Local transport/bookkeeping verified; external acceptance pending | Recipient preview/preparation, immutable snapshots, cancellation, explicit retry, accepted-message duplicate protection and attempts; real SMTP acceptance intentionally not tested |
| 8 Email templates | Locally verified | Original library, edit/preview, no unsupported creation, archive/restore, stale revisions and unchanged message snapshots; API/visual comparison |
| 9 KPI and HR monitoring | Locally verified | Current database regression: four formulas, due cohorts, hires, ownership, archived/invalid dates, hold/reinstatement, unique funnel credit, chart/table agreement and filters; API/visual parity |
| 10 Settings and access | Locally verified | Three-role access, Department emails, protected records/downloads, CSRF, acting-password checks, password session revocation, group changes without rewriting requests |
| 11 Department dashboard | Locally verified | Account-scoped dashboard cards/charts and response-driven rendering match source desktop/mobile views; original coverage/date boundary logic retained |
| 12 Department requests | Locally verified | Form/validation, idempotent submission, independent headcounts, account scope, retained details, record/management prints and responsive comparison |
| 13 Candidate Information Form | Locally verified | Fields/year/address/text/optional-ID rules, optimistic drafts/recovery, full submission, permanent ID/original snapshot and edit rejection; desktop/mobile form comparison |
| 14 Candidate tracker | Locally verified | Published facts/privacy, required/optional roadmap, schedule/update times, no internal scores/notes, private unpublished changes, release independent of email failure/no automatic resend; desktop/mobile comparison |

## Validation evidence

- [x] Laravel implementation present for all 14 modules.
- [x] 36 PHP tests and 219 assertions passed in the final run.
- [x] Nine original/Laravel API comparisons and 32 desktop/mobile comparisons passed.
- [x] All 41 copied recruitment tables and all 278 reference files rechecked.
- [x] Syntax/style checks passed; disposable test databases and private staged files cleaned up.
- [ ] Controlled real SMTP acceptance test (separate authorization/configuration).
- [ ] Production hosting/cutover acceptance (not performed or authorized).

- PHP suite: `PortalAccessTest`, `RecruitmentWorkflowTest`, `LaravelMailTransportTest`,
  `MrfImportStagingTest`, 18 translated rule scripts, and the current MRF/HR KPI database regression.
  Final results: `docs/validation/20261028/phpunit.txt`.
- Laravel browser checks passed: `check-dashboard-pairs`, `check-requestor-edit`,
  `check-deployed-readonly`, `check-archived-invitations`, `check-mrf-import`.
  Each uses a disposable database; imports compare source/download bytes exactly.
- Original/Laravel comparison: 9 API responses, 32 desktop/mobile images, no page
  errors. All images matched exactly with equivalent fonts and disabled-mail
  presentation. Aggregate: `docs/validation/20261028/visual-results.json`.
  Images: `storage/app/migration-validation/visual`; representative screens were
  also inspected visually.
- Native filters were tested with `utf8mb4_general_ci` and `utf8mb4_unicode_ci`
  connection collations and emulated prepares disabled.
- `recruitment:compare-data`: 41 tables, zero differences after the fresh copy;
  no personal data printed and no source writes.
- Runtime/setup/test commands: `docs/RECRUITMENT_RUNTIME.md`.
- Local server restarted after the database change; anonymous session-state HTTP
  check returned 200 at `http://127.0.0.1:8013`. No real account was used for that check.

## Remaining acceptance work / next task

1. Review the local portals using representative HR, Department and Candidate
   workflows before a deployment decision. The implementation/evidence are ready.
2. Configure Laravel SMTP privately and perform a separately authorized controlled
   acceptance test. In-memory acceptance/bookkeeping are verified; actual service
   acceptance and inbox delivery are not claimed.
3. Production extensions, storage permissions, HTTPS/cookies, hosting rewrites,
   environment settings and a cutover/rollback plan remain separate deployment
   work. Nothing hosted has been replaced.
4. Some older browser checks assume retired exams, interviews before screening,
   pending-MRF selection, legacy username login or superseded filters. They were
   not used to weaken current behavior. Current source and native integration
   checks replace those assertions. Rewrite the obsolete scenarios in a follow-up.

Continue from these results. Do not repeat database recovery, reset the source,
overwrite the older dataset, or claim production completion before external
mail/hosting acceptance.
