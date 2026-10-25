<?php
$pageTitle = 'State Audit Findings & Compliance Tracker';
require_once __DIR__ . '/config/db.php';
require_role(['super_admin', 'minister', 'state_minister', 'director_general', 'auditor'], 'Audit Findings & Compliance');

$message = '';
$error = '';

// Handle Create Finding
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_finding') {
        $title = trim($_POST['title']);
        $module = trim($_POST['module']);
        $severity = $_POST['severity'] ?? 'medium';
        $orgId = (int)$_POST['organization_id'];
        $desc = trim($_POST['description']);
        $rec = trim($_POST['recommendation']);
        $auditorId = current_user()['id'] ?? 9;
        $code = generate_code('audit_findings', 'finding_code', 'FND');

        if ($title === '' || $desc === '') {
            $error = 'Finding title and audit description are required.';
        } else {
            try {
                $sql = "INSERT INTO audit_findings (finding_code, title, module, severity, organization_id, description, recommendation, status, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?)";
                db_execute($sql, [$code, $title, $module, $severity, $orgId, $desc, $rec, $auditorId]);
                record_audit("Filed Audit Finding {$code}", 'Audit & Compliance', 'audit_findings', $code, null, "Severity: {$severity} | {$title}");
                $message = "Audit Finding {$code} officially filed.";
            } catch (Exception $e) {
                $error = 'Failed to file finding: ' . $e->getMessage();
            }
        }
    }

    if ($_POST['action'] === 'resolve_finding') {
        $fId = (int)$_POST['finding_id'];
        $status = $_POST['status']; // 'in_progress' or 'resolved'
        $actionTaken = trim($_POST['corrective_action'] ?? '');
        try {
            $f = db_row("SELECT * FROM audit_findings WHERE id = ?", [$fId]);
            db_execute("UPDATE audit_findings SET status = ?, corrective_action = ? WHERE id = ?", [$status, $actionTaken, $fId]);
            record_audit("Updated Finding {$f['finding_code']}", 'Audit & Compliance', 'audit_findings', $f['finding_code'], "status: {$f['status']}", "status: {$status}");
            $message = "Audit Finding {$f['finding_code']} updated to " . strtoupper($status) . ".";
        } catch (Exception $e) {
            $error = 'Failed to update finding: ' . $e->getMessage();
        }
    }
}

