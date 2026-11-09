<section id="templates" class="hide mail-page" aria-label="Email templates">
 <div class="mail-heading"><div><h2>Reusable messages</h2><p>Use the existing templates for each hiring phase. Edit them when needed.</p></div><div class="mail-buttons"><button class="btn g" id="templates-refresh" type="button">Refresh</button></div></div>
 <div class="mail-notice"><?php 
echo \App\Services\Recruitment\PortalIcons::portalIcon('email-settings');
?><div><strong>Using email templates</strong><p>Edit a saved message or choose Use template to send it to a candidate.</p></div></div>
 <div class="card"><div class="mail-filters"><input type="search" id="template-search" placeholder="Search templates or subject" aria-label="Search templates" maxlength="150"><select id="template-filter-category" aria-label="Template category"><option value="">All categories</option></select><select id="template-filter-view" aria-label="Template view"><option value="active">Active templates</option><option value="archived">Archived templates</option></select></div>
  <p id="template-feedback" class="mail-feedback" role="status" aria-live="polite"></p><div class="tw"><table class="mail-table"><thead><tr><th>Template / subject</th><th>Hiring phase</th><th>Last updated</th><th>Actions</th></tr></thead><tbody id="template-rows"></tbody></table></div><p id="template-info" class="mail-feedback"></p>
 </div>
</section>
<section id="mail" class="hide mail-page" aria-label="Mail management">
 <div class="mail-heading"><div><h2>Outgoing messages</h2><p>Send messages and monitor delivery attempts in one place.</p></div><div class="mail-buttons"><button class="btn g" id="mail-refresh" type="button">Refresh</button><button class="btn" id="mail-compose" type="button">Send email</button></div></div>
 <div class="mail-notice"><?php 
echo \App\Services\Recruitment\PortalIcons::portalIcon('email-settings');
?><div><strong>Sending emails</strong><p>Checking whether email sending is available...</p></div></div>
 <div id="mail-stats" class="stats" aria-label="Mail summaries"></div>
 <div class="card"><div class="invitation-list-heading"><h3 id="mail-list-title">Recent emails</h3><div class="invitation-view-switch" aria-label="Email view"><button id="mail-active-view" class="btn g" type="button" aria-pressed="true">Active</button><button id="mail-archived-view" class="btn g" type="button" aria-pressed="false">Archived</button></div></div><div class="mail-filters"><input id="mail-search" type="search" maxlength="150" placeholder="Search recipient, subject or template" aria-label="Search messages"><select id="mail-status" aria-label="Filter mail status"><option value="">All messages</option><option value="Prepared">Not sent</option><option>Sent</option><option>Failed</option><option>Cancelled</option></select></div>
  <p id="mail-feedback" class="mail-feedback" role="status" aria-live="polite"></p><div class="tw"><table class="mail-table"><thead><tr><th>Recipient</th><th>Subject / template</th><th>Status</th><th>Created by</th><th>Created</th><th>Actions</th></tr></thead><tbody id="mail-rows"></tbody></table></div><div class="pgn"><span id="mail-info"></span><div id="mail-pages"></div></div>
 </div>
</section>
<dialog class="mail-dialog" id="template-editor" aria-labelledby="template-editor-title">
 <div class="mail-heading"><div><p class="mail-kicker">MESSAGE LIBRARY</p><h2 id="template-editor-title">Edit email template</h2></div><button class="btn g" type="button" id="template-close">Close</button></div>
 <form id="template-form"><div class="mail-editor-grid"><div><label for="template-name">Template name</label><input id="template-name" required maxlength="150"></div><div><label for="template-category">Hiring phase</label><select id="template-category" required></select></div></div>
  <label for="template-subject">Subject</label><input id="template-subject" required maxlength="200">
  <label for="template-body">Message</label><textarea id="template-body" rows="10" required maxlength="10000" spellcheck="true"></textarea>
  <details class="mail-variables"><summary>Insert recipient details</summary><p>Choose a detail to add to your message, such as the recipient's name. It will be filled in automatically for each recipient. Preview the email to check the final message.</p><div id="template-variables"></div></details>
  <p id="template-error" class="mail-error" role="alert" hidden></p><div class="mail-dialog-actions"><span>Text is formatted automatically when you save.</span><button class="btn" id="template-save" type="submit">Save template</button></div>
 </form>
