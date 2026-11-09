# APEC Homes recruitment: Laravel migration handoff

Prepared: 28 October 2026 (Asia/Singapore).

## Objective and current state

Rebuild the current APEC Homes recruitment system in the existing Laravel project with the same pages, design, workflows, permissions, saved data, calculations and user-visible behavior. Changing the framework must not silently redesign or simplify the product.

- Working PHP reference: `C:\xampp\htdocs\cif`.
- Laravel target: `C:\LaravelProjects\cif-laravel`.
- Reference Git commit: `f708d9d82549985eb345cc35032da8c1819a7fe8`.
- Installed target framework observed: `laravel/framework v13.38.0`.
- Target currently contains the Laravel starter route and base controller. No recruitment modules have been implemented or verified in Laravel by this handoff.
- Feature inventory: `deliverables/APEC Homes Recruitment System - Modules and Submodules.docx` in the source project, also copied to `docs/reference/` in Laravel (14 modules, 87 submodules).
- Source hashes: `docs/SOURCE_REFERENCE_MANIFEST.json` (reference files only; no private credentials).
- The target copy of this handoff and `docs/MIGRATION_PROGRESS.md` are the continuing migration records. This source copy records the starting requirements.

Chat history is supporting context. The current working source, its tests, these written requirements and later explicit user instructions are the durable reference. Older README sections contain retired status names and obsolete email limitations; resolve discrepancies by reading current implementation and tests.

## Product and user expectations

Use short, familiar wording. Keep the current navy, blue and teal design, card styles, responsive layouts, page headings, filters and button placement. Do not introduce technical implementation language into screens. Preserve existing refresh controls, searchable tables, summary-card filtering, print views, archive behavior and browser state restoration.

HR, Department requestors and Candidates have distinct portals. All HR users share recruitment records; HR ownership and action history identify responsibility. Requestors see only their account's requests. Candidates see only their application and information HR has made available to them.

Management approval occurs outside this system. The system records HR verification of that decision; do not invent a management approval login, online signature or approval workflow.

## Feature parity checklist

Unchecked items require implementation and validation in Laravel. Documentation and a rendered screen alone do not establish parity.

### 1. HR recruitment dashboard

- [ ] Preserve five paired columns, with total above its related outstanding count: Candidates, Invitations, MRF requests, My assigned MRFs, Open positions.
- [ ] Candidates outstanding excludes archived candidates and current Failed, Backout, Declined and Deployed outcomes. A successful reopened phase or skipped optional assessment must not incorrectly exclude someone.
- [ ] Invitations outstanding includes valid, unsubmitted Pending and In Progress invitations; archived and expired invitations are excluded.
- [ ] MRF totals count original requests, not individual headcount files. Outstanding requests have unfinished headcounts and are not Denied.
- [ ] My assigned MRF total counts the signed-in HR member's assigned approved headcount files across all dates and positions. Assigned MRF due matches that member's current-month KPI needed-headcount cohort, including filled targets where the KPI includes them.
- [ ] Open positions counts approved, available, unfilled headcounts. Positions in progress counts distinct openings with an active candidate, not the number of candidates.
- [ ] Small amber text, without a background, sits beside the main count near its lower edge: "X awaiting screening" and "X awaiting approval". Awaiting screening means current Screening candidates within the same dashboard scope.
- [ ] Reporting-period and position filters, refresh and every card's destination retain matching counts.
- [ ] Open positions chart appears above the submission trend in Recruitment. Group available headcounts by position, sort largest first, show a matching total, and open the matching MRF headcounts when a row is clicked.
- [ ] Preserve Recruitment, Candidate profile, and Location & salary tabs; all current charts, recent applications and date/status drill-downs.
- [ ] Preserve daily, weekly, monthly and yearly submission grouping, empty/partial periods, three monthly estimates, insufficient-history handling and current-status filtering. Estimates must never be saved as applications.
- [ ] Keep the latest short, plain-language dashboard and estimate notes from the source, without exposing forecasting algorithm terminology in the UI.

