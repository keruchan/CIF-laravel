<?php

$departmentPage = $departmentPage ?? 'portal';
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php 
echo $departmentPage === 'mrf-record' ? 'MRF ' . \App\Services\Recruitment\Mrf::mrfEscape($departmentRecord['mrf_number']) . ' — MRF Requestor | APEC Homes' : ($departmentPage === 'mrf-form' ? 'New Manpower Request — MRF Requestor | APEC Homes' : 'MRF Requestor | APEC Homes');
?></title>
<?php 
\App\Services\Recruitment\Branding::renderCifBrandHead();
?>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php 
echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/session-loading.css'), ENT_QUOTES, 'UTF-8');
?>"><link rel="stylesheet" href="<?php 
echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/admin.css'), ENT_QUOTES, 'UTF-8');
?>"><link rel="stylesheet" href="<?php 
echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/hr-staff.css'), ENT_QUOTES, 'UTF-8');
?>"><link rel="stylesheet" href="<?php 
echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/department.css'), ENT_QUOTES, 'UTF-8');
?>"><link rel="stylesheet" href="<?php 
echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/sidebar.css'), ENT_QUOTES, 'UTF-8');
?>"><link rel="stylesheet" href="<?php 
echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/mrf.css'), ENT_QUOTES, 'UTF-8');
?>"><link rel="stylesheet" href="<?php 
echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/position-picker.css'), ENT_QUOTES, 'UTF-8');
?>">
<link rel="stylesheet" href="<?php 
echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/actions.css'), ENT_QUOTES, 'UTF-8');
?>">
<link rel="stylesheet" href="<?php 
echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/mrf-status-palette.css'), ENT_QUOTES, 'UTF-8');
?>">
</head><body<?php 
echo $departmentPage === 'mrf-record' ? ' class="mrf-portal-record"' : '';
?>>
<?php 
\App\Services\Recruitment\SessionLoading::renderCifSessionLoading();
?>
<noscript><style>#session-loading{display:none}</style><p>Please enable JavaScript to use the CIF portal.</p></noscript>
<section id="login" class="hide"><form id="department-login"><?php 
\App\Services\Recruitment\Branding::renderCifBrandLogo('brand-login-logo');
?><h2>MRF Requestor</h2><p>Sign in to submit and track manpower requests.</p>
 <div class="f"><label for="username">Email</label><input id="username" type="email" autocomplete="username" maxlength="150" placeholder="name@company.com" required></div>
 <div class="f"><label for="password">Password</label><input id="password" type="password" autocomplete="current-password" required></div>
 <p id="login-error" class="settings-error" role="alert" hidden></p><button class="btn" id="login-submit" type="submit">Sign in</button>
</form></section>
<div id="app" class="hide" data-department-page="<?php 
echo \App\Services\Recruitment\Mrf::mrfEscape($departmentPage);
?>"<?php 
echo $departmentPage === 'mrf-record' ? ' data-request-number="' . \App\Services\Recruitment\Mrf::mrfEscape($departmentRecord['mrf_number']) . '"' : '';
?>>
 <aside id="portal-sidebar" aria-label="MRF requestor navigation"><div class="logo portal-sidebar-brand"><div class="portal-brand-identity"><span class="portal-brand-logo"><?php 
\App\Services\Recruitment\Branding::renderCifBrandLogo();
?></span><strong>MRF Requestor</strong></div><button class="sidebar-desktop-toggle" type="button" aria-label="Hide sidebar" aria-controls="portal-sidebar" aria-expanded="true"><?php 
echo \App\Services\Recruitment\PortalIcons::portalIcon('menu');
?></button></div><small>Requestor menu</small>
  <button class="nv on" type="button" data-view="dashboard"><span class="ic"><?php 
echo \App\Services\Recruitment\PortalIcons::portalIcon('dashboard');
?></span>Dashboard</button>
  <button class="nv" type="button" data-view="mrf"><span class="ic"><?php 
echo \App\Services\Recruitment\PortalIcons::portalIcon('manpower');
?></span>Manpower requests</button>
  <div class="me"><button id="department-settings-open" type="button" aria-haspopup="dialog" aria-controls="department-settings" title="Open requestor settings"><span class="av"><?php 
