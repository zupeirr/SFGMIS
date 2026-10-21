<?php
$pageTitle = 'Procurement Purchase Requisitions';
require_once __DIR__ . '/config/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_pr') {
    $orgId = (int)$_POST['organization_id'];
    $title = trim($_POST['title']);
    $department = trim($_POST['department']);
    $cost = (float)$_POST['estimated_cost'];
    $budgetCode = trim($_POST['budget_code']);
    $justification = trim($_POST['justification']);
    $officerId = current_user()['id'] ?? 8;
    $reqNo = generate_code('purchase_requests', 'request_no', 'PR');

    if ($title === '' || $cost <= 0) {
        $error = 'Requisition title and estimated cost are required.';
    } else {
        try {
            $sql = "INSERT INTO purchase_requests (request_no, organization_id, title, department, estimated_cost, budget_code, justification, status, requested_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
            db_execute($sql, [$reqNo, $orgId, $title, $department, $cost, $budgetCode, $justification, $officerId]);
            record_audit('Submitted Purchase Request', 'Procurement Management', 'purchase_requests', $reqNo, null, "Estimated cost: " . format_currency($cost));
            $message = "Purchase Request {$reqNo} submitted successfully.";
        } catch (Exception $e) {
            $error = 'Failed to submit purchase request: ' . $e->getMessage();
        }
    }
}

$requests = db_select("
    SELECT pr.*, o.name as org_name, o.code as org_code, u.name as requester_name
    FROM purchase_requests pr
    JOIN organizations o ON o.id = pr.organization_id
    LEFT JOIN users u ON u.id = pr.requested_by
    ORDER BY pr.id DESC
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
            Initial requisitions initiated by line ministries linked to statutory budget lines.
        </p>
    </div>
    <button onclick="document.getElementById('addPRModal').style.display='flex'" class="btn-sfgmis-green">
        <i data-lucide="plus-circle" style="width:16px;height:16px;"></i> Create Purchase Requisition
    </button>
</div>

<div class="sfgmis-panel">
    <div class="sfgmis-panel-head">
        <h3><i data-lucide="file-plus" style="width:18px;height:18px;color:var(--sl-green-700);"></i> Requisitions Register</h3>
        <span style="font-size:0.8rem; color:#64748b; font-weight:600;"><?php echo count($requests); ?> Requisitions</span>
    </div>
    <div>
        <table class="sfgmis-table">
            <thead>
                <tr>
                    <th>PR Number</th>
                    <th>Submitting MDA</th>
                    <th>Requisition Title & Justification</th>
                    <th>Budget Code</th>
                    <th>Estimated Cost</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                <tr>
                    <td>
                        <span style="font-family:monospace; font-weight:700; color:var(--sl-green-800); background:#dcfce7; padding:3px 8px; border-radius:4px;">
                            <?php echo htmlspecialchars($r['request_no']); ?>
                        </span>
                    </td>
                    <td>
                        <strong style="color:#0f172a;"><?php echo htmlspecialchars($r['org_name']); ?></strong>
                        <div style="font-size:0.72rem; color:#64748b;"><?php echo htmlspecialchars($r['department']); ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600; color:#1e293b;"><?php echo htmlspecialchars($r['title']); ?></div>
                        <div style="font-size:0.75rem; color:#64748b; max-width:240px;"><?php echo htmlspecialchars($r['justification']); ?></div>
                    </td>
                    <td>
                        <span style="font-family:monospace; font-weight:600;"><?php echo htmlspecialchars($r['budget_code']); ?></span>
                    </td>
                    <td><strong style="color:#0f172a;"><?php echo format_currency((float)$r['estimated_cost']); ?></strong></td>
                    <td>
                        <span class="badge-status badge-<?php echo htmlspecialchars($r['status']); ?>">
                            <?php echo htmlspecialchars(str_replace('_', ' ', $r['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <a href="procurement-orders.php?from_pr=<?php echo $r['id']; ?>" class="btn-sfgmis-outline" style="font-size:0.75rem; padding:3px 8px;">
                            <i data-lucide="shopping-bag" style="width:12px;height:12px;"></i> Generate PO
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── MODAL: CREATE REQUISITION ────────────────────────────────── -->
<div id="addPRModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:100; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#ffffff; border-radius:12px; max-width:540px; width:100%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2); overflow:hidden;">
        <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.1rem; color:#0f172a; font-weight:700;">Initiate Purchase Requisition</h3>
            <button onclick="document.getElementById('addPRModal').style.display='none'" style="border:none; background:transparent; cursor:pointer; color:#94a3b8;">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" style="padding:20px;">
            <input type="hidden" name="action" value="add_pr">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Requesting MDA *</label>
                    <select name="organization_id" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                        <?php foreach ($orgsList as $o): ?>
                        <option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['name'] . ' (' . $o['code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Internal Department</label>
                    <input type="text" name="department" placeholder="e.g. ICT Services" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Requisition Title *</label>
                <input type="text" name="title" placeholder="e.g. Procurement of High-Capacity Database Storage Servers" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Budget Chart Code *</label>
                    <input type="text" name="budget_code" placeholder="e.g. 311102" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Estimated Cost ($) *</label>
                    <input type="number" step="100" name="estimated_cost" placeholder="e.g. 85000" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Operational Justification</label>
                <textarea name="justification" rows="2" placeholder="Specify program need, procurement rationale, and tender details..." required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" onclick="document.getElementById('addPRModal').style.display='none'" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">Submit Requisition</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
