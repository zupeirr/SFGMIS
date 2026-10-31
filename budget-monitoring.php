<?php
$pageTitle = 'Budget Monitoring & Variance Control';
require_once __DIR__ . '/config/db.php';

// ── 1. AJAX DRILLDOWN ENDPOINT ───────────────────────────────
if (isset($_GET['ajax_drilldown'])) {
    header('Content-Type: application/json; charset=utf-8');
    $orgId = (int)$_GET['ajax_drilldown'];
    $org = db_row("SELECT * FROM organizations WHERE id = ?", [$orgId]);

    if (!$org) {
        echo json_encode(['success' => false, 'error' => 'Organization not found']);
        exit;
    }

    $vouchers = db_select("
        SELECT id, request_no, requested_amount, status, created_at, category, description 
        FROM expenditure_requests 
        WHERE organization_id = ? 
        ORDER BY id DESC LIMIT 10
    ", [$orgId]);

    $proposals = db_select("
        SELECT id, proposal_code, program, total_requested, approved_amount, status 
        FROM budget_proposals 
        WHERE organization_id = ? 
        ORDER BY id DESC LIMIT 5
    ", [$orgId]);

    echo json_encode([
        'success' => true,
        'org' => $org,
        'vouchers' => $vouchers,
        'proposals' => $proposals
    ]);
    exit;
}

// ── 2. CSV EXPORT ────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $fyFilter = (int)($_GET['fiscal_year_id'] ?? 0);
    $search = trim($_GET['q'] ?? '');

    $where = " WHERE 1=1 ";
    $params = [];

    if ($fyFilter > 0) {
        $where .= " AND a.fiscal_year_id = ? ";
        $params[] = $fyFilter;
    }
    if ($search !== '') {
        $where .= " AND (o.name LIKE ? OR o.code LIKE ?) ";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $exportData = db_select("
        SELECT o.name as org_name, o.code as org_code, f.name as fy_name,
               a.approved_budget, a.allocated_amount, a.committed_amount, a.spent_amount,
               (a.allocated_amount - a.spent_amount) as remaining_balance,
               ROUND((a.spent_amount / NULLIF(a.allocated_amount, 0)) * 100, 1) as utilization_pct
        FROM budget_allocations a
        JOIN organizations o ON o.id = a.organization_id
        JOIN fiscal_years f ON f.id = a.fiscal_year_id
        {$where}
        ORDER BY a.allocated_amount DESC
    ", $params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=SFGMIS_Budget_Monitoring_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Organization', 'Code', 'Fiscal Year', 'Approved Budget ($)', 'Allocated ($)', 'Committed ($)', 'Spent ($)', 'Remaining ($)', 'Utilization (%)']);
    foreach ($exportData as $row) {
        fputcsv($out, [
            $row['org_name'],
            $row['org_code'],
            $row['fy_name'],
            number_format((float)$row['approved_budget'], 2),
            number_format((float)$row['allocated_amount'], 2),
            number_format((float)$row['committed_amount'], 2),
            number_format((float)$row['spent_amount'], 2),
            number_format((float)$row['remaining_balance'], 2),
            $row['utilization_pct'] ?? '0.0'
        ]);
    }
    fclose($out);
    exit;
}

// ── 3. FILTERING & DATA FETCHING ─────────────────────────────
$fyFilter = (int)($_GET['fiscal_year_id'] ?? 0);
$tierFilter = trim($_GET['tier'] ?? 'all');
$search = trim($_GET['q'] ?? '');

$where = " WHERE 1=1 ";
$params = [];

if ($fyFilter > 0) {
    $where .= " AND a.fiscal_year_id = ? ";
    $params[] = $fyFilter;
}
if ($search !== '') {
    $where .= " AND (o.name LIKE ? OR o.code LIKE ?) ";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Fetch all allocations with organization details
$allocations = db_select("
    SELECT a.*, o.name as org_name, o.code as org_code, f.name as fy_name,
           (a.allocated_amount - a.spent_amount) as remaining_balance
    FROM budget_allocations a
    JOIN organizations o ON o.id = a.organization_id
    JOIN fiscal_years f ON f.id = a.fiscal_year_id
    {$where}
    ORDER BY a.allocated_amount DESC
", $params);

// Tier filter in PHP
if ($tierFilter !== 'all') {
    $allocations = array_filter($allocations, function($al) use ($tierFilter) {
        $spentPct = $al['allocated_amount'] > 0 ? ($al['spent_amount'] / $al['allocated_amount']) * 100 : 0;
        if ($tierFilter === 'critical') return $spentPct >= 80;
        if ($tierFilter === 'moderate') return $spentPct >= 50 && $spentPct < 80;
        if ($tierFilter === 'low') return $spentPct < 50;
        return true;
    });
}

// National Totals
$totalApproved  = (float)db_row("SELECT SUM(approved_budget) as t FROM budget_allocations")['t'];
$totalAllocated = (float)db_row("SELECT SUM(allocated_amount) as t FROM budget_allocations")['t'];
$totalCommitted = (float)db_row("SELECT SUM(committed_amount) as t FROM budget_allocations")['t'];
$totalSpent     = (float)db_row("SELECT SUM(spent_amount) as t FROM budget_allocations")['t'];
$totalAvailable = $totalAllocated - $totalSpent;
$nationalUtilPct = $totalAllocated > 0 ? round(($totalSpent / $totalAllocated) * 100, 1) : 0;

$fiscalYears = db_select("SELECT id, name FROM fiscal_years ORDER BY id DESC");

include __DIR__ . '/includes/header.php';
?>

<!-- ── TOP HEADER & ACTIONS ──────────────────────────────────── -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:16px;">
    <div>
        <div style="display:flex; align-items:center; gap:8px;">
            <span style="display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:8px; background:var(--gov-green-badge-bg); color:var(--gov-green);">
                <i data-lucide="activity" style="width:20px;height:20px;"></i>
            </span>
            <h2 style="font-family:'Poppins',sans-serif; font-size:1.55rem; font-weight:800; color:var(--gov-green); margin:0;">
                Budget Monitoring & Variance Control
            </h2>
        </div>
        <p style="color:#64748b; font-size:0.875rem; margin:4px 0 0 42px;">
            Real-time expenditure burn-rate, procurement commitment checks, and statutory variance tracking.
        </p>
    </div>

    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <a href="budget-monitoring.php?export=csv&fiscal_year_id=<?php echo $fyFilter; ?>&q=<?php echo urlencode($search); ?>" class="btn-sfgmis-outline" title="Export Monitoring Report to CSV">
            <i data-lucide="download" style="width:16px;height:16px;"></i> Export CSV Report
        </a>
    </div>
</div>

<!-- ── SUBPAGE TABS ───────────────────────────────────────────── -->
<div class="sfgmis-tabs">
    <a href="budget-cycles.php" class="sfgmis-tab-item">
        <i data-lucide="calendar" style="width:16px;height:16px;"></i>
        <span>Fiscal Years & Cycles</span>
    </a>
    <a href="budget-proposals.php" class="sfgmis-tab-item">
        <i data-lucide="file-text" style="width:16px;height:16px;"></i>
        <span>Budget Proposals</span>
    </a>
    <a href="budget-allocations.php" class="sfgmis-tab-item">
        <i data-lucide="pie-chart" style="width:16px;height:16px;"></i>
        <span>Budget Allocations</span>
    </a>
    <a href="budget-monitoring.php" class="sfgmis-tab-item active">
        <i data-lucide="activity" style="width:16px;height:16px;"></i>
        <span>Monitoring & Variance</span>
        <span class="tab-count"><?php echo count($allocations); ?></span>
    </a>
</div>

<!-- ── NATIONAL EXECUTION RATIO CARD ──────────────────────────── -->
<div style="background:#ffffff; border:1px solid var(--gov-border); border-radius:12px; padding:22px; margin-bottom:24px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <div>
            <h3 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:0;">National Budget Execution Ratio</h3>
            <div style="font-size:0.8rem; color:#64748b; margin-top:2px;">Consolidated performance across all Somaliland Ministries & MDAs</div>
        </div>
        <div style="font-size:1.6rem; font-weight:800; color:var(--gov-green); font-family:'Poppins',sans-serif;">
            <?php echo $nationalUtilPct; ?>% <span style="font-size:0.82rem; font-weight:600; color:#64748b;">Disbursed</span>
        </div>
    </div>

    <div class="gov-progress-wrap" style="height:12px; margin-bottom:18px;">
        <div class="gov-progress-bar <?php echo $nationalUtilPct >= 80 ? 'danger' : ($nationalUtilPct >= 60 ? 'warning' : ''); ?>" style="width:<?php echo min(100, $nationalUtilPct); ?>%;"></div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap:14px; text-align:center;">
        <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
            <div style="font-size:0.72rem; color:#64748b; font-weight:700; text-transform:uppercase;">Approved Ceilings</div>
            <div style="font-size:1.15rem; font-weight:800; color:#0f172a; font-family:'Poppins',sans-serif; margin-top:3px;"><?php echo format_currency($totalApproved); ?></div>
        </div>
        <div style="background:#f0fdf4; padding:12px; border-radius:8px; border:1px solid #bbf7d0;">
            <div style="font-size:0.72rem; color:#166534; font-weight:700; text-transform:uppercase;">Allocated Disbursement</div>
            <div style="font-size:1.15rem; font-weight:800; color:var(--gov-green); font-family:'Poppins',sans-serif; margin-top:3px;"><?php echo format_currency($totalAllocated); ?></div>
        </div>
        <div style="background:#eff6ff; padding:12px; border-radius:8px; border:1px solid #bfdbfe;">
            <div style="font-size:0.72rem; color:#1e40af; font-weight:700; text-transform:uppercase;">Committed in POs</div>
            <div style="font-size:1.15rem; font-weight:800; color:#2563eb; font-family:'Poppins',sans-serif; margin-top:3px;"><?php echo format_currency($totalCommitted); ?></div>
        </div>
        <div style="background:#fef2f2; padding:12px; border-radius:8px; border:1px solid #fecaca;">
            <div style="font-size:0.72rem; color:#991b1b; font-weight:700; text-transform:uppercase;">Actual Expenditure</div>
            <div style="font-size:1.15rem; font-weight:800; color:#b91c1c; font-family:'Poppins',sans-serif; margin-top:3px;"><?php echo format_currency($totalSpent); ?></div>
        </div>
        <div style="background:#fffbeb; padding:12px; border-radius:8px; border:1px solid #fde68a;">
            <div style="font-size:0.72rem; color:#92400e; font-weight:700; text-transform:uppercase;">Available Liquidity</div>
            <div style="font-size:1.15rem; font-weight:800; color:#b45309; font-family:'Poppins',sans-serif; margin-top:3px;"><?php echo format_currency($totalAvailable); ?></div>
        </div>
    </div>
</div>

<!-- ── SEARCH & FILTER TOOLBAR ────────────────────────────────── -->
<div class="sfgmis-filter-bar">
    <form method="GET" action="budget-monitoring.php" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; flex:1;">
        <div class="sfgmis-search-box">
            <span class="search-ic"><i data-lucide="search" style="width:16px;height:16px;"></i></span>
            <input type="text" name="q" placeholder="Search by ministry or code..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <select name="tier" class="sfgmis-select" style="width:auto; min-width:160px;" onchange="this.form.submit()">
            <option value="all" <?php echo $tierFilter === 'all' ? 'selected' : ''; ?>>All Execution Tiers</option>
            <option value="critical" <?php echo $tierFilter === 'critical' ? 'selected' : ''; ?>>Critical (>= 80% Spent)</option>
            <option value="moderate" <?php echo $tierFilter === 'moderate' ? 'selected' : ''; ?>>Moderate (50-79% Spent)</option>
            <option value="low" <?php echo $tierFilter === 'low' ? 'selected' : ''; ?>>Low (&lt; 50% Spent)</option>
        </select>

        <select name="fiscal_year_id" class="sfgmis-select" style="width:auto; min-width:160px;" onchange="this.form.submit()">
            <option value="0">All Fiscal Years</option>
            <?php foreach ($fiscalYears as $fy): ?>
                <option value="<?php echo $fy['id']; ?>" <?php echo $fyFilter === (int)$fy['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($fy['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-sfgmis-outline btn-sfgmis-sm">
            <i data-lucide="filter" style="width:14px;height:14px;"></i> Apply Filter
        </button>

        <?php if ($search !== '' || $tierFilter !== 'all' || $fyFilter > 0): ?>
            <a href="budget-monitoring.php" class="btn-sfgmis-outline btn-sfgmis-sm" style="color:var(--gov-red);" title="Reset filters">
                <i data-lucide="x" style="width:14px;height:14px;"></i> Clear
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- ── MDA EXECUTION DETAILS TABLE ───────────────────────────── -->
<div class="sfgmis-panel">
    <div class="sfgmis-panel-head">
        <h3>
            <i data-lucide="activity" style="width:18px;height:18px;color:var(--sl-green-700);"></i>
            <span>MDA Budget Monitoring & Execution Rate</span>
        </h3>
        <span style="font-size:0.8rem; color:#64748b; font-weight:600;">Live Real-Time Variance</span>
    </div>

    <div style="overflow-x:auto;">
        <table class="sfgmis-table">
            <thead>
                <tr>
                    <th>Ministry / Agency</th>
                    <th style="text-align:right;">Approved</th>
                    <th style="text-align:right;">Allocated</th>
                    <th style="text-align:right;">Committed (POs)</th>
                    <th style="text-align:right;">Spent (Disbursed)</th>
                    <th style="text-align:right;">Remaining Balance</th>
                    <th style="min-width:180px;">Utilization & Burn Rate</th>
                    <th style="text-align:center; width:90px;">Drilldown</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allocations)): ?>
                <tr>
                    <td colspan="8" style="text-align:center; padding:45px 20px;">
                        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px;">
                            <div style="width:50px; height:50px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8;">
                                <i data-lucide="inbox" style="width:26px;height:26px;"></i>
                            </div>
                            <h4 style="margin:0; font-size:1rem; color:#334155;">No matching monitoring records found</h4>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($allocations as $al): 
                        $spentPct = $al['allocated_amount'] > 0 ? round(($al['spent_amount'] / $al['allocated_amount']) * 100, 1) : 0;
                        $committedPct = $al['allocated_amount'] > 0 ? round(($al['committed_amount'] / $al['allocated_amount']) * 100, 1) : 0;
                        $barColor = ($spentPct >= 80) ? 'danger' : (($spentPct >= 60) ? 'warning' : '');
                    ?>
                    <tr>
                        <td>
                            <strong style="color:#0f172a; font-size:0.88rem;"><?php echo htmlspecialchars($al['org_name']); ?></strong>
                            <div style="font-size:0.75rem; color:#64748b; margin-top:2px;">
                                <span style="font-family:monospace; font-weight:700; color:var(--gov-green);"><?php echo htmlspecialchars($al['org_code']); ?></span>
                                &bull; <?php echo htmlspecialchars($al['fy_name']); ?>
                            </div>
                        </td>

                        <td style="text-align:right; font-family:'Poppins',sans-serif; font-size:0.9rem;">
                            <?php echo format_currency((float)$al['approved_budget']); ?>
                        </td>

                        <td style="text-align:right;">
                            <strong style="color:var(--gov-green); font-family:'Poppins',sans-serif; font-size:0.92rem;">
                                <?php echo format_currency((float)$al['allocated_amount']); ?>
                            </strong>
                        </td>

                        <td style="text-align:right; color:#2563eb; font-size:0.88rem;">
                            <?php echo format_currency((float)$al['committed_amount']); ?>
                        </td>

                        <td style="text-align:right; color:#b91c1c; font-weight:700; font-size:0.88rem;">
                            <?php echo format_currency((float)$al['spent_amount']); ?>
                        </td>

                        <td style="text-align:right;">
                            <strong style="color:var(--sl-green-800); font-family:'Poppins',sans-serif; font-size:0.92rem;">
                                <?php echo format_currency((float)$al['remaining_balance']); ?>
                            </strong>
                        </td>

                        <td>
                            <div style="display:flex; justify-content:space-between; font-size:0.72rem; font-weight:700; margin-bottom:4px;">
                                <span style="color:<?php echo $spentPct >= 80 ? '#b91c1c' : 'var(--gov-green)'; ?>;"><?php echo $spentPct; ?>% Spent</span>
                                <span style="color:#2563eb;"><?php echo $committedPct; ?>% committed</span>
                            </div>
                            <div class="gov-progress-wrap" style="height:8px;">
                                <div class="gov-progress-bar <?php echo $barColor; ?>" style="width:<?php echo min(100, $spentPct); ?>%;"></div>
                            </div>
                        </td>

                        <td style="text-align:center;">
                            <button type="button" onclick="openDrilldownModal(<?php echo $al['organization_id']; ?>)" class="sfgmis-btn-action action-view" title="Drilldown Vouchers & Commitments">
                                <i data-lucide="eye" style="width:14px;height:14px;"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- ── MODAL: DRILLDOWN EXPENDITURE & COMMITMENT BREAKDOWN ────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="drilldownModal" class="sfgmis-modal-backdrop">
    <div class="sfgmis-modal sfgmis-modal-lg">
        <div class="sfgmis-modal-head">
            <h3>
                <i data-lucide="activity" style="width:20px;height:20px;color:var(--gov-green);"></i>
                <span id="drilldown_title">MDA Variance & Expenditure Drilldown</span>
            </h3>
            <button type="button" onclick="closeModal('drilldownModal')" class="sfgmis-modal-close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>

        <div class="sfgmis-modal-body" id="drilldown_content">
            <div style="text-align:center; padding:30px; color:#64748b;">
                <p>Loading expenditure vouchers...</p>
            </div>
        </div>

        <div class="sfgmis-modal-foot">
            <button type="button" onclick="closeModal('drilldownModal')" class="btn-sfgmis-outline">Close</button>
        </div>
    </div>
</div>

<!-- ── JAVASCRIPT FOR MONITORING ──────────────────────────────── -->
<script>
function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.add('active');
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.remove('active');
    }
}

function openDrilldownModal(orgId) {
    const content = document.getElementById('drilldown_content');
    content.innerHTML = '<div style="text-align:center; padding:35px; color:#64748b;"><p>Loading expenditure vouchers...</p></div>';
    openModal('drilldownModal');

    fetch('budget-monitoring.php?ajax_drilldown=' + orgId)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                content.innerHTML = '<div style="color:var(--gov-red); padding:20px;">' + (data.error || 'Failed to load details') + '</div>';
                return;
            }

            const o = data.org;
            const vouchers = data.vouchers || [];
            const proposals = data.proposals || [];

            document.getElementById('drilldown_title').textContent = o.name + ' (' + o.code + ') — Expenditure Audit';

            let vouchersHtml = '';
            if (vouchers.length > 0) {
                vouchersHtml = `
                    <table class="sfgmis-table" style="font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th>Voucher No</th>
                                <th>Category & Description</th>
                                <th style="text-align:right;">Amount ($)</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${vouchers.map(v => `
                                <tr>
                                    <td><span style="font-family:monospace; font-weight:700; color:var(--gov-green);">${v.request_no}</span></td>
                                    <td><strong>${v.category || 'General'}</strong><div style="font-size:0.72rem; color:#64748b;">${v.description || ''}</div></td>
                                    <td style="text-align:right;"><strong>$${Number(v.requested_amount).toLocaleString()}</strong></td>
                                    <td><span class="badge-status badge-${v.status}">${v.status}</span></td>
                                    <td>${v.created_at ? v.created_at.substring(0, 10) : ''}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                vouchersHtml = '<p style="color:#94a3b8; font-size:0.82rem; padding:10px 0;">No expenditure vouchers processed yet for this entity.</p>';
            }

            content.innerHTML = `
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:18px;">
                    <strong style="color:#0f172a; font-size:0.95rem;">${o.name}</strong>
                    <div style="font-size:0.8rem; color:#64748b; margin-top:2px;">
                        Statutory Ceiling: <strong>$${Number(o.budget_ceiling).toLocaleString()}</strong> &bull; Lead: ${o.responsible_officer || 'Not Assigned'}
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <h4 style="margin:0 0 8px 0; font-size:0.92rem; font-weight:700; color:var(--gov-green);">Recent Processed Expenditure Vouchers</h4>
                    ${vouchersHtml}
                </div>
            `;

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        })
        .catch(err => {
            content.innerHTML = '<div style="color:var(--gov-red); padding:20px;">Failed to load drilldown: ' + err.message + '</div>';
        });
}

document.querySelectorAll('.sfgmis-modal-backdrop').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
