<?php
$pageTitle = 'Government Chart of Accounts';
require_once __DIR__ . '/config/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_account') {
    $code = trim($_POST['account_code'] ?? '');
    $name = trim($_POST['account_name'] ?? '');
    $type = $_POST['account_type'] ?? 'expense';

    if ($code === '' || $name === '') {
        $error = 'Account code and title are required.';
    } else {
        try {
            $sql = "INSERT INTO chart_of_accounts (account_code, account_name, account_type, is_active) VALUES (?, ?, ?, 1)";
            db_execute($sql, [$code, $name, $type]);
            record_audit('Created Chart of Account', 'Accounting', 'chart_of_accounts', $code, null, "Added {$name} ({$type})");
            $message = "Chart of account '{$name}' created.";
        } catch (Exception $e) {
            $error = 'Failed to create account: ' . $e->getMessage();
        }
    }
}

$accounts = db_select("
    SELECT c.*, 
           COALESCE(SUM(ji.debit), 0) as total_debit,
           COALESCE(SUM(ji.credit), 0) as total_credit
    FROM chart_of_accounts c
    LEFT JOIN journal_items ji ON ji.account_id = c.id
    GROUP BY c.id
    ORDER BY c.account_code ASC
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
            Standardized public financial classification schema aligning assets, liabilities, fund reserves, revenues, and expenditures.
        </p>
    </div>
    <button onclick="document.getElementById('addAccountModal').style.display='flex'" class="btn-sfgmis-green">
        <i data-lucide="plus-circle" style="width:16px;height:16px;"></i> Add Chart Account
    </button>
</div>

<div class="sfgmis-panel">
    <div class="sfgmis-panel-head">
        <h3><i data-lucide="book" style="width:18px;height:18px;color:var(--sl-green-700);"></i> Chart of Accounts Register</h3>
        <span style="font-size:0.8rem; color:#64748b; font-weight:600;"><?php echo count($accounts); ?> Accounts</span>
    </div>
    <div>
        <table class="sfgmis-table">
            <thead>
                <tr>
                    <th>Account Code</th>
                    <th>Account Description</th>
                    <th>Classification Type</th>
                    <th>Cumulative Debit</th>
                    <th>Cumulative Credit</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $acc): ?>
                <tr>
                    <td>
                        <span style="font-family:monospace; font-weight:700; color:var(--sl-green-800); background:#dcfce7; padding:3px 8px; border-radius:4px;">
                            <?php echo htmlspecialchars($acc['account_code']); ?>
                        </span>
                    </td>
                    <td><strong style="color:#0f172a;"><?php echo htmlspecialchars($acc['account_name']); ?></strong></td>
                    <td>
                        <span style="text-transform:uppercase; font-size:0.72rem; font-weight:700; color:#475569; background:#f1f5f9; padding:2px 8px; border-radius:12px;">
                            <?php echo htmlspecialchars($acc['account_type']); ?>
                        </span>
                    </td>
                    <td><strong style="color:#2563eb;"><?php echo format_currency((float)$acc['total_debit']); ?></strong></td>
                    <td><strong style="color:var(--sl-green-800);"><?php echo format_currency((float)$acc['total_credit']); ?></strong></td>
                    <td>
                        <span class="badge-status badge-active">Active</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── MODAL: ADD ACCOUNT ───────────────────────────────────────── -->
<div id="addAccountModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:100; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#ffffff; border-radius:12px; max-width:500px; width:100%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2); overflow:hidden;">
        <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.1rem; color:#0f172a; font-weight:700;">Add Chart of Account</h3>
            <button onclick="document.getElementById('addAccountModal').style.display='none'" style="border:none; background:transparent; cursor:pointer; color:#94a3b8;">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" style="padding:20px;">
            <input type="hidden" name="action" value="add_account">
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Account Code *</label>
                <input type="text" name="account_code" placeholder="e.g. 5040" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Account Name / Title *</label>
                <input type="text" name="account_name" placeholder="e.g. Public Health Infrastructure Grants" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Classification Type *</label>
                <select name="account_type" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                    <option value="asset">1000 - Asset</option>
                    <option value="liability">2000 - Liability</option>
                    <option value="equity">3000 - Equity / Consolidated Reserve</option>
                    <option value="revenue">4000 - Government Revenue</option>
                    <option value="expense" selected>5000 - Government Expenditure</option>
                </select>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" onclick="document.getElementById('addAccountModal').style.display='none'" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">Save Account</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
