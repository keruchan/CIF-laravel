<?php $kpiToday=new \DateTimeImmutable('today',new \DateTimeZone('Asia/Shanghai')); ?>
<section id="staff" class="hide" aria-label="KPI" data-default-year="<?= $kpiToday->format('Y') ?>" data-default-month="<?= $kpiToday->format('n') ?>" data-default-today="<?= $kpiToday->format('Y-m-d') ?>" data-default-from="<?= $kpiToday->modify('first day of this month')->modify('-11 months')->format('Y-m-d') ?>" data-default-to="<?= $kpiToday->modify('last day of this month')->format('Y-m-d') ?>">
 <div class="hr-monitor-heading"><div><h2>Recruitment performance</h2><p>MRF targets, hiring speed and hired candidates.</p></div><div class="hr-monitor-heading-actions"><button id="hr-monitor-refresh" class="btn g" type="button">Refresh</button><button id="hr-profile-open" class="btn g" type="button" aria-haspopup="dialog" aria-controls="hr-profile-dialog" disabled>My profile picture</button></div></div>
 <form id="hr-kpi-filter-form" class="card hr-monitor-filters hr-kpi-filter-preview">
  <label>HR member<select id="hr-monitor-person"><option value="0">All HR members</option></select></label>
  <label>Group chart by<select id="hr-kpi-period"><option value="weekly">Weeks</option><option value="monthly" selected>Months</option><option value="yearly">Years</option></select></label>
  <label>From<input id="hr-kpi-from" type="date" min="1900-01-01" max="9998-12-31" value="<?= $kpiToday->modify('first day of this month')->modify('-11 months')->format('Y-m-d') ?>" aria-describedby="hr-kpi-coverage-preview"></label>
  <label>To<input id="hr-kpi-to" type="date" min="1900-01-01" max="9998-12-31" value="<?= $kpiToday->modify('last day of this month')->format('Y-m-d') ?>" aria-describedby="hr-kpi-coverage-preview"></label>
  <div class="hr-kpi-filter-toolbar"><div class="hr-kpi-shortcuts" role="group" aria-label="Coverage shortcuts"><button type="button" data-kpi-coverage="week">This week</button><button type="button" data-kpi-coverage="month">This month</button><button type="button" data-kpi-coverage="year">This year</button><button type="button" data-kpi-coverage="last12">Last 12 months</button><button type="button" data-kpi-coverage="all">All dates</button></div><div class="hr-kpi-filter-actions"><button id="hr-kpi-filter-reset" type="button" class="btn g">Reset</button></div></div>
  <p id="hr-kpi-coverage-preview" class="hr-kpi-coverage-preview" aria-live="polite"></p>
  <p id="hr-kpi-filter-error" class="hr-kpi-filter-error" role="alert" hidden></p>
 </form>
 <p id="hr-monitor-feedback" class="hr-monitor-feedback" role="status" aria-live="polite"></p>
 <div id="hr-monitor-results">
  <div class="stats hr-monitor-stats" aria-label="Recruitment KPI summary">
   <div class="card stat hr-kpi-due-card" style="--c:var(--navy)"><b id="hr-kpi-due">0</b><span>Due</span><small>Required headcount due within the selected coverage</small></div>
   <div class="card stat" style="--c:var(--teal)"><b id="hr-kpi-fill-rate">—</b><span>Fill rate</span><small id="hr-kpi-fill-count">Filled / needed headcount</small></div>
   <div class="card stat" style="--c:var(--blue)"><b id="hr-kpi-timeliness">—</b><span>Timeliness rate</span><small>Days to fill ÷ (2 × lead time). Lower is faster.</small></div>
   <div class="card stat" style="--c:var(--navy)"><b id="hr-kpi-average">—</b><span>Average days to fill</span><small>Calendar days from management approval to date hired</small></div>
   <div class="card stat" style="--c:#6875c9"><b id="hr-kpi-hires">0</b><span>Number hired</span><small>Completed candidate deployments for these MRFs</small></div>
  </div>
  <div id="hr-kpi-notices" class="hr-monitor-help" role="status"></div>
  <div class="hr-kpi-charts" aria-label="Recruitment charts">
   <section class="card hr-monitor-card hr-kpi-trend" aria-labelledby="hr-kpi-trend-title">
    <div class="hr-kpi-chart-heading"><div><h3 id="hr-kpi-trend-title">KPI trends</h3><p id="hr-kpi-trend-context" class="hr-monitor-help"></p></div><span id="hr-kpi-trend-unit" class="hr-kpi-chart-unit">Hired</span></div>
    <div class="hr-kpi-metric-switch" role="group" aria-label="Choose a KPI to chart">
     <button type="button" data-kpi-metric="fill_rate" aria-pressed="false">Fill rate</button>
     <button type="button" data-kpi-metric="timeliness_rate" aria-pressed="false">Timeliness rate</button>
     <button type="button" data-kpi-metric="average_days" aria-pressed="false">Average days</button>
     <button type="button" data-kpi-metric="hires" aria-pressed="true">Hired</button>
    </div>
    <div id="hr-kpi-line" class="hr-kpi-line-scroll" tabindex="0" aria-label="KPI trend chart; scroll horizontally on smaller screens"></div>
    <p id="hr-kpi-chart-tooltip" class="hr-kpi-chart-tooltip" role="status">Hover or focus a point to see its value.</p>
    <details class="hr-kpi-data-details"><summary>View chart values</summary><div class="tw"><table class="hr-kpi-values"><thead><tr><th>Period</th><th>Fill rate</th><th>Timeliness rate</th><th>Average days</th><th>Hired</th></tr></thead><tbody id="hr-kpi-chart-values"></tbody></table></div></details>
   </section>
   <section class="card hr-monitor-card hr-kpi-funnel" aria-labelledby="hr-kpi-funnel-title">
    <h3 id="hr-kpi-funnel-title">Recruitment funnel</h3>
    <p id="hr-kpi-funnel-context" class="hr-monitor-help"></p>
    <ol id="hr-kpi-funnel-stages" class="hr-kpi-funnel-stages" aria-label="Candidate conversion by stage"></ol>
    <p class="hr-kpi-funnel-help">Percentages show the share of applicants and conversion from the previous step. Completed stages only; drafts do not count. Backout and No Response do not count as interviewed.</p>
   </section>
  </div>
  <div class="card hr-monitor-card"><h3>HR member performance</h3><p class="hr-monitor-help">Targets and hired candidates follow the MRF’s assigned HR member. Click an HR row to automatically apply their filter and view their MRFs in the table below.</p><div class="tw"><table><thead><tr><th>HR member</th><th>Due</th><th>Filled</th><th>Fill rate</th><th>Timeliness rate</th><th>Avg. days to fill</th><th>Hired</th></tr></thead><tbody id="hr-monitor-members"></tbody></table></div></div>
  <div class="card hr-monitor-card" id="hr-kpi-table-card"><h3>MRFs due in this period</h3><p class="hr-monitor-help">Each separate MRF file is one needed hire. Open an MRF to assign its HR owner. A completed deployment fills the file; manually marking an MRF Completed does not count as a hire.</p>
   <p class="hr-monitor-help">These table controls narrow the records within the HR/date coverage above. They do not change KPI cards or charts.</p>
   <div class="hr-kpi-table-filters">
    <label class="hr-kpi-table-search-label">Search<input type="search" id="hr-kpi-table-search" maxlength="254" placeholder="MRF no., position, HR, requestor, department or site" autocomplete="off"></label>
    <label>Status<select id="hr-kpi-table-status"><option value="">All statuses</option><option>Pending</option><option>In Progress</option><option>Completed</option><option>On Hold</option><option>Cancelled</option><option>Rejected</option></select></label>
    <label>Category<select id="hr-kpi-table-category"><option value="">All categories</option><option>A</option><option>B</option><option>C-1</option><option>C-2</option><option>D</option></select></label>
    <button class="btn g" type="button" id="hr-kpi-table-reset">Reset table</button>
   </div>
   <p id="hr-kpi-table-feedback" class="hr-monitor-help" role="status" aria-live="polite"></p>
   <div class="tw"><table class="hr-kpi-mrf-table"><thead><tr>
    <th aria-sort="none"><button class="sort-header" type="button" data-kpi-sort="mrf" aria-label="Sort by MRF number">MRF / position <span class="sort-direction" aria-hidden="true">↕</span></button></th>
    <th>Assigned HR</th><th>Management approved</th><th>Category / lead time</th>
    <th aria-sort="ascending"><button class="sort-header" type="button" data-kpi-sort="due">Hiring due <span class="sort-direction" aria-hidden="true">↑</span></button></th>
    <th>Status</th><th>First date hired</th>
    <th aria-sort="none"><button class="sort-header" type="button" data-kpi-sort="days">Days to fill / days open <span class="sort-direction" aria-hidden="true">↕</span></button></th>
   </tr></thead><tbody id="hr-kpi-mrfs"></tbody></table></div>
   <div class="hr-monitor-pagination hr-kpi-table-pagination"><span id="hr-monitor-page-info" role="status" aria-live="polite"></span><nav id="hr-kpi-pages" aria-label="KPI MRF table pagination"><button id="hr-kpi-first" type="button">First</button><button id="hr-monitor-prev" type="button">Previous</button><span id="hr-kpi-page-numbers"></span><button id="hr-monitor-next" type="button">Next</button><button id="hr-kpi-last" type="button">Last</button></nav></div>
  </div>
  <details class="card hr-monitor-card"><summary>How these KPIs are calculated</summary>
   <p class="hr-monitor-definition">All five cards use approved MRF files whose hiring due date falls between From and To, including both dates, even if the hire happened outside that coverage. Due date = management approval date + category lead time: A 30, B 35, C-1 40, C-2 45, D 60 calendar days.</p>
   <p class="hr-monitor-definition">Fill rate = filled files ÷ needed files × 100. Each file counts once. Hired = candidates with a completed deployment. Average days to fill uses date hired minus management approval date. Timeliness is the average of each hire’s days to fill ÷ (2 × category lead time) × 100; lower is faster and values can exceed 100%.</p>
   <p class="hr-monitor-definition">On Hold, Cancelled and Rejected files are excluded from targets until reopened. Unassigned files appear in team totals but are not credited to an HR member. Assignment changes are recorded and move the file’s KPI credit to its current owner. Missing approval dates are not guessed. Invalid hire dates are excluded from timing averages and reported above.</p>
   <p class="hr-monitor-definition">Changing the HR member, chart grouping or valid From/To dates automatically updates the results. Coverage can span up to five years. Invalid or incomplete dates leave the last valid results displayed until corrected. Weeks run Monday–Sunday; Months and Years use calendar boundaries. Partial first/last periods include only dates inside your coverage. Grouping changes the chart breakdown, not the card totals. Reset and revisiting KPI restore All HR and the current month. Choose a KPI to use its own percentage, days or number hired scale. Gaps mean no target or no valid timing data, not zero performance.</p>
   <p class="hr-monitor-definition">The funnel follows candidates who submitted a CIF between From and To, including both dates and archived records. Each candidate counts once, after completing the preceding steps: HR interview, job offer, accepted offer, and deployment. It uses the current owner of the deployment MRF, or the latest screening/interview MRF when there is no deployment. Candidates without an assigned MRF owner appear only under All HR. Historical candidate outcomes remain counted even if an MRF is later put On Hold or Cancelled. The number hired in the funnel can differ from the KPI cards because the funnel uses the application date, while KPI cards use the MRF due date.</p>
  </details>
 </div>
