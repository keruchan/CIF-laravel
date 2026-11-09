<dialog id="candidate-update-dialog" class="completion-dialog" aria-labelledby="candidate-update-title">
 <?php 
\App\Services\Recruitment\Branding::renderCifBrandLogo();
?>
 <h2 id="candidate-update-title">Update candidate</h2>
 <p>Only this stage will be released. Other unpublished results stay hidden. The tracker will update even if the email fails.</p>
 <p id="candidate-update-summary"></p>
 <form id="candidate-update-form" class="interview-decision-fields">
  <label>To <input id="candidate-update-to" readonly></label>
  <fieldset id="candidate-update-schedule-fields" hidden>
   <legend>Next phase schedule</legend>
   <div class="candidate-schedule-inputs">
    <label>Next phase <span class="schedule-optional">Optional</span><select id="candidate-update-next-phase"></select></label>
    <label>Date and time <span class="schedule-optional">Optional · Philippine time (UTC+8)</span><input id="candidate-update-next-datetime" type="datetime-local" min="2000-01-01T00:00" max="2100-12-31T23:59"></label>
   </div>
   <p id="candidate-update-schedule-preview"></p>
   <small>This schedule will be added to the email and applicant tracker. Leave the date blank until confirmed.</small>
  </fieldset>
  <label>Subject <input id="candidate-update-subject" maxlength="200" required></label>
  <label>Message <textarea id="candidate-update-body" maxlength="12000" rows="9" required></textarea></label>
  <div class="mail-brand-signature"><strong>APEC Homes</strong>HR Recruitment</div>
  <p id="candidate-update-error" role="alert" hidden></p>
  <div class="completion-dialog-actions"><button id="candidate-update-cancel" class="draft-button" type="button">Cancel</button><button id="candidate-update-submit" class="primary-button" type="submit">Update tracker &amp; send email</button></div>
 </form>
</dialog>