References: `includes/recruitment-dashboard.php`, `includes/candidate-analytics.php`, `includes/candidate-forecast.php`, `js/admin.js`, `css/admin.css`, `pages/admin/index.php`.

### 2. Candidate management

- [ ] Shared candidate directory with search, status/position filters, summary cards, sorting, pagination and refresh.
- [ ] Active/Archived tabs, archive/restore confirmation, and preserved filters and selected view after refresh.
- [ ] Original submitted CIF, later HR revisions, applicant ID, profile information and printable record.
- [ ] Eligible MRF selection, linked headcount, stage navigation, candidate update actions and history.

References: `ajax/access.php`, `includes/cif-editor.php`, `includes/candidate-storage.php`, `includes/candidate-view.php`, `includes/candidate-mrf.php`, `js/admin.js`, `pages/admin/record.php`.

### 3. Hiring process, assessments and deployment

- [ ] CIF screening -> HR/Initial interview -> required Department interview and/or Background check -> Job offer -> Deployment.
- [ ] Department interview and Background check can be done in either order after the interview passes. HR may require both, either or neither; retain existing assessment results when requirements change.
- [ ] Preserve stage decisions, required and optional answers, partial saves, explicit completion validation, remarks and schedules.
- [ ] Candidate status is calculated from the current workflow, not an arbitrary status dropdown.
- [ ] Preserve HR starter/editor/finisher attribution, revisions and successful-action history without awarding duplicate completion credit.
- [ ] Completing deployment makes the CIF and all stage forms view-only, including requirement controls. Server checks must reject saves from stale tabs too. Draft deployment remains editable; employee ID is optional.
- [ ] Preserve candidate-facing update/publication rules; do not expose internal assessment scores, remarks or personal HR notes.

Current status labels: Screening; Screening Failed; Screening Backout; Initial Interview; Department Interview & Background Check; Department Interview; Background Check; Job Offer; Deployment; Deployed.

The internal key `lie_detection` and flag `ldt_required` remain for compatibility; the displayed name is Background check. Renaming the framework must not rename stored identifiers without an explicit migration.

References: `includes/candidate-statuses.php`, `includes/candidate-interviews.php`, `includes/candidate-roadmap.php`, `includes/candidate-deployment.php`, `includes/candidate-publication.php`, `includes/candidate-schedule.php`, `includes/candidate-update-email.php`.

### 4. Invitations

- [ ] Invitation creation with email, applied position, access code, validity and tracked invitation email.
- [ ] Pending/In Progress/Submitted/Expired classification; search, status/position filters, sorting, pagination and refresh.
- [ ] Active/Archived tabs and archive/restore history.
- [ ] An archived invitation must not block another invitation to the same email. Preserve duplicate protections for the active cases defined in current code.
- [ ] Application access, expiry, resume and post-submission tracking behavior remain the same.
- [ ] Filters work on the hosted database as well as localhost; preserve connection/expression collation handling.

References: `includes/applicant-access.php`, `ajax/access.php`, invitation controls in `pages/admin/index.php`, invitation logic in `js/admin.js`.

### 5. HR manpower requests

- [ ] Request directory and record page; all search, approval/status/date/deadline filters, summary cards, sorting and refresh.
- [ ] One original request may generate multiple numbered headcount files. Preserve batch numbers, indices, links and independent per-headcount status.
- [ ] Upload .docx/searchable PDF, extract candidate fields, review/correct before saving, retain/download original source and prevent retry duplicates.
- [ ] Record verified management approval/denial, approval date, category, lead time and hiring due date. Approval/denial and its notes are final once confirmed.
- [ ] Pending/Denied requests cannot be selected for recruitment. Preserve existing status eligibility rules.
- [ ] HR ownership per headcount, automatic/manual assignment behavior, status updates, notes and history.
- [ ] Candidate assignment, deployment matching, completed-opening exclusions and MRF print layouts.