</dialog>
<dialog class="mail-dialog" id="mail-composer" aria-labelledby="mail-compose-title">
 <div class="mail-heading"><div><p class="mail-kicker">OUTGOING EMAIL</p><h2 id="mail-compose-title">Send email</h2></div><button class="btn g" type="button" id="mail-compose-close">Close</button></div>
 <p id="mail-process-tag" class="mail-help" hidden><span class="mail-status">CIF invitation</span></p>
 <div id="mail-composer-options"><div class="mail-editor-grid"><div><label for="mail-template">Email template</label><select id="mail-template"></select></div><div><label for="mail-recipient-kind">Recipient source</label><select id="mail-recipient-kind"><option value="candidate">Submitted candidate</option><option value="invitation">Valid invitation</option></select></div></div>
 <label for="mail-recipient-search">Find a registered recipient</label><input id="mail-recipient-search" type="search" maxlength="150" placeholder="Search name, email or App ID" autocomplete="off"><div id="mail-recipient-results" class="mail-recipient-results" aria-label="Matching recipients"></div></div>
 <p id="mail-selected" class="mail-help">Choose a recipient from the results. Typed text alone is not a selection.</p>
 <button class="btn g" type="button" id="mail-preview">Preview message</button>
 <div id="mail-preview-panel" class="mail-preview" hidden><?php 
\App\Services\Recruitment\Branding::renderCifBrandLogo();
?><p>To <strong id="mail-preview-to"></strong></p><h3 id="mail-preview-subject"></h3><pre id="mail-preview-body"></pre></div>
 <div id="mail-invitation-editor" hidden><label for="mail-edit-subject">Subject</label><input id="mail-edit-subject" maxlength="200" required><label for="mail-edit-body">Message</label><textarea id="mail-edit-body" rows="11" maxlength="12000" required></textarea><p class="mail-help">Changes apply to this email only.</p></div>
 <p id="mail-compose-error" class="mail-error" role="alert" hidden></p>
 <div class="mail-dialog-actions"><span>Review the message above. Submit sends this email immediately.</span><button class="btn" id="mail-prepare" type="button" disabled>Submit</button></div>
</dialog>
<dialog class="mail-dialog" id="mail-detail" aria-labelledby="mail-detail-subject">
 <div class="mail-heading"><h2>Message details</h2><button class="btn g" type="button" id="mail-detail-close">Close</button></div>
 <?php 
\App\Services\Recruitment\Branding::renderCifBrandLogo();
?>
 <p id="mail-detail-meta" class="mail-help"></p><h3 id="mail-detail-subject"></h3><pre id="mail-detail-body" class="mail-message-body"></pre><div class="mail-brand-signature"><strong>APEC Homes</strong>HR Recruitment</div>
 <p class="mail-help" id="mail-transport-help">Sent does not confirm that the recipient received or read the email.</p><div class="mail-dialog-actions"><button class="btn" id="mail-detail-send" type="button" disabled>Resend email</button></div>
</dialog>
<dialog class="mail-dialog" id="mail-archive-confirmation" aria-labelledby="mail-archive-title">
 <h2 id="mail-archive-title">Archive email?</h2><p id="mail-archive-description" class="mail-help"></p><p id="mail-archive-error" class="mail-error" role="alert" hidden></p>
 <div class="mail-dialog-actions"><button class="btn g" id="mail-archive-cancel" type="button">Cancel</button><button class="btn" id="mail-archive-submit" type="button">Archive email</button></div>
</dialog>

