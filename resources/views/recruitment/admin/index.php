<?php








$adminPage = $adminPage ?? 'portal';


?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $adminPage === 'cif-record' ? \App\Services\Recruitment\Mrf::mrfEscape($adminCandidate['candidate_number']) . ' — HR Recruitment | APEC Homes' : ($adminPage === 'mrf-record' ? 'MRF ' . \App\Services\Recruitment\Mrf::mrfEscape($adminRecord['mrf_number']) . ' — HR Recruitment | APEC Homes' : 'HR Recruitment | APEC Homes') ?></title>
<?php \App\Services\Recruitment\Branding::renderCifBrandHead(); ?>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/session-loading.css'), ENT_QUOTES, 'UTF-8') ?>"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/admin.css'), ENT_QUOTES, 'UTF-8') ?>"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/sidebar.css'), ENT_QUOTES, 'UTF-8') ?>"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/mrf.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/hr-staff.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/reports.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/hr-email.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/mrf-status-palette.css'), ENT_QUOTES, 'UTF-8') ?>">
<?php if ($adminPage === 'cif-record'): ?><link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/cif-record.css'), ENT_QUOTES, 'UTF-8') ?>"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/cif-photo.css'), ENT_QUOTES, 'UTF-8') ?>"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/cif-print.css'), ENT_QUOTES, 'UTF-8') ?>"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/candidate-updates.css'), ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/position-picker.css'), ENT_QUOTES, 'UTF-8') ?>"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/candidate-workflow.css'), ENT_QUOTES, 'UTF-8') ?>"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/actions.css'), ENT_QUOTES, 'UTF-8') ?>"></head><body<?= $adminPage === 'cif-record' ? ' class="cif-portal-record"' : ($adminPage === 'mrf-record' ? ' class="mrf-portal-record"' : '') ?>>

<?php  \App\Services\Recruitment\SessionLoading::renderCifSessionLoading(); ?>
<noscript><style>#session-loading{display:none}</style><p>Please enable JavaScript to use the CIF portal.</p></noscript>

<!-- ADMIN LOGIN: PHP session login (HR account) -->
<section id="login" class="hide"><form id="lf"><?php \App\Services\Recruitment\Branding::renderCifBrandLogo('brand-login-logo'); ?><h2>HR Recruitment</h2><p>Sign in to manage candidate information.</p>
 <div class="f"><label for="ae">Username</label><input id="ae" type="text" placeholder="Enter your username" autocomplete="username" required></div>
 <div class="f"><label for="ap">Password</label><input id="ap" type="password" placeholder="Enter your password" autocomplete="current-password" required></div>
 <p id="ae_err" class="hide" style="color:#b32d3a;font-weight:600;margin:-6px 0 14px">Incorrect username or password. Please try again.</p>
 <button class="btn" style="width:100%">Sign in</button><p class="portal-return"><a href="../portal/">Choose another portal</a></p>
 </form></section>

