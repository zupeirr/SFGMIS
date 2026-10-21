<?php 
require_once __DIR__ . '/../config/db.php'; 
$u = current_user() ?? [
    'id' => 1, 'name' => 'Admin', 'role' => 'super_admin', 'role_name' => 'Administrator',
    'org_name' => 'Ministry of Finance & Economic Development', 'org_code' => 'MOFED'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — SFGMIS | Somaliland Ministry of Finance' : 'SFGMIS — Somaliland Government Financial Management Platform'; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/admin.css">
<link rel="stylesheet" href="assets/css/sfgmis.css">
<script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="admin-body">

<?php
$currentPage = basename($_SERVER['PHP_SELF']);
include __DIR__ . '/sidebar.php';
?>

<div class="admin-main">
    <header class="sfgmis-topbar">
        <div style="display:flex; align-items:center; gap:16px;">
            <button class="mobile-topbar-toggle" id="sidebarToggle" aria-label="Toggle menu"
                style="background:transparent; border:none; cursor:pointer; color:var(--gov-green); padding:4px; display:flex; align-items:center;">
                <i data-lucide="menu" style="width:22px;height:22px;"></i>
            </button>
            <div>
                <h1><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?></h1>
                <div style="font-size:0.75rem; color:var(--gov-text-muted); margin-top:2px;">
                    <?php echo htmlspecialchars($u['org_name'] ?? 'Ministry of Finance & Economic Development'); ?> &bull; Jamhuuriyadda Somaliland
                </div>
            </div>
        </div>

        <!-- Global Search -->
        <div class="topbar-search-wrap" style="display:none; @media(min-width: 900px){display:flex;}">
            <i data-lucide="search" style="width:15px;height:15px;color:var(--gov-text-muted);flex-shrink:0;"></i>
            <input type="text" class="topbar-search-input" placeholder="Search vouchers, MDAs, codes..." onkeydown="if(event.key==='Enter'){window.location.href='audit-trail.php?q='+encodeURIComponent(this.value);}">
        </div>

        <div style="display:flex; align-items:center; gap:14px;">
            <!-- Public Portal Link -->
            <a href="transparency.php" target="_blank" class="btn-sfgmis-outline" style="font-size:0.78rem; padding:6px 12px;" title="View Public Ministry Portal">
                <i data-lucide="globe" style="width:14px;height:14px;color:var(--gov-green);"></i> Public Portal
            </a>

            <!-- Quick Role Switcher for Demo / Evaluation (Restricted to Authorized Admins) -->
            <?php if (in_array($u['role'] ?? '', ['super_admin', 'director_general'])): ?>
            <div class="role-switcher-badge" title="Evaluate platform as different Somaliland government roles">
                <i data-lucide="shield-check" style="width:16px;height:16px;color:var(--gov-green);"></i>
                <span style="font-size:0.75rem;font-weight:700;color:var(--gov-text-muted);text-transform:uppercase;">Role:</span>
                <select class="role-switcher-select" onchange="window.location.href='?switch_role=' + this.value;">
                    <option value="super_admin" <?php echo ($u['role'] === 'super_admin') ? 'selected' : ''; ?>>Super Admin (Full Access)</option>
                    <option value="minister" <?php echo ($u['role'] === 'minister') ? 'selected' : ''; ?>>Minister of Finance</option>
                    <option value="dg" <?php echo ($u['role'] === 'director_general') ? 'selected' : ''; ?>>Director General</option>
                    <option value="budget_officer" <?php echo ($u['role'] === 'budget_officer') ? 'selected' : ''; ?>>Budget Officer</option>
                    <option value="finance_officer" <?php echo ($u['role'] === 'finance_officer') ? 'selected' : ''; ?>>Finance Officer</option>
                    <option value="accountant" <?php echo ($u['role'] === 'accountant') ? 'selected' : ''; ?>>Accountant General</option>
                    <option value="procurement_officer" <?php echo ($u['role'] === 'procurement_officer') ? 'selected' : ''; ?>>Procurement Officer</option>
                    <option value="auditor" <?php echo ($u['role'] === 'auditor') ? 'selected' : ''; ?>>State Auditor General</option>
                    <option value="mda_officer" <?php echo ($u['role'] === 'mda_officer') ? 'selected' : ''; ?>>MDA Finance (Ministry of Health)</option>
                </select>
            </div>
            <?php endif; ?>

            <!-- Notifications Bell -->
            <?php
            $headerUnread = 0;
            try {
                $headerUnread = (int)db_row("SELECT count(*) as c FROM notifications WHERE is_read = 0")['c'];
            } catch (Exception $e) {}
            ?>
            <a href="notifications.php" style="position:relative; width:36px; height:36px; border-radius:50%; border:1px solid var(--gov-border); background:#ffffff; display:flex; align-items:center; justify-content:center; color:var(--gov-text-secondary); text-decoration:none;" title="<?php echo $headerUnread; ?> Unread Notifications">
                <i data-lucide="bell" style="width:17px;height:17px;"></i>
                <?php if ($headerUnread > 0): ?>
                <span style="position:absolute; top:-3px; right:-3px; min-width:18px; height:18px; padding:0 4px; background:var(--gov-red); color:#fff; font-size:0.65rem; font-weight:800; border-radius:999px; border:2px solid #ffffff; display:flex; align-items:center; justify-content:center;">
                    <?php echo $headerUnread; ?>
                </span>
                <?php endif; ?>
            </a>

            <!-- User Info Pill with Sign Out -->
            <div style="display:flex; align-items:center; gap:10px; border-left:1px solid var(--gov-border); padding-left:14px;">
                <div style="width:34px; height:34px; border-radius:50%; background:var(--gov-green); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.85rem; border:2px solid var(--gov-gold);">
                    <?php echo strtoupper(substr($u['name'] ?? 'A', 0, 1)); ?>
                </div>
                <div style="display:flex; flex-direction:column; line-height:1.2;">
                    <span style="font-weight:700; font-size:0.85rem; color:var(--gov-text-primary);"><?php echo htmlspecialchars($u['name'] ?? 'Officer'); ?></span>
                    <span style="font-size:0.72rem; color:var(--gov-gold); font-weight:600;"><?php echo htmlspecialchars($u['role_name'] ?? 'Administrator'); ?></span>
                </div>
                <a href="logout.php" style="color:var(--gov-text-muted); margin-left:6px; display:flex; align-items:center;" title="Sign Out">
                    <i data-lucide="log-out" style="width:16px;height:16px;"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="admin-content" style="padding:28px;">
