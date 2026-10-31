<?php
$pageTitle = 'Budget Allocations & Treasury Release';
require_once __DIR__ . '/config/db.php';

// Flash messages
$message = $_SESSION['flash_message'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

// ── 1. AJAX ALLOCATION DETAILS ENDPOINT ──────────────────────
if (isset($_GET['ajax_details'])) {
    header('Content-Type: application/json; charset=utf-8');
    $allocId = (int)$_GET['ajax_details'];
    $alloc = db_row("
        SELECT a.*, o.name as org_name, o.code as org_code, f.name as fy_name,
               (a.allocated_amount - a.spent_amount) as remaining_balance,
               p.proposal_code, p.department as proposal_department, p.program as proposal_program
        FROM budget_allocations a
        JOIN organizations o ON o.id = a.organization_id
        JOIN fiscal_years f ON f.id = a.fiscal_year_id
        LEFT JOIN budget_proposals p ON p.id = a.proposal_id
        WHERE a.id = ?
    ", [$allocId]);

    if (!$alloc) {
        echo json_encode(['success' => false, 'error' => 'Allocation record not found']);
        exit;
    }

    echo json_encode(['success' => true, 'allocation' => $alloc]);
    exit;
}

// ── 2. CSV EXPORT ────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filterFy = (int)($_GET['fiscal_year_id'] ?? 0);
    $search = trim($_GET['q'] ?? '');

    $where = " WHERE 1=1 ";
    $params = [];

    if ($filterFy > 0) {
        $where .= " AND a.fiscal_year_id = ? ";
        $params[] = $filterFy;
    }
    if ($search !== '') {
        $where .= " AND (o.name LIKE ? OR o.code LIKE ? OR p.proposal_code LIKE ?) ";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $exportData = db_select("
        SELECT o.name as org_name, o.code as org_code, f.name as fy_name, p.proposal_code,
               a.approved_budget, a.allocated_amount, a.committed_amount, a.spent_amount,
               (a.allocated_amount - a.spent_amount) as remaining_balance,
               a.allocation_date, a.status
        FROM budget_allocations a
        JOIN organizations o ON o.id = a.organization_id
        JOIN fiscal_years f ON f.id = a.fiscal_year_id
        LEFT JOIN budget_proposals p ON p.id = a.proposal_id
        {$where}
        ORDER BY a.allocated_amount DESC
    ", $params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=SFGMIS_Budget_Allocations_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Organization', 'Code', 'Fiscal Year', 'Proposal Code', 'Approved Budget ($)', 'Allocated ($)', 'Committed ($)', 'Spent ($)', 'Remaining ($)', 'Allocation Date', 'Status']);
    foreach ($exportData as $row) {
        fputcsv($out, [
            $row['org_name'],
            $row['org_code'],
            $row['fy_name'],
            $row['proposal_code'] ?? 'Direct Allocation',
            number_format((float)$row['approved_budget'], 2),
            number_format((float)$row['allocated_amount'], 2),
            number_format((float)$row['committed_amount'], 2),
            number_format((float)$row['spent_amount'], 2),
            number_format((float)$row['remaining_balance'], 2),
            $row['allocation_date'],
            strtoupper($row['status'])
        ]);
    }
    fclose($out);
    exit;
}

// ── 3. FORM CRUD OPERATIONS ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ── CREATE / RELEASE ALLOCATION ──
    if ($action === 'allocate_funds') {
        $orgId = (int)$_POST['organization_id'];
        $fyId = (int)$_POST['fiscal_year_id'];
        $approved = (float)$_POST['approved_budget'];
        $allocated = (float)$_POST['allocated_amount'];
        $proposalId = !empty($_POST['proposal_id']) ? (int)$_POST['proposal_id'] : null;

        if ($allocated > $approved) {
            $_SESSION['flash_error'] = 'Allocated amount cannot exceed the approved budget quota.';
        } else {
            try {
                $existing = db_row("SELECT id FROM budget_allocations WHERE organization_id = ? AND fiscal_year_id = ?", [$orgId, $fyId]);
                if ($existing) {
                    db_execute("UPDATE budget_allocations SET approved_budget = ?, allocated_amount = ?, proposal_id = COALESCE(?, proposal_id) WHERE id = ?",
                        [$approved, $allocated, $proposalId, $existing['id']]);
                    record_audit('Updated Budget Allocation', 'Budget Management', 'budget_allocations', (string)$existing['id'], null, "Updated to " . format_currency($allocated));
                    $_SESSION['flash_message'] = "Existing budget allocation updated with allocated quota " . format_currency($allocated) . ".";
                } else {
                    $sql = "INSERT INTO budget_allocations (organization_id, fiscal_year_id, proposal_id, approved_budget, allocated_amount, committed_amount, spent_amount, allocation_date, status)
                            VALUES (?, ?, ?, ?, ?, 0.00, 0.00, CURDATE(), 'active')";
                    db_execute($sql, [$orgId, $fyId, $proposalId, $approved, $allocated]);
                    record_audit('Created Budget Allocation', 'Budget Management', 'budget_allocations', "ORG-{$orgId}", null, "Allocated " . format_currency($allocated));
                    $_SESSION['flash_message'] = "Budget allocation successfully registered and funds released to MDA.";
                }
            } catch (Exception $e) {
                $_SESSION['flash_error'] = 'Failed to allocate budget: ' . $e->getMessage();
            }
        }
        header('Location: budget-allocations.php');
        exit;
    }

    // ── EDIT ALLOCATION ──
    if ($action === 'edit_allocation') {
        $allocId = (int)$_POST['alloc_id'];
        $approved = (float)$_POST['approved_budget'];
        $allocated = (float)$_POST['allocated_amount'];
        $status = $_POST['status'] ?? 'active';

        try {
            $prev = db_row("SELECT * FROM budget_allocations WHERE id = ?", [$allocId]);
            if (!$prev) {
                $_SESSION['flash_error'] = 'Allocation record not found.';
            } elseif ($allocated > $approved) {
                $_SESSION['flash_error'] = 'Allocated amount cannot exceed approved budget.';
            } elseif ($allocated < (float)$prev['spent_amount']) {
                $_SESSION['flash_error'] = 'Allocated amount cannot be less than already spent vouchers ($' . number_format($prev['spent_amount'], 2) . ').';
            } else {
                db_execute("UPDATE budget_allocations SET approved_budget = ?, allocated_amount = ?, status = ? WHERE id = ?",
                    [$approved, $allocated, $status, $allocId]);
                record_audit('Edited Budget Allocation', 'Budget Management', 'budget_allocations', (string)$allocId, json_encode($prev), "Updated allocation to " . format_currency($allocated));
                $_SESSION['flash_message'] = "Allocation updated successfully.";
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Failed to update allocation: ' . $e->getMessage();
        }
        header('Location: budget-allocations.php');
        exit;
    }

    // ── DELETE / RECLAIM ALLOCATION ──
    if ($action === 'delete_allocation') {
        $allocId = (int)$_POST['alloc_id'];
        try {
            $prev = db_row("SELECT * FROM budget_allocations WHERE id = ?", [$allocId]);
            if (!$prev) {
                $_SESSION['flash_error'] = 'Allocation record not found.';
            } elseif ((float)$prev['spent_amount'] > 0 || (float)$prev['committed_amount'] > 0) {
                $_SESSION['flash_error'] = "Cannot delete allocation: Funds have already been committed or spent ($" . number_format($prev['spent_amount'], 2) . " disbursed).";
            } else {
                db_execute("DELETE FROM budget_allocations WHERE id = ?", [$allocId]);
                record_audit('Deleted Budget Allocation', 'Budget Management', 'budget_allocations', (string)$allocId, json_encode($prev), "Reclaimed unspent allocation");
                $_SESSION['flash_message'] = "Budget allocation reclaimed successfully.";
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Failed to delete allocation: ' . $e->getMessage();
        }
        header('Location: budget-allocations.php');
        exit;
    }
}

// ── 4. FILTERING & QUERIES ───────────────────────────────────
$fyFilter = (int)($_GET['fiscal_year_id'] ?? 0);
$search = trim($_GET['q'] ?? '');

$where = " WHERE 1=1 ";
$params = [];

if ($fyFilter > 0) {
    $where .= " AND a.fiscal_year_id = ? ";
    $params[] = $fyFilter;
}
if ($search !== '') {
    $where .= " AND (o.name LIKE ? OR o.code LIKE ? OR p.proposal_code LIKE ?) ";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Fetch allocations
$allocations = db_select("
    SELECT a.*, o.name as org_name, o.code as org_code, f.name as fy_name,
           (a.allocated_amount - a.spent_amount) as remaining_balance,
           p.proposal_code
    FROM budget_allocations a
    JOIN organizations o ON o.id = a.organization_id
    JOIN fiscal_years f ON f.id = a.fiscal_year_id
    LEFT JOIN budget_proposals p ON p.id = a.proposal_id
    {$where}
    ORDER BY a.allocated_amount DESC
", $params);

// National Summary Totals
$totalApproved = (float)db_row("SELECT SUM(approved_budget) as total FROM budget_allocations")['total'];
$totalAllocated = (float)db_row("SELECT SUM(allocated_amount) as total FROM budget_allocations")['total'];
$totalCommitted = (float)db_row("SELECT SUM(committed_amount) as total FROM budget_allocations")['total'];
$totalSpent = (float)db_row("SELECT SUM(spent_amount) as total FROM budget_allocations")['total'];
$totalUnallocated = $totalApproved - $totalAllocated;
$allocationRatio = $totalApproved > 0 ? round(($totalAllocated / $totalApproved) * 100, 1) : 0;

$orgsList = db_select("SELECT id, name, code FROM organizations WHERE type != 'government' ORDER BY name ASC");
$fiscalYears = db_select("SELECT id, name FROM fiscal_years ORDER BY id DESC");
$approvedProposals = db_select("SELECT id, proposal_code, organization_id, approved_amount FROM budget_proposals WHERE status IN ('approved', 'allocated') ORDER BY id DESC");

include __DIR__ . '/includes/header.php';
?>

<!-- ── ALERT MESSAGES ────────────────────────────────────────── -->
<?php if ($message): ?>
    <div style="background:#dcfce7; border:1px solid #86efac; color:#166534; padding:12px 18px; border-radius:10px; margin-bottom:22px; display:flex; align-items:center; gap:10px; box-shadow:0 2px 4px rgba(22,101,52,0.06);">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <div style="font-weight:600; font-size:0.9rem;"><?php echo htmlspecialchars($message); ?></div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; padding:12px 18px; border-radius:10px; margin-bottom:22px; display:flex; align-items:center; gap:10px; box-shadow:0 2px 4px rgba(153,27,27,0.06);">
        <i data-lucide="alert-octagon" style="width:20px;height:20px;flex-shrink:0;"></i>
        <div style="font-weight:600; font-size:0.9rem;"><?php echo htmlspecialchars($error); ?></div>
    </div>
<?php endif; ?>

<!-- ── TOP HEADER & ACTIONS ──────────────────────────────────── -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:16px;">
    <div>
        <div style="display:flex; align-items:center; gap:8px;">
            <span style="display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:8px; background:var(--gov-green-badge-bg); color:var(--gov-green);">
                <i data-lucide="pie-chart" style="width:20px;height:20px;"></i>
            </span>
            <h2 style="font-family:'Poppins',sans-serif; font-size:1.55rem; font-weight:800; color:var(--gov-green); margin:0;">
                Budget Allocations & Treasury Release
            </h2>
        </div>
        <p style="color:#64748b; font-size:0.875rem; margin:4px 0 0 42px;">
            Authorizing and disbursing approved budget quotas from the Central Treasury to designated MDAs.
        </p>
    </div>

    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <a href="budget-allocations.php?export=csv&fiscal_year_id=<?php echo $fyFilter; ?>&q=<?php echo urlencode($search); ?>" class="btn-sfgmis-outline" title="Export Allocations to CSV">
            <i data-lucide="download" style="width:16px;height:16px;"></i> Export CSV
        </a>
        <button onclick="openAddAllocationModal()" class="btn-sfgmis-green">
            <i data-lucide="plus-circle" style="width:17px;height:17px;"></i> Allocate Budget Quota
        </button>
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
    <a href="budget-allocations.php" class="sfgmis-tab-item active">
        <i data-lucide="pie-chart" style="width:16px;height:16px;"></i>
        <span>Budget Allocations</span>
        <span class="tab-count"><?php echo count($allocations); ?></span>
    </a>
    <a href="budget-monitoring.php" class="sfgmis-tab-item">
        <i data-lucide="activity" style="width:16px;height:16px;"></i>
        <span>Monitoring & Variance</span>
    </a>
</div>

<!-- ── SLEEK TREASURY FORMULA STRIP ───────────────────────────── -->
<div style="background:#ffffff; border:1px solid var(--gov-border); border-radius:12px; padding:20px; margin-bottom:24px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
        <div style="font-size:0.78rem; font-weight:700; text-transform:uppercase; color:var(--gov-green); display:flex; align-items:center; gap:6px;">
            <i data-lucide="landmark" style="width:15px;height:15px;"></i> Central Treasury Allocation Balance
        </div>
        <div style="font-size:0.8rem; font-weight:700; color:var(--gov-green);">
            <?php echo $allocationRatio; ?>% Released to Line MDAs
        </div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
        <div style="background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0;">
            <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">Approved National Budget</div>
            <div style="font-size:1.45rem; font-weight:800; color:#0f172a; font-family:'Poppins',sans-serif; margin-top:4px;">
                <?php echo format_currency($totalApproved); ?>
            </div>
            <div style="font-size:0.72rem; color:#64748b; margin-top:2px;">Appropriated by Parliament</div>
        </div>

        <div style="background:#f0fdf4; padding:16px; border-radius:10px; border:1px solid #bbf7d0;">
            <div style="font-size:0.75rem; color:#166534; font-weight:700; text-transform:uppercase;">Allocated to MDAs</div>
            <div style="font-size:1.45rem; font-weight:800; color:var(--gov-green); font-family:'Poppins',sans-serif; margin-top:4px;">
                <?php echo format_currency($totalAllocated); ?>
            </div>
            <div style="font-size:0.72rem; color:#15803d; margin-top:2px;">Treasury Warrants Disbursed</div>
        </div>

        <div style="background:#fffbeb; padding:16px; border-radius:10px; border:1px solid #fde68a;">
            <div style="font-size:0.75rem; color:#92400e; font-weight:700; text-transform:uppercase;">Unallocated Treasury Reserve</div>
            <div style="font-size:1.45rem; font-weight:800; color:#b45309; font-family:'Poppins',sans-serif; margin-top:4px;">
                <?php echo format_currency($totalUnallocated); ?>
            </div>
            <div style="font-size:0.72rem; color:#b45309; margin-top:2px;">Available for Supplementary Quotas</div>
        </div>
    </div>
</div>

<!-- ── SEARCH & FILTER TOOLBAR ────────────────────────────────── -->
<div class="sfgmis-filter-bar">
    <form method="GET" action="budget-allocations.php" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; flex:1;">
        <div class="sfgmis-search-box">
            <span class="search-ic"><i data-lucide="search" style="width:16px;height:16px;"></i></span>
            <input type="text" name="q" placeholder="Search by ministry, code, or proposal..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

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

        <?php if ($search !== '' || $fyFilter > 0): ?>
            <a href="budget-allocations.php" class="btn-sfgmis-outline btn-sfgmis-sm" style="color:var(--gov-red);" title="Reset filters">
                <i data-lucide="x" style="width:14px;height:14px;"></i> Clear
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- ── ALLOCATIONS DATA TABLE ─────────────────────────────────── -->
<div class="sfgmis-panel">
    <div class="sfgmis-panel-head">
        <h3>
            <i data-lucide="pie-chart" style="width:18px;height:18px;color:var(--sl-green-700);"></i>
            <span>MDA Approved Budget Allocations</span>
        </h3>
        <span style="font-size:0.8rem; color:#64748b; font-weight:600;">
            Showing <?php echo count($allocations); ?> Active Allocations
        </span>
    </div>

    <div style="overflow-x:auto;">
        <table class="sfgmis-table">
            <thead>
                <tr>
                    <th>Ministry / Line MDA</th>
                    <th>Fiscal Year</th>
                    <th style="text-align:right;">Approved Budget</th>
                    <th style="text-align:right;">Allocated Amount</th>
                    <th style="text-align:right;">Committed (POs)</th>
                    <th style="text-align:right;">Spent (Vouchers)</th>
                    <th style="text-align:right;">Available Remaining</th>
                    <th style="text-align:center; width:90px;">Status</th>
                    <th style="text-align:center; width:130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allocations)): ?>
                <tr>
                    <td colspan="9" style="text-align:center; padding:45px 20px;">
                        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px;">
                            <div style="width:50px; height:50px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8;">
                                <i data-lucide="inbox" style="width:26px;height:26px;"></i>
                            </div>
                            <h4 style="margin:0; font-size:1rem; color:#334155;">No budget allocations registered</h4>
                            <p style="margin:0; color:#64748b; font-size:0.82rem;">Disburse authorized funds from the central treasury to line MDAs.</p>
                            <button onclick="openAddAllocationModal()" class="btn-sfgmis-green btn-sfgmis-sm" style="margin-top:6px;">
                                <i data-lucide="plus-circle" style="width:14px;height:14px;"></i> Allocate Budget Quota
                            </button>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($allocations as $al): ?>
                    <tr>
                        <td>
                            <strong style="color:#0f172a; font-size:0.88rem;"><?php echo htmlspecialchars($al['org_name']); ?></strong>
                            <div style="font-size:0.75rem; color:#64748b; margin-top:2px;">
                                <span style="font-family:monospace; font-weight:700; color:var(--gov-green);"><?php echo htmlspecialchars($al['org_code']); ?></span>
                                <?php if (!empty($al['proposal_code'])): ?>
                                    &bull; <span style="color:#94a3b8;"><?php echo htmlspecialchars($al['proposal_code']); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td>
                            <span style="font-size:0.85rem; font-weight:600; color:#334155;"><?php echo htmlspecialchars($al['fy_name']); ?></span>
                        </td>

                        <td style="text-align:right;">
                            <span style="font-family:'Poppins',sans-serif; font-size:0.9rem;"><?php echo format_currency((float)$al['approved_budget']); ?></span>
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

                        <td style="text-align:center;">
                            <span class="badge-status badge-<?php echo htmlspecialchars($al['status']); ?>">
                                <?php echo htmlspecialchars($al['status']); ?>
                            </span>
                        </td>

                        <td style="text-align:center;">
                            <div class="sfgmis-actions">
                                <!-- View Details -->
                                <button type="button" onclick="viewAllocationDetails(<?php echo $al['id']; ?>)" class="sfgmis-btn-action action-view" title="View Allocation Dossier">
                                    <i data-lucide="eye" style="width:14px;height:14px;"></i>
                                </button>

                                <!-- Edit Allocation -->
                                <button type="button" onclick="openEditAllocationModal(<?php echo htmlspecialchars(json_encode($al), ENT_QUOTES, 'UTF-8'); ?>)" class="sfgmis-btn-action action-edit" title="Edit Allocation Quota">
                                    <i data-lucide="pencil" style="width:14px;height:14px;"></i>
                                </button>

                                <!-- Reclaim / Delete Allocation -->
                                <button type="button" onclick="openDeleteAllocationModal(<?php echo $al['id']; ?>, '<?php echo htmlspecialchars(addslashes($al['org_name'])); ?>', <?php echo (float)$al['allocated_amount']; ?>, <?php echo (float)$al['spent_amount']; ?>)" class="sfgmis-btn-action action-delete" title="Reclaim Allocation">
                                    <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- ── MODAL 1: ALLOCATE BUDGET QUOTA ────────────────────────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="addAllocationModal" class="sfgmis-modal-backdrop">
    <div class="sfgmis-modal">
        <div class="sfgmis-modal-head">
            <h3>
                <i data-lucide="plus-circle" style="width:20px;height:20px;color:var(--gov-green);"></i>
                <span>Allocate MDA Budget Quota</span>
            </h3>
            <button type="button" onclick="closeModal('addAllocationModal')" class="sfgmis-modal-close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" action="budget-allocations.php">
            <input type="hidden" name="action" value="allocate_funds">

            <div class="sfgmis-modal-body">
                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Target Ministry / MDA <span class="req">*</span></label>
                        <select name="organization_id" required class="sfgmis-select">
                            <?php foreach ($orgsList as $o): ?>
                                <option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['name'] . ' (' . $o['code'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="sfgmis-label">Fiscal Year <span class="req">*</span></label>
                        <select name="fiscal_year_id" required class="sfgmis-select">
                            <?php foreach ($fiscalYears as $fy): ?>
                                <option value="<?php echo $fy['id']; ?>"><?php echo htmlspecialchars($fy['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="sfgmis-form-group">
                    <label class="sfgmis-label">Link Approved Budget Proposal (Optional)</label>
                    <select name="proposal_id" class="sfgmis-select">
                        <option value="">Direct Quota Release (No Proposal Link)</option>
                        <?php foreach ($approvedProposals as $ap): ?>
                            <option value="<?php echo $ap['id']; ?>">
                                <?php echo htmlspecialchars($ap['proposal_code'] . ' — Approved: ' . format_currency((float)$ap['approved_amount'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Approved Budget Ceiling ($) <span class="req">*</span></label>
                        <input type="number" step="1000" min="0" name="approved_budget" placeholder="e.g. 15000000" value="10000000" required class="sfgmis-input">
                    </div>
                    <div>
                        <label class="sfgmis-label">Initial Released Allocation ($) <span class="req">*</span></label>
                        <input type="number" step="1000" min="0" name="allocated_amount" placeholder="e.g. 5000000" value="5000000" required class="sfgmis-input">
                    </div>
                </div>
            </div>

            <div class="sfgmis-modal-foot">
                <button type="button" onclick="closeModal('addAllocationModal')" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">
                    <i data-lucide="check" style="width:16px;height:16px;"></i> Disburse Funds
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- ── MODAL 2: EDIT ALLOCATION ──────────────────────────────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="editAllocationModal" class="sfgmis-modal-backdrop">
    <div class="sfgmis-modal">
        <div class="sfgmis-modal-head">
            <h3>
                <i data-lucide="pencil" style="width:20px;height:20px;color:var(--gov-gold);"></i>
                <span>Edit Budget Allocation Quota</span>
            </h3>
            <button type="button" onclick="closeModal('editAllocationModal')" class="sfgmis-modal-close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" action="budget-allocations.php">
            <input type="hidden" name="action" value="edit_allocation">
            <input type="hidden" name="alloc_id" id="edit_alloc_id">

            <div class="sfgmis-modal-body">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:16px;">
                    <strong id="edit_alloc_org" style="font-size:0.95rem; color:#0f172a;">MDA Name</strong>
                    <div style="font-size:0.75rem; color:#64748b; margin-top:2px;" id="edit_alloc_fy">Fiscal Year</div>
                </div>

                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Approved Budget Ceiling ($) <span class="req">*</span></label>
                        <input type="number" step="1000" min="0" name="approved_budget" id="edit_approved_budget" required class="sfgmis-input">
                    </div>
                    <div>
                        <label class="sfgmis-label">Allocated Disbursement ($) <span class="req">*</span></label>
                        <input type="number" step="1000" min="0" name="allocated_amount" id="edit_allocated_amount" required class="sfgmis-input">
                    </div>
                </div>

                <div class="sfgmis-form-group">
                    <label class="sfgmis-label">Allocation Status</label>
                    <select name="status" id="edit_status" class="sfgmis-select">
                        <option value="active">Active (Warrant Released)</option>
                        <option value="inactive">Suspended / Frozen</option>
                    </select>
                </div>
            </div>

            <div class="sfgmis-modal-foot">
                <button type="button" onclick="closeModal('editAllocationModal')" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">
                    <i data-lucide="check" style="width:16px;height:16px;"></i> Update Quota
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- ── MODAL 3: VIEW ALLOCATION DOSSIER ──────────────────────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="viewAllocationModal" class="sfgmis-modal-backdrop">
    <div class="sfgmis-modal sfgmis-modal-lg">
        <div class="sfgmis-modal-head">
            <h3>
                <i data-lucide="info" style="width:20px;height:20px;color:var(--gov-green);"></i>
                <span id="view_alloc_title">Treasury Allocation Dossier</span>
            </h3>
            <button type="button" onclick="closeModal('viewAllocationModal')" class="sfgmis-modal-close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>

        <div class="sfgmis-modal-body" id="view_alloc_content">
            <div style="text-align:center; padding:30px; color:#64748b;">
                <p>Loading allocation details...</p>
            </div>
        </div>

        <div class="sfgmis-modal-foot">
            <button type="button" onclick="closeModal('viewAllocationModal')" class="btn-sfgmis-outline">Close</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- ── MODAL 4: DELETE / RECLAIM ALLOCATION ──────────────────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="deleteAllocationModal" class="sfgmis-modal-backdrop">
    <div class="sfgmis-modal" style="max-width:480px;">
        <div class="sfgmis-modal-head" style="background:#fef2f2; border-bottom:1px solid #fecaca;">
            <h3 style="color:var(--gov-red);">
                <i data-lucide="alert-triangle" style="width:20px;height:20px;"></i>
                <span>Confirm Allocation Reclaim</span>
            </h3>
            <button type="button" onclick="closeModal('deleteAllocationModal')" class="sfgmis-modal-close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" action="budget-allocations.php">
            <input type="hidden" name="action" value="delete_allocation">
            <input type="hidden" name="alloc_id" id="delete_alloc_id">

            <div class="sfgmis-modal-body">
                <p style="font-size:0.92rem; color:#1e293b; margin-top:0;">
                    Are you sure you want to reclaim and delete this budget allocation quota?
                </p>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:14px;">
                    <strong id="delete_alloc_org" style="font-size:0.95rem; color:#0f172a;">MDA Name</strong>
                    <div style="font-size:0.8rem; color:#64748b; margin-top:4px;">
                        Allocated: <strong id="delete_alloc_amount" style="color:var(--gov-green);">$0</strong>
                    </div>
                </div>
                <p style="font-size:0.8rem; color:#64748b; margin-bottom:0;">
                    Note: If any purchase orders are committed or vouchers spent against this quota, deletion will be blocked by statutory Treasury controls.
                </p>
            </div>

            <div class="sfgmis-modal-foot">
                <button type="button" onclick="closeModal('deleteAllocationModal')" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-danger">
                    <i data-lucide="trash-2" style="width:16px;height:16px;"></i> Reclaim Allocation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── JAVASCRIPT FOR ALLOCATIONS ─────────────────────────────── -->
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

function openAddAllocationModal() {
    openModal('addAllocationModal');
}

function openEditAllocationModal(al) {
    if (typeof al === 'string') {
        try { al = JSON.parse(al); } catch(e) {}
    }
    document.getElementById('edit_alloc_id').value = al.id;
    document.getElementById('edit_alloc_org').textContent = al.org_name + ' (' + al.org_code + ')';
    document.getElementById('edit_alloc_fy').textContent = 'Fiscal Year: ' + al.fy_name;
    document.getElementById('edit_approved_budget').value = al.approved_budget;
    document.getElementById('edit_allocated_amount').value = al.allocated_amount;
    document.getElementById('edit_status').value = al.status;
    openModal('editAllocationModal');
}

function openDeleteAllocationModal(id, orgName, allocated, spent) {
    document.getElementById('delete_alloc_id').value = id;
    document.getElementById('delete_alloc_org').textContent = orgName;
    document.getElementById('delete_alloc_amount').textContent = '$' + Number(allocated).toLocaleString();
    openModal('deleteAllocationModal');
}

function viewAllocationDetails(id) {
    const content = document.getElementById('view_alloc_content');
    content.innerHTML = '<div style="text-align:center; padding:35px; color:#64748b;"><p>Loading allocation details...</p></div>';
    openModal('viewAllocationModal');

    fetch('budget-allocations.php?ajax_details=' + id)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                content.innerHTML = '<div style="color:var(--gov-red); padding:20px;">' + (data.error || 'Failed to load details') + '</div>';
                return;
            }

            const al = data.allocation;
            const spentPct = al.allocated_amount > 0 ? Math.round((al.spent_amount / al.allocated_amount) * 100) : 0;
            const committedPct = al.allocated_amount > 0 ? Math.round((al.committed_amount / al.allocated_amount) * 100) : 0;

            document.getElementById('view_alloc_title').textContent = al.org_name + ' — ' + al.fy_name;

            content.innerHTML = `
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px; margin-bottom:20px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
                    <div>
                        <div style="font-size:0.72rem; text-transform:uppercase; color:#64748b; font-weight:700;">Entity</div>
                        <div style="font-size:0.95rem; font-weight:800; color:var(--gov-green);">${al.org_name} (${al.org_code})</div>
                    </div>
                    <div>
                        <div style="font-size:0.72rem; text-transform:uppercase; color:#64748b; font-weight:700;">Fiscal Year</div>
                        <div style="font-size:0.9rem; font-weight:700; color:#1e293b;">${al.fy_name}</div>
                    </div>
                    <div>
                        <div style="font-size:0.72rem; text-transform:uppercase; color:#64748b; font-weight:700;">Allocation Date</div>
                        <div style="font-size:0.85rem; font-weight:600; color:#334155;">${al.allocation_date}</div>
                    </div>
                    <div>
                        <div style="font-size:0.72rem; text-transform:uppercase; color:#64748b; font-weight:700;">Status</div>
                        <div style="margin-top:2px;"><span class="badge-status badge-${al.status}">${al.status}</span></div>
                    </div>
                </div>

                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:18px; margin-bottom:20px;">
                    <div style="font-size:0.75rem; text-transform:uppercase; color:#166534; font-weight:700; margin-bottom:12px;">Quota Financial Performance</div>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:14px;">
                        <div>
                            <div style="font-size:0.72rem; color:#15803d; font-weight:600;">Approved Ceiling</div>
                            <div style="font-size:1.25rem; font-weight:800; font-family:'Poppins',sans-serif; color:#0f172a;">$${Number(al.approved_budget).toLocaleString()}</div>
                        </div>
                        <div>
                            <div style="font-size:0.72rem; color:#15803d; font-weight:600;">Allocated Warrants</div>
                            <div style="font-size:1.25rem; font-weight:800; font-family:'Poppins',sans-serif; color:var(--gov-green);">$${Number(al.allocated_amount).toLocaleString()}</div>
                        </div>
                        <div>
                            <div style="font-size:0.72rem; color:#15803d; font-weight:600;">Committed in POs</div>
                            <div style="font-size:1.25rem; font-weight:800; font-family:'Poppins',sans-serif; color:#2563eb;">$${Number(al.committed_amount).toLocaleString()}</div>
                        </div>
                        <div>
                            <div style="font-size:0.72rem; color:#15803d; font-weight:600;">Spent (Disbursed)</div>
                            <div style="font-size:1.25rem; font-weight:800; font-family:'Poppins',sans-serif; color:#b91c1c;">$${Number(al.spent_amount).toLocaleString()}</div>
                        </div>
                        <div>
                            <div style="font-size:0.72rem; color:#15803d; font-weight:600;">Remaining Balance</div>
                            <div style="font-size:1.25rem; font-weight:800; font-family:'Poppins',sans-serif; color:#047857;">$${Number(al.remaining_balance).toLocaleString()}</div>
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; font-weight:700; margin-bottom:5px;">
                            <span>Utilization Execution</span>
                            <span>${spentPct}% Spent &bull; ${committedPct}% Committed</span>
                        </div>
                        <div class="gov-progress-wrap" style="height:10px;">
                            <div class="gov-progress-bar ${spentPct >= 80 ? 'danger' : (spentPct >= 60 ? 'warning' : '')}" style="width:${Math.min(100, spentPct)}%;"></div>
                        </div>
                    </div>
                </div>

                ${al.proposal_code ? `
                <div style="border:1px solid var(--gov-border); border-radius:8px; padding:14px;">
                    <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700; margin-bottom:4px;">Linked Statutory Proposal</div>
                    <div style="font-size:0.9rem; font-weight:700; color:#0f172a;">${al.proposal_code} &bull; ${al.proposal_department || ''}</div>
                    <div style="font-size:0.8rem; color:#64748b;">${al.proposal_program || ''}</div>
                </div>` : ''}
            `;

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        })
        .catch(err => {
            content.innerHTML = '<div style="color:var(--gov-red); padding:20px;">Failed to load dossier: ' + err.message + '</div>';
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