<div id="app" class="hide" data-admin-page="<?= \App\Services\Recruitment\Mrf::mrfEscape($adminPage) ?>"<?= $adminPage === 'mrf-record' ? ' data-request-number="' . \App\Services\Recruitment\Mrf::mrfEscape($adminRecord['mrf_number']) . '"' : '' ?>>
<aside id="portal-sidebar" aria-label="HR navigation"><div class="logo portal-sidebar-brand"><div class="portal-brand-identity"><span class="portal-brand-logo"><?php \App\Services\Recruitment\Branding::renderCifBrandLogo(); ?></span><strong>HR Recruitment</strong></div><button class="sidebar-desktop-toggle" type="button" aria-label="Hide sidebar" aria-controls="portal-sidebar" aria-expanded="true"><?= \App\Services\Recruitment\PortalIcons::portalIcon('menu') ?></button></div>
 <small>Menu</small>
 <button class="nv on" data-v="dash"><span class="ic"><?= \App\Services\Recruitment\PortalIcons::portalIcon('dashboard') ?></span>Dashboard</button>
 <button class="nv" data-v="list"><span class="ic"><?= \App\Services\Recruitment\PortalIcons::portalIcon('candidates') ?></span>Candidates<em id="cnt" title="Candidates awaiting screening"></em></button>
 <button class="nv" data-v="gen"><span class="ic"><?= \App\Services\Recruitment\PortalIcons::portalIcon('invitations') ?></span>Invitations<em id="invite-cnt" title="Pending invitations"></em></button>
 <small class="hr-staff-section">MRF</small>
 <button class="nv" data-v="mrf" type="button"><span class="ic"><?= \App\Services\Recruitment\PortalIcons::portalIcon('manpower') ?></span>MRF requests<em id="mrf-cnt" title="Manpower files awaiting approval"></em></button>
 <button class="nv" data-v="requestors" type="button"><span class="ic"><?= \App\Services\Recruitment\PortalIcons::portalIcon('candidates') ?></span>MRF requestors</button>
 <small class="hr-staff-section">Communication</small>
 <button class="nv" data-v="templates" type="button"><span class="ic"><?= \App\Services\Recruitment\PortalIcons::portalIcon('email-templates') ?></span>Email templates</button>
 <button class="nv" data-v="mail" type="button"><span class="ic"><?= \App\Services\Recruitment\PortalIcons::portalIcon('mail-management') ?></span>Mail management</button>
 <small class="hr-staff-section">Team management</small>
 <button class="nv" data-v="staff" type="button"><span class="ic"><?= \App\Services\Recruitment\PortalIcons::portalIcon('hr-staff') ?></span>KPI</button>
 <button class="nv" data-v="reports" type="button"><span class="ic"><?= \App\Services\Recruitment\PortalIcons::portalIcon('cif') ?></span>Printables</button>
 <div class="me"><button id="admin-settings-open" type="button" aria-haspopup="dialog" aria-controls="admin-settings" title="Open admin settings"><span class="av"><?= \App\Services\Recruitment\PortalIcons::portalIcon('settings') ?></span><span><strong id="admin-profile-name">HR Admin</strong><small id="admin-profile-username">Account settings</small></span></button><button id="out" type="button" title="Sign out" aria-label="Sign out"><?= \App\Services\Recruitment\PortalIcons::portalIcon('logout') ?></button></div>