References: `ajax/mrf.php`, `ajax/mrf-import.php`, `includes/mrf.php`, `includes/mrf-document.php`, `includes/mrf-approval.php`, `includes/mrf-record-content.php`, `includes/mrf-view.php`, `js/mrf.js` and related `includes/mrf-*` helpers.

### 6. MRF requestor accounts

- [ ] Registration and directory with name, email, position, department, group and access status.
- [ ] Use Email, not Email/username, in HR requestor forms and Department sign-in. Email input must actually validate an email.
- [ ] Editing requestor information and resetting an individual's password require the acting HR member's own password.
- [ ] Optional reset requires at least 8 characters, with simple user instructions. Retain the underlying password safety checks without displaying byte terminology.
- [ ] Preserve suspension/archive effects, audit history and session revocation behavior without deleting accounts or their MRF history.
- [ ] Do not silently rewrite legacy usernames into invented emails.

The 8-character change applies to HR's requestor-password reset. Other current self-service password forms still use their own existing validation; do not assume every password rule was changed globally.

References: `ajax/mrf-requestors.php`, `includes/mrf-requestors.php`, `ajax/department.php`, requestor edit dialog in `pages/admin/index.php`.

### 7. Email management

- [ ] Candidate/invitation recipient selection, preview, one-message edits, prepare/submit/send, cancel and permitted retry/resend actions.
- [ ] Preserve Not sent/Sent/Failed/Cancelled results, archived mail, attempt history, message snapshots, responsible HR actor and retry duplicate protections.
- [ ] Actual sending works with configured credentials. Sent means mail-service acceptance, not confirmed inbox delivery.
- [ ] Preserve candidate stage updates and tracker release rules. A post-send bookkeeping problem must not create an unsafe automatic duplicate-send loop.
- [ ] Use plain-language unavailable/failure messages; keep server filenames, SMTP terminology and raw database errors out of product screens.
- [ ] Keep credentials private and environment-specific. Do not commit or copy live passwords into migration documents, seeders or frontend assets.

References: `ajax/email.php`, `includes/hr-email.php`, `includes/hr-email-content.php`, `includes/mail-transport.php`, `includes/candidate-update-email.php`, `js/hr-email.js`, `config/EMAIL-SETUP.md` and example mail configuration.

### 8. Email templates

- [ ] Shared existing template library; edit names/categories/subjects/body and supported personalization variables.
- [ ] Preserve current allowed template operations, archive/restore, revision checks and old message snapshots.
- [ ] Do not add unsupported template creation just because Laravel scaffolding makes it easy.

### 9. KPI and HR monitoring

- [ ] Preserve date coverage and staff filters, charts, table controls, refresh and My profile placement.
- [ ] Needed/filled openings, hires, fill rate, timing calculations, overdue/unassigned records and exclusion rules must match existing formulas exactly.
- [ ] Targets and results follow current HR ownership; the dashboard's Assigned MRF due cohort must match KPI.
- [ ] Preserve unique candidate-phase credit, attribution, workload and history. Repeated Finish must not inflate results.
- [ ] Retain staff profile/photo behavior and handling of missing historical attribution or invalid dates.

References: `includes/hr-kpi.php`, `includes/hr-staff.php`, `includes/hr-profile.php`, relevant `ajax/access.php` actions and `js/hr-staff.js`.

### 10. Account settings and access

- [ ] HR sign-in/settings/password changes; Department email sign-in/settings/group/password changes; candidate invitation access.
- [ ] Preserve existing user identity, password hashes, role boundaries, session behavior, CSRF protection and rate limiting through Laravel equivalents.
- [ ] Preserve record-access validation for all API, print, photo, upload and download routes; UI hiding alone is insufficient.
- [ ] Preserve audit events and conflicting/stale-save rejection.

### 11. Department dashboard