$findings = db_select("
    SELECT f.*, o.name as org_name, o.code as org_code, u.name as auditor_name
    FROM audit_findings f
    JOIN organizations o ON o.id = f.organization_id
    LEFT JOIN users u ON u.id = f.created_by
    ORDER BY f.id DESC
");

$orgsList = db_select("SELECT id, name, code FROM organizations WHERE type != 'government' ORDER BY name ASC");

include __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
    <div style="background:#dcfce7; border:1px solid #86efac; color:#166534; padding:12px 16px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:8px;">
        <i data-lucide="check-circle" style="width:18px;height:18px;"></i> <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; padding:12px 16px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:8px;">
        <i data-lucide="alert-octagon" style="width:18px;height:18px;"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">
            Independent oversight tracker for the Office of the Auditor General to monitor compliance and enforce corrective actions.
        </p>
    </div>
    <button onclick="document.getElementById('addFindingModal').style.display='flex'" class="btn-sfgmis-green">
        <i data-lucide="shield-alert" style="width:16px;height:16px;"></i> File Audit Finding
    </button>
</div>

<div class="sfgmis-panel">
    <div class="sfgmis-panel-head">
        <h3><i data-lucide="shield-alert" style="width:18px;height:18px;color:#dc2626;"></i> State Audit Findings & Corrective Measures</h3>
        <span style="font-size:0.8rem; color:#64748b; font-weight:600;"><?php echo count($findings); ?> Findings</span>
    </div>
    <div>
        <table class="sfgmis-table">
            <thead>
                <tr>
                    <th>Finding Code</th>
                    <th>Audit Title & Target MDA</th>
                    <th>Module</th>
                    <th>Severity</th>
                    <th>Recommendation</th>
                    <th>Corrective Action Taken</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($findings as $f): 
                    $sevColor = ($f['severity'] === 'critical' || $f['severity'] === 'high') ? 'badge-rejected' : (($f['severity'] === 'medium') ? 'badge-under_review' : 'badge-draft');
                ?>
                <tr>
                    <td>
                        <span style="font-family:monospace; font-weight:700; color:var(--sl-green-800); background:#dcfce7; padding:3px 8px; border-radius:4px;">
                            <?php echo htmlspecialchars($f['finding_code']); ?>
                        </span>
                        <div style="font-size:0.7rem; color:#94a3b8; margin-top:4px;"><?php echo date('M d, Y', strtotime($f['created_at'])); ?></div>
                    </td>
                    <td>
                        <strong style="color:#0f172a; font-size:0.9rem;"><?php echo htmlspecialchars($f['title']); ?></strong>
                        <div style="font-size:0.75rem; color:#64748b; margin-top:2px;">MDA: <?php echo htmlspecialchars($f['org_name']); ?></div>
                        <div style="font-size:0.75rem; color:#475569; margin-top:4px; max-width:240px;"><?php echo htmlspecialchars($f['description']); ?></div>
                    </td>
                    <td>
                        <span style="font-size:0.75rem; font-weight:600; color:#334155;"><?php echo htmlspecialchars($f['module']); ?></span>
                    </td>
                    <td>
                        <span class="badge-status <?php echo $sevColor; ?>">
                            <?php echo htmlspecialchars($f['severity']); ?>
                        </span>
                    </td>
                    <td style="font-size:0.75rem; color:#334155; max-width:200px;">
                        <?php echo htmlspecialchars($f['recommendation']); ?>
                    </td>
                    <td style="font-size:0.75rem; color:#15803d; max-width:180px;">
                        <?php echo !empty($f['corrective_action']) ? htmlspecialchars($f['corrective_action']) : '<span style="color:#94a3b8;">Pending Action</span>'; ?>
                    </td>
                    <td>
                        <span class="badge-status badge-<?php echo htmlspecialchars($f['status']); ?>">
                            <?php echo htmlspecialchars(str_replace('_', ' ', $f['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($f['status'] !== 'resolved'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="resolve_finding">
                            <input type="hidden" name="finding_id" value="<?php echo $f['id']; ?>">
                            <input type="hidden" name="status" value="resolved">
                            <input type="hidden" name="corrective_action" value="Validated compliance by Senior State Auditor.">
                            <button type="submit" class="btn-sfgmis-outline" style="font-size:0.72rem; padding:3px 6px; color:#166534; border-color:#86efac;">
                                <i data-lucide="check-check" style="width:12px;height:12px;"></i> Mark Resolved
                            </button>
                        </form>
                        <?php else: ?>
                            <span style="font-size:0.75rem; color:#166534; font-weight:700;">Remediated</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── MODAL: FILE AUDIT FINDING ────────────────────────────────── -->
<div id="addFindingModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:100; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#ffffff; border-radius:12px; max-width:540px; width:100%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2); overflow:hidden;">
        <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.1rem; color:#0f172a; font-weight:700;">File State Audit Finding</h3>
            <button onclick="document.getElementById('addFindingModal').style.display='none'" style="border:none; background:transparent; cursor:pointer; color:#94a3b8;">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" style="padding:20px;">
            <input type="hidden" name="action" value="add_finding">
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Finding Title *</label>
                <input type="text" name="title" placeholder="e.g. Unreconciled Fuel Disbursement Vouchers" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Target MDA *</label>
                    <select name="organization_id" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                        <?php foreach ($orgsList as $o): ?>
                        <option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['name'] . ' (' . $o['code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Relevant Module</label>
                    <select name="module" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                        <option value="Expenditure Management">Expenditure Management</option>
                        <option value="Procurement Management">Procurement Management</option>
                        <option value="Budget Management">Budget Management</option>
                        <option value="Revenue Management">Revenue Management</option>
                        <option value="Asset Management">Asset Management</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Severity Classification</label>
                <select name="severity" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                    <option value="low">Low (Procedural irregularity)</option>
                    <option value="medium" selected>Medium (Control variance)</option>
                    <option value="high">High (Material financial risk)</option>
                    <option value="critical">Critical (Potential misappropriation / urgent)</option>
                </select>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Audit Observation & Evidence *</label>
                <textarea name="description" rows="2" placeholder="Detail the discrepancy, vouchers, and audit sample tested..." required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;"></textarea>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Auditor Recommendation *</label>
                <textarea name="recommendation" rows="2" placeholder="Required corrective measures and policy remediation..." required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" onclick="document.getElementById('addFindingModal').style.display='none'" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">File Finding</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