</aside>
<main>
 <header><button id="burger" type="button" aria-label="Show sidebar" aria-controls="portal-sidebar" aria-expanded="false"><?= \App\Services\Recruitment\PortalIcons::portalIcon('menu') ?></button><div><h1 id="ttl"></h1><p id="sub"></p></div></header>
 <div class="pg">
 <?php if ($adminPage === 'mrf-record'): ?>
 <section id="mrf-record"><?php \App\Services\Recruitment\MrfRecordContent::renderMrfRecordContent($adminRecord, 'admin'); ?></section>
 <?php elseif ($adminPage === 'cif-record'): ?>
 <section id="cif-record"><?php \App\Services\Recruitment\CifRecordContent::renderAdminCandidateRecord($adminCandidate); ?></section>
 <?php else: ?>
 <section id="dash">
  <div class="dashboard-heading"><div><h2>Recruitment overview</h2><p>Click a card or chart row to explore the matching records.</p></div><button class="btn g" id="dashboard-refresh" type="button">Refresh overview</button></div>
  <?php $dashboardToday=new \DateTimeImmutable('today',new \DateTimeZone('Asia/Manila')); ?>
  <form id="dashboard-filter-form" class="card hr-monitor-filters hr-kpi-filter-preview dashboard-filters" data-today="<?= $dashboardToday->format('Y-m-d') ?>" data-default-from="" data-default-to="">
   <label>Position<select id="dashboard-position"><option value="">All positions</option><?php foreach (\App\Services\Recruitment\HiringPositions::cifAppliedPositions() as $position): ?><option value="<?= htmlspecialchars($position, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($position, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option><?php endforeach; ?></select></label>
   <label>Group chart by<select id="dashboard-period"><option value="daily">Days</option><option value="weekly">Weeks</option><option value="monthly" selected>Months</option><option value="yearly">Years</option></select></label>
   <label>From<input id="dashboard-from" type="date" min="1900-01-01" max="9998-12-31" value="" aria-describedby="dashboard-context"></label>
   <label>To<input id="dashboard-to" type="date" min="1900-01-01" max="9998-12-31" value="" aria-describedby="dashboard-context"></label>
   <div class="hr-kpi-filter-toolbar"><div class="hr-kpi-shortcuts" role="group" aria-label="Dashboard date shortcuts"><button type="button" data-dashboard-coverage="week">This week</button><button type="button" data-dashboard-coverage="month">This month</button><button type="button" data-dashboard-coverage="year">This year</button><button type="button" data-dashboard-coverage="last12">Last 12 months</button><button type="button" data-dashboard-coverage="all">All dates</button></div><div class="hr-kpi-filter-actions"><button id="dashboard-filter-reset" type="button" class="btn g">Reset</button></div></div>
   <p id="dashboard-context" class="hr-kpi-coverage-preview" role="status" aria-live="polite"></p>
   <p id="dashboard-filter-error" class="hr-kpi-filter-error" role="alert" hidden></p>
  </form>
  <div class="stats" id="stats"></div>
  <div class="dashboard-tabs" role="tablist" aria-label="Dashboard analysis">
   <button id="analysis-recruitment" type="button" role="tab" data-analysis="recruitment" aria-selected="true" aria-controls="dashboard-analysis">Recruitment</button>
   <button id="analysis-profile" type="button" role="tab" data-analysis="profile" aria-selected="false" aria-controls="dashboard-analysis" tabindex="-1">Candidate profile</button>
   <button id="analysis-location" type="button" role="tab" data-analysis="location" aria-selected="false" aria-controls="dashboard-analysis" tabindex="-1">Location & salary</button>
  </div>
  <p class="dashboard-note">All cards follow the selected dates and position. Dates use form submissions and when invitations or requests were created; Assigned MRF due uses hiring deadlines. Archived candidates and invitations are hidden. Open positions are approved openings still to be filled.</p>
  <div id="dashboard-analysis" role="tabpanel" aria-labelledby="analysis-recruitment">
  <div class="dashboard-grid" id="dashboard-charts"></div>
  <div class="card dashboard-recent"><div class="invitation-list-heading"><h3>Recent submissions</h3></div><div class="tw"><table><thead><tr><th>Candidate</th><th>Position</th><th>Status</th><th>Submitted</th></tr></thead><tbody id="dashboard-recent"></tbody></table></div></div>
  </div>
 </section>

 <section id="list" class="hide" aria-labelledby="candidates-page-title">
  <div class="mrf-heading"><div><h2 id="candidates-page-title">Application overview</h2><p>Review submitted CIFs, assessment results and each candidate's hiring stage.</p></div><div class="mrf-heading-actions"><button class="btn g" id="candidates-refresh" type="button">Refresh</button></div></div>
  <div class="stats candidate-stats" id="candidate-stats" aria-label="Candidate summaries"></div>
  <div class="card">
  <div class="invitation-list-heading"><h3 id="candidate-list-title">Active candidates</h3><div class="invitation-view-switch" aria-label="Candidate view"><button id="candidate-active-view" class="btn g" type="button" aria-pressed="true">Active</button><button id="candidate-archived-view" class="btn g" type="button" aria-pressed="false">Archived</button></div></div>
  <div id="candidate-insight" class="candidate-insight hide"><span id="candidate-insight-label"></span><button id="candidate-insight-clear" class="btn g" type="button">Clear dashboard filter</button></div>
  <div class="tb table-filters"><input class="s" id="q" type="search" maxlength="254" aria-label="Search candidates" placeholder="Search name, email, App ID or applied position">
   <select id="fs" aria-label="Filter by stage"></select><select id="fss" aria-label="Filter by stage status"></select><select id="fp" aria-label="Filter by applied position"><option value="">All positions</option><?php foreach (\App\Services\Recruitment\HiringPositions::cifAppliedPositions() as $position): ?><option value="<?= htmlspecialchars($position, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($position, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option><?php endforeach; ?></select></div>
  <div class="tw"><table><thead><tr><th aria-sort="none"><button class="sort-header candidate-sort-header" data-key="id" type="button">App ID <span class="sort-direction" aria-hidden="true">↕</span></button></th><th aria-sort="none"><button class="sort-header candidate-sort-header" data-key="n" type="button">Candidate <span class="sort-direction" aria-hidden="true">↕</span></button></th><th>Applied position</th><th>Stage / status</th><th aria-sort="descending"><button class="sort-header candidate-sort-header" data-key="d" type="button">Submitted <span class="sort-direction" aria-hidden="true">↓</span></button></th><th>Actions</th></tr></thead><tbody id="tbd"></tbody></table></div>
  <div class="pgn"><span id="info"></span><div id="pn"></div></div>
 </div></section>

 <section id="mrf" class="hide"><?php \App\Services\Recruitment\MrfView::renderMrfList(false); ?></section>
 <section id="requestors" class="hide" aria-labelledby="requestors-title">
  <div class="mrf-heading"><div><h2 id="requestors-title">Account management</h2><p>Create accounts, control sign-in access and review archived requestors.</p></div><div class="mrf-heading-actions"><button class="btn g" id="requestors-refresh" type="button">Refresh</button><button class="btn" id="requestors-register" type="button">Register requestor</button></div></div>
  <div class="invitation-list-heading"><h3 id="requestors-list-title">Current requestors</h3><div class="invitation-view-switch" aria-label="Requestor account view"><button id="requestors-current" class="btn g" type="button" aria-pressed="true">Current</button><button id="requestors-archived" class="btn g" type="button" aria-pressed="false">Archived</button></div></div>
  <div class="card"><div class="tb"><input class="s" id="requestors-search" type="search" maxlength="150" placeholder="Search requestor, email or group" aria-label="Search MRF requestors"></div>
   <p class="mrf-list-note">Requestors sign in with their email address. New accounts start Active. Suspended or archived accounts cannot sign in. No accounts or past requests are deleted.</p>
   <div class="tw"><table><thead><tr><th>Requestor / position</th><th>Email</th><th>Department</th><th>Group</th><th>Status</th><th>Actions</th></tr></thead><tbody id="requestors-rows"></tbody></table></div>
   <p id="requestors-error" class="settings-error" role="alert" hidden></p>
   <div class="pgn"><span id="requestors-info" role="status" aria-live="polite"></span><div id="requestors-pages" aria-label="MRF requestor pagination"></div></div>
  </div>
  <dialog id="requestor-register-dialog" class="requestor-dialog" aria-labelledby="requestor-register-title">
   <div class="invite-heading"><h3 id="requestor-register-title">Register MRF requestor</h3><button class="btn g" id="requestor-register-close" type="button">Close</button></div>
   <form id="requestor-register-form">
    <div class="f"><label for="requestor-email">Email</label><input id="requestor-email" type="email" maxlength="150" autocomplete="off" required></div>
    <div class="f"><label for="requestor-name">Full name</label><input id="requestor-name" maxlength="150" autocomplete="off" required></div>
    <div class="f"><label for="requestor-position">Requestor’s position</label><input id="requestor-position" maxlength="150" required><small>Used on the MRF and configured by HR.</small></div>
    <div class="f"><label for="requestor-department">Department</label><input id="requestor-department" maxlength="150" required></div>
    <div class="f"><label for="requestor-group">Group</label><input id="requestor-group" maxlength="150" required></div>
    <p class="mrf-list-note">A unique default password will be generated. The requestor can change only their group and password in settings.</p>
    <p id="requestor-register-error" class="settings-error" role="alert" hidden></p>
    <div class="settings-actions"><button class="btn" id="requestor-register-submit" type="submit">Register requestor</button></div>
   </form>
   <div id="requestor-register-result" hidden><p id="requestor-register-message" role="status"></p>
    <div class="f"><label for="requestor-created-username">Email</label><input id="requestor-created-username" type="email" readonly autocomplete="off"></div>
    <div class="f"><label for="requestor-created-password">Default password</label><input id="requestor-created-password" readonly autocomplete="off"></div>
    <p class="mrf-list-note">Save these details and share them securely. This password is shown only here and is not emailed automatically.</p>
    <p class="mrf-list-note"><a href="../mrf-requestor/" target="_blank" rel="noopener">Open MRF requestor sign-in</a></p>
   </div>
  </dialog>
  <dialog id="requestor-edit-dialog" class="requestor-dialog" aria-labelledby="requestor-edit-title">
   <div class="invite-heading"><h3 id="requestor-edit-title">Edit requestor</h3><button class="btn g" id="requestor-edit-close" type="button">Close</button></div>
   <p class="mrf-list-note">Update this account or reset its password. Changes apply to new requests; previously submitted MRFs keep their saved details.</p>
   <form id="requestor-edit-form" autocomplete="off">
    <div class="f"><label for="requestor-edit-username">Email</label><input id="requestor-edit-username" type="email" maxlength="150" required autocomplete="off"><small class="mrf-hint">The requestor will use this email address to sign in.</small></div>
    <div class="f"><label for="requestor-edit-name">Full name</label><input id="requestor-edit-name" maxlength="150" required autocomplete="off"></div>
    <div class="f"><label for="requestor-edit-position">Position</label><input id="requestor-edit-position" maxlength="150"></div>
    <div class="f"><label for="requestor-edit-department">Department</label><input id="requestor-edit-department" maxlength="150"></div>
    <div class="f"><label for="requestor-edit-group">Group</label><input id="requestor-edit-group" maxlength="150"></div>
    <label class="requestor-password-toggle"><input id="requestor-edit-reset" type="checkbox"> Reset requestor password</label>
    <fieldset id="requestor-edit-password-fields" class="requestor-password-fields" hidden disabled>
     <legend>Requestor's new password</legend>
     <div class="f"><label for="requestor-edit-new-password">New password</label><input id="requestor-edit-new-password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required><small class="mrf-hint">Choose a password with at least 8 characters.</small></div>
     <div class="f"><label for="requestor-edit-confirm-password">Confirm new password</label><input id="requestor-edit-confirm-password" type="password" autocomplete="new-password" maxlength="72" required></div>
    </fieldset>
    <div class="f"><label for="requestor-edit-admin-password">Your HR account password</label><input id="requestor-edit-admin-password" type="password" autocomplete="current-password" maxlength="72" required><small class="mrf-hint">Enter your own password to approve these changes.</small></div>
    <p id="requestor-edit-error" class="settings-error" role="alert" hidden></p>
    <div class="settings-actions"><button class="btn g" id="requestor-edit-cancel" type="button">Cancel</button><button class="btn" id="requestor-edit-submit" type="submit">Save changes</button></div>
   </form>
  </dialog>
 </section>
 <?php echo view('recruitment.partials.hr-staff-content')->render(); ?>
 <?php echo view('recruitment.partials.reports-content')->render(); ?>
 <?php echo view('recruitment.partials.hr-email-content')->render(); ?>
 <section id="gen" class="hide" aria-labelledby="invitations-page-title">
  <div class="mrf-heading"><div><h2 id="invitations-page-title">Candidate access</h2><p>Generate invitation codes and monitor first use and submission progress.</p></div><div class="mrf-heading-actions"><button class="btn g" id="invitations-refresh" type="button">Refresh</button><button class="btn" id="invite-open" type="button">Invite candidate / Generate code</button></div></div>
  <div class="stats invitation-stats" id="invite-stats"></div>
  <div class="card"><div class="invitation-list-heading"><h3 id="invitation-list-title">Recent invitations</h3><div class="invitation-view-switch" aria-label="Invitation view"><button id="invite-active-view" class="btn g" type="button" aria-pressed="true">Active</button><button id="invite-archived-view" class="btn g" type="button" aria-pressed="false">Archived</button></div></div>
   <div id="invitation-dashboard-filter" class="candidate-insight hide"><span id="invitation-dashboard-filter-label"></span><button id="invitation-dashboard-filter-clear" class="btn g" type="button">Clear dashboard filter</button></div><div class="tb invitation-filters"><input class="s" id="invite-search" type="search" aria-label="Search invitations" placeholder="Search email, code or applied position" maxlength="254"><select id="invite-status" aria-label="Filter invitation status"><option value="">All statuses</option><option>Pending</option><option>In Progress</option><option>Submitted</option><option>Expired</option></select><select id="invite-position" aria-label="Filter invitation applied position"><option value="">All applied positions</option><?php foreach (\App\Services\Recruitment\HiringPositions::cifAppliedPositions() as $position): ?><option value="<?= htmlspecialchars($position, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($position, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option><?php endforeach; ?></select></div>
   <p class="invitation-notice">Unused codes expire after 10 days. Once used, a code stays valid for completing the form and tracking the application. Progress saves automatically. Applicant IDs are generated on submission and remain permanent.</p>
   <div class="tw"><table><thead><tr><th>Candidate email</th><th>Applied position</th><th>Invitation code</th><th>Status</th><th aria-sort="descending"><button class="sort-header invite-sort-header" data-date="created" type="button">Created <span class="sort-direction" aria-hidden="true">↓</span></button></th><th aria-sort="none"><button class="sort-header invite-sort-header" data-date="expires" type="button">First-use deadline <span class="sort-direction" aria-hidden="true">↕</span></button></th><th>Actions</th></tr></thead><tbody id="rl"></tbody></table></div>
   <div class="pgn"><span id="invite-info" role="status" aria-live="polite"></span><div id="invite-pages" aria-label="Invitation pagination"></div></div>
  </div></section>
 <?php endif; ?>
 </div>
