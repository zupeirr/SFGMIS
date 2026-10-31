<?php
/**
 * Budget Page — National Budget, Execution, Documents, Archive
 */
?>
<div class="inner-banner">
  <div class="container">
    <div class="breadcrumb"><a href="transparency.php">Home</a> <i data-lucide="chevron-right"></i> <span>Budget</span></div>
    <h1>National Budget &amp; Public Finances</h1>
    <p>Official parliamentary appropriations, approved expenditure estimates, revenue projections, and financial performance records.</p>
  </div>
</div>

<div class="container section">
  <div class="page-subnav">
    <a href="#annual" class="subnav-link active">Approved Budget</a>
    <a href="#execution" class="subnav-link">Budget Execution</a>
    <a href="#documents" class="subnav-link">Budget Documents</a>
    <a href="#archive" class="subnav-link">Historical Archive</a>
  </div>

  <!-- APPROVED BUDGET OVERVIEW -->
  <section id="annual" class="content-block">
    <div class="sec-header">
      <div class="sec-eyebrow"><?php echo htmlspecialchars($fiscalYearName); ?> Fiscal Year</div>
      <h2 class="sec-title">Annual Approved National Budget</h2>
      <p class="sec-sub">Appropriations officially passed by the House of Representatives of Somaliland.</p>
    </div>

    <div class="grid-3 gap-3">
      <div class="budget-stat-box">
        <span class="budget-stat-label">Total Approved Appropriation</span>
        <div class="budget-stat-num"><?php echo format_currency($totalBudget); ?></div>
        <p class="budget-stat-desc">Enacted parliamentary appropriation bill for national development.</p>
      </div>
      <div class="budget-stat-box">
        <span class="budget-stat-label">Disbursed Expenditure to Date</span>
        <div class="budget-stat-num" style="color:#b91c1c;"><?php echo format_currency($totalSpent); ?></div>
        <p class="budget-stat-desc">Warranted payments across operational and capital development heads.</p>
      </div>
      <div class="budget-stat-box">
        <span class="budget-stat-label">Budget Execution Rate</span>
        <div class="budget-stat-num" style="color:var(--g);"><?php echo $executionRate; ?>%</div>
        <p class="budget-stat-desc">Cumulative statutory fiscal utilization and disbursement progress.</p>
      </div>
    </div>

    <!-- Allocation Highlights Table -->
    <div class="budget-table-card" style="margin-top:32px;">
      <div class="table-card-header">
        <h3>Major Sectoral Allocations (<?php echo htmlspecialchars($fiscalYearName); ?>)</h3>
        <span class="badge badge-green">Official Appropriations</span>
      </div>
      <div style="overflow-x:auto;">
        <table class="portal-table">
          <thead>
            <tr>
              <th>Entity / Ministry</th>
              <th>Category</th>
              <th>Allocated Budget</th>
              <th>Executed Spend</th>
              <th>Execution Rate</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($sectors as $s): 
              $pct = round((float)($s['util_pct']??0), 1);
            ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($s['org_name']); ?></strong> <span style="font-size:.75rem;color:var(--muted);font-family:monospace;"><?php echo htmlspecialchars($s['code']); ?></span></td>
              <td><span class="badge badge-gray"><?php echo htmlspecialchars(ucfirst($s['type'])); ?></span></td>
              <td><strong><?php echo format_currency((float)$s['allocated_amount']); ?></strong></td>
              <td style="color:#b91c1c;font-weight:600;"><?php echo format_currency((float)$s['spent_amount']); ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <span style="font-weight:700;font-size:.8rem;"><?php echo $pct; ?>%</span>
                  <div class="progress" style="width:100px;"><div class="progress-bar" style="width:<?php echo min(100,$pct); ?>%;background:var(--g);"></div></div>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- BUDGET EXECUTION -->
  <section id="execution" class="content-block" style="margin-top:64px;">
    <div class="sec-header">
      <div class="sec-eyebrow">Fiscal Accountability</div>
      <h2 class="sec-title">Budget Execution &amp; Monitoring</h2>
      <p class="sec-sub">Planned commitments versus real-world expenditure disbursements.</p>
    </div>
    <div class="grid-2 gap-4">
      <div class="prose-card">
        <h3>Quarterly Fiscal Performance</h3>
        <p>Budget execution oversight is conducted under the PFM statutory guidelines. The Ministry monitors monthly line-item expenditures to prevent unauthorized expenditure ceilings and verify fund usage.</p>
        <p>Warrants are issued quarterly in accordance with verified domestic revenue receipts, ensuring government obligations are paid without creating sovereign domestic arrears.</p>
        <ul class="styled-list">
          <li><strong>Quarter 1:</strong> Balanced operational disbursements &amp; primary school educational grants.</li>
          <li><strong>Quarter 2:</strong> Capital road and public health infrastructure investments.</li>
          <li><strong>Quarter 3:</strong> Customs automation upgrades and civil service capacity initiatives.</li>
        </ul>
      </div>
      <div class="prose-card">
        <h3>Expenditure Controls &amp; Warrants</h3>
        <p>Every government payment must be substantiated by a verified purchase order, supplier delivery note, and Accountant General voucher before funds are released through the Central Bank.</p>
        <div class="highlight-box">
          <i data-lucide="shield-alert"></i>
          <div>
            <strong>Treasury Single Account (TSA)</strong>
            <p>All line ministry accounts are consolidated under the Central Bank for transparent cash management.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- BUDGET DOCUMENTS & DOWNLOADS -->
  <section id="documents" class="content-block" style="margin-top:64px;">
    <div class="sec-header">
      <div class="sec-eyebrow">Publications &amp; Legislation</div>
      <h2 class="sec-title">Budget Documents</h2>
      <p class="sec-sub">Official PDFs of appropriation bills, budget speeches, and financial statements.</p>
    </div>
    <div class="docs-list">
      <div class="doc-row">
        <div class="doc-icon" style="background:#dcfce7;color:#166534;"><i data-lucide="file-text"></i></div>
        <div class="doc-info">
          <strong>National Appropriation Bill FY 2026-2027</strong>
          <span>Parliamentary approved statutory budget act — Full Gazette Publication</span>
        </div>
        <button class="doc-dl"><i data-lucide="download"></i> PDF · 3.8 MB</button>
      </div>
      <div class="doc-row">
        <div class="doc-icon" style="background:#fef3c7;color:#92400e;"><i data-lucide="file-spreadsheet"></i></div>
        <div class="doc-info">
          <strong>Minister of Finance Annual Budget Speech</strong>
          <span>Fiscal policy statement delivered before the Parliament of Somaliland</span>
        </div>
        <button class="doc-dl"><i data-lucide="download"></i> PDF · 1.6 MB</button>
      </div>
      <div class="doc-row">
        <div class="doc-icon" style="background:#dbeafe;color:#1e40af;"><i data-lucide="pie-chart"></i></div>
        <div class="doc-info">
          <strong>Citizen's Budget Guide FY 2026-2027</strong>
          <span>Simplified public-friendly summary of revenues, expenditures, and development goals</span>
        </div>
        <button class="doc-dl"><i data-lucide="download"></i> PDF · 2.1 MB</button>
      </div>
      <div class="doc-row">
        <div class="doc-icon" style="background:#f3e8ff;color:#7c3aed;"><i data-lucide="file-check-2"></i></div>
        <div class="doc-info">
          <strong>Medium-Term Fiscal Framework (MTFF) 2026-2029</strong>
          <span>Macroeconomic forecasts, revenue mobilization strategies, and ceiling estimates</span>
        </div>
        <button class="doc-dl"><i data-lucide="download"></i> PDF · 4.2 MB</button>
      </div>
    </div>
  </section>

  <!-- BUDGET ARCHIVE -->
  <section id="archive" class="content-block" style="margin-top:64px;">
    <div class="sec-header">
      <div class="sec-eyebrow">Historical Records</div>
      <h2 class="sec-title">Budget Archive</h2>
      <p class="sec-sub">Historical approved national budgets categorized by fiscal year.</p>
    </div>
    <div class="grid-4 gap-2">
      <?php
      $archiveYears = ['FY 2025-2026', 'FY 2024-2025', 'FY 2023-2024', 'FY 2022-2023'];
      foreach($archiveYears as $y): ?>
      <div class="archive-card">
        <i data-lucide="folder-archive"></i>
        <h4><?php echo $y; ?></h4>
        <span>Approved Budget &amp; Reports</span>
        <button class="btn btn-outline btn-sm" style="width:100%;margin-top:12px;">Browse Year</button>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>
