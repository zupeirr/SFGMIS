<?php
$pageTitle = 'Government Audit Trail & Traceability';
require_once __DIR__ . '/config/db.php';
require_role(['super_admin', 'minister', 'state_minister', 'director_general', 'auditor'], 'Audit Trail & Compliance');

$filterModule = $_GET['module'] ?? '';

$sql = "SELECT a.*, o.name as org_name, o.code as org_code 
        FROM audit_logs a 
        LEFT JOIN organizations o ON o.id = a.organization_id ";
$params = [];
if ($filterModule !== '') {
    $sql .= " WHERE a.module = ? ";
    $params[] = $filterModule;
}
$sql .= " ORDER BY a.id DESC LIMIT 100";

$logs = db_select($sql, $params);
$modules = db_select("SELECT DISTINCT module FROM audit_logs ORDER BY module ASC");

include __DIR__ . '/includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">
            Immutable, non-repudiable audit ledger recording every financial transaction, approval, and system modification.
        </p>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
        <span style="font-size:0.8rem; font-weight:600; color:#475569;">Filter Module:</span>
        <select onchange="window.location.href='?module=' + encodeURIComponent(this.value);" style="padding:6px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.82rem;">
            <option value="">All Government Modules</option>
            <?php foreach ($modules as $m): ?>
            <option value="<?php echo htmlspecialchars($m['module']); ?>" <?php echo $filterModule === $m['module'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($m['module']); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="sfgmis-panel">
    <div class="sfgmis-panel-head">
        <h3><i data-lucide="shield" style="width:18px;height:18px;color:#dc2626;"></i> Immutable Audit Ledger</h3>
        <span style="font-size:0.8rem; color:#64748b; font-weight:600;"><?php echo count($logs); ?> Logged Events</span>
    </div>
    <div>
        <table class="sfgmis-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User & Role</th>
                    <th>Module</th>
                    <th>Action Executed</th>
                    <th>Entity Ref</th>
                    <th>Change Delta (Previous &rarr; New)</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                <tr>
                    <td>
                        <span style="font-family:monospace; font-size:0.75rem; color:#475569;">
                            <?php echo date('M d, Y H:i:s', strtotime($l['created_at'])); ?>
                        </span>
                    </td>
                    <td>
                        <strong style="color:#0f172a; font-size:0.85rem;"><?php echo htmlspecialchars($l['user_name']); ?></strong>
                        <div style="font-size:0.72rem; color:var(--sl-green-800); font-weight:600; text-transform:uppercase;">
                            <?php echo htmlspecialchars(str_replace('_', ' ', $l['user_role'])); ?>
                        </div>
                    </td>
                    <td>
                        <span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:0.75rem; font-weight:600; color:#334155;">
                            <?php echo htmlspecialchars($l['module']); ?>
                        </span>
                    </td>
                    <td><strong style="color:#0f172a; font-size:0.85rem;"><?php echo htmlspecialchars($l['action']); ?></strong></td>
                    <td>
                        <span style="font-family:monospace; font-weight:700; color:var(--sl-green-800); background:#dcfce7; padding:2px 6px; border-radius:4px;">
                            <?php echo htmlspecialchars($l['record_id'] ?? '—'); ?>
                        </span>
                    </td>
                    <td style="max-width:280px;">
                        <?php if ($l['previous_value']): ?>
                            <div style="font-size:0.7rem; color:#b91c1c; font-family:monospace; text-decoration:line-through;">
                                <?php echo htmlspecialchars($l['previous_value']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($l['new_value']): ?>
                            <div style="font-size:0.72rem; color:#15803d; font-family:monospace; background:#f0fdf4; padding:2px 6px; border-radius:4px; margin-top:2px;">
                                <?php echo htmlspecialchars($l['new_value']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="font-family:monospace; font-size:0.72rem; color:#64748b;"><?php echo htmlspecialchars($l['ip_address']); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