</main></div>

<button id="sidebar-backdrop" type="button" aria-label="Close sidebar" tabindex="-1" hidden></button>
<div id="toast"></div>
<?php if ($adminPage === 'portal'): ?>
<div id="ov"></div>
<div id="dr"><div id="dc"></div></div>
<dialog id="archive-confirmation" aria-labelledby="archive-confirmation-title" aria-describedby="archive-confirmation-description">
 <div id="archive-confirmation-icon" aria-hidden="true">↗</div>
 <h3 id="archive-confirmation-title">Archive candidate?</h3>
 <p id="archive-confirmation-description"></p>
 <div class="archive-candidate-preview"><strong id="archive-candidate-name"></strong><span id="archive-candidate-id"></span><small id="archive-candidate-email"></small></div>
 <p id="archive-confirmation-error" role="alert" hidden></p>
 <div class="archive-confirmation-actions"><button id="archive-confirmation-cancel" class="btn g" type="button" autofocus>Cancel</button><button id="archive-confirmation-submit" class="btn" type="button">Archive candidate</button></div>
</dialog>
<dialog id="invite-form" aria-labelledby="invite-title">
 <div class="invite-heading"><h3 id="invite-title">Invite a candidate</h3><button class="btn g" id="invite-close" type="button">Close</button></div>
 <form id="generate-form">
  <div class="f"><label for="ge">Candidate email</label><input id="ge" type="email" placeholder="candidate@email.com" maxlength="254" required autofocus><small class="mrf-hint">You can invite this email again if its previous invitations are archived.</small></div>
  <div class="f"><label for="gp">Applied position</label><?php \App\Services\Recruitment\HiringPositions::renderCifPositionPicker('gp', ' id="gp" required maxlength="150"'); ?></div>
  <p id="invite-error" class="hide" role="alert"></p>
  <p class="invitation-notice">Candidates must first use their code within 10 days. Once used, it will not expire. They can return to finish their form or track their application with the same email and code.</p>
  <button class="btn" id="gb" type="submit">Send invitation code</button>
 </form>
