<?php
// Helper: return 'active' class if current page matches
if (!function_exists('isActive')) {
    function isActive($page, $queryParam = null) {
        global $currentPage;
        if ($currentPage !== $page) return '';
        if ($queryParam !== null) {
            foreach ($queryParam as $k => $v) {
                if (($_GET[$k] ?? '') !== $v) return '';
            }
        }
        return ' class="active"';
    }
}

// Helper: return 'open active-group' if any child page is current
if (!function_exists('isGroupActive')) {
    function isGroupActive($pages) {
        global $currentPage;
        return in_array($currentPage, $pages) ? ' class="nav-group open active-group"' : ' class="nav-group"';
    }
}

$currentRole = $_SESSION['user_role'] ?? 'super_admin';

// Real-time counter badges for workflow queues
$badgeProposals = 0;
$badgeExpenses = 0;
$badgePayments = 0;
$badgeFindings = 0;
$badgeNotifs = 0;

try {
    $badgeProposals = (int)db_row("SELECT count(*) as c FROM budget_proposals WHERE status IN ('submitted', 'under_review')")['c'];
    $badgeExpenses  = (int)db_row("SELECT count(*) as c FROM expenditure_requests WHERE status IN ('submitted', 'pending_approval')")['c'];
    $badgePayments  = (int)db_row("SELECT count(*) as c FROM payments WHERE status = 'pending'")['c'];
    $badgeFindings  = (int)db_row("SELECT count(*) as c FROM audit_findings WHERE status = 'open'")['c'];
    $badgeNotifs    = (int)db_row("SELECT count(*) as c FROM notifications WHERE is_read = 0")['c'];
} catch (Exception $e) {}
?>
<aside class="admin-sidebar" id="adminSidebar">
    <a href="index.php" class="brand brand-sfgmis" title="SFGMIS — Ministry of Finance">
        <img src="assets/images/LOGO.webp" alt="Somaliland Ministry of Finance Emblem" class="brand-logo-img">
        <div class="brand-info">
            <span class="brand-acronym">SFGMIS</span>
            <span class="brand-ministry">Ministry of Finance</span>
            <span class="brand-subtext">Jamhuuriyadda Somaliland</span>
        </div>
    </a>

    <nav class="admin-nav" style="padding-top:10px;">

        <!-- 1. DASHBOARD -->
        <a href="index.php"<?php echo isActive('index.php'); ?> data-tip="Executive Dashboard">
            <span class="ic"><i data-lucide="layout-dashboard"></i></span> <span>Dashboard</span>
        </a>

        <!-- 2. ORGANIZATIONS & MDAS (Admin, Minister, DG, Budget, Auditor) -->
        <?php if (in_array($currentRole, ['super_admin', 'minister', 'state_minister', 'director_general', 'budget_officer', 'auditor'])): ?>
        <div<?php echo isGroupActive(['organizations.php']); ?>>
            <button class="nav-group-toggle" data-tip="Organizations & MDAs">
                <span class="ic"><i data-lucide="building-2"></i></span>
                <span>Organizations & MDAs</span>
                <span class="chevron"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
            </button>
            <div class="nav-group-children">
                <a href="organizations.php"<?php echo ($currentPage === 'organizations.php' && (empty($_GET['type']) || $_GET['type'] === 'all') && empty($_GET['view'])) ? ' class="active"' : ''; ?>>All Organizations</a>
                <a href="organizations.php?type=ministry"<?php echo ($currentPage === 'organizations.php' && ($_GET['type'] ?? '') === 'ministry') ? ' class="active"' : ''; ?>>Ministries</a>
                <a href="organizations.php?type=department"<?php echo ($currentPage === 'organizations.php' && ($_GET['type'] ?? '') === 'department') ? ' class="active"' : ''; ?>>Departments</a>
                <a href="organizations.php?type=mda"<?php echo ($currentPage === 'organizations.php' && ($_GET['type'] ?? '') === 'mda') ? ' class="active"' : ''; ?>>Agencies / MDAs</a>
                <a href="organizations.php?type=unit"<?php echo ($currentPage === 'organizations.php' && ($_GET['type'] ?? '') === 'unit') ? ' class="active"' : ''; ?>>Offices & Units</a>
                <a href="organizations.php?view=hierarchy"<?php echo ($currentPage === 'organizations.php' && ($_GET['view'] ?? '') === 'hierarchy') ? ' class="active"' : ''; ?>>Institutional Hierarchy</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 3. USERS & ROLES (Super Admin, DG) -->
        <?php if (in_array($currentRole, ['super_admin', 'director_general'])): ?>
        <div<?php echo isGroupActive(['users.php']); ?>>
            <button class="nav-group-toggle" data-tip="Users & Roles (RBAC)">
                <span class="ic"><i data-lucide="users"></i></span>
                <span>Users & Roles</span>
                <span class="chevron"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
            </button>
            <div class="nav-group-children">
                <a href="users.php"<?php echo isActive('users.php'); ?>>All Users</a>
                <a href="users.php?tab=roles">Roles & Permissions</a>
                <a href="users.php?tab=assignments">User Assignments</a>
                <a href="users.php?tab=status">Account Status</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 4. BUDGET MANAGEMENT -->
        <?php if (in_array($currentRole, ['super_admin', 'minister', 'state_minister', 'director_general', 'budget_officer', 'finance_officer', 'auditor', 'mda_officer'])): ?>
        <div<?php echo isGroupActive(['budget-cycles.php','budget-proposals.php','budget-allocations.php','budget-monitoring.php']); ?>>
            <button class="nav-group-toggle" data-tip="Budget Management">
                <span class="ic"><i data-lucide="pie-chart"></i></span>
                <span style="display:flex; align-items:center; justify-content:space-between; width:100%;">
                    <span>Budget Management</span>
                    <?php if ($badgeProposals > 0): ?>
                    <span class="sfgmis-badge-mini" style="background:var(--gov-gold); color:#123D2B; font-weight:800;"><?php echo $badgeProposals; ?></span>
                    <?php endif; ?>
                </span>
                <span class="chevron"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
            </button>
            <div class="nav-group-children">
                <a href="budget-cycles.php"<?php echo isActive('budget-cycles.php'); ?>>Fiscal Years & Cycles</a>
                <a href="budget-proposals.php"<?php echo isActive('budget-proposals.php'); ?>>Budget Proposals</a>
                <a href="budget-allocations.php"<?php echo isActive('budget-allocations.php'); ?>>Budget Allocations</a>
                <a href="budget-monitoring.php"<?php echo isActive('budget-monitoring.php'); ?>>Budget Monitoring & Variance</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 5. REVENUE MANAGEMENT -->
        <?php if (in_array($currentRole, ['super_admin', 'minister', 'director_general', 'accountant', 'finance_officer', 'auditor'])): ?>
        <div<?php echo isGroupActive(['revenue.php','revenue-categories.php']); ?>>
            <button class="nav-group-toggle" data-tip="Revenue Management">
                <span class="ic"><i data-lucide="landmark"></i></span>
                <span>Revenue Management</span>
                <span class="chevron"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
            </button>
            <div class="nav-group-children">
                <a href="revenue.php"<?php echo ($currentPage === 'revenue.php' && empty($_GET['view'])) ? ' class="active"' : ''; ?>>Revenue Collections</a>
                <a href="revenue-categories.php"<?php echo isActive('revenue-categories.php'); ?>>Revenue Categories</a>
                <a href="revenue.php?view=orgs"<?php echo ($currentPage === 'revenue.php' && ($_GET['view'] ?? '') === 'orgs') ? ' class="active"' : ''; ?>>Revenue by Organization</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 6. EXPENDITURE MANAGEMENT -->
        <?php if (in_array($currentRole, ['super_admin', 'minister', 'state_minister', 'director_general', 'finance_officer', 'budget_officer', 'accountant', 'procurement_officer', 'auditor', 'mda_officer'])): ?>
        <div<?php echo isGroupActive(['expenditures.php','expenditure-approvals.php']); ?>>
            <button class="nav-group-toggle" data-tip="Expenditure Management">
                <span class="ic"><i data-lucide="receipt"></i></span>
                <span style="display:flex; align-items:center; justify-content:space-between; width:100%;">
                    <span>Expenditure Control</span>
                    <?php if ($badgeExpenses > 0): ?>
                    <span class="sfgmis-badge-mini" style="background:#2563eb; color:#ffffff; font-weight:700;"><?php echo $badgeExpenses; ?></span>
                    <?php endif; ?>
                </span>
                <span class="chevron"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
            </button>
            <div class="nav-group-children">
                <a href="expenditures.php"<?php echo isActive('expenditures.php'); ?>>Expenditure Requests</a>
                <a href="expenditure-approvals.php"<?php echo isActive('expenditure-approvals.php'); ?>>Review & Approvals</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 7. PROCUREMENT (PROCURE-TO-PAY) -->
        <?php if (in_array($currentRole, ['super_admin', 'minister', 'director_general', 'procurement_officer', 'finance_officer', 'auditor', 'mda_officer'])): ?>
        <div<?php echo isGroupActive(['procurement-requests.php','procurement-vendors.php','procurement-orders.php']); ?>>
            <button class="nav-group-toggle" data-tip="Procurement (P2P)">
                <span class="ic"><i data-lucide="shopping-cart"></i></span>
                <span>Procurement (P2P)</span>
                <span class="chevron"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
            </button>
            <div class="nav-group-children">
                <a href="procurement-requests.php"<?php echo isActive('procurement-requests.php'); ?>>Purchase Requests</a>
                <a href="procurement-vendors.php"<?php echo isActive('procurement-vendors.php'); ?>>Registered Vendors</a>
                <a href="procurement-orders.php"<?php echo isActive('procurement-orders.php'); ?>>Purchase Orders & Contracts</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 8. PAYMENT MANAGEMENT -->
        <?php if (in_array($currentRole, ['super_admin', 'minister', 'director_general', 'finance_officer', 'accountant', 'auditor'])): ?>
        <a href="payments.php"<?php echo isActive('payments.php'); ?> data-tip="Payment & Treasury Vouchers">
            <span class="ic"><i data-lucide="credit-card"></i></span>
            <span style="display:flex; align-items:center; justify-content:space-between; width:100%;">
                <span>Payments & Treasury</span>
                <?php if ($badgePayments > 0): ?>
                <span class="sfgmis-badge-mini" style="background:#16a34a; color:#ffffff; font-weight:700;"><?php echo $badgePayments; ?></span>
                <?php endif; ?>
            </span>
        </a>
        <?php endif; ?>

        <!-- 9. ACCOUNTING & GENERAL LEDGER -->
        <?php if (in_array($currentRole, ['super_admin', 'minister', 'director_general', 'accountant', 'auditor'])): ?>
        <div<?php echo isGroupActive(['accounting-chart.php','accounting-ledger.php']); ?>>
            <button class="nav-group-toggle" data-tip="Accounting & General Ledger">
                <span class="ic"><i data-lucide="book-open"></i></span>
                <span>Accounting & Ledger</span>
                <span class="chevron"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
            </button>
            <div class="nav-group-children">
                <a href="accounting-chart.php"<?php echo isActive('accounting-chart.php'); ?>>Chart of Accounts</a>
                <a href="accounting-ledger.php"<?php echo isActive('accounting-ledger.php'); ?>>General Ledger & Journal</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 10. FIXED ASSET MANAGEMENT -->
        <a href="assets.php"<?php echo isActive('assets.php'); ?> data-tip="Government Fixed Assets">
            <span class="ic"><i data-lucide="package"></i></span> <span>Asset Management</span>
        </a>

        <!-- 11. FINANCIAL REPORTS -->
        <a href="reports.php"<?php echo isActive('reports.php'); ?> data-tip="Statutory Financial Reports">
            <span class="ic"><i data-lucide="bar-chart-3"></i></span> <span>Financial Reports</span>
        </a>

        <!-- 12. AUDIT & COMPLIANCE -->
        <?php if (in_array($currentRole, ['super_admin', 'minister', 'director_general', 'auditor'])): ?>
        <div<?php echo isGroupActive(['audit-trail.php','audit-findings.php']); ?>>
            <button class="nav-group-toggle" data-tip="Audit & Compliance">
                <span class="ic"><i data-lucide="shield-alert"></i></span>
                <span style="display:flex; align-items:center; justify-content:space-between; width:100%;">
                    <span>Audit & Compliance</span>
                    <?php if ($badgeFindings > 0): ?>
                    <span class="sfgmis-badge-mini" style="background:var(--gov-red); color:#ffffff; font-weight:700;"><?php echo $badgeFindings; ?></span>
                    <?php endif; ?>
                </span>
                <span class="chevron"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
            </button>
            <div class="nav-group-children">
                <a href="audit-trail.php"<?php echo isActive('audit-trail.php'); ?>>Audit Trail & Logs</a>
                <a href="audit-findings.php"<?php echo isActive('audit-findings.php'); ?>>Audit Findings Tracker</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 13. DOCUMENT MANAGEMENT (All Roles) -->
        <div<?php echo isGroupActive(['documents.php']); ?>>
            <button class="nav-group-toggle" data-tip="Document Management">
                <span class="ic"><i data-lucide="folder-archive"></i></span>
                <span>Document Repository</span>
                <span class="chevron"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
            </button>
            <div class="nav-group-children">
                <a href="documents.php"<?php echo isActive('documents.php'); ?>>All Documents</a>
                <a href="documents.php?category=budget">Budget Documents</a>
                <a href="documents.php?category=invoice_receipt">Invoices & Receipts</a>
                <a href="documents.php?category=contract_po">Contracts & POs</a>
                <a href="documents.php?category=payment">Payment Vouchers</a>
                <a href="documents.php?category=audit">Audit Dossiers</a>
            </div>
        </div>

        <!-- 14. NOTIFICATIONS -->
        <a href="notifications.php"<?php echo isActive('notifications.php'); ?> data-tip="Notifications Center">
            <span class="ic"><i data-lucide="bell"></i></span>
            <span style="display:flex; align-items:center; justify-content:space-between; width:100%;">
                <span>Notifications</span>
                <?php if ($badgeNotifs > 0): ?>
                <span class="sfgmis-badge-mini" style="background:var(--gov-gold); color:#123D2B; font-weight:800;"><?php echo $badgeNotifs; ?></span>
                <?php endif; ?>
            </span>
        </a>

        <!-- 15. SYSTEM ADMINISTRATION (Super Admin & DG) -->
        <?php if (in_array($currentRole, ['super_admin', 'director_general'])): ?>
        <a href="settings.php"<?php echo isActive('settings.php'); ?> data-tip="System Administration">
            <span class="ic"><i data-lucide="settings"></i></span> <span>Administration</span>
        </a>
        <?php endif; ?>

        <!-- 16. PUBLIC CITIZEN TRANSPARENCY (Independent Public Gateway) -->
        <div style="margin-top:12px; padding-top:12px; border-top:1px dashed rgba(255,255,255,0.12);">
            <a href="transparency.php" target="_blank" data-tip="Public Citizen Transparency Portal" style="color:var(--gov-gold) !important;">
                <span class="ic" style="color:var(--gov-gold) !important;"><i data-lucide="external-link"></i></span>
                <span style="font-weight:600;">Citizen Portal &rarr;</span>
            </a>
        </div>

    </nav>

    <div class="admin-sidebar-footer" style="padding:14px; border-top:1px solid rgba(255,255,255,0.08);">
        <a href="logout.php" class="btn btn-outline btn-block" style="border-color:rgba(255,255,255,0.2); color:#cbd5e1; display:flex; align-items:center; gap:8px; justify-content:center; font-size:0.8rem;">
            <i data-lucide="log-out" style="width:15px;height:15px;"></i> <span class="btn-label">Sign Out</span>
        </a>
    </div>
</aside>
