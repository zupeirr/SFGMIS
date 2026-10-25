<?php
$pageTitle = 'Government Fixed Asset Registry (Section 15)';
require_once __DIR__ . '/config/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_asset') {
    $name = trim($_POST['asset_name'] ?? '');
    $category = $_POST['category'] ?? 'vehicles';
    $orgId = (int)$_POST['organization_id'];
    $department = trim($_POST['department'] ?? 'General');
    $purchaseDate = $_POST['purchase_date'] ?? date('Y-m-d');
    $cost = (float)$_POST['purchase_cost'];
    $currentVal = (float)$_POST['current_value'];
    $location = trim($_POST['location'] ?? 'Hargeisa');
    $condition = $_POST['condition_status'] ?? 'good';
    $officer = trim($_POST['responsible_officer'] ?? '');
    $code = generate_code('assets', 'asset_code', 'AST');

    if ($name === '' || $cost <= 0) {
        $error = 'Valid asset description and purchase cost are required.';
    } else {
        try {
            $sql = "INSERT INTO assets (asset_code, asset_name, category, organization_id, department, purchase_date, purchase_cost, current_value, location, condition_status, responsible_officer, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_use')";
            db_execute($sql, [$code, $name, $category, $orgId, $department, $purchaseDate, $cost, $currentVal, $location, $condition, $officer]);
            record_audit('Registered Fixed Asset', 'Asset Management', 'assets', $code, null, "Added {$name} valued at " . format_currency($cost));
            $message = "Government Asset {$code} registered successfully.";
        } catch (Exception $e) {
            $error = 'Failed to register asset: ' . $e->getMessage();
        }
    }
}

$catFilter = trim($_GET['category'] ?? 'all');
$whereAsset = " WHERE 1=1 ";
$paramsAsset = [];
if ($catFilter !== 'all') {
    $whereAsset .= " AND a.category LIKE ? ";
    $paramsAsset[] = '%' . $catFilter . '%';
}