</dialog>

<?php elseif ($adminPage === 'mrf-record'): ?>
<dialog id="mrf-status-confirm" aria-labelledby="mrf-confirm-title"><h2 id="mrf-confirm-title">Confirm request status</h2><p id="mrf-confirm-description"></p><div class="settings-actions"><button class="btn g" id="mrf-confirm-cancel" type="button" autofocus>Cancel</button><button class="btn" id="mrf-confirm-save" type="button">Confirm & save</button></div></dialog>
<?php endif; ?>
<dialog id="admin-settings" aria-labelledby="admin-settings-title">
 <div class="invite-heading"><div><h3 id="admin-settings-title">Admin settings</h3><p class="settings-description">Manage your profile and password.</p></div><button class="btn g" id="admin-settings-close" type="button">Close</button></div>
 <form id="admin-photo-form" class="settings-photo" data-placeholder="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('assets/candidate-photo-example.jpg'), ENT_QUOTES, 'UTF-8') ?>">
  <div class="hr-profile-editor"><img id="admin-photo-preview" class="hr-profile-avatar hr-profile-preview" src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('assets/candidate-photo-example.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="Your profile picture preview"><div><label for="admin-photo-file">Profile picture</label><input id="admin-photo-file" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="admin-photo-help" disabled><p id="admin-photo-help" class="settings-description">JPEG, PNG or WebP, up to 10 MB. Used on the KPI page.</p></div></div>
  <p id="admin-photo-status" class="settings-description" role="status" aria-live="polite">Your picture saves automatically when selected.</p>
  <p id="admin-photo-error" class="settings-error" role="alert" hidden></p>
 </form>
 <form id="admin-settings-form">
  <div class="f"><label for="admin-settings-username">Username</label><input id="admin-settings-username" autocomplete="username" readonly><small>Your sign-in username cannot be changed here.</small></div>
  <div class="f"><label for="admin-settings-name">Display name</label><input id="admin-settings-name" maxlength="150" required autocomplete="name"></div>
  <fieldset class="settings-password"><legend>Change password</legend><p>Leave both new-password fields blank to keep your password.</p>
   <div class="f"><label for="admin-settings-new">New password</label><input id="admin-settings-new" type="password" autocomplete="new-password" minlength="12" maxlength="72" aria-describedby="admin-password-help"><small id="admin-password-help">Use at least 12 characters (maximum 72 bytes). A long, unique passphrase works well.</small></div>
   <div class="f"><label for="admin-settings-confirm">Confirm new password</label><input id="admin-settings-confirm" type="password" autocomplete="new-password" maxlength="72"></div>
  </fieldset>
  <div class="f"><label for="admin-settings-current">Current password</label><input id="admin-settings-current" type="password" autocomplete="current-password" required maxlength="72"><small>Required to confirm any account changes.</small></div>
  <p id="admin-settings-error" class="settings-error" role="alert" hidden></p>
  <p id="admin-settings-feedback" class="settings-feedback" role="status" aria-live="polite" hidden></p>
  <div class="settings-actions"><button class="btn g" id="admin-settings-cancel" type="button">Cancel</button><button class="btn" id="admin-settings-save" type="submit">Save changes</button></div>
 </form>
</dialog>
<?php if ($adminPage === 'portal'): ?><?php \App\Services\Recruitment\MrfView::renderMrfUpload(); ?>
<dialog id="mrf-action-dialog" class="requestor-dialog" aria-labelledby="mrf-action-title" aria-describedby="mrf-action-summary mrf-action-help">
 <h2 id="mrf-action-title">Change request status</h2>
 <p id="mrf-action-summary" class="settings-description"></p>
 <form id="mrf-action-form">
  <div class="f mrf-field"><label for="mrf-action-reason">Reason <small>(required)</small></label><textarea id="mrf-action-reason" rows="3" maxlength="4000" required></textarea></div>
  <p id="mrf-action-help" class="settings-description">This affects only this headcount file, not the other files in the request. Recruitment cannot continue for this file while it is on hold or cancelled. Its management approval and original hiring due date stay unchanged.</p>
  <p id="mrf-action-error" class="settings-error" role="alert" hidden></p>
  <div class="settings-actions"><button class="btn g" id="mrf-action-close" type="button">Go back</button><button class="btn" id="mrf-action-save" type="submit">Confirm</button></div>
 </form>
</dialog>
<?php endif; ?>
<?php if ($adminPage !== 'cif-record'): \App\Services\Recruitment\MrfView::renderMrfApprovalDialog(); endif; ?>
<script id="candidate-status-catalogue" type="application/json"><?= json_encode(\App\Services\Recruitment\CandidateStatuses::cifCandidateStatuses(), JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?></script>
<script id="candidate-stage-catalogue" type="application/json"><?= json_encode(\App\Services\Recruitment\CandidateListStages::cifCandidateListStageCatalogue(), JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?></script>
<script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/access.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/sidebar.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/admin-shell.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/position-picker.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/hr-profile.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/recruitment-dashboard-filters.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php if ($adminPage !== 'cif-record'): ?><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/mrf-approval.js'), ENT_QUOTES, 'UTF-8') ?>"></script><?php endif; ?>
<?php if ($adminPage === 'cif-record'): ?><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/cif-photo.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/hr-scores.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/cif-record.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/candidate-mrf.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/candidate-stages.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/candidate-deployment.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/candidate-updates.js'), ENT_QUOTES, 'UTF-8') ?>"></script><?php elseif ($adminPage === 'mrf-record'): ?><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/mrf-record.js'), ENT_QUOTES, 'UTF-8') ?>"></script><?php else: ?><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/mrf.js'), ENT_QUOTES, 'UTF-8') ?>" data-portal="admin"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/mrf-requestors.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/hr-staff.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/hr-email.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/reports.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/admin.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/mrf-import.js'), ENT_QUOTES, 'UTF-8') ?>"></script><?php endif; ?>
<?php if ($adminPage === 'mrf-record' && isset($_GET['print'])): ?><script>window.addEventListener('load',async()=>{try{const state=await cifReady;if(state.admin)window.print();}catch(error){/* Session errors are shown by the portal. */}});</script><?php endif; ?>
</body></html>