echo \App\Services\Recruitment\PortalIcons::portalIcon('settings');
?></span><span><strong id="profile-name">MRF Requestor</strong><small id="profile-username">Account settings</small></span></button><button id="out" type="button" aria-label="Sign out" title="Sign out"><?php 
echo \App\Services\Recruitment\PortalIcons::portalIcon('logout');
?></button></div>
 </aside>
 <main><header><button id="burger" type="button" aria-label="Show sidebar" aria-controls="portal-sidebar" aria-expanded="false"><?php 
echo \App\Services\Recruitment\PortalIcons::portalIcon('menu');
?></button><div><h1 id="ttl">Dashboard</h1><p id="sub">Your manpower requests at a glance.</p></div></header><div class="pg">
  <?php 
if ($departmentPage === 'mrf-form') {
    ?>
  <section id="mrf-form" data-page><?php 
    \App\Services\Recruitment\MrfView::renderMrfForm();
    ?></section>
  <?php 
} elseif ($departmentPage === 'mrf-record') {
    ?>
  <section id="mrf-record" data-page><?php 
    \App\Services\Recruitment\MrfRecordContent::renderMrfRecordContent($departmentRecord, 'department');
    ?></section>
  <?php 
} else {
    ?>
  <section id="dashboard" data-page aria-label="Manpower request dashboard">
   <div class="mrf-heading"><div><h2>Manpower overview</h2><p>Your submitted requests, recruitment progress and hiring deadlines.</p></div><div class="requestor-dashboard-actions"><button class="btn g" id="dashboard-refresh" type="button">Refresh</button><a class="btn" href="mrf-form.php">New manpower request</a></div></div>
   <?php 
    \App\Services\Recruitment\MrfView::renderDepartmentCoverage('dashboard');
    ?>
   <p id="dashboard-error" class="settings-error" role="alert" hidden></p>
   <p id="dashboard-loading" role="status">Loading your dashboard...</p>
   <div id="dashboard-content" hidden>
    <div id="dashboard-metrics" class="stats mrf-stats requestor-metrics" aria-label="Manpower summary"></div>
    <div class="requestor-dashboard-grid requestor-charts">
     <section class="card" aria-labelledby="dashboard-approval-title"><h3 id="dashboard-approval-title">Management approval</h3><p class="requestor-hint">Management decisions recorded by HR. Select a bar to view requests.</p><div id="dashboard-approval" class="requestor-chart"></div></section>
     <section class="card" aria-labelledby="dashboard-progress-title"><h3 id="dashboard-progress-title">Recruitment progress</h3><p class="requestor-hint">One status per request. Mixed headcount statuses show In Progress.</p><div id="dashboard-progress" class="requestor-chart"></div></section>
    </div>
    <div class="requestor-dashboard-grid">
     <section class="card"><h3><button type="button" class="requestor-panel-link" id="dashboard-open-deadlines">Hiring deadlines &rarr;</button></h3><p class="requestor-hint">Earliest due dates for approved, unfinished requests, including On Hold.</p><div id="dashboard-deadlines"></div></section>
     <section class="card"><h3><button type="button" class="requestor-panel-link" id="dashboard-open-recent">Recent requests &rarr;</button></h3><p class="requestor-hint">Your five most recently submitted requests.</p><div id="dashboard-recent"></div></section>
    </div>
    <p class="requestor-hint">All figures cover this account's requests within the selected date coverage. Headcount is the total requested, not a count of filled vacancies. Deadlines use Philippine dates.</p>
   </div>
  </section>
  <section id="mrf" class="hide" data-page><?php 
    \App\Services\Recruitment\MrfView::renderMrfList(true);
    ?></section>
  <?php 
}
?>
 </div></main>
