<?php
$pageTitle = 'Purchase Orders & Procure-to-Pay (P2P)';
require_once __DIR__ . '/config/db.php';

$message = '';
$error = '';

// Handle Status Advancement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'advance_status') {
        $poId = (int)$_POST['po_id'];
        $nextStatus = $_POST['next_status'];
        try {
            $po = db_row("SELECT * FROM purchase_orders WHERE id = ?", [$poId]);
            db_execute("UPDATE purchase_orders SET status = ? WHERE id = ?", [$nextStatus, $poId]);
            record_audit("Updated PO Status to {$nextStatus}", 'Procurement Management', 'purchase_orders', $po['po_number'], "status: {$po['status']}", "status: {$nextStatus}");
            $message = "Purchase Order {$po['po_number']} advanced to status: " . strtoupper(str_replace('_', ' ', $nextStatus)) . ".";
        } catch (Exception $e) {
            $error = 'Failed to advance status: ' . $e->getMessage();
        }
    }

    // Handle Create PO
    if ($_POST['action'] === 'create_po') {
        $vendorId = (int)$_POST['vendor_id'];
        $orgId = (int)$_POST['organization_id'];
        $prId = !empty($_POST['purchase_request_id']) ? (int)$_POST['purchase_request_id'] : null;
        $amount = (float)$_POST['total_amount'];
        $terms = trim($_POST['payment_terms'] ?? 'Net 30 Days upon delivery');
        $deliveryDate = $_POST['delivery_date'] ?? date('Y-m-d', strtotime('+30 days'));
        $officerId = current_user()['id'] ?? 8;
        $poNum = generate_code('purchase_orders', 'po_number', 'PO');

        if ($amount <= 0) {
            $error = 'Valid total amount is required.';
        } else {
            try {
                $sql = "INSERT INTO purchase_orders (po_number, purchase_request_id, vendor_id, organization_id, total_amount, payment_terms, delivery_date, status, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'issued', ?)";
                db_execute($sql, [$poNum, $prId, $vendorId, $orgId, $amount, $terms, $deliveryDate, $officerId]);
                
                if ($prId) {
                    db_execute("UPDATE purchase_requests SET status = 'approved' WHERE id = ?", [$prId]);
                }

                record_audit('Issued Purchase Order', 'Procurement Management', 'purchase_orders', $poNum, null, "Contracted " . format_currency($amount));
                $message = "Purchase Order {$poNum} successfully issued to vendor.";
            } catch (Exception $e) {
                $error = 'Failed to issue purchase order: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all POs with Vendor and Org
$orders = db_select("
    SELECT po.*, v.name as vendor_name, v.vendor_code, v.bank_name,
           o.name as org_name, o.code as org_code,
           pr.request_no as pr_ref
    FROM purchase_orders po
    JOIN vendors v ON v.id = po.vendor_id
    JOIN organizations o ON o.id = po.organization_id
    LEFT JOIN purchase_requests pr ON pr.id = po.purchase_request_id
    ORDER BY po.id DESC
");

$vendorsList = db_select("SELECT id, name, vendor_code FROM vendors WHERE status = 'vetted' ORDER BY name ASC");
$orgsList = db_select("SELECT id, name, code FROM organizations WHERE type != 'government' ORDER BY name ASC");
$prList = db_select("SELECT id, request_no, title, estimated_cost FROM purchase_requests WHERE status = 'pending' ORDER BY id DESC");

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
            Binding contractual purchase orders issued to vetted suppliers and tracking fulfilment.
        </p>
    </div>
    <button onclick="document.getElementById('addPOModal').style.display='flex'" class="btn-sfgmis-green">
        <i data-lucide="shopping-cart" style="width:16px;height:16px;"></i> Issue Purchase Order
    </button>
</div>

<!-- ── PURCHASE ORDERS TABLE ────────────────────────────────────── -->
<div class="sfgmis-panel">
    <div class="sfgmis-panel-head">
        <h3><i data-lucide="package" style="width:18px;height:18px;color:var(--sl-green-700);"></i> Purchase Orders Register</h3>
        <span style="font-size:0.8rem; color:#64748b; font-weight:600;"><?php echo count($orders); ?> Orders</span>
    </div>
    <div>
        <table class="sfgmis-table">
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Contracted Vendor</th>
                    <th>Procuring Ministry</th>
                    <th>Contract Value</th>
                    <th>Payment Terms</th>
                    <th>Delivery Deadline</th>
                    <th>Fulfillment Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $po): ?>
                <tr>
                    <td>
                        <strong style="color:var(--sl-green-800); font-family:monospace;"><?php echo htmlspecialchars($po['po_number']); ?></strong>
                        <?php if ($po['pr_ref']): ?>
                            <div style="font-size:0.7rem; color:#94a3b8;">From: <?php echo htmlspecialchars($po['pr_ref']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong style="color:#0f172a;"><?php echo htmlspecialchars($po['vendor_name']); ?></strong>
                        <div style="font-size:0.72rem; color:#64748b;"><?php echo htmlspecialchars($po['vendor_code']); ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600; color:#1e293b;"><?php echo htmlspecialchars($po['org_name']); ?></div>
                    </td>
                    <td><strong style="color:#0f172a; font-size:1rem;"><?php echo format_currency((float)$po['total_amount']); ?></strong></td>
                    <td style="font-size:0.75rem; color:#64748b; max-width:160px;"><?php echo htmlspecialchars($po['payment_terms']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($po['delivery_date'])); ?></td>
                    <td>
                        <span class="badge-status badge-<?php echo htmlspecialchars($po['status']); ?>">
                            <?php echo htmlspecialchars(str_replace('_', ' ', $po['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($po['status'] === 'issued'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="advance_status">
                            <input type="hidden" name="po_id" value="<?php echo $po['id']; ?>">
                            <input type="hidden" name="next_status" value="goods_received">
                            <button type="submit" class="btn-sfgmis-outline" style="font-size:0.72rem; padding:3px 6px;" title="Confirm Goods/Service Received">
                                <i data-lucide="check" style="width:12px;height:12px;"></i> Confirm Delivery
                            </button>
                        </form>
                        <?php elseif ($po['status'] === 'goods_received'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="advance_status">
                            <input type="hidden" name="po_id" value="<?php echo $po['id']; ?>">
                            <input type="hidden" name="next_status" value="invoiced">
                            <button type="submit" class="btn-sfgmis-outline" style="font-size:0.72rem; padding:3px 6px;">
                                <i data-lucide="file-text" style="width:12px;height:12px;"></i> Mark Invoiced
                            </button>
                        </form>
                        <?php else: ?>
                            <span style="font-size:0.75rem; color:#166534; font-weight:600;">Fulfilled</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── MODAL: CREATE PURCHASE ORDER ─────────────────────────────── -->
<div id="addPOModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:100; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#ffffff; border-radius:12px; max-width:540px; width:100%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2); overflow:hidden;">
        <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.1rem; color:#0f172a; font-weight:700;">Issue Government Purchase Order</h3>
            <button onclick="document.getElementById('addPOModal').style.display='none'" style="border:none; background:transparent; cursor:pointer; color:#94a3b8;">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" style="padding:20px;">
            <input type="hidden" name="action" value="create_po">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Contracted Vetted Vendor *</label>
                    <select name="vendor_id" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                        <?php foreach ($vendorsList as $v): ?>
                        <option value="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['name'] . ' (' . $v['vendor_code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Procuring Ministry *</label>
                    <select name="organization_id" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                        <?php foreach ($orgsList as $o): ?>
                        <option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['name'] . ' (' . $o['code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Link to Approved Requisition (Optional)</label>
                <select name="purchase_request_id" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                    <option value="">Direct Tender / No Preceding PR</option>
                    <?php foreach ($prList as $pr): ?>
                    <option value="<?php echo $pr['id']; ?>"><?php echo htmlspecialchars($pr['request_no'] . ' - ' . $pr['title'] . ' (' . format_currency((float)$pr['estimated_cost']) . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Total PO Amount ($) *</label>
                    <input type="number" step="100" name="total_amount" placeholder="e.g. 150000" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Delivery Due Date</label>
                    <input type="date" name="delivery_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Payment Terms & Contractual Conditions</label>
                <input type="text" name="payment_terms" value="Net 30 Days upon certified delivery and inspection" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" onclick="document.getElementById('addPOModal').style.display='none'" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">Issue Binding PO</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