- [ ] Keep the dashboard, not the old workspace shortcut panel. Retain New manpower request wording.
- [ ] Standardized cards and date/deadline filters; remove the Date basis selector from both Department pages as in the current reference.
- [ ] Management approval and Recruitment progress charts stay as charts with clickable matching request filters.
- [ ] Interactive summary cards, due-within-seven-days coverage, deadlines and recent requests remain scoped to that account.

References: `pages/department/index.php`, `includes/mrf-department.php`, `js/department-dashboard.js`, `js/department-coverage.js`, `css/department.css`.

### 12. Department requests

- [ ] Full MRF form, validation, submitted-details lock, leave/submit confirmation and generated request number.
- [ ] Printable management approval form, request list/detail/history, status/approval/date/deadline filters and refresh.
- [ ] Preserve group/account scope and the original details recorded on existing requests when an account is edited.

References: `pages/department/mrf-form.php`, `pages/department/mrf.php`, `ajax/department.php`, `ajax/mrf.php`, `includes/mrf-view.php`, `js/department.js`, `js/mrf-form.js`, `js/mrf-record.js`.

### 13. Candidate Information Form

- [ ] Invited-email/code access, introductory information, ten-day invitation behavior, automatic saving, retry and saved-draft recovery.
- [ ] Personal details/photo, government identifiers, application source/position/salary, present/permanent addresses, emergency contact, education/licensure, awards/affiliations, employment/references/connections, certification and consent.
- [ ] Preserve structured address inputs, year ranges, required/optional fields, validation, text formatting, allowed records per group and photo controls.
- [ ] Permanent Applicant ID generated on submission. Candidate cannot edit the submitted form; original submission stays available to HR.

References: `pages/candidate/index.php`, `includes/cif-fields.php`, `includes/cif-photo.php`, `includes/candidate-address.php`, `includes/cif-education.php`, `includes/text-format.php`, `includes/candidate-storage.php`, `js/candidate.js`.

### 14. Candidate application tracker

- [ ] Same invited email/code after submission, applicant summary and HR-shared current status.
- [ ] Required-phase roadmap, optional/parallel assessment routes, completion indicators, shared next schedule and refresh.
- [ ] Internal HR notes/scores and unpublished outcomes stay private. Match existing release behavior instead of exposing raw workflow tables.

References: `includes/candidate-portal.php`, `includes/candidate-publication.php`, `includes/candidate-schedule.php`, `js/candidate-tracking.js`, `css/candidate-tracking.css`.

## Data, configuration and previous defects

1. Treat the PHP database and uploaded files as existing application data. Preserve primary keys, MRF numbers, applicant IDs, relationships, original submissions, revisions, dates, passwords and histories. Develop against an isolated Laravel database copy; do not run destructive migrations or resets against the working source or hosted database.
2. The original MariaDB instance was recovered after corrupt redo logs. Preserved backups are at `C:\xampp\mysql-recovery-20261028-093250`; `RECOVERY-REPORT.txt` explains validation and limitations. Recovery is not a migration step and must not be repeated against healthy databases.
3. Hosted MySQL/MariaDB previously failed on mixed `utf8mb4_general_ci`/`utf8mb4_unicode_ci` comparisons in computed invitation filters and email bookkeeping. Preserve the fixes and verify native prepared statements on both connection collations. Avoid reintroducing comparisons between incompatible computed strings or bound parameters.
4. Application timestamps are stored in UTC; business dates/displays use the existing Philippine/UTC+8 rules. Preserve inclusive date ranges with exclusive next-day upper boundaries where implemented.
5. Keep existing private DB/mail configuration out of Git and documentation. Inspect names/examples to map settings into Laravel's environment configuration without exposing values. Never send real invitations as a test by default.
6. Uploaded MRFs, candidate photos and other personal records need equivalent private storage and authorized download routes. Do not place backend includes, database scripts or secrets in Laravel's public directory.
7. Do not rely solely on generated fake data. Compare relevant record counts, relationships, formulas and representative real-data reads without altering source records.