</section>
<dialog id="hr-profile-dialog" aria-labelledby="hr-profile-title" aria-describedby="hr-profile-help" data-placeholder="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('assets/candidate-photo-example.jpg'), ENT_QUOTES, 'UTF-8') ?>">
 <h3 id="hr-profile-title">My profile picture</h3>
 <p id="hr-profile-help" class="hr-monitor-help">Your photo appears on the KPI page. Only you can change it.</p>
 <form id="hr-profile-form">
  <div class="hr-profile-editor"><img id="hr-profile-preview" class="hr-profile-avatar hr-profile-preview" src="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('assets/candidate-photo-example.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="Your profile picture preview"><div><label for="hr-profile-file">Upload picture</label><input id="hr-profile-file" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="hr-profile-file-help"><p id="hr-profile-file-help" class="hr-monitor-help">JPEG, PNG or WebP, up to 10 MB. Use a clear head-and-shoulders photo.</p><button id="hr-profile-remove" type="button" class="hr-profile-remove" disabled>Use sample picture</button></div></div>
  <p id="hr-profile-error" class="settings-error" role="alert" hidden></p>
  <p id="hr-profile-status" class="hr-monitor-help" role="status" aria-live="polite"></p>
  <div class="settings-actions"><button id="hr-profile-cancel" type="button" class="btn g">Cancel</button><button id="hr-profile-save" type="submit" class="btn" disabled>Save picture</button></div>
 </form>
</dialog>
