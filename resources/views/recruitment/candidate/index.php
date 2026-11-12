<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Shanghai');








$definition = \App\Services\Recruitment\CifFields::cifDefinition();


?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Candidate Information Form | APEC Homes</title>
<?php \App\Services\Recruitment\Branding::renderCifBrandHead(); ?>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/session-loading.css'), ENT_QUOTES, 'UTF-8') ?>"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/candidate.css'), ENT_QUOTES, 'UTF-8') ?>"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/cif-photo.css'), ENT_QUOTES, 'UTF-8') ?>"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/position-picker.css'), ENT_QUOTES, 'UTF-8') ?>"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/candidate-tracking.css'), ENT_QUOTES, 'UTF-8') ?>"></head><body>
<?php  \App\Services\Recruitment\SessionLoading::renderCifSessionLoading(); ?>
<noscript><style>#session-loading{display:none}</style><p>Please enable JavaScript to complete the CIF.</p></noscript>
<section class="login hide" id="login"><form class="lcard" id="lf"><?php \App\Services\Recruitment\Branding::renderCifBrandLogo('brand-login-logo'); ?>
 <h2>Candidate Information Form</h2><p>Enter your invited email and code to continue your saved CIF or track your submitted application.</p><p>Use your code within 10 days. Once used, it will not expire. You can return with the same email and code to finish your form or track your application.</p>
 <div class="field"><label for="em">Email address</label><input id="em" type="email" autocomplete="email" required></div>
 <div class="field"><label for="cd">Invitation code</label><input id="cd" class="code" maxlength="12" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" placeholder="XXXX-XXXX" required></div>
 <p id="lerr" class="hide login-error" role="alert"></p><button class="btn pri" type="submit">Continue</button>
