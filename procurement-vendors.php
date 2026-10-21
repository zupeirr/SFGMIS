<?php
$pageTitle = 'Government Vendor Registry & Vetting';
require_once __DIR__ . '/config/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_vendor') {
    $name = trim($_POST['name'] ?? '');
    $regNo = trim($_POST['registration_no'] ?? '');
    $taxId = trim($_POST['tax_id'] ?? '');
    $contact = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');
    $bankAccount = trim($_POST['bank_account'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $code = generate_code('vendors', 'vendor_code', 'VND');

    if ($name === '' || $taxId === '') {
        $error = 'Vendor official legal name and tax ID are required.';
    } else {
        try {
            $sql = "INSERT INTO vendors (vendor_code, name, registration_no, tax_id, contact_person, email, phone, address, bank_name, bank_account, status, category)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'vetted', ?)";
            db_execute($sql, [$code, $name, $regNo, $taxId, $contact, $email, $phone, $address, $bankName, $bankAccount, $category]);
            record_audit('Registered Vendor', 'Procurement Management', 'vendors', $code, null, "Registered {$name} (Tax: {$taxId})");
            $message = "Vendor '{$name}' successfully registered and marked as VETTED.";
        } catch (Exception $e) {
            $error = 'Failed to register vendor: ' . $e->getMessage();
        }
    }
}

$vendors = db_select("
    SELECT v.*, COUNT(p.id) as total_pos, COALESCE(SUM(p.total_amount), 0) as total_contracted
    FROM vendors v
    LEFT JOIN purchase_orders p ON p.vendor_id = v.id
    GROUP BY v.id
    ORDER BY v.id ASC
");

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
            Central government vetted contractors, suppliers, and service providers.
        </p>
    </div>
    <button onclick="document.getElementById('addVendorModal').style.display='flex'" class="btn-sfgmis-green">
        <i data-lucide="plus-circle" style="width:16px;height:16px;"></i> Register Vetted Vendor
    </button>
</div>

<div class="sfgmis-panel">
    <div class="sfgmis-panel-head">
        <h3><i data-lucide="briefcase" style="width:18px;height:18px;color:var(--sl-green-700);"></i> Registered Suppliers Directory</h3>
        <span style="font-size:0.8rem; color:#64748b; font-weight:600;"><?php echo count($vendors); ?> Suppliers</span>
    </div>
    <div>
        <table class="sfgmis-table">
            <thead>
                <tr>
                    <th>Vendor Code</th>
                    <th>Company Legal Name</th>
                    <th>Category</th>
                    <th>Tax ID / CR</th>
                    <th>Banking Details</th>
                    <th>Total Orders</th>
                    <th>Vetting Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendors as $v): ?>
                <tr>
                    <td>
                        <span style="font-family:monospace; font-weight:700; color:var(--sl-green-800); background:#dcfce7; padding:3px 8px; border-radius:4px;">
                            <?php echo htmlspecialchars($v['vendor_code']); ?>
                        </span>
                    </td>
                    <td>
                        <strong style="color:#0f172a;"><?php echo htmlspecialchars($v['name']); ?></strong>
                        <div style="font-size:0.75rem; color:#64748b;">Contact: <?php echo htmlspecialchars($v['contact_person'] ?? ''); ?> (<?php echo htmlspecialchars($v['phone'] ?? ''); ?>)</div>
                    </td>
                    <td>
                        <span style="font-size:0.8rem; color:#334155;"><?php echo htmlspecialchars($v['category'] ?? 'General Supplier'); ?></span>
                    </td>
                    <td>
                        <div style="font-family:monospace; font-size:0.75rem; color:#475569;"><?php echo htmlspecialchars($v['tax_id']); ?></div>
                        <div style="font-size:0.7rem; color:#94a3b8;"><?php echo htmlspecialchars($v['registration_no'] ?? ''); ?></div>
                    </td>
                    <td>
                        <div style="font-size:0.8rem; font-weight:600; color:#0f172a;"><?php echo htmlspecialchars($v['bank_name'] ?? '—'); ?></div>
                        <div style="font-size:0.72rem; color:#64748b; font-family:monospace;"><?php echo htmlspecialchars($v['bank_account'] ?? ''); ?></div>
                    </td>
                    <td>
                        <strong><?php echo $v['total_pos']; ?> POs</strong>
                        <div style="font-size:0.72rem; color:var(--sl-green-800); font-weight:600;"><?php echo format_currency((float)$v['total_contracted']); ?></div>
                    </td>
                    <td>
                        <span class="badge-status badge-<?php echo htmlspecialchars($v['status']); ?>">
                            <?php echo htmlspecialchars($v['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── MODAL: REGISTER VENDOR ───────────────────────────────────── -->
<div id="addVendorModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:100; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#ffffff; border-radius:12px; max-width:540px; width:100%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2); overflow:hidden;">
        <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.1rem; color:#0f172a; font-weight:700;">Register Vetted Government Supplier</h3>
            <button onclick="document.getElementById('addVendorModal').style.display='none'" style="border:none; background:transparent; cursor:pointer; color:#94a3b8;">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" style="padding:20px;">
            <input type="hidden" name="action" value="add_vendor">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Legal Company Name *</label>
                    <input type="text" name="name" placeholder="e.g. Somaliland Logistics Co." required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Category / Industry *</label>
                    <input type="text" name="category" placeholder="e.g. Civil Works or IT" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Taxpayer Identification (TIN) *</label>
                    <input type="text" name="tax_id" placeholder="e.g. TIN-992019" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Commercial Registry (CR) #</label>
                    <input type="text" name="registration_no" placeholder="e.g. CR-48201" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Primary Contact Officer</label>
                    <input type="text" name="contact_person" placeholder="e.g. Mohamed Omar" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Official Phone</label>
                    <input type="text" name="phone" placeholder="+252 63 4400111" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Authorized Bank Name</label>
                    <input type="text" name="bank_name" placeholder="e.g. Dahabshiil Bank or Salaam Bank" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Bank Account Number / IBAN</label>
                    <input type="text" name="bank_account" placeholder="e.g. DIB-9920194" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" onclick="document.getElementById('addVendorModal').style.display='none'" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">Complete Vetting & Save</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