## Implementation sequence

1. Inspect target `AGENTS.md`, installed PHP/Laravel versions, existing code, tests and configuration. Preserve target work and credentials. Record the reference revision and test baseline.
2. Map the existing schema to Laravel migrations/models with compatible table names, IDs, relationships and hashes. Set up an isolated working copy and verify connection/collation handling.
3. Implement role-specific authentication, authorization, CSRF/session/rate limits, storage and compatibility/navigation routes.
4. Reuse the existing HTML/CSS/JavaScript appearance through Laravel views/assets, then move endpoint behavior into Laravel controllers/services. Do not claim a complete migration merely by wrapping the old PHP application behind Laravel routes.
5. Port Department MRFs and HR MRF approval/ownership first, then invitations and candidate CIF/assessments/deployment, communication, dashboards/KPI and tracker. Dependencies may justify changing this order; record the reason.
6. Compare each module's success, validation, permission, concurrency, empty-state and date-filter behavior with the reference. Translate relevant checks into Laravel tests and browser coverage.
7. Validate data migration, all roles and production configuration separately. A local migration does not authorize replacing the hosted system.

## Validation references

Check which scripts are current before running them; some old tests describe retired behavior. Most browser fixtures create isolated test databases. Never adapt a test by pointing a reset/drop helper at a real database.

| Area | Existing reference checks |
| --- | --- |
| Dashboard and counts | `database/test-dashboard-summary-cards.cjs`, `database/check-dashboard-pairs.cjs`, `database/test-dashboard-pairs-fixture.php` |
| MRF records, openings and import | `database/check-department-mrf.cjs`, `database/check-mrf-headcounts.cjs`, `database/check-mrf-import.cjs`, `database/test-mrf-document.php` |
| Approval and deadlines | `database/check-mrf-approval.cjs`, `database/check-mrf-approval-timing.cjs`, `database/test-mrf-approval-timing.php` |
| Requestor edit/reset | `database/check-requestor-edit.cjs`, `database/check-mrf-requestors.cjs` |
| Candidate workflow | `database/check-candidate-stages.cjs`, `database/check-assessment-requirements.cjs`, `database/test-candidate-roadmap.php`, `database/test-candidate-release-order.php` |
| Candidate/MRF linkage | `database/check-candidate-mrf.cjs`, `database/check-screening-mrf.cjs` |
| Deployment lock | `database/check-deployed-readonly.cjs`, `database/check-job-offer-deployment.cjs` |
| Invitations/collation | `database/check-archived-invitations.cjs`, `database/test-invitation-filter-collation.php`, `database/test-mrf-filter-collation.php` |
| Email | `database/check-email-send.cjs`, `database/check-hr-email.cjs` (older preparation-only expectations require review) |
| KPI | `database/test-mrf-hr-kpi.php`, `database/test-kpi-coverage.php`, `database/check-hr-staff.cjs` |
| Tracker and form details | `database/check-candidate-publication.cjs`, `database/test-tracker-required-assessments.php`, `database/check-candidate-addresses.cjs`, `database/check-text-format.cjs` |

Module completion requires equivalent working behavior, appropriate automated checks, responsive visual comparison and an updated progress record. Report remaining gaps explicitly; do not mark the entire migration complete while any required module remains unverified.

## Prompt for a future session in the Laravel folder

> Read AGENTS.md, docs/LARAVEL_MIGRATION_HANDOFF.md, docs/MIGRATION_PROGRESS.md and docs/SOURCE_REFERENCE_MANIFEST.json. Continue migrating C:\xampp\htdocs\cif into this Laravel project. Preserve the current appearance and behavior for all three roles and all 14 modules, including data, permissions, filters, printing, email, workflow, dashboards and KPI formulas. Use the original source and current tests to resolve details. Keep the source system working, use an isolated development database, do not expose credentials, and update the progress record with changes, checks and remaining gaps. Complete the next unfinished work in dependency order.