</div>
<dialog id="department-settings" aria-labelledby="settings-title">
 <div class="invite-heading"><div><h3 id="settings-title">Requestor settings</h3><p class="settings-description">Only your group and password can be changed. Contact HR about other account details.</p></div><button class="btn g" id="settings-close" type="button">Close</button></div>
 <form id="settings-form">
  <div class="f"><label for="settings-username">Email</label><input id="settings-username" type="email" autocomplete="username" readonly><small>Contact HR to change your sign-in email.</small></div>
  <div class="f"><label for="settings-name">Name / requestor’s name</label><input id="settings-name" maxlength="150" autocomplete="name" readonly><small>Configured by HR and used on new manpower requests.</small></div>
  <div class="f"><label for="settings-position">Requestor’s position</label><input id="settings-position" readonly><small>Configured by HR.</small></div>
  <div class="f"><label for="settings-department">Department</label><input id="settings-department" readonly></div>
  <div class="f"><label for="settings-group">Group</label><input id="settings-group" maxlength="150" required><small>Changes apply to new requests. Existing MRFs keep their original details.</small></div>
  <fieldset class="settings-password"><legend>Change password</legend><p>Leave both new-password fields blank to keep your password.</p>
   <div class="f"><label for="settings-new">New password</label><input id="settings-new" type="password" autocomplete="new-password" minlength="12" maxlength="72"><small>Use at least 12 characters (maximum 72 bytes).</small></div>
   <div class="f"><label for="settings-confirm">Confirm new password</label><input id="settings-confirm" type="password" autocomplete="new-password" maxlength="72"></div>
  </fieldset>
  <div class="f"><label for="settings-current">Current password</label><input id="settings-current" type="password" autocomplete="current-password" maxlength="72" required></div>
  <p id="settings-error" class="settings-error" role="alert" hidden></p><p id="settings-feedback" class="settings-feedback" role="status" aria-live="polite" hidden></p>
  <div class="settings-actions"><button class="btn g" id="settings-cancel" type="button">Cancel</button><button class="btn" id="settings-save" type="submit">Save changes</button></div>
 </form>
</dialog>
<?php 
if ($departmentPage === 'mrf-form') {
    ?>
<dialog id="mrf-leave-confirm" aria-labelledby="mrf-leave-title" aria-describedby="mrf-leave-description">
 <h2 id="mrf-leave-title">Leave without submitting?</h2>
 <p id="mrf-leave-description">Your changes have not been submitted. Leaving this page will clear the form, and you will need to fill it out again.</p>
 <div class="settings-actions"><button class="btn g" id="mrf-leave-stay" type="button" autofocus>Keep editing</button><button class="btn" id="mrf-leave-discard" type="button">Leave page</button></div>
</dialog>
<dialog id="mrf-submit-confirm" aria-labelledby="mrf-submit-title" aria-describedby="mrf-submit-description">
 <h2 id="mrf-submit-title">Complete manpower request?</h2>
 <p id="mrf-submit-description">This generates the MRF number. You will not be able to edit the request afterward. Print it for management approval outside this system and deliver the approved copy or confirmation to HR. The request cannot be selected for recruitment until HR records Approved.</p>
 <p id="mrf-submit-summary"></p>
 <div class="settings-actions"><button class="btn g" id="mrf-submit-cancel" type="button" autofocus>Review form</button><button class="btn" id="mrf-submit-confirm-send" type="button">Complete request</button></div>
</dialog>
<?php 
}
?>
<button id="sidebar-backdrop" type="button" aria-label="Close sidebar" tabindex="-1" hidden></button>
<div id="department-toast" role="status" aria-live="polite" hidden></div>
<script src="<?php 
echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/access.js'), ENT_QUOTES, 'UTF-8');
?>" data-endpoint="../ajax/department.php"></script><script src="<?php 
echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/sidebar.js'), ENT_QUOTES, 'UTF-8');
?>"></script><script src="<?php 
echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/position-picker.js'), ENT_QUOTES, 'UTF-8');
?>"></script>
<script src="<?php 
echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/department-coverage.js'), ENT_QUOTES, 'UTF-8');
?>"></script>
<?php 
if ($departmentPage === 'mrf-form') {
    ?><script src="<?php 
    echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/mrf-form.js'), ENT_QUOTES, 'UTF-8');
    ?>"></script><?php 
} elseif ($departmentPage === 'mrf-record') {
    ?><script src="<?php 
    echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/mrf-record.js'), ENT_QUOTES, 'UTF-8');
    ?>"></script><?php 
} else {
    ?><script src="<?php 
    echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/mrf.js'), ENT_QUOTES, 'UTF-8');
    ?>" data-portal="department"></script><?php 
}
?>
<script src="<?php 
echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/department-dashboard.js'), ENT_QUOTES, 'UTF-8');
?>"></script>
<script src="<?php 
echo htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/department.js'), ENT_QUOTES, 'UTF-8');
?>"></script>
<?php 
if ($departmentPage === 'mrf-record' && isset(request()->query()['print'])) {
    ?><script>window.addEventListener('load',async()=>{try{const state=await cifReady;if(state.department)window.print();}catch(error){/* Session errors are shown by the portal. */}});</script><?php 
}
?>
</body></html>