</form></section>
<section id="app" class="hide">
 <div class="top"><div class="candidate-header-brand"><?php \App\Services\Recruitment\Branding::renderCifBrandLogo(); ?><span>Candidate Information Form</span></div><div class="candidate-exit-actions"><p id="candidate-exit-error" class="candidate-exit-error" role="alert" hidden></p><button class="btn sec" id="candidate-exit" type="button">Exit</button></div></div>
 <div class="wrap">
 <section class="panel intro-panel hide" id="candidate-intro" aria-labelledby="intro-title">
  <p class="intro-kicker">Welcome to APEC Homes</p><h1 id="intro-title" tabindex="-1">Before you begin</h1>
  <div class="intro-copy">
   <p>Hey! I hope you are doing fine.</p>
   <p>This form allows interested candidates like you to provide us your personal information relevant to your application. Do not worry! We assure you that information gathered here shall be kept with high confidentiality and in compliance with RA 10173, otherwise known as the <strong>Data Privacy Act of 2012.</strong></p>
   <p>Thank you.</p><p>Stay Healthy. Stay Safe. Smile.</p><p class="intro-hashtags">#APECHomes #PamilyaAngBawatIsa</p>
  </div>
  <aside class="intro-readiness" aria-labelledby="readiness-title"><h2 id="readiness-title">Please prepare your information</h2>
   <p>Have your numbers or supporting documents ready so you can enter the correct details:</p>
   <ul><li>SSS number</li><li>PhilHealth number</li><li>TIN</li><li>Pag-IBIG number</li></ul>
  </aside>
  <div class="intro-privacy">
   <button class="privacy-link" id="read-privacy" type="button" aria-haspopup="dialog">Read data privacy notice and consent form</button>
   <p id="privacy-status" role="status">Please read and accept the data privacy notice before starting your CIF.</p>
  </div>
  <div class="intro-actions"><p>Your progress saves automatically while you fill out the form. Return with the same email and code to continue your saved answers.</p><button class="btn pri" id="start-cif" type="button" disabled>Start filling out the CIF</button></div>
 </section>
 <div class="steps hide" id="steps"></div>
 <form class="panel hide" id="cf" novalidate inert>
 <?php foreach ($definition['sections'] as $section => $fields): ?>
  <div class="step hide" data-label="<?= \App\Services\Recruitment\CandidateForm::escaped($section) ?>"><h3><?= \App\Services\Recruitment\CandidateForm::escaped($section) ?></h3>
   <?php if ($section === 'Personal Information'): ?>
    <div class="application-summary"><span>Your permanent Applicant ID is generated when you submit your CIF.</span><span>Position applied: <b id="applied-position"></b></span><span>Saved progress can be resumed with the same email and code. After submission, those credentials provide application tracking only.</span></div>
   <?php endif; ?>
   <?php if ($section === 'Certification & consent'): ?>
    <div class="consent-copy"><h4>Certification of information</h4><p>I further certify that the information I have indicated in this Candidate Information Form are true, accurate and complete and contains no false or misleading statements or no omission of any material facts that should have been stated therein. I am fully aware that any misrepresentation thereof shall be sufficient cause for the company to immediately terminate my service and/or application.</p></div>
    <label class="chk"><input type="checkbox" id="ok" required><span>I certify that my information is true, accurate and complete.</span></label>
    <p class="signature-note">Your data privacy consent and background and reference check authorization were recorded before you started this form. Background checks remain subject to your prior approval of their timing.</p>
    <p class="signature-note">Type your full name for each declaration. These names and the acceptance time will be saved; handwritten signatures are not collected here.</p>
   <?php endif; ?>
   <?php if ($section === 'Residential Information'): ?>
    <?php foreach (['present' => 'Present address', 'permanent' => 'Permanent address'] as $addressGroup => $addressLabel): ?>
     <fieldset class="address-group"><legend><?= \App\Services\Recruitment\CandidateForm::escaped($addressLabel) ?></legend>
      <?php if ($addressGroup === 'permanent'): ?><label class="chk address-copy"><input type="checkbox" id="same-address"><span>Same as present address</span></label><?php endif; ?>
      <div class="grid address-grid"><?php foreach (\App\Services\Recruitment\CandidateAddress::cifCandidateAddressFields() as $part => $field) { \App\Services\Recruitment\CandidateForm::renderField($addressGroup . '_' . $part, $field); } ?></div>
     </fieldset>
    <?php endforeach; ?>
    <fieldset class="address-group"><legend>Emergency contact</legend><div class="grid">
     <?php foreach (['ecn', 'ecp', 'ecr'] as $name) { \App\Services\Recruitment\CandidateForm::renderField($name, $fields[$name]); } ?>
     <div class="full"><h4 class="address-subheading">Emergency contact address</h4><div class="grid address-grid">
      <?php foreach (\App\Services\Recruitment\CandidateAddress::cifCandidateAddressFields() as $part => $field) { \App\Services\Recruitment\CandidateForm::renderField('emergency_' . $part, $field); } ?>
     </div></div>
    </div></fieldset>
   <?php else: ?>
    <div class="grid"><?php foreach ($fields as $name => $field) { \App\Services\Recruitment\CandidateForm::renderField($name, $field); } ?></div>
   <?php endif; ?>
   <?php foreach ($definition['groups'] as $group => $record): if ($record['section'] !== $section) continue; ?>
    <fieldset class="record-group"><legend><?= \App\Services\Recruitment\CandidateForm::escaped($record['label']) ?> <span class="optional-label">(optional)</span></legend>
     <?php if ($group === 'connections'): ?><p class="record-guide">Do you know someone from APEC Homes? Please share their name and your relationship below. If not, leave this section blank.</p><?php endif; ?>
     <p class="record-help">Leave unused records blank. If you add a record, provide its <?= \App\Services\Recruitment\CandidateForm::escaped(mb_strtolower($record['fields'][$record['anchor']]['label'])) ?>.</p>
     <?php for ($row = 0; $row < $record['max']; $row++): ?>
      <div class="record-row" data-anchor="<?= \App\Services\Recruitment\CandidateForm::escaped($record['anchor']) ?>"><h4>Record <?= $row + 1 ?></h4><div class="grid">
       <?php foreach ($record['fields'] as $name => $field) { \App\Services\Recruitment\CandidateForm::renderField($group . '[' . $row . '][' . $name . ']', $field); } ?>
      </div></div>
     <?php endfor; ?>
    </fieldset>
   <?php endforeach; ?>
  </div>
 <?php endforeach; ?>
 <p id="form-error" class="hide form-error" role="alert"></p>
 <div class="candidate-draft-status"><p id="draft-save-status" role="status" aria-live="polite">Progress saves automatically as you type.</p><button type="button" id="draft-save-retry" class="btn sec" hidden>Retry save</button></div>
 <div id="submit-hint" class="submission-hint hide"><p id="submit-hint-text" role="status"></p><button type="button" class="btn sec" id="review-fields">Review fields</button></div>
 <div class="nav"><button type="button" class="btn sec" id="bk">Back</button><button type="button" class="btn pri" id="nx">Next</button></div>
 </form>
 <section class="application-progress hide" id="fin" aria-labelledby="application-progress-title">
  <div class="tracking-panel">
   <div class="tracking-page-heading"><div><?php \App\Services\Recruitment\Branding::renderCifBrandLogo('brand-tracker-logo'); ?><p class="tracking-eyebrow">APEC HOMES · CANDIDATE PORTAL</p><h1 id="application-progress-title">Application tracker</h1></div><div class="tracking-header-actions"><button class="btn sec" id="application-refresh" type="button"><?= \App\Services\Recruitment\PortalIcons::portalIcon('refresh') ?>Refresh progress</button></div></div>
   <section class="tracking-hero" aria-label="Application summary"><div class="tracking-applicant-badge"><span>Applicant ID</span><b id="applicant-id"></b><span id="application-position"></span></div><div class="tracking-current-phase"><span>Current phase</span><h2 id="application-status"></h2><span id="application-updated" title="Philippine time"></span></div></section>
   <section class="tracking-current" aria-labelledby="tracking-current-title"><span class="tracking-current-icon" aria-hidden="true"><?= \App\Services\Recruitment\PortalIcons::portalIcon('interview') ?></span><div><p id="tracking-current-title">CURRENT STATUS</p><h2 class="tracking-current-tag" id="tracking-current-tag"></h2><p id="tracking-current-message"></p><div id="tracking-parallel-statuses"></div></div></section>
   <section id="tracking-next-schedule" class="tracking-next-schedule" aria-labelledby="tracking-next-label" hidden><span aria-hidden="true"><?= \App\Services\Recruitment\PortalIcons::portalIcon('calendar') ?></span><div><p id="tracking-next-label">NEXT PHASE</p><h3 id="tracking-next-phase"></h3><p id="tracking-next-date"></p></div></section>
   <p id="application-progress-error" class="tracking-progress-error" role="alert" hidden></p>
   <section class="tracking-route" aria-labelledby="tracking-route-title"><div class="tracking-route-heading"><h2 id="tracking-route-title">Hiring process roadmap</h2><p id="application-progress-count"></p></div><div class="tracking-meter" role="progressbar" aria-label="Completed hiring phases" aria-valuemin="0" aria-valuemax="6" aria-valuenow="0"><span class="tracking-meter-value"></span></div><ol id="application-progress-stages" aria-label="Your required hiring phases"></ol><p id="tracking-route-help"></p><div class="tracking-legend" aria-label="Roadmap legend"><span><i class="tracking-legend-completed" aria-hidden="true"></i>Completed</span><span><i class="tracking-legend-current" aria-hidden="true"></i>Current phase</span><span><i class="tracking-legend-pending" aria-hidden="true"></i>Upcoming</span></div></section>
  </div>
 </section>
 <template id="candidate-tracking-icons"><?php foreach (['cif'=>'cif','initial_interview'=>'interview','department_interview'=>'candidates','lie_detection'=>'screening','job_offer'=>'job-offer','deployment'=>'deployment','check'=>'check'] as $key=>$icon): ?><span data-phase-icon="<?= $key ?>"><?= \App\Services\Recruitment\PortalIcons::portalIcon($icon) ?></span><?php endforeach; ?></template>
 </div>
</section>
<dialog id="privacy-dialog" class="privacy-dialog" aria-labelledby="privacy-title" data-version="<?= \App\Services\Recruitment\ApplicantPrivacy::version() ?>">
 <div class="privacy-dialog-header"><h2 id="privacy-title" tabindex="-1">APPLICANT DATA PRIVACY NOTICE AND CONSENT FORM</h2><button class="btn sec" id="close-privacy" type="button" aria-label="Close data privacy notice">Close</button></div>
 <div class="privacy-dialog-body" tabindex="0" role="region" aria-label="Full applicant data privacy notice"><?= \App\Services\Recruitment\ApplicantPrivacy::notice() ?></div>
 <div class="privacy-dialog-footer">
  <p id="privacy-read-hint" role="status">Scroll to the bottom of the notice to enable the checkboxes and Continue.</p>
  <p id="privacy-error" role="alert" hidden></p>
  <button class="btn pri" id="accept-privacy" type="button" aria-describedby="privacy-read-hint" disabled>Continue</button>
 </div>
</dialog>
<script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/access.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/cif-photo.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/position-picker.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/candidate-tracking.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('js/candidate.js'), ENT_QUOTES, 'UTF-8') ?>"></script></body></html>