$assets = db_select("
    SELECT a.*, o.name as org_name, o.code as org_code
    FROM assets a
    JOIN organizations o ON o.id = a.organization_id
    {$whereAsset}
    ORDER BY a.purchase_cost DESC
", $paramsAsset);

$totalAssetValuation = (float)db_row("SELECT SUM(current_value) as t FROM assets WHERE status != 'disposed'")['t'];
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

<!-- ── ASSET VALUATION STATS ────────────────────────────────────── -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
        <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">TOTAL ASSET VALUATION</div>
        <div style="font-size:1.6rem; font-weight:800; color:var(--sl-green-800); margin-top:4px;"><?php echo format_currency($totalAssetValuation); ?></div>
        <div style="font-size:0.75rem; color:#64748b; margin-top:4px;">Net Book Value across MDAs</div>
    </div>
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
        <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">REGISTERED ASSETS</div>
        <div style="font-size:1.6rem; font-weight:800; color:#0f172a; margin-top:4px;"><?php echo count($assets); ?> Units</div>
        <div style="font-size:0.75rem; color:#64748b; margin-top:4px;">Vehicles, IT, Civil Infrastructure</div>
    </div>
</div>

<!-- ── SUBPAGE TABS (All Assets, Vehicles, Medical, Machinery, IT & Equipment) ── -->
<div class="sfgmis-tabs" style="margin-bottom:20px;">
    <a href="assets.php?category=all" class="sfgmis-tab-item <?php echo $catFilter === 'all' ? 'active' : ''; ?>">
        <i data-lucide="layers" style="width:15px;height:15px;"></i> All Assets
    </a>
    <a href="assets.php?category=Vehicles" class="sfgmis-tab-item <?php echo $catFilter === 'Vehicles' ? 'active' : ''; ?>">
        <i data-lucide="truck" style="width:15px;height:15px;"></i> Vehicles & Fleet
    </a>
    <a href="assets.php?category=Medical" class="sfgmis-tab-item <?php echo $catFilter === 'Medical' ? 'active' : ''; ?>">
        <i data-lucide="heart-pulse" style="width:15px;height:15px;"></i> Medical & Health
    </a>
    <a href="assets.php?category=Machinery" class="sfgmis-tab-item <?php echo $catFilter === 'Machinery' ? 'active' : ''; ?>">
        <i data-lucide="hammer" style="width:15px;height:15px;"></i> Road Machinery
    </a>
    <a href="assets.php?category=IT" class="sfgmis-tab-item <?php echo $catFilter === 'IT' ? 'active' : ''; ?>">
        <i data-lucide="monitor" style="width:15px;height:15px;"></i> IT & Telemetry
    </a>
</div>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">
            Central inventory tracking Somaliland government vehicles, heavy road machinery, medical equipment, and real property.
        </p>
    </div>
    <button onclick="document.getElementById('addAssetModal').style.display='flex'" class="btn-sfgmis-green">
        <i data-lucide="plus-circle" style="width:16px;height:16px;"></i> Register Fixed Asset
    </button>
</div>

<div class="sfgmis-panel">
    <div class="sfgmis-panel-head">
        <h3><i data-lucide="package" style="width:18px;height:18px;color:var(--sl-green-700);"></i> Government Fixed Asset Registry</h3>
        <span style="font-size:0.8rem; color:#64748b; font-weight:600;"><?php echo count($assets); ?> Assets</span>
    </div>
    <div>
        <table class="sfgmis-table">
            <thead>
                <tr>
                    <th>Asset Code</th>
                    <th>Asset Description</th>
                    <th>Category</th>
                    <th>Assigned Ministry</th>
                    <th>Location</th>
                    <th>Purchase Cost</th>
                    <th>Current Value</th>
                    <th>Condition</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assets as $a): ?>
                <tr>
                    <td>
                        <span style="font-family:monospace; font-weight:700; color:var(--sl-green-800); background:#dcfce7; padding:3px 8px; border-radius:4px;">
                            <?php echo htmlspecialchars($a['asset_code']); ?>
                        </span>
                    </td>
                    <td>
                        <strong style="color:#0f172a;"><?php echo htmlspecialchars($a['asset_name']); ?></strong>
                        <div style="font-size:0.72rem; color:#64748b;">Custodian: <?php echo htmlspecialchars($a['responsible_officer'] ?? 'Not Assigned'); ?></div>
                    </td>
                    <td>
                        <span style="text-transform:uppercase; font-size:0.7rem; font-weight:700; color:#475569; background:#f1f5f9; padding:2px 8px; border-radius:12px;">
                            <?php echo htmlspecialchars(str_replace('_', ' ', $a['category'])); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($a['org_code']); ?></td>
                    <td><?php echo htmlspecialchars($a['location']); ?></td>
                    <td><?php echo format_currency((float)$a['purchase_cost']); ?></td>
                    <td><strong style="color:var(--sl-green-800);"><?php echo format_currency((float)$a['current_value']); ?></strong></td>
                    <td>
                        <span style="font-size:0.75rem; font-weight:600; text-transform:uppercase; color:#1e293b;">
                            <?php echo htmlspecialchars($a['condition_status']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge-status badge-active"><?php echo htmlspecialchars(str_replace('_', ' ', $a['status'])); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── MODAL: REGISTER ASSET ────────────────────────────────────── -->
<div id="addAssetModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:100; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#ffffff; border-radius:12px; max-width:540px; width:100%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2); overflow:hidden;">
        <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.1rem; color:#0f172a; font-weight:700;">Register Fixed Asset</h3>
            <button onclick="document.getElementById('addAssetModal').style.display='none'" style="border:none; background:transparent; cursor:pointer; color:#94a3b8;">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" style="padding:20px;">
            <input type="hidden" name="action" value="add_asset">
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Asset Name / Description *</label>
                <input type="text" name="asset_name" placeholder="e.g. Toyota Land Cruiser Ambulance or Ultrasound Machine" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Category *</label>
                    <select name="category" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                        <option value="vehicles">Vehicles & Transport Fleet</option>
                        <option value="it_equipment">IT & Telecommunications Hardware</option>
                        <option value="buildings">Real Property & Buildings</option>
                        <option value="office_furniture">Office Equipment & Fixtures</option>
                        <option value="infrastructure">Civil Infrastructure & Heavy Machinery</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Assigned Ministry *</label>
                    <select name="organization_id" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                        <?php foreach ($orgsList as $o): ?>
                        <option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['name'] . ' (' . $o['code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Purchase Cost ($) *</label>
                    <input type="number" step="100" name="purchase_cost" placeholder="e.g. 65000" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Current Book Value ($) *</label>
                    <input type="number" step="100" name="current_value" placeholder="e.g. 58000" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Physical Location</label>
                    <input type="text" name="location" placeholder="e.g. Burao Regional Hospital" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Condition Status</label>
                    <select name="condition_status" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                        <option value="new">Brand New</option>
                        <option value="good" selected>Good Working Condition</option>
                        <option value="fair">Fair (Routine Maintenance Due)</option>
                        <option value="needs_repair">Needs Major Repair</option>
                    </select>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" onclick="document.getElementById('addAssetModal').style.display='none'" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">Register Asset</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
