<p class="privacy-company">THE NEW APEC DEVELOPMENT CORPORATION AND ITS AFFILIATES</p>
<p>The New Apec Development Corporation and its affiliates value your privacy. We are committed to protecting your personal data in accordance with the Philippine Data Privacy Act of 2012 (R.A. 10173). This notice explains what information we collect during our recruitment process, why we collect it, how we use it, and how long we keep it.</p>

<h3>1. PERSONAL DATA WE COLLECT FROM APPLICANTS</h3>
<p>When you apply for a position with us, we collect and process the following information:</p>
<ul>
 <li><strong>Personal &amp; Contact Details:</strong> Full name, permanent and current address, contact numbers, personal email address, and digital profiles (e.g., LinkedIn link).</li>
 <li><strong>Academic &amp; Employment History:</strong> Resumes/CVs, cover letters, transcript of records, diplomas, professional licenses, certifications, previous employment details, and job descriptions.</li>
 <li><strong>Sensitive Personal Information:</strong> Age, date of birth, gender, marital status, health declaration (if applicable for physical fitness requirements), and current/expected salary.</li>
 <li><strong>Background Check Data:</strong> References from previous employers, character references, and background screening results (e.g., NBI, Police, or Barangay clearances) where legally permissible.</li>
</ul>
<h3>2. PURPOSE OF COLLECTION AND PROCESSING</h3>
<p>We process your personal data solely for legitimate recruitment and evaluation purposes, which include:</p>
<ul>
 <li>Evaluating your qualifications, experience, and suitability for the position applied for or other vacant roles.</li>
 <li>Verifying the accuracy of the information provided, including background checks and character reference verification.</li>
 <li>Communicating with you regarding the status of your application, interview schedules, and potential job offers.</li>
 <li>Creating an internal talent pool database for future career opportunities within the company, subject to the retention period and your rights described below.</li>
</ul>
<h3>3. DATA SHARING AND DISCLOSURE</h3>
<p>Your applicant data is treated with strict confidentiality. It will only be shared internally with authorized personnel involved in the hiring process (e.g., the Human Resources team, department hiring managers, and authorized interviewers).</p>
<p>We will not disclose your personal data to external third parties unless:</p>
<ul>
 <li>You have given explicit consent to contact your character references.</li>
 <li>It is processed by an authorized third-party background screening vendor under a strict Data Sharing Agreement.</li>
 <li>It is required or authorized by Philippine laws and regulations.</li>
</ul>
<h3>4. DATA RETENTION PERIOD</h3>
<p>We maintain applicant data in accordance with the following schedules:</p>
<div class="privacy-table-wrap"><table>
 <thead><tr><th scope="col">Application Status</th><th scope="col">Retention Policy</th><th scope="col">Final Disposition</th></tr></thead>
 <tbody>
  <tr><th scope="row">Successful Applicants</th><td>Transferred directly to your active Employee Personnel File (201 File).</td><td>Retained for the full duration of employment.</td></tr>
  <tr><th scope="row">Unsuccessful Applicants</th><td>Retained in our active Talent Database for two (2) years from the date of application.</td><td>Permanently deleted, shredded, or securely destroyed after the retention period.</td></tr>
 </tbody>
</table></div>
<p><strong>Note:</strong> Unsuccessful applicant data is retained to match your profile against future job openings. If you prefer that we do not keep your resume for future roles, you may request its immediate deletion.</p>
<h3>5. YOUR RIGHTS AS A DATA SUBJECT</h3>
<p>As a data subject under R.A. 10173, you have the right to be informed about how your personal data is used, access your data, request corrections, object to processing, and request the erasure or blocking of your data, as applicable. You may inquire about these rights or raise privacy-related concerns with our Data Protection Officer. You may also contact the National Privacy Commission (NPC) for information or guidance.</p>
<?php if ($dpoEmail !== ''): ?>
<p>For any privacy-related requests or questions, you may reach our Data Protection Officer at <a href="mailto:<?= htmlspecialchars($dpoEmail, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dpoEmail, ENT_QUOTES, 'UTF-8') ?></a>.</p>
<?php else: ?>
<p>For privacy-related requests or questions, please contact the Human Resources team to reach our Data Protection Officer.</p>
<?php endif; ?>
<h3>APPLICANT CONSENT AND DECLARATION</h3>
<p>By selecting the checkboxes below, I certify that I have read and understood this notice and voluntarily give the corresponding consent and authorization.</p>
<label class="chk consent-choice"><input type="checkbox" id="privacy-agree" aria-describedby="privacy-read-hint" disabled><span><strong>DATA PRIVACY CONSENT</strong><span><?= htmlspecialchars($privacyDeclarations['data_privacy'], ENT_QUOTES, 'UTF-8') ?></span></span></label>
<label class="chk consent-choice"><input type="checkbox" id="privacy-background-agree" aria-describedby="privacy-read-hint" disabled><span><strong>BACKGROUND AND REFERENCE CHECK AUTHORIZATION</strong><span><?= htmlspecialchars($privacyDeclarations['background'], ENT_QUOTES, 'UTF-8') ?></span></span></label>
