<?php $reportsToday=new \DateTimeImmutable('today',new \DateTimeZone('Asia/Shanghai')); ?>
<section id="reports" class="hide" aria-labelledby="reports-title" data-today="<?= $reportsToday->format('Y-m-d') ?>">
 <div class="mrf-heading"><div><h2 id="reports-title">Reports to print or download</h2><p>Choose your reports and coverage, then download Excel or open a print preview.</p></div><button id="reports-refresh" class="btn g" type="button">Refresh</button></div>
 <form id="reports-form">
  <div class="card hr-monitor-filters hr-kpi-filter-preview">
   <label>Include<select id="reports-scope"><option value="all">All HR data</option><option value="mine">My assigned data</option></select></label>
   <label>Candidates<select id="reports-archives"><option value="active">Active only</option><option value="both">Active and archived</option><option value="archived">Archived only</option></select></label>
   <label>From<input id="reports-from" type="date" min="1900-01-01" max="9998-12-31"></label>
   <label>To<input id="reports-to" type="date" min="1900-01-01" max="9998-12-31"></label>
   <div class="hr-kpi-filter-toolbar"><div class="hr-kpi-shortcuts" role="group" aria-label="Report date shortcuts"><button type="button" data-report-coverage="month">This month</button><button type="button" data-report-coverage="year">This year</button><button type="button" data-report-coverage="last12">Last 12 months</button><button type="button" data-report-coverage="all">All dates</button></div><div class="hr-kpi-filter-actions"><button id="reports-reset" type="button" class="btn g">Reset</button></div></div>
   <p class="hr-kpi-coverage-preview" id="reports-coverage">All dates · All HR data</p>
  </div>
  <div class="reports-selection-heading"><h3>Choose reports</h3><button type="button" class="btn g" id="reports-select-all">Select all</button></div>
  <div class="reports-options">
   <label class="card reports-option"><input type="checkbox" name="report" value="candidates" checked><span><strong>Candidates</strong><small>Applicant details, hiring stage, MRF and assigned HR.</small><em>Dates use application submission.</em></span></label>
   <label class="card reports-option"><input type="checkbox" name="report" value="mrfs" checked><span><strong>Manpower requests</strong><small>MRF numbers, approvals, recruitment progress and deadlines.</small><em>Dates use request creation.</em></span></label>
   <label class="card reports-option"><input type="checkbox" name="report" value="kpi" checked><span><strong>Recruitment KPI</strong><small>HR performance summary and supporting MRF headcounts.</small><em>Dates use MRF hiring due dates.</em></span></label>
  </div>
  <div class="card reports-actions"><div><h3>Ready to prepare your reports?</h3><p>Excel puts each report on its own worksheet, with headers, filters and print formatting. Print preview starts each report on a new page.</p><p>“My assigned data” follows the current HR owner of each MRF. Candidate and MRF reports show current statuses. KPI includes historical hires, including archived candidates.</p></div><div class="reports-buttons"><button class="btn g" type="submit" value="print">Print preview</button><button class="btn" type="submit" value="xlsx">Download Excel</button></div><p id="reports-feedback" role="status" aria-live="polite" hidden></p><p id="reports-error" class="settings-error" role="alert" hidden></p></div>
 </form>
</section>
