<?php
$pageTitle = 'Budget Cycles & Fiscal Years';
require_once __DIR__ . '/config/db.php';

// Flash messages
$message = $_SESSION['flash_message'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

// ── 1. CSV EXPORT ────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filterStatus = trim($_GET['status'] ?? 'all');
    $filterFy = (int)($_GET['fiscal_year_id'] ?? 0);
    $search = trim($_GET['q'] ?? '');

    $where = " WHERE 1=1 ";
    $params = [];

    if ($filterStatus !== 'all') {
        $where .= " AND c.status = ? ";
        $params[] = $filterStatus;
    }
    if ($filterFy > 0) {
        $where .= " AND c.fiscal_year_id = ? ";
        $params[] = $filterFy;
    }
    if ($search !== '') {
        $where .= " AND (c.cycle_code LIKE ? OR c.name LIKE ? OR f.name LIKE ?) ";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $exportData = db_select("
        SELECT c.cycle_code, c.name, f.name as fy_name, c.start_date, c.end_date, c.ceiling_amount, c.status
        FROM budget_cycles c
        JOIN fiscal_years f ON f.id = c.fiscal_year_id
        {$where}
        ORDER BY c.id DESC
    ", $params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=SFGMIS_Budget_Cycles_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Cycle Code', 'Cycle Title', 'Fiscal Year', 'Start Date', 'Submission Deadline', 'Ceiling Envelope ($)', 'Status']);
    foreach ($exportData as $row) {
        fputcsv($out, [
            $row['cycle_code'],
            $row['name'],
            $row['fy_name'],
            $row['start_date'],
            $row['end_date'],
            number_format((float)$row['ceiling_amount'], 2),
            strtoupper($row['status'])
        ]);
    }
    fclose($out);
    exit;
}

// ── 2. FORM CRUD OPERATIONS ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ── CREATE BUDGET CYCLE ──
    if ($action === 'add_cycle') {
        $fiscalYearId = (int)$_POST['fiscal_year_id'];
        $cycleCode = strtoupper(trim($_POST['cycle_code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $startDate = $_POST['start_date'] ?? date('Y-01-01');
        $endDate = $_POST['end_date'] ?? date('Y-12-31');
        $ceiling = (float)($_POST['ceiling_amount'] ?? 0);

        if ($cycleCode === '' || $name === '') {
            $_SESSION['flash_error'] = 'Cycle code and cycle title are required.';
        } else {
            $existing = db_row("SELECT id FROM budget_cycles WHERE cycle_code = ?", [$cycleCode]);
            if ($existing) {
                $_SESSION['flash_error'] = "A budget cycle with code '{$cycleCode}' already exists.";
            } else {
                try {
                    $sql = "INSERT INTO budget_cycles (fiscal_year_id, cycle_code, name, start_date, end_date, ceiling_amount, status)
                            VALUES (?, ?, ?, ?, ?, ?, 'active')";
                    db_execute($sql, [$fiscalYearId, $cycleCode, $name, $startDate, $endDate, $ceiling]);
                    record_audit('Created Budget Cycle', 'Budget Management', 'budget_cycles', $cycleCode, null, "Created cycle {$name} with ceiling " . format_currency($ceiling));
                    $_SESSION['flash_message'] = "Budget Cycle '{$name}' [{$cycleCode}] created successfully.";
                } catch (Exception $e) {
                    $_SESSION['flash_error'] = 'Failed to create budget cycle: ' . $e->getMessage();
                }
            }
        }
        header('Location: budget-cycles.php');
        exit;
    }

    // ── EDIT BUDGET CYCLE ──
    if ($action === 'edit_cycle') {
        $cycleId = (int)$_POST['cycle_id'];
        $fiscalYearId = (int)$_POST['fiscal_year_id'];
        $cycleCode = strtoupper(trim($_POST['cycle_code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $ceiling = (float)$_POST['ceiling_amount'];
        $status = $_POST['status'] ?? 'active';

        try {
            $prev = db_row("SELECT * FROM budget_cycles WHERE id = ?", [$cycleId]);
            if (!$prev) {
                $_SESSION['flash_error'] = 'Budget cycle not found.';
            } else {
                db_execute("UPDATE budget_cycles SET fiscal_year_id = ?, cycle_code = ?, name = ?, start_date = ?, end_date = ?, ceiling_amount = ?, status = ? WHERE id = ?",
                    [$fiscalYearId, $cycleCode, $name, $startDate, $endDate, $ceiling, $status, $cycleId]);
                record_audit('Updated Budget Cycle', 'Budget Management', 'budget_cycles', (string)$prev['cycle_code'], json_encode($prev), "Updated {$name} [{$cycleCode}]");
                $_SESSION['flash_message'] = "Budget Cycle '{$name}' updated successfully.";
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Failed to update budget cycle: ' . $e->getMessage();
        }
        header('Location: budget-cycles.php');
        exit;
    }

    // ── TOGGLE CYCLE STATUS ──
    if ($action === 'toggle_cycle_status') {
        $cycleId = (int)$_POST['cycle_id'];
        $cycle = db_row("SELECT * FROM budget_cycles WHERE id = ?", [$cycleId]);
        if ($cycle) {
            $newStatus = ($cycle['status'] === 'active') ? 'inactive' : 'active';
            db_execute("UPDATE budget_cycles SET status = ? WHERE id = ?", [$newStatus, $cycleId]);
            record_audit('Toggled Budget Cycle Status', 'Budget Management', 'budget_cycles', $cycle['cycle_code'], $cycle['status'], "Status changed to {$newStatus}");
            $_SESSION['flash_message'] = "Budget Cycle '{$cycle['name']}' status set to " . strtoupper($newStatus) . ".";
        }
        header('Location: budget-cycles.php');
        exit;
    }

    // ── DELETE BUDGET CYCLE ──
    if ($action === 'delete_cycle') {
        $cycleId = (int)$_POST['cycle_id'];
        try {
            $prev = db_row("SELECT * FROM budget_cycles WHERE id = ?", [$cycleId]);
            if ($prev) {
                db_execute("DELETE FROM budget_cycles WHERE id = ?", [$cycleId]);
                record_audit('Deleted Budget Cycle', 'Budget Management', 'budget_cycles', (string)$prev['cycle_code'], json_encode($prev), "Deleted cycle {$prev['name']}");
                $_SESSION['flash_message'] = "Budget Cycle '{$prev['name']}' deleted.";
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Failed to delete cycle: ' . $e->getMessage();
        }
        header('Location: budget-cycles.php');
        exit;
    }

    // ── CREATE FISCAL YEAR ──
    if ($action === 'add_fiscal_year') {
        $name = trim($_POST['name'] ?? '');
        $startDate = $_POST['start_date'] ?? date('Y-01-01');
        $endDate = $_POST['end_date'] ?? date('Y-12-31');
        $totalBudget = (float)($_POST['total_budget'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if ($name === '') {
            $_SESSION['flash_error'] = 'Fiscal Year name is required.';
        } else {
            try {
                db_execute("INSERT INTO fiscal_years (name, start_date, end_date, total_budget, status) VALUES (?, ?, ?, ?, ?)",
                    [$name, $startDate, $endDate, $totalBudget, $status]);
                record_audit('Created Fiscal Year', 'Budget Management', 'fiscal_years', $name, null, "Created {$name} with budget " . format_currency($totalBudget));
                $_SESSION['flash_message'] = "Fiscal Year '{$name}' configured successfully.";
            } catch (Exception $e) {
                $_SESSION['flash_error'] = 'Failed to create fiscal year: ' . $e->getMessage();
            }
        }
        header('Location: budget-cycles.php');
        exit;
    }

    // ── EDIT FISCAL YEAR ──
    if ($action === 'edit_fiscal_year') {
        $fyId = (int)$_POST['fy_id'];
        $name = trim($_POST['name'] ?? '');
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $totalBudget = (float)$_POST['total_budget'];
        $status = $_POST['status'] ?? 'active';

        try {
            $prev = db_row("SELECT * FROM fiscal_years WHERE id = ?", [$fyId]);
            if ($prev) {
                db_execute("UPDATE fiscal_years SET name = ?, start_date = ?, end_date = ?, total_budget = ?, status = ? WHERE id = ?",
                    [$name, $startDate, $endDate, $totalBudget, $status, $fyId]);
                record_audit('Updated Fiscal Year', 'Budget Management', 'fiscal_years', $name, json_encode($prev), "Updated {$name}");
                $_SESSION['flash_message'] = "Fiscal Year '{$name}' updated successfully.";
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Failed to update fiscal year: ' . $e->getMessage();
        }
        header('Location: budget-cycles.php');
        exit;
    }
}

// ── 3. FILTERING & DATA FETCHING ─────────────────────────────
$statusFilter = trim($_GET['status'] ?? 'all');
$fyFilter = (int)($_GET['fiscal_year_id'] ?? 0);
$search = trim($_GET['q'] ?? '');

$where = " WHERE 1=1 ";
$params = [];

if ($statusFilter !== 'all') {
    $where .= " AND c.status = ? ";
    $params[] = $statusFilter;
}
if ($fyFilter > 0) {
    $where .= " AND c.fiscal_year_id = ? ";
    $params[] = $fyFilter;
}
if ($search !== '') {
    $where .= " AND (c.cycle_code LIKE ? OR c.name LIKE ? OR f.name LIKE ?) ";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$fiscalYears = db_select("
    SELECT f.*, 
           (SELECT COUNT(*) FROM budget_cycles c WHERE c.fiscal_year_id = f.id) as cycle_count,
           (SELECT COUNT(*) FROM budget_allocations a WHERE a.fiscal_year_id = f.id) as alloc_count
    FROM fiscal_years f 
    ORDER BY f.id DESC
");

$cycles = db_select("
    SELECT c.*, f.name as fiscal_year_name 
    FROM budget_cycles c 
    JOIN fiscal_years f ON f.id = c.fiscal_year_id 
    {$where}
    ORDER BY c.id DESC
", $params);

// Overall Statistics
$totalCycles = (int)db_row("SELECT count(*) as c FROM budget_cycles")['c'];
$activeCycles = (int)db_row("SELECT count(*) as c FROM budget_cycles WHERE status = 'active'")['c'];
$totalCeiling = (float)db_row("SELECT SUM(ceiling_amount) as s FROM budget_cycles")['s'];
$nationalBudget = (float)db_row("SELECT SUM(total_budget) as s FROM fiscal_years WHERE status = 'active'")['s'];

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
                <i data-lucide="calendar" style="width:20px;height:20px;"></i>
            </span>
            <h2 style="font-family:'Poppins',sans-serif; font-size:1.55rem; font-weight:800; color:var(--gov-green); margin:0;">
                Budget Cycles & Fiscal Years
            </h2>
        </div>
        <p style="color:#64748b; font-size:0.875rem; margin:4px 0 0 42px;">
            Medium Term Expenditure Framework (MTEF) cycles and statutory national budget ceilings.
        </p>
    </div>

    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <a href="budget-cycles.php?export=csv&status=<?php echo urlencode($statusFilter); ?>&fiscal_year_id=<?php echo $fyFilter; ?>&q=<?php echo urlencode($search); ?>" class="btn-sfgmis-outline" title="Export Cycles to CSV">
            <i data-lucide="download" style="width:16px;height:16px;"></i> Export CSV
        </a>
        <button onclick="openAddFiscalYearModal()" class="btn-sfgmis-outline">
            <i data-lucide="calendar-plus" style="width:16px;height:16px;"></i> New Fiscal Year
        </button>
        <button onclick="openAddCycleModal()" class="btn-sfgmis-green">
            <i data-lucide="plus-circle" style="width:17px;height:17px;"></i> Configure Budget Cycle
        </button>
    </div>
</div>

<!-- ── SUBPAGE TABS ───────────────────────────────────────────── -->
<div class="sfgmis-tabs">
    <a href="budget-cycles.php" class="sfgmis-tab-item active">
        <i data-lucide="calendar" style="width:16px;height:16px;"></i>
        <span>Fiscal Years & Cycles</span>
        <span class="tab-count"><?php echo $totalCycles; ?></span>
    </a>
    <a href="budget-proposals.php" class="sfgmis-tab-item">
        <i data-lucide="file-text" style="width:16px;height:16px;"></i>
        <span>Budget Proposals</span>
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
            <i data-lucide="calendar" style="width:24px;height:24px;"></i>
        </div>
        <div>
            <div class="sfgmis-kpi-val"><?php echo count($fiscalYears); ?></div>
            <div class="sfgmis-kpi-lbl">Statutory Fiscal Years</div>
            <div style="font-size:0.75rem; color:var(--gov-green); font-weight:600; margin-top:2px;">
                Active: <?php echo htmlspecialchars($fiscalYears[0]['name'] ?? 'None'); ?>
            </div>
        </div>
    </div>

    <div class="sfgmis-kpi-card">
        <div class="sfgmis-kpi-icon gold">
            <i data-lucide="dollar-sign" style="width:24px;height:24px;"></i>
        </div>
        <div>
            <div class="sfgmis-kpi-val" style="font-size:1.25rem;"><?php echo format_currency($nationalBudget); ?></div>
            <div class="sfgmis-kpi-lbl">Active National Budget</div>
            <div style="font-size:0.75rem; color:#92400e; font-weight:600; margin-top:2px;">
                Parliamentary Appropriation
            </div>
        </div>
    </div>

    <div class="sfgmis-kpi-card">
        <div class="sfgmis-kpi-icon blue">
            <i data-lucide="clock" style="width:24px;height:24px;"></i>
        </div>
        <div>
            <div class="sfgmis-kpi-val"><?php echo $totalCycles; ?></div>
            <div class="sfgmis-kpi-lbl">Configured Cycles</div>
            <div style="font-size:0.75rem; color:#0369a1; font-weight:600; margin-top:2px;">
                <?php echo $activeCycles; ?> Operational Cycles
            </div>
        </div>
    </div>

    <div class="sfgmis-kpi-card">
        <div class="sfgmis-kpi-icon purple">
            <i data-lucide="layers" style="width:24px;height:24px;"></i>
        </div>
        <div>
            <div class="sfgmis-kpi-val" style="font-size:1.25rem;"><?php echo format_currency($totalCeiling); ?></div>
            <div class="sfgmis-kpi-lbl">Total MTEF Ceilings</div>
            <div style="font-size:0.75rem; color:#7e22ce; font-weight:600; margin-top:2px;">
                Cumulative Multi-Year Envelopes
            </div>
        </div>
    </div>
</div>

<!-- ── FISCAL YEARS CARDS ────────────────────────────────────── -->
<div style="margin-bottom:28px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
        <h3 style="font-size:1.15rem; font-weight:700; color:var(--gov-green); margin:0; display:flex; align-items:center; gap:8px;">
            <i data-lucide="calendar-check" style="width:18px;height:18px;"></i> National Fiscal Years Framework
        </h3>
        <button onclick="openAddFiscalYearModal()" class="btn-sfgmis-outline btn-sfgmis-sm">
            <i data-lucide="plus" style="width:14px;height:14px;"></i> Add Fiscal Year
        </button>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:18px;">
        <?php foreach ($fiscalYears as $fy): ?>
        <div style="background:#fff; border:1px solid var(--gov-border); border-top:4px solid var(--gov-green); border-radius:10px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <span style="font-weight:800; font-size:1.15rem; font-family:'Poppins',sans-serif; color:#0f172a;"><?php echo htmlspecialchars($fy['name']); ?></span>
                <span class="badge-status badge-<?php echo htmlspecialchars($fy['status']); ?>">
                    <?php echo htmlspecialchars($fy['status']); ?>
                </span>
            </div>
            <div style="font-size:0.8rem; color:#64748b; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
                <i data-lucide="calendar" style="width:13px;height:13px;"></i>
                <span><?php echo date('M d, Y', strtotime($fy['start_date'])); ?> &mdash; <?php echo date('M d, Y', strtotime($fy['end_date'])); ?></span>
            </div>
            <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <span style="font-size:0.78rem; font-weight:600; color:#475569;">Approved National Envelope:</span>
                <strong style="color:var(--gov-green); font-size:1.05rem; font-family:'Poppins',sans-serif;"><?php echo format_currency((float)$fy['total_budget']); ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:0.75rem; color:#64748b;"><?php echo $fy['cycle_count']; ?> cycles &bull; <?php echo $fy['alloc_count']; ?> allocations</span>
                <button onclick="openEditFiscalYearModal(<?php echo htmlspecialchars(json_encode($fy), ENT_QUOTES, 'UTF-8'); ?>)" class="sfgmis-btn-action action-edit" title="Edit Fiscal Year">
                    <i data-lucide="pencil" style="width:13px;height:13px;"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── SEARCH & FILTER TOOLBAR ────────────────────────────────── -->
<div class="sfgmis-filter-bar">
    <form method="GET" action="budget-cycles.php" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; flex:1;">
        <div class="sfgmis-search-box">
            <span class="search-ic"><i data-lucide="search" style="width:16px;height:16px;"></i></span>
            <input type="text" name="q" placeholder="Search by cycle code, title, or year..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <select name="status" class="sfgmis-select" style="width:auto; min-width:140px;" onchange="this.form.submit()">
            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active Only</option>
            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
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

        <?php if ($search !== '' || $statusFilter !== 'all' || $fyFilter > 0): ?>
            <a href="budget-cycles.php" class="btn-sfgmis-outline btn-sfgmis-sm" style="color:var(--gov-red);" title="Reset filters">
                <i data-lucide="x" style="width:14px;height:14px;"></i> Clear
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- ── BUDGET CYCLES TABLE ──────────────────────────────────────── -->
<div class="sfgmis-panel">
    <div class="sfgmis-panel-head">
        <h3>
            <i data-lucide="calendar" style="width:18px;height:18px;color:var(--sl-green-700);"></i>
            <span>Scheduled Budget Cycles</span>
        </h3>
        <span style="font-size:0.8rem; color:#64748b; font-weight:600;">
            Showing <?php echo count($cycles); ?> of <?php echo $totalCycles; ?> Cycles
        </span>
    </div>

    <div style="overflow-x:auto;">
        <table class="sfgmis-table">
            <thead>
                <tr>
                    <th style="width:120px;">Cycle Code</th>
                    <th>Cycle Title</th>
                    <th>Fiscal Year</th>
                    <th>Start Date</th>
                    <th>Submission Deadline</th>
                    <th style="text-align:right;">Ceiling Envelope</th>
                    <th style="text-align:center; width:100px;">Status</th>
                    <th style="text-align:center; width:130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cycles)): ?>
                <tr>
                    <td colspan="8" style="text-align:center; padding:45px 20px;">
                        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px;">
                            <div style="width:50px; height:50px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8;">
                                <i data-lucide="calendar" style="width:26px;height:26px;"></i>
                            </div>
                            <h4 style="margin:0; font-size:1rem; color:#334155;">No budget cycles found</h4>
                            <p style="margin:0; color:#64748b; font-size:0.82rem;">Create a new cycle to commence planning workflows.</p>
                            <button onclick="openAddCycleModal()" class="btn-sfgmis-green btn-sfgmis-sm" style="margin-top:6px;">
                                <i data-lucide="plus-circle" style="width:14px;height:14px;"></i> Configure Cycle
                            </button>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($cycles as $cyc): ?>
                    <tr>
                        <td>
                            <span style="font-family:monospace; font-weight:700; color:var(--sl-green-800); background:#dcfce7; padding:4px 8px; border-radius:5px; font-size:0.82rem; border:1px solid #bbf7d0; display:inline-block;">
                                <?php echo htmlspecialchars($cyc['cycle_code']); ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color:#0f172a; font-size:0.88rem;"><?php echo htmlspecialchars($cyc['name']); ?></strong>
                        </td>
                        <td>
                            <span style="font-weight:600; color:#334155; font-size:0.85rem;"><?php echo htmlspecialchars($cyc['fiscal_year_name']); ?></span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($cyc['start_date'])); ?></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:4px; color:#475569; font-size:0.85rem;">
                                <i data-lucide="clock" style="width:13px;height:13px;color:#94a3b8;"></i>
                                <span><?php echo date('M d, Y', strtotime($cyc['end_date'])); ?></span>
                            </div>
                        </td>
                        <td style="text-align:right;">
                            <strong style="color:var(--gov-green); font-family:'Poppins',sans-serif; font-size:0.92rem;">
                                <?php echo format_currency((float)$cyc['ceiling_amount']); ?>
                            </strong>
                        </td>
                        <td style="text-align:center;">
                            <span class="badge-status badge-<?php echo htmlspecialchars($cyc['status']); ?>">
                                <?php echo htmlspecialchars($cyc['status']); ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <div class="sfgmis-actions">
                                <!-- Edit -->
                                <button type="button" onclick="openEditCycleModal(<?php echo htmlspecialchars(json_encode($cyc), ENT_QUOTES, 'UTF-8'); ?>)" class="sfgmis-btn-action action-edit" title="Edit Cycle">
                                    <i data-lucide="pencil" style="width:14px;height:14px;"></i>
                                </button>

                                <!-- Toggle Status -->
                                <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Toggle status of <?php echo htmlspecialchars(addslashes($cyc['name'])); ?>?');">
                                    <input type="hidden" name="action" value="toggle_cycle_status">
                                    <input type="hidden" name="cycle_id" value="<?php echo $cyc['id']; ?>">
                                    <button type="submit" class="sfgmis-btn-action action-toggle" title="Toggle Status">
                                        <i data-lucide="<?php echo $cyc['status'] === 'active' ? 'toggle-right' : 'toggle-left'; ?>" style="width:14px;height:14px;"></i>
                                    </button>
                                </form>

                                <!-- Delete -->
                                <button type="button" onclick="openDeleteCycleModal(<?php echo $cyc['id']; ?>, '<?php echo htmlspecialchars(addslashes($cyc['name'])); ?>', '<?php echo htmlspecialchars($cyc['cycle_code']); ?>')" class="sfgmis-btn-action action-delete" title="Delete Cycle">
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
<!-- ── MODAL 1: CONFIGURE BUDGET CYCLE ───────────────────────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="addCycleModal" class="sfgmis-modal-backdrop">
    <div class="sfgmis-modal">
        <div class="sfgmis-modal-head">
            <h3>
                <i data-lucide="plus-circle" style="width:20px;height:20px;color:var(--gov-green);"></i>
                <span>Configure Budget Cycle</span>
            </h3>
            <button type="button" onclick="closeModal('addCycleModal')" class="sfgmis-modal-close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" action="budget-cycles.php">
            <input type="hidden" name="action" value="add_cycle">

            <div class="sfgmis-modal-body">
                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Fiscal Year <span class="req">*</span></label>
                        <select name="fiscal_year_id" required class="sfgmis-select">
                            <?php foreach ($fiscalYears as $fy): ?>
                                <option value="<?php echo $fy['id']; ?>"><?php echo htmlspecialchars($fy['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="sfgmis-label">Cycle Code <span class="req">*</span></label>
                        <input type="text" name="cycle_code" placeholder="e.g. CYC-2026-Q1" required class="sfgmis-input" style="text-transform:uppercase;">
                    </div>
                </div>

                <div class="sfgmis-form-group">
                    <label class="sfgmis-label">Cycle Title / Designation <span class="req">*</span></label>
                    <input type="text" name="name" placeholder="e.g. 2026 Annual Budget Formulation Round" required class="sfgmis-input">
                </div>

                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Cycle Start Date <span class="req">*</span></label>
                        <input type="date" name="start_date" value="<?php echo date('Y-01-01'); ?>" required class="sfgmis-input">
                    </div>
                    <div>
                        <label class="sfgmis-label">Submission Deadline <span class="req">*</span></label>
                        <input type="date" name="end_date" value="<?php echo date('Y-12-31'); ?>" required class="sfgmis-input">
                    </div>
                </div>

                <div class="sfgmis-form-group">
                    <label class="sfgmis-label">Statutory Ceiling Envelope ($ USD) <span class="req">*</span></label>
                    <input type="number" step="1000" min="0" name="ceiling_amount" placeholder="e.g. 350000000" value="350000000" required class="sfgmis-input">
                </div>
            </div>

            <div class="sfgmis-modal-foot">
                <button type="button" onclick="closeModal('addCycleModal')" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">
                    <i data-lucide="check" style="width:16px;height:16px;"></i> Save Cycle
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- ── MODAL 2: EDIT BUDGET CYCLE ────────────────────────────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="editCycleModal" class="sfgmis-modal-backdrop">
    <div class="sfgmis-modal">
        <div class="sfgmis-modal-head">
            <h3>
                <i data-lucide="pencil" style="width:20px;height:20px;color:var(--gov-gold);"></i>
                <span>Edit Budget Cycle</span>
            </h3>
            <button type="button" onclick="closeModal('editCycleModal')" class="sfgmis-modal-close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" action="budget-cycles.php">
            <input type="hidden" name="action" value="edit_cycle">
            <input type="hidden" name="cycle_id" id="edit_cycle_id">

            <div class="sfgmis-modal-body">
                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Fiscal Year <span class="req">*</span></label>
                        <select name="fiscal_year_id" id="edit_fiscal_year_id" required class="sfgmis-select">
                            <?php foreach ($fiscalYears as $fy): ?>
                                <option value="<?php echo $fy['id']; ?>"><?php echo htmlspecialchars($fy['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="sfgmis-label">Cycle Code <span class="req">*</span></label>
                        <input type="text" name="cycle_code" id="edit_cycle_code" required class="sfgmis-input" style="text-transform:uppercase;">
                    </div>
                </div>

                <div class="sfgmis-form-group">
                    <label class="sfgmis-label">Cycle Title <span class="req">*</span></label>
                    <input type="text" name="name" id="edit_name" required class="sfgmis-input">
                </div>

                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Start Date <span class="req">*</span></label>
                        <input type="date" name="start_date" id="edit_start_date" required class="sfgmis-input">
                    </div>
                    <div>
                        <label class="sfgmis-label">Submission Deadline <span class="req">*</span></label>
                        <input type="date" name="end_date" id="edit_end_date" required class="sfgmis-input">
                    </div>
                </div>

                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Ceiling Envelope ($ USD) <span class="req">*</span></label>
                        <input type="number" step="1000" min="0" name="ceiling_amount" id="edit_ceiling_amount" required class="sfgmis-input">
                    </div>
                    <div>
                        <label class="sfgmis-label">Status</label>
                        <select name="status" id="edit_status" class="sfgmis-select">
                            <option value="active">Active (Open for Submissions)</option>
                            <option value="inactive">Inactive (Closed)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="sfgmis-modal-foot">
                <button type="button" onclick="closeModal('editCycleModal')" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">
                    <i data-lucide="check" style="width:16px;height:16px;"></i> Update Cycle
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- ── MODAL 3: ADD FISCAL YEAR ──────────────────────────────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="addFiscalYearModal" class="sfgmis-modal-backdrop">
    <div class="sfgmis-modal">
        <div class="sfgmis-modal-head">
            <h3>
                <i data-lucide="calendar-plus" style="width:20px;height:20px;color:var(--gov-green);"></i>
                <span>Configure Fiscal Year</span>
            </h3>
            <button type="button" onclick="closeModal('addFiscalYearModal')" class="sfgmis-modal-close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" action="budget-cycles.php">
            <input type="hidden" name="action" value="add_fiscal_year">

            <div class="sfgmis-modal-body">
                <div class="sfgmis-form-group">
                    <label class="sfgmis-label">Fiscal Year Designation <span class="req">*</span></label>
                    <input type="text" name="name" placeholder="e.g. FY 2027/2028" required class="sfgmis-input">
                </div>

                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Start Date <span class="req">*</span></label>
                        <input type="date" name="start_date" value="<?php echo date('Y-01-01'); ?>" required class="sfgmis-input">
                    </div>
                    <div>
                        <label class="sfgmis-label">End Date <span class="req">*</span></label>
                        <input type="date" name="end_date" value="<?php echo date('Y-12-31'); ?>" required class="sfgmis-input">
                    </div>
                </div>

                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Total Approved National Budget ($) <span class="req">*</span></label>
                        <input type="number" step="1000" min="0" name="total_budget" placeholder="e.g. 380000000" value="380000000" required class="sfgmis-input">
                    </div>
                    <div>
                        <label class="sfgmis-label">Status</label>
                        <select name="status" class="sfgmis-select">
                            <option value="planning">Planning Phase</option>
                            <option value="active" selected>Active</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="sfgmis-modal-foot">
                <button type="button" onclick="closeModal('addFiscalYearModal')" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">
                    <i data-lucide="check" style="width:16px;height:16px;"></i> Save Fiscal Year
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- ── MODAL 4: EDIT FISCAL YEAR ─────────────────────────────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="editFiscalYearModal" class="sfgmis-modal-backdrop">
    <div class="sfgmis-modal">
        <div class="sfgmis-modal-head">
            <h3>
                <i data-lucide="pencil" style="width:20px;height:20px;color:var(--gov-gold);"></i>
                <span>Edit Fiscal Year</span>
            </h3>
            <button type="button" onclick="closeModal('editFiscalYearModal')" class="sfgmis-modal-close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" action="budget-cycles.php">
            <input type="hidden" name="action" value="edit_fiscal_year">
            <input type="hidden" name="fy_id" id="edit_fy_modal_id">

            <div class="sfgmis-modal-body">
                <div class="sfgmis-form-group">
                    <label class="sfgmis-label">Fiscal Year Designation <span class="req">*</span></label>
                    <input type="text" name="name" id="edit_fy_modal_name" required class="sfgmis-input">
                </div>

                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Start Date <span class="req">*</span></label>
                        <input type="date" name="start_date" id="edit_fy_modal_start" required class="sfgmis-input">
                    </div>
                    <div>
                        <label class="sfgmis-label">End Date <span class="req">*</span></label>
                        <input type="date" name="end_date" id="edit_fy_modal_end" required class="sfgmis-input">
                    </div>
                </div>

                <div class="sfgmis-form-row">
                    <div>
                        <label class="sfgmis-label">Total Approved National Budget ($) <span class="req">*</span></label>
                        <input type="number" step="1000" min="0" name="total_budget" id="edit_fy_modal_budget" required class="sfgmis-input">
                    </div>
                    <div>
                        <label class="sfgmis-label">Status</label>
                        <select name="status" id="edit_fy_modal_status" class="sfgmis-select">
                            <option value="planning">Planning Phase</option>
                            <option value="active">Active</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="sfgmis-modal-foot">
                <button type="button" onclick="closeModal('editFiscalYearModal')" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">
                    <i data-lucide="check" style="width:16px;height:16px;"></i> Update Fiscal Year
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- ── MODAL 5: DELETE CYCLE CONFIRMATION ────────────────────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="deleteCycleModal" class="sfgmis-modal-backdrop">
    <div class="sfgmis-modal" style="max-width:480px;">
        <div class="sfgmis-modal-head" style="background:#fef2f2; border-bottom:1px solid #fecaca;">
            <h3 style="color:var(--gov-red);">
                <i data-lucide="alert-triangle" style="width:20px;height:20px;"></i>
                <span>Confirm Cycle Deletion</span>
            </h3>
            <button type="button" onclick="closeModal('deleteCycleModal')" class="sfgmis-modal-close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" action="budget-cycles.php">
            <input type="hidden" name="action" value="delete_cycle">
            <input type="hidden" name="cycle_id" id="delete_cycle_id">

            <div class="sfgmis-modal-body">
                <p style="font-size:0.92rem; color:#1e293b; margin-top:0;">
                    Are you sure you want to delete this budget cycle?
                </p>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:14px;">
                    <span style="font-family:monospace; font-weight:700; color:var(--gov-green); background:#dcfce7; padding:2px 6px; border-radius:4px; font-size:0.75rem;" id="delete_cycle_code">
                        CYC-CODE
                    </span>
                    <strong id="delete_cycle_name" style="font-size:0.95rem; color:#0f172a; margin-left:8px;">Cycle Title</strong>
                </div>
                <p style="font-size:0.8rem; color:#64748b; margin-bottom:0;">
                    Note: Deleting an active cycle will revoke the submission window for attached MDAs.
                </p>
            </div>

            <div class="sfgmis-modal-foot">
                <button type="button" onclick="closeModal('deleteCycleModal')" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-danger">
                    <i data-lucide="trash-2" style="width:16px;height:16px;"></i> Confirm Delete
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── JAVASCRIPT FOR CYCLES & FISCAL YEARS ───────────────────── -->
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

function openAddCycleModal() {
    openModal('addCycleModal');
}

function openAddFiscalYearModal() {
    openModal('addFiscalYearModal');
}

function openEditCycleModal(cyc) {
    if (typeof cyc === 'string') {
        try { cyc = JSON.parse(cyc); } catch(e) {}
    }
    document.getElementById('edit_cycle_id').value = cyc.id;
    document.getElementById('edit_fiscal_year_id').value = cyc.fiscal_year_id;
    document.getElementById('edit_cycle_code').value = cyc.cycle_code;
    document.getElementById('edit_name').value = cyc.name;
    document.getElementById('edit_start_date').value = cyc.start_date;
    document.getElementById('edit_end_date').value = cyc.end_date;
    document.getElementById('edit_ceiling_amount').value = cyc.ceiling_amount;
    document.getElementById('edit_status').value = cyc.status;
    openModal('editCycleModal');
}

function openEditFiscalYearModal(fy) {
    if (typeof fy === 'string') {
        try { fy = JSON.parse(fy); } catch(e) {}
    }
    document.getElementById('edit_fy_modal_id').value = fy.id;
    document.getElementById('edit_fy_modal_name').value = fy.name;
    document.getElementById('edit_fy_modal_start').value = fy.start_date;
    document.getElementById('edit_fy_modal_end').value = fy.end_date;
    document.getElementById('edit_fy_modal_budget').value = fy.total_budget;
    document.getElementById('edit_fy_modal_status').value = fy.status;
    openModal('editFiscalYearModal');
}

function openDeleteCycleModal(id, name, code) {
    document.getElementById('delete_cycle_id').value = id;
    document.getElementById('delete_cycle_name').textContent = name;
    document.getElementById('delete_cycle_code').textContent = code;
    openModal('deleteCycleModal');
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
