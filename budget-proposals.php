<?php
$pageTitle = 'Budget Proposals & Review Workflow';
require_once __DIR__ . '/config/db.php';

// Flash messages
$message = $_SESSION['flash_message'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

// ── 1. AJAX PROPOSAL DETAILS ENDPOINT ────────────────────────
if (isset($_GET['ajax_details'])) {
    header('Content-Type: application/json; charset=utf-8');
    $propId = (int)$_GET['ajax_details'];
    $proposal = db_row("
        SELECT p.*, o.name as org_name, o.code as org_code, f.name as fy_name, 
               u.name as reviewer_name, u.designation as reviewer_designation
        FROM budget_proposals p
        JOIN organizations o ON o.id = p.organization_id
        JOIN fiscal_years f ON f.id = p.fiscal_year_id
        LEFT JOIN users u ON u.id = p.reviewed_by
        WHERE p.id = ?
    ", [$propId]);

    if (!$proposal) {
        echo json_encode(['success' => false, 'error' => 'Proposal not found']);
        exit;
    }

    $allocation = db_row("
        SELECT * FROM budget_allocations WHERE proposal_id = ?
    ", [$propId]);

    $lineItems = db_select("
        SELECT * FROM budget_items WHERE proposal_id = ? ORDER BY id ASC
    ", [$propId]);

    echo json_encode([
        'success'    => true,
        'proposal'   => $proposal,
        'allocation' => $allocation,
        'line_items' => $lineItems
    ]);
    exit;
}

// ── 2. CSV EXPORT ────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filterStatus = trim($_GET['status'] ?? 'all');
    $filterOrg = (int)($_GET['org_id'] ?? 0);
    $search = trim($_GET['q'] ?? '');

    $where = " WHERE 1=1 ";
    $params = [];

    if ($filterStatus !== 'all') {
        $where .= " AND p.status = ? ";
        $params[] = $filterStatus;
    }
    if ($filterOrg > 0) {
        $where .= " AND p.organization_id = ? ";
        $params[] = $filterOrg;
    }
    if ($search !== '') {
        $where .= " AND (p.proposal_code LIKE ? OR p.department LIKE ? OR p.program LIKE ? OR p.activity LIKE ?) ";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $exportData = db_select("
        SELECT p.proposal_code, o.name as org_name, o.code as org_code, f.name as fy_name,
               p.department, p.program, p.activity, p.total_requested, p.approved_amount,
               p.status, p.submission_date, p.reviewer_comments
        FROM budget_proposals p
        JOIN organizations o ON o.id = p.organization_id
        JOIN fiscal_years f ON f.id = p.fiscal_year_id
        {$where}
        ORDER BY p.id DESC
    ", $params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=SFGMIS_Budget_Proposals_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Proposal Code', 'Organization', 'Org Code', 'Fiscal Year', 'Department', 'Program', 'Activity', 'Requested ($)', 'Approved ($)', 'Status', 'Submission Date', 'Reviewer Directives']);
    foreach ($exportData as $row) {
        fputcsv($out, [
            $row['proposal_code'],
            $row['org_name'],
            $row['org_code'],
            $row['fy_name'],
            $row['department'],
            $row['program'],
            $row['activity'],
            number_format((float)$row['total_requested'], 2),
            number_format((float)$row['approved_amount'], 2),
            strtoupper(str_replace('_', ' ', $row['status'])),
            $row['submission_date'],
            $row['reviewer_comments'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// ── 3. FORM CRUD & WORKFLOW ACTIONS ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ── C: SUBMIT NEW PROPOSAL ──
    if ($action === 'submit_proposal') {
        $orgId = (int)$_POST['organization_id'];
        $fyId = (int)$_POST['fiscal_year_id'];
        $dept = trim($_POST['department'] ?? '');
        $program = trim($_POST['program'] ?? '');
        $activity = trim($_POST['activity'] ?? '');
        $requested = (float)($_POST['total_requested'] ?? 0);
        $code = generate_code('budget_proposals', 'proposal_code', 'PROP');

        if ($orgId <= 0 || $dept === '' || $requested <= 0) {
            $_SESSION['flash_error'] = 'Submitting MDA, department, and valid requested envelope are required.';
        } else {
            try {
                $sql = "INSERT INTO budget_proposals (proposal_code, organization_id, fiscal_year_id, department, program, activity, total_requested, status, submission_date)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted', CURDATE())";
                db_execute($sql, [$code, $orgId, $fyId, $dept, $program, $activity, $requested]);
                record_audit('Submitted Budget Proposal', 'Budget Management', 'budget_proposals', $code, null, "Requested " . format_currency($requested));
                $_SESSION['flash_message'] = "Budget Proposal {$code} successfully submitted for review.";
            } catch (Exception $e) {
                $_SESSION['flash_error'] = 'Failed to submit proposal: ' . $e->getMessage();
            }
        }
        header('Location: budget-proposals.php');
        exit;
    }

    // ── U: WORKFLOW REVIEW & DECISION ──
    if ($action === 'review_proposal') {
        $proposalId = (int)$_POST['proposal_id'];
        $status = $_POST['new_status'];
        $approvedAmount = (float)($_POST['approved_amount'] ?? 0);
        $comments = trim($_POST['reviewer_comments'] ?? '');
        $reviewerId = current_user()['id'] ?? 5;

        try {
            $prev = db_row("SELECT * FROM budget_proposals WHERE id = ?", [$proposalId]);
            if (!$prev) {
                $_SESSION['flash_error'] = 'Proposal not found.';
            } else {
                $sql = "UPDATE budget_proposals 
                        SET status = ?, approved_amount = ?, reviewer_comments = ?, reviewed_by = ? 
                        WHERE id = ?";
                db_execute($sql, [$status, $approvedAmount, $comments, $reviewerId, $proposalId]);

                // If Approved or Allocated, create or update treasury allocation
                if ($status === 'approved' || $status === 'allocated') {
                    $existingAlloc = db_row("SELECT id FROM budget_allocations WHERE proposal_id = ?", [$proposalId]);
                    if (!$existingAlloc) {
                        $insertAlloc = "INSERT INTO budget_allocations (organization_id, fiscal_year_id, proposal_id, approved_budget, allocated_amount, committed_amount, spent_amount, allocation_date, status)
                                        VALUES (?, ?, ?, ?, ?, 0.00, 0.00, CURDATE(), 'active')";
                        db_execute($insertAlloc, [$prev['organization_id'], $prev['fiscal_year_id'], $proposalId, $approvedAmount, $approvedAmount]);
                    } else {
                        db_execute("UPDATE budget_allocations SET approved_budget = ?, allocated_amount = ? WHERE id = ?", [$approvedAmount, $approvedAmount, $existingAlloc['id']]);
                    }
                }

                record_audit("Updated Proposal Status to {$status}", 'Budget Management', 'budget_proposals', (string)$prev['proposal_code'], "Status: {$prev['status']}", "Status: {$status}, Approved: " . format_currency($approvedAmount));
                $_SESSION['flash_message'] = "Budget Proposal {$prev['proposal_code']} status set to '" . strtoupper(str_replace('_', ' ', $status)) . "'.";
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Failed to update proposal workflow: ' . $e->getMessage();
        }
        header('Location: budget-proposals.php');
        exit;
    }

    // ── U: EDIT PROPOSAL DETAILS ──
    if ($action === 'edit_proposal') {
        $proposalId = (int)$_POST['proposal_id'];
        $orgId = (int)$_POST['organization_id'];
        $fyId = (int)$_POST['fiscal_year_id'];
        $dept = trim($_POST['department'] ?? '');
        $program = trim($_POST['program'] ?? '');
        $activity = trim($_POST['activity'] ?? '');
        $requested = (float)($_POST['total_requested'] ?? 0);

        try {
            $prev = db_row("SELECT * FROM budget_proposals WHERE id = ?", [$proposalId]);
            if (!$prev) {
                $_SESSION['flash_error'] = 'Proposal not found.';
            } else {
                $sql = "UPDATE budget_proposals 
                        SET organization_id = ?, fiscal_year_id = ?, department = ?, program = ?, activity = ?, total_requested = ?
                        WHERE id = ?";
                db_execute($sql, [$orgId, $fyId, $dept, $program, $activity, $requested, $proposalId]);
                record_audit('Edited Budget Proposal', 'Budget Management', 'budget_proposals', (string)$prev['proposal_code'], json_encode($prev), "Updated envelope to " . format_currency($requested));
                $_SESSION['flash_message'] = "Proposal {$prev['proposal_code']} updated successfully.";
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Failed to edit proposal: ' . $e->getMessage();
        }
        header('Location: budget-proposals.php');
        exit;
    }

    // ── D: DELETE / WITHDRAW PROPOSAL ──
    if ($action === 'delete_proposal') {
        $proposalId = (int)$_POST['proposal_id'];
        try {
            $prev = db_row("SELECT * FROM budget_proposals WHERE id = ?", [$proposalId]);
            if (!$prev) {
                $_SESSION['flash_error'] = 'Proposal not found.';
            } else {
                // Check if already allocated and has commitments
                $alloc = db_row("SELECT * FROM budget_allocations WHERE proposal_id = ?", [$proposalId]);
                if ($alloc && ((float)$alloc['spent_amount'] > 0 || (float)$alloc['committed_amount'] > 0)) {
                    $_SESSION['flash_error'] = "Cannot delete proposal {$prev['proposal_code']}: Allocation has committed purchase orders or spent vouchers.";
                } else {
                    if ($alloc) {
                        db_execute("DELETE FROM budget_allocations WHERE id = ?", [$alloc['id']]);
                    }
                    db_execute("DELETE FROM budget_proposals WHERE id = ?", [$proposalId]);
                    record_audit('Deleted Budget Proposal', 'Budget Management', 'budget_proposals', (string)$prev['proposal_code'], json_encode($prev), "Deleted proposal {$prev['proposal_code']}");
                    $_SESSION['flash_message'] = "Budget Proposal {$prev['proposal_code']} deleted successfully.";
                }
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Failed to delete proposal: ' . $e->getMessage();
        }
        header('Location: budget-proposals.php');
        exit;
    }
}

// ── 4. FILTERING & DATA FETCHING ─────────────────────────────
$statusFilter = trim($_GET['status'] ?? 'all');
$orgFilter = (int)($_GET['org_id'] ?? 0);
$fyFilter = (int)($_GET['fiscal_year_id'] ?? 0);
$search = trim($_GET['q'] ?? '');

$where = " WHERE 1=1 ";
$params = [];

if ($statusFilter !== 'all') {
    $where .= " AND p.status = ? ";
    $params[] = $statusFilter;
}
if ($orgFilter > 0) {
    $where .= " AND p.organization_id = ? ";
    $params[] = $orgFilter;
}
if ($fyFilter > 0) {
    $where .= " AND p.fiscal_year_id = ? ";
    $params[] = $fyFilter;
}
if ($search !== '') {
    $where .= " AND (p.proposal_code LIKE ? OR p.department LIKE ? OR p.program LIKE ? OR p.activity LIKE ? OR o.name LIKE ?) ";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Fetch proposals
$proposals = db_select("
    SELECT p.*, o.name as org_name, o.code as org_code, f.name as fy_name, u.name as reviewer_name
    FROM budget_proposals p
    JOIN organizations o ON o.id = p.organization_id
    JOIN fiscal_years f ON f.id = p.fiscal_year_id
    LEFT JOIN users u ON u.id = p.reviewed_by
    {$where}
    ORDER BY p.id DESC
", $params);

// Overall Statistics
$totalCount = (int)db_row("SELECT count(*) as c FROM budget_proposals")['c'];
$submittedCount = (int)db_row("SELECT count(*) as c FROM budget_proposals WHERE status = 'submitted'")['c'];
$underReviewCount = (int)db_row("SELECT count(*) as c FROM budget_proposals WHERE status = 'under_review'")['c'];
$approvedCount = (int)db_row("SELECT count(*) as c FROM budget_proposals WHERE status IN ('approved', 'allocated')")['c'];
$revisionCount = (int)db_row("SELECT count(*) as c FROM budget_proposals WHERE status = 'revision_required'")['c'];
$rejectedCount = (int)db_row("SELECT count(*) as c FROM budget_proposals WHERE status = 'rejected'")['c'];

$totalRequested = (float)db_row("SELECT SUM(total_requested) as s FROM budget_proposals")['s'];
$totalApproved = (float)db_row("SELECT SUM(approved_amount) as s FROM budget_proposals WHERE status IN ('approved', 'allocated')")['s'];

$orgsList = db_select("SELECT id, name, code FROM organizations WHERE type != 'government' ORDER BY name ASC");
$fiscalYears = db_select("SELECT id, name, status FROM fiscal_years ORDER BY id DESC");

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
                <i data-lucide="file-text" style="width:20px;height:20px;"></i>
            </span>
            <h2 style="font-family:'Poppins',sans-serif; font-size:1.55rem; font-weight:800; color:var(--gov-green); margin:0;">
                Budget Proposals
            </h2>
        </div>
        <p style="color:#64748b; font-size:0.875rem; margin:4px 0 0 42px;">
            Line ministry expenditure proposals submission, review, and committee decisions.
        </p>
    </div>

    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <a href="budget-proposals.php?export=csv&status=<?php echo urlencode($statusFilter); ?>&org_id=<?php echo $orgFilter; ?>&q=<?php echo urlencode($search); ?>" class="btn-sfgmis-outline" title="Export Proposals to CSV">
            <i data-lucide="download" style="width:16px;height:16px;"></i> Export CSV
        </a>
        <button onclick="openAddProposalModal()" class="btn-sfgmis-green">
            <i data-lucide="plus-circle" style="width:17px;height:17px;"></i> Submit MDA Proposal
        </button>
    </div>
</div>

<!-- ── SUBPAGE TABS (Budget Module Navigation) ───────────────── -->
<div class="sfgmis-tabs">
    <a href="budget-cycles.php" class="sfgmis-tab-item">
        <i data-lucide="calendar" style="width:16px;height:16px;"></i>
        <span>Fiscal Years & Cycles</span>
    </a>
    <a href="budget-proposals.php" class="sfgmis-tab-item active">
        <i data-lucide="file-text" style="width:16px;height:16px;"></i>
        <span>Budget Proposals</span>
        <span class="tab-count"><?php echo $totalCount; ?></span>
    </a>
    <a href="budget-allocations.php" class="sfgmis-tab-item">
        <i data-lucide="pie-chart" style="width:16px;height:16px;"></i>
        <span>Budget Allocations</span>
    </a>
    <a href="budget-monitoring.php" class="sfgmis-tab-item">
        <i data-lucide="activity" style="width:16px;height:16px;"></i>
        <span>Monitoring & Variance</span>
    </a>
</div>

<!-- ── KPI METRICS CARDS ─────────────────────────────────────── -->
<div class="sfgmis-kpi-grid">
    <div class="sfgmis-kpi-card">
        <div class="sfgmis-kpi-icon green">
            <i data-lucide="file-text" style="width:24px;height:24px;"></i>
        </div>
        <div>
            <div class="sfgmis-kpi-val"><?php echo $totalCount; ?></div>
            <div class="sfgmis-kpi-lbl">Total Proposals</div>
            <div style="font-size:0.75rem; color:var(--gov-green); font-weight:600; margin-top:2px;">
                <?php echo ($submittedCount + $underReviewCount); ?> In Review Queue
            </div>
        </div>
    </div>

    <div class="sfgmis-kpi-card">
        <div class="sfgmis-kpi-icon blue">
            <i data-lucide="dollar-sign" style="width:24px;height:24px;"></i>
        </div>
        <div>
            <div class="sfgmis-kpi-val" style="font-size:1.25rem;"><?php echo format_currency($totalRequested); ?></div>
            <div class="sfgmis-kpi-lbl">Requested Envelope</div>
            <div style="font-size:0.75rem; color:#0369a1; font-weight:600; margin-top:2px;">
                MDA Submissions Total
            </div>
        </div>
    </div>

    <div class="sfgmis-kpi-card">
        <div class="sfgmis-kpi-icon gold">
            <i data-lucide="check-circle" style="width:24px;height:24px;"></i>
        </div>
        <div>
            <div class="sfgmis-kpi-val" style="font-size:1.25rem;"><?php echo format_currency($totalApproved); ?></div>
            <div class="sfgmis-kpi-lbl">Approved Envelope</div>
            <div style="font-size:0.75rem; color:#92400e; font-weight:600; margin-top:2px;">
                <?php echo $approvedCount; ?> Approved / Allocated
            </div>
        </div>
    </div>

    <div class="sfgmis-kpi-card">
        <div class="sfgmis-kpi-icon purple">
            <i data-lucide="clock" style="width:24px;height:24px;"></i>
        </div>
        <div>
            <div class="sfgmis-kpi-val"><?php echo ($submittedCount + $underReviewCount); ?></div>
            <div class="sfgmis-kpi-lbl">Pending Scrutiny</div>
            <div style="font-size:0.75rem; color:#7e22ce; font-weight:600; margin-top:2px;">
                Needs Committee Action
            </div>
        </div>
    </div>
</div>

<!-- ── SEARCH & FILTER TOOLBAR ────────────────────────────────── -->
<div class="sfgmis-filter-bar">
    <form method="GET" action="budget-proposals.php" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; flex:1;">
        <!-- Search Input -->
        <div class="sfgmis-search-box">
            <span class="search-ic"><i data-lucide="search" style="width:16px;height:16px;"></i></span>
            <input type="text" name="q" placeholder="Search by code, program, activity, or MDA..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <!-- Filter Status -->
        <select name="status" class="sfgmis-select" style="width:auto; min-width:150px;" onchange="this.form.submit()">
            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
            <option value="submitted" <?php echo $statusFilter === 'submitted' ? 'selected' : ''; ?>>Submitted (<?php echo $submittedCount; ?>)</option>
            <option value="under_review" <?php echo $statusFilter === 'under_review' ? 'selected' : ''; ?>>Under Review (<?php echo $underReviewCount; ?>)</option>
            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="allocated" <?php echo $statusFilter === 'allocated' ? 'selected' : ''; ?>>Allocated</option>
            <option value="revision_required" <?php echo $statusFilter === 'revision_required' ? 'selected' : ''; ?>>Revision Required (<?php echo $revisionCount; ?>)</option>
            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected (<?php echo $rejectedCount; ?>)</option>
        </select>

        <!-- Filter Organization -->
        <select name="org_id" class="sfgmis-select" style="width:auto; min-width:180px;" onchange="this.form.submit()">
            <option value="0">All Ministries & MDAs</option>
            <?php foreach ($orgsList as $o): ?>
                <option value="<?php echo $o['id']; ?>" <?php echo $orgFilter === (int)$o['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($o['name'] . ' (' . $o['code'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-sfgmis-outline btn-sfgmis-sm">
            <i data-lucide="filter" style="width:14px;height:14px;"></i> Apply Filter
        </button>

        <?php if ($search !== '' || $statusFilter !== 'all' || $orgFilter > 0): ?>
            <a href="budget-proposals.php" class="btn-sfgmis-outline btn-sfgmis-sm" style="color:var(--gov-red);" title="Reset filters">
                <i data-lucide="x" style="width:14px;height:14px;"></i> Clear
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- ── PROPOSALS DATA TABLE ───────────────────────────────────── -->
<div class="sfgmis-panel">
    <div class="sfgmis-panel-head">
        <h3>
            <i data-lucide="file-text" style="width:18px;height:18px;color:var(--sl-green-700);"></i>
            <span>Line Ministry Budget Submissions</span>
        </h3>
        <span style="font-size:0.8rem; color:#64748b; font-weight:600;">
            Showing <?php echo count($proposals); ?> of <?php echo $totalCount; ?> Proposals
        </span>
    </div>

    <div style="overflow-x:auto;">
        <table class="sfgmis-table">
            <thead>
                <tr>
                    <th style="width:120px;">Proposal Code</th>
                    <th>Submitting Ministry / MDA</th>
                    <th>Program & Activity</th>
                    <th style="text-align:right;">Requested</th>
                    <th style="text-align:right;">Approved</th>
                    <th style="text-align:center; width:120px;">Status</th>
                    <th style="text-align:center; width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($proposals)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:45px 20px;">
                        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px;">
                            <div style="width:50px; height:50px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8;">
                                <i data-lucide="inbox" style="width:26px;height:26px;"></i>
                            </div>
                            <h4 style="margin:0; font-size:1rem; color:#334155;">No budget proposals found</h4>
                            <p style="margin:0; color:#64748b; font-size:0.82rem;">Adjust your filter criteria or submit a new proposal.</p>
                            <button onclick="openAddProposalModal()" class="btn-sfgmis-green btn-sfgmis-sm" style="margin-top:6px;">
                                <i data-lucide="plus-circle" style="width:14px;height:14px;"></i> Submit Proposal
                            </button>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($proposals as $prop): ?>
                    <tr>
                        <!-- Proposal Code -->
                        <td>
                            <span style="font-family:monospace; font-weight:700; color:var(--sl-green-800); background:#dcfce7; padding:4px 8px; border-radius:5px; font-size:0.82rem; border:1px solid #bbf7d0; display:inline-block;">
                                <?php echo htmlspecialchars($prop['proposal_code']); ?>
                            </span>
                            <div style="font-size:0.7rem; color:#94a3b8; margin-top:4px;">
                                <?php echo date('M d, Y', strtotime($prop['submission_date'])); ?>
                            </div>
                        </td>

                        <!-- Submitting MDA -->
                        <td>
                            <a href="javascript:void(0)" onclick="viewProposalDetails(<?php echo $prop['id']; ?>)" style="color:#0f172a; text-decoration:none; font-weight:700; font-size:0.88rem;">
                                <?php echo htmlspecialchars($prop['org_name']); ?>
                            </a>
                            <div style="font-size:0.75rem; color:#64748b; margin-top:2px;">
                                <?php echo htmlspecialchars($prop['org_code']); ?> &bull; <?php echo htmlspecialchars($prop['department']); ?>
                            </div>
                        </td>

                        <!-- Program & Activity -->
                        <td>
                            <div style="font-weight:600; color:#1e293b; font-size:0.85rem;">
                                <?php echo htmlspecialchars($prop['program']); ?>
                            </div>
                            <div style="font-size:0.75rem; color:#64748b; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo htmlspecialchars($prop['activity']); ?>">
                                <?php echo htmlspecialchars($prop['activity']); ?>
                            </div>
                        </td>

                        <!-- Requested Envelope -->
                        <td style="text-align:right;">
                            <strong style="color:#0f172a; font-family:'Poppins',sans-serif; font-size:0.92rem;">
                                <?php echo format_currency((float)$prop['total_requested']); ?>
                            </strong>
                        </td>

                        <!-- Approved Amount -->
                        <td style="text-align:right;">
                            <?php if ($prop['approved_amount'] > 0): ?>
                                <strong style="color:var(--gov-green); font-family:'Poppins',sans-serif; font-size:0.92rem;">
                                    <?php echo format_currency((float)$prop['approved_amount']); ?>
                                </strong>
                            <?php else: ?>
                                <span style="color:#94a3b8; font-size:0.8rem;">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Status Badge -->
                        <td style="text-align:center;">
                            <span class="badge-status badge-<?php echo htmlspecialchars($prop['status']); ?>">
                                <?php echo htmlspecialchars(str_replace('_', ' ', $prop['status'])); ?>
                            </span>
                            <?php if (!empty($prop['reviewer_comments'])): ?>
                                <div style="font-size:0.68rem; color:#64748b; max-width:140px; margin:4px auto 0 auto; background:#f8fafc; padding:2px 6px; border-radius:4px; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;" title="<?php echo htmlspecialchars($prop['reviewer_comments']); ?>">
                                    "<?php echo htmlspecialchars($prop['reviewer_comments']); ?>"
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Action Buttons -->
                        <td style="text-align:center;">
                            <div class="sfgmis-actions">
                                <!-- View Dossier -->
                                <button type="button" onclick="viewProposalDetails(<?php echo $prop['id']; ?>)" class="sfgmis-btn-action action-view" title="View Proposal Dossier">
                                    <i data-lucide="eye" style="width:14px;height:14px;"></i>
                                </button>

                                <!-- Workflow Review & Decide -->
                                <button type="button" onclick="openReviewModal(<?php echo htmlspecialchars(json_encode($prop), ENT_QUOTES, 'UTF-8'); ?>)" class="sfgmis-btn-action action-toggle" title="Review & Decide Workflow">
                                    <i data-lucide="shield-check" style="width:14px;height:14px;"></i>
                                </button>

                                <!-- Edit Proposal -->
                                <button type="button" onclick="openEditProposalModal(<?php echo htmlspecialchars(json_encode($prop), ENT_QUOTES, 'UTF-8'); ?>)" class="sfgmis-btn-action action-edit" title="Edit Proposal">
                                    <i data-lucide="pencil" style="width:14px;height:14px;"></i>
                                </button>

                                <!-- Delete Proposal -->
                                <button type="button" onclick="openDeleteProposalModal(<?php echo $prop['id']; ?>, '<?php echo htmlspecialchars(addslashes($prop['proposal_code'])); ?>', '<?php echo htmlspecialchars(addslashes($prop['org_name'])); ?>')" class="sfgmis-btn-action action-delete" title="Delete Proposal">
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
<!-- ── MODAL 1: SUBMIT NEW PROPOSAL ──────────────────────────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="addProposalModal" class="sfgmis-modal-backdrop">
    <div class="sfgmis-modal">
        <div class="sfgmis-modal-head">
            <h3>
                <i data-lucide="plus-circle" style="width:20px;height:20px;color:var(--gov-green);"></i>
                <span>Submit MDA Budget Proposal</span>
            </h3>
            <button type="button" onclick="closeModal('addProposalModal')" class="sfgmis-modal-close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" action="budget-proposals.php">
            <input type="hidden" name="action" value="submit_proposal">

            <div class="sfgmis-modal-body">
                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Submitting Ministry / MDA <span class="req">*</span></label>
                        <select name="organization_id" required class="sfgmis-select">
                            <?php foreach ($orgsList as $o): ?>
                                <option value="<?php echo $o['id']; ?>">
                                    <?php echo htmlspecialchars($o['name'] . ' (' . $o['code'] . ')'); ?>
                                </option>
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

                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Department / Division <span class="req">*</span></label>
                        <input type="text" name="department" placeholder="e.g. Planning & Technical Services" required class="sfgmis-input">
                    </div>
                    <div>
                        <label class="sfgmis-label">Program Name <span class="req">*</span></label>
                        <input type="text" name="program" placeholder="e.g. Primary Health Support" required class="sfgmis-input">
                    </div>
                </div>

                <div class="sfgmis-form-group">
                    <label class="sfgmis-label">Activity Description <span class="req">*</span></label>
                    <textarea name="activity" rows="2" placeholder="e.g. Regional clinic supplies and vaccine cold-chain equipment" required class="sfgmis-textarea"></textarea>
                </div>

                <div class="sfgmis-form-group">
                    <label class="sfgmis-label">Total Requested Envelope ($ USD) <span class="req">*</span></label>
                    <input type="number" step="1000" min="0" name="total_requested" placeholder="e.g. 5000000" required class="sfgmis-input">
                </div>
            </div>

            <div class="sfgmis-modal-foot">
                <button type="button" onclick="closeModal('addProposalModal')" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">
                    <i data-lucide="check" style="width:16px;height:16px;"></i> Submit Proposal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- ── MODAL 2: WORKFLOW REVIEW & DECIDE ─────────────────────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="reviewModal" class="sfgmis-modal-backdrop">
    <div class="sfgmis-modal">
        <div class="sfgmis-modal-head">
            <h3>
                <i data-lucide="shield-check" style="width:20px;height:20px;color:var(--gov-green);"></i>
                <span>Review & Decide Proposal Workflow</span>
            </h3>
            <button type="button" onclick="closeModal('reviewModal')" class="sfgmis-modal-close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" action="budget-proposals.php">
            <input type="hidden" name="action" value="review_proposal">
            <input type="hidden" name="proposal_id" id="rev_proposal_id">

            <div class="sfgmis-modal-body">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                        <span style="font-size:0.75rem; color:#64748b; font-weight:600;">PROPOSAL CODE:</span>
                        <strong id="rev_code" style="color:var(--sl-green-800); font-family:monospace;"></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="font-size:0.75rem; color:#64748b; font-weight:600;">REQUESTED ENVELOPE:</span>
                        <strong id="rev_requested" style="color:#0f172a; font-family:'Poppins',sans-serif;"></strong>
                    </div>
                </div>

                <div class="sfgmis-form-group">
                    <label class="sfgmis-label">Workflow Decision <span class="req">*</span></label>
                    <select name="new_status" id="rev_status" required class="sfgmis-select">
                        <option value="under_review">Under Review (Technical Scrutiny)</option>
                        <option value="revision_required">Revision Required (Return with comments)</option>
                        <option value="approved">Approved by Budget Committee</option>
                        <option value="allocated">Allocated to Treasury Accounts</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>

                <div class="sfgmis-form-group">
                    <label class="sfgmis-label">Approved Envelope Amount ($ USD) <span class="req">*</span></label>
                    <input type="number" step="1000" min="0" name="approved_amount" id="rev_amount" required class="sfgmis-input">
                </div>

                <div class="sfgmis-form-group">
                    <label class="sfgmis-label">Reviewer Comments / Directives</label>
                    <textarea name="reviewer_comments" id="rev_comments" rows="3" placeholder="Provide budgetary notes, ceiling alignment justification, or revision instructions..." class="sfgmis-textarea"></textarea>
                </div>
            </div>

            <div class="sfgmis-modal-foot">
                <button type="button" onclick="closeModal('reviewModal')" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">
                    <i data-lucide="check" style="width:16px;height:16px;"></i> Record Decision
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- ── MODAL 3: EDIT PROPOSAL DETAILS ────────────────────────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="editProposalModal" class="sfgmis-modal-backdrop">
    <div class="sfgmis-modal">
        <div class="sfgmis-modal-head">
            <h3>
                <i data-lucide="pencil" style="width:20px;height:20px;color:var(--gov-gold);"></i>
                <span>Edit Budget Proposal</span>
            </h3>
            <button type="button" onclick="closeModal('editProposalModal')" class="sfgmis-modal-close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" action="budget-proposals.php">
            <input type="hidden" name="action" value="edit_proposal">
            <input type="hidden" name="proposal_id" id="edit_prop_id">

            <div class="sfgmis-modal-body">
                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Submitting Ministry / MDA <span class="req">*</span></label>
                        <select name="organization_id" id="edit_org_id" required class="sfgmis-select">
                            <?php foreach ($orgsList as $o): ?>
                                <option value="<?php echo $o['id']; ?>">
                                    <?php echo htmlspecialchars($o['name'] . ' (' . $o['code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="sfgmis-label">Fiscal Year <span class="req">*</span></label>
                        <select name="fiscal_year_id" id="edit_fy_id" required class="sfgmis-select">
                            <?php foreach ($fiscalYears as $fy): ?>
                                <option value="<?php echo $fy['id']; ?>"><?php echo htmlspecialchars($fy['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Department / Division <span class="req">*</span></label>
                        <input type="text" name="department" id="edit_dept" required class="sfgmis-input">
                    </div>
                    <div>
                        <label class="sfgmis-label">Program Name <span class="req">*</span></label>
                        <input type="text" name="program" id="edit_program" required class="sfgmis-input">
                    </div>
                </div>

                <div class="sfgmis-form-group">
                    <label class="sfgmis-label">Activity Description <span class="req">*</span></label>
                    <textarea name="activity" id="edit_activity" rows="2" required class="sfgmis-textarea"></textarea>
                </div>

                <div class="sfgmis-form-group">
                    <label class="sfgmis-label">Total Requested Envelope ($ USD) <span class="req">*</span></label>
                    <input type="number" step="1000" min="0" name="total_requested" id="edit_requested" required class="sfgmis-input">
                </div>
            </div>

            <div class="sfgmis-modal-foot">
                <button type="button" onclick="closeModal('editProposalModal')" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">
                    <i data-lucide="check" style="width:16px;height:16px;"></i> Update Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- ── MODAL 4: VIEW PROPOSAL DOSSIER ────────────────────────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="viewProposalModal" class="sfgmis-modal-backdrop">
    <div class="sfgmis-modal sfgmis-modal-lg">
        <div class="sfgmis-modal-head">
            <h3>
                <i data-lucide="info" style="width:20px;height:20px;color:var(--gov-green);"></i>
                <span id="view_prop_title">Budget Proposal Dossier</span>
            </h3>
            <button type="button" onclick="closeModal('viewProposalModal')" class="sfgmis-modal-close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>

        <div class="sfgmis-modal-body" id="view_prop_content">
            <div style="text-align:center; padding:30px; color:#64748b;">
                <p>Loading proposal dossier...</p>
            </div>
        </div>

        <div class="sfgmis-modal-foot">
            <button type="button" onclick="closeModal('viewProposalModal')" class="btn-sfgmis-outline">Close</button>
            <button type="button" id="view_prop_review_btn" class="btn-sfgmis-green">
                <i data-lucide="shield-check" style="width:16px;height:16px;"></i> Review Workflow
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- ── MODAL 5: DELETE PROPOSAL MODAL ────────────────────────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="deleteProposalModal" class="sfgmis-modal-backdrop">
    <div class="sfgmis-modal" style="max-width:480px;">
        <div class="sfgmis-modal-head" style="background:#fef2f2; border-bottom:1px solid #fecaca;">
            <h3 style="color:var(--gov-red);">
                <i data-lucide="alert-triangle" style="width:20px;height:20px;"></i>
                <span>Confirm Proposal Deletion</span>
            </h3>
            <button type="button" onclick="closeModal('deleteProposalModal')" class="sfgmis-modal-close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" action="budget-proposals.php">
            <input type="hidden" name="action" value="delete_proposal">
            <input type="hidden" name="proposal_id" id="delete_prop_id">

            <div class="sfgmis-modal-body">
                <p style="font-size:0.92rem; color:#1e293b; margin-top:0;">
                    Are you sure you want to permanently delete or withdraw this budget proposal?
                </p>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:14px;">
                    <span style="font-family:monospace; font-weight:700; color:var(--gov-green); background:#dcfce7; padding:2px 6px; border-radius:4px; font-size:0.75rem;" id="delete_prop_code">
                        PROP-CODE
                    </span>
                    <strong id="delete_prop_org" style="font-size:0.95rem; color:#0f172a; margin-left:8px;">Submitting MDA</strong>
                </div>
                <p style="font-size:0.8rem; color:#64748b; margin-bottom:0;">
                    Note: If funds have already been allocated or spent against this proposal, deletion will be blocked to safeguard audit trails.
                </p>
            </div>

            <div class="sfgmis-modal-foot">
                <button type="button" onclick="closeModal('deleteProposalModal')" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-danger">
                    <i data-lucide="trash-2" style="width:16px;height:16px;"></i> Confirm Delete
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── JAVASCRIPT FOR PROPOSALS ───────────────────────────────── -->
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

function openAddProposalModal() {
    openModal('addProposalModal');
}

function openReviewModal(prop) {
    if (typeof prop === 'string') {
        try { prop = JSON.parse(prop); } catch(e) {}
    }
    document.getElementById('rev_proposal_id').value = prop.id;
    document.getElementById('rev_code').textContent = prop.proposal_code;
    document.getElementById('rev_requested').textContent = '$' + Number(prop.total_requested).toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('rev_amount').value = prop.approved_amount > 0 ? prop.approved_amount : prop.total_requested;
    document.getElementById('rev_status').value = prop.status;
    document.getElementById('rev_comments').value = prop.reviewer_comments || '';
    openModal('reviewModal');
}

function openEditProposalModal(prop) {
    if (typeof prop === 'string') {
        try { prop = JSON.parse(prop); } catch(e) {}
    }
    document.getElementById('edit_prop_id').value = prop.id;
    document.getElementById('edit_org_id').value = prop.organization_id;
    document.getElementById('edit_fy_id').value = prop.fiscal_year_id;
    document.getElementById('edit_dept').value = prop.department;
    document.getElementById('edit_program').value = prop.program;
    document.getElementById('edit_activity').value = prop.activity;
    document.getElementById('edit_requested').value = prop.total_requested;
    openModal('editProposalModal');
}

function openDeleteProposalModal(id, code, orgName) {
    document.getElementById('delete_prop_id').value = id;
    document.getElementById('delete_prop_code').textContent = code;
    document.getElementById('delete_prop_org').textContent = orgName;
    openModal('deleteProposalModal');
}

function viewProposalDetails(id) {
    const content = document.getElementById('view_prop_content');
    content.innerHTML = '<div style="text-align:center; padding:35px; color:#64748b;"><p>Loading proposal dossier...</p></div>';
    openModal('viewProposalModal');

    fetch('budget-proposals.php?ajax_details=' + id)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                content.innerHTML = '<div style="color:var(--gov-red); padding:20px;">' + (data.error || 'Failed to load details') + '</div>';
                return;
            }

            const p = data.proposal;
            const al = data.allocation;

            document.getElementById('view_prop_title').textContent = p.proposal_code + ' — ' + p.org_name;
            document.getElementById('view_prop_review_btn').onclick = function() {
                closeModal('viewProposalModal');
                openReviewModal(p);
            };

            content.innerHTML = `
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px; margin-bottom:20px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
                    <div>
                        <div style="font-size:0.72rem; text-transform:uppercase; color:#64748b; font-weight:700;">Proposal Code</div>
                        <div style="font-size:1rem; font-weight:800; font-family:monospace; color:var(--gov-green);">${p.proposal_code}</div>
                    </div>
                    <div>
                        <div style="font-size:0.72rem; text-transform:uppercase; color:#64748b; font-weight:700;">Submitting MDA</div>
                        <div style="font-size:0.9rem; font-weight:700; color:#0f172a;">${p.org_name} (${p.org_code})</div>
                    </div>
                    <div>
                        <div style="font-size:0.72rem; text-transform:uppercase; color:#64748b; font-weight:700;">Fiscal Year</div>
                        <div style="font-size:0.9rem; font-weight:700; color:#1e293b;">${p.fy_name}</div>
                    </div>
                    <div>
                        <div style="font-size:0.72rem; text-transform:uppercase; color:#64748b; font-weight:700;">Current Status</div>
                        <div style="margin-top:2px;">
                            <span class="badge-status badge-${p.status}">${p.status.replace('_', ' ')}</span>
                        </div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
                    <div style="border:1px solid var(--gov-border); border-radius:8px; padding:16px;">
                        <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700; margin-bottom:6px;">Department / Division</div>
                        <div style="font-size:0.95rem; font-weight:700; color:#0f172a;">${p.department}</div>
                        <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700; margin:12px 0 4px 0;">Program Name</div>
                        <div style="font-size:0.9rem; font-weight:600; color:#1e293b;">${p.program}</div>
                    </div>

                    <div style="border:1px solid var(--gov-border); border-radius:8px; padding:16px;">
                        <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700; margin-bottom:6px;">Requested Envelope</div>
                        <div style="font-size:1.35rem; font-weight:800; font-family:'Poppins',sans-serif; color:#0f172a;">$${Number(p.total_requested).toLocaleString()}</div>
                        <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700; margin:10px 0 4px 0;">Approved Envelope</div>
                        <div style="font-size:1.35rem; font-weight:800; font-family:'Poppins',sans-serif; color:var(--gov-green);">${p.approved_amount > 0 ? '$' + Number(p.approved_amount).toLocaleString() : 'Pending Committee Action'}</div>
                    </div>
                </div>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:20px;">
                    <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700; margin-bottom:6px;">Activity Description</div>
                    <div style="font-size:0.88rem; color:#334155; line-height:1.5;">${p.activity}</div>
                </div>

                ${p.reviewer_comments ? `
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:14px; margin-bottom:16px;">
                    <div style="font-size:0.75rem; text-transform:uppercase; color:#166534; font-weight:700; margin-bottom:6px;">Reviewer Directives & Committee Notes</div>
                    <div style="font-size:0.88rem; color:#15803d; font-style:italic;">"${p.reviewer_comments}"</div>
                    <div style="font-size:0.72rem; color:#64748b; margin-top:6px;">Reviewed by: ${p.reviewer_name || 'Budget Officer'}</div>
                </div>` : ''}

                ${al ? `
                <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:14px;">
                    <div style="font-size:0.75rem; text-transform:uppercase; color:#92400e; font-weight:700; margin-bottom:6px;">Treasury Allocation Record</div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.85rem;">
                        <span>Allocated to Accounts: <strong>$${Number(al.allocated_amount).toLocaleString()}</strong></span>
                        <span>Committed: <strong>$${Number(al.committed_amount).toLocaleString()}</strong></span>
                        <span>Spent: <strong>$${Number(al.spent_amount).toLocaleString()}</strong></span>
                    </div>
                </div>` : ''}
            `;

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        })
        .catch(err => {
            content.innerHTML = '<div style="color:var(--gov-red); padding:20px;">Failed to retrieve dossier: ' + err.message + '</div>';
        });
}

// Close modals when clicking backdrop
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

<!-- ── BUDGET LINE ITEMS SECTION ───────────────────────────────── -->
<?php
$allLineItems = db_select("
    SELECT bi.*, p.proposal_code, p.department, o.name as org_name
    FROM budget_items bi
    JOIN budget_proposals p ON p.id = bi.proposal_id
    JOIN organizations o ON o.id = p.organization_id
    ORDER BY bi.proposal_id ASC, bi.id ASC
");
$liCount = count($allLineItems);
$liTotalReq = array_sum(array_column($allLineItems, 'requested_amount'));
$liTotalApp = array_sum(array_column($allLineItems, 'approved_amount'));
?>
<div class="sfgmis-panel" style="margin-top:28px;">
    <div class="sfgmis-panel-head">
        <h3><i data-lucide="list" style="width:18px;height:18px;color:var(--sl-green-700);"></i>
            Budget Line Items Registry
        </h3>
        <span style="font-size:0.8rem;color:#64748b;font-weight:600;"><?php echo $liCount; ?> line items across all proposals</span>
    </div>

    <!-- Line Items KPIs -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;padding:16px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
        <div>
            <div style="font-size:0.7rem;text-transform:uppercase;color:#64748b;font-weight:700;">Total Line Items</div>
            <div style="font-size:1.4rem;font-weight:800;color:#0f172a;margin-top:2px;"><?php echo $liCount; ?></div>
        </div>
        <div>
            <div style="font-size:0.7rem;text-transform:uppercase;color:#64748b;font-weight:700;">Total Requested</div>
            <div style="font-size:1.4rem;font-weight:800;color:#d97706;margin-top:2px;"><?php echo format_currency($liTotalReq); ?></div>
        </div>
        <div>
            <div style="font-size:0.7rem;text-transform:uppercase;color:#16a34a;font-weight:700;">Total Approved</div>
            <div style="font-size:1.4rem;font-weight:800;color:#16a34a;margin-top:2px;"><?php echo format_currency($liTotalApp); ?></div>
        </div>
    </div>

    <?php if (empty($allLineItems)): ?>
    <div style="padding:32px;text-align:center;color:#94a3b8;">
        <i data-lucide="inbox" style="width:32px;height:32px;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;"></i>
        No budget line items recorded yet. Line items are added when proposals are submitted with detailed expenditure breakdowns.
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="sfgmis-table">
            <thead>
                <tr>
                    <th>Proposal</th>
                    <th>Organization</th>
                    <th>Category</th>
                    <th>Line Item Code</th>
                    <th>Description</th>
                    <th style="text-align:right;">Requested</th>
                    <th style="text-align:right;">Approved</th>
                    <th style="text-align:right;">Variance</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $catColors = [
                    'personnel'           => ['bg'=>'#eff6ff','color'=>'#2563eb'],
                    'operating_expenses'  => ['bg'=>'#fef3c7','color'=>'#d97706'],
                    'capital_expenditure' => ['bg'=>'#f0fdf4','color'=>'#16a34a'],
                    'transfers'           => ['bg'=>'#f5f3ff','color'=>'#7c3aed'],
                ];
                foreach ($allLineItems as $li):
                    $cat = $li['category'] ?? 'operating_expenses';
                    $cb = $catColors[$cat] ?? ['bg'=>'#f1f5f9','color'=>'#64748b'];
                    $req = (float)$li['requested_amount'];
                    $app = (float)$li['approved_amount'];
                    $variance = $app - $req;
                ?>
                <tr>
                    <td>
                        <strong style="font-family:monospace;color:var(--sl-green-800);font-size:0.82rem;"><?php echo htmlspecialchars($li['proposal_code']); ?></strong>
                        <div style="font-size:0.7rem;color:#64748b;"><?php echo htmlspecialchars($li['department']); ?></div>
                    </td>
                    <td style="font-size:0.82rem;"><?php echo htmlspecialchars($li['org_name']); ?></td>
                    <td>
                        <span style="font-size:0.72rem;font-weight:700;padding:2px 8px;border-radius:10px;background:<?php echo $cb['bg']; ?>;color:<?php echo $cb['color']; ?>;white-space:nowrap;">
                            <?php echo ucwords(str_replace('_', ' ', $cat)); ?>
                        </span>
                    </td>
                    <td><code style="font-size:0.78rem;background:#f1f5f9;padding:2px 6px;border-radius:4px;"><?php echo htmlspecialchars($li['line_item_code']); ?></code></td>
                    <td style="font-size:0.82rem;max-width:220px;"><?php echo htmlspecialchars($li['description']); ?></td>
                    <td style="text-align:right;font-weight:600;"><?php echo format_currency($req); ?></td>
                    <td style="text-align:right;font-weight:600;color:<?php echo $app > 0 ? '#16a34a' : '#94a3b8'; ?>;"><?php echo $app > 0 ? format_currency($app) : '—'; ?></td>
                    <td style="text-align:right;font-weight:700;color:<?php echo $variance >= 0 ? '#16a34a' : '#dc2626'; ?>;">
                        <?php if ($app > 0): ?>
                            <?php echo ($variance >= 0 ? '+' : '') . format_currency($variance); ?>
                        <?php else: ?>
                            <span style="color:#94a3b8;">Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
