<?php
$pageTitle = 'General Ledger & Journal Entries';
require_once __DIR__ . '/config/db.php';

$message = '';
$error = '';

// Handle Create Manual Journal Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_journal') {
    $desc = trim($_POST['description']);
    $ref = trim($_POST['reference'] ?? '');
    $orgId = (int)$_POST['organization_id'];
    $debitAccId = (int)$_POST['debit_account_id'];
    $creditAccId = (int)$_POST['credit_account_id'];
    $amount = (float)$_POST['amount'];
    $officerId = current_user()['id'] ?? 7;
    $entryNo = generate_code('journal_entries', 'entry_number', 'JRN');

    if ($amount <= 0 || $debitAccId === $creditAccId) {
        $error = 'Valid amount is required and Debit/Credit accounts must be different.';
    } else {
        try {
            $sql = "INSERT INTO journal_entries (entry_number, entry_date, reference, description, organization_id, status, created_by)
                    VALUES (?, CURDATE(), ?, ?, ?, 'posted', ?)";
            db_execute($sql, [$entryNo, $ref, $desc, $orgId, $officerId]);
            $jrnId = db_row("SELECT id FROM journal_entries WHERE entry_number = ?", [$entryNo])['id'];

            // Debit item
            db_execute("INSERT INTO journal_items (entry_id, account_id, debit, credit, line_description) VALUES (?, ?, ?, 0.00, ?)", [$jrnId, $debitAccId, $amount, $desc]);
            // Credit item
            db_execute("INSERT INTO journal_items (entry_id, account_id, debit, credit, line_description) VALUES (?, ?, 0.00, ?, ?)", [$jrnId, $creditAccId, $amount, $desc]);

            record_audit('Posted Journal Entry', 'Accounting', 'journal_entries', $entryNo, null, "Posted double-entry for " . format_currency($amount));
            $message = "Double-entry Journal {$entryNo} successfully posted to General Ledger.";
        } catch (Exception $e) {
            $error = 'Failed to post entry: ' . $e->getMessage();
        }
    }
}

// Fetch all journal entries with their line items
$entries = db_select("
    SELECT j.*, o.name as org_name, o.code as org_code, u.name as created_by_name
    FROM journal_entries j
    JOIN organizations o ON o.id = j.organization_id
    LEFT JOIN users u ON u.id = j.created_by
    ORDER BY j.entry_date DESC, j.id DESC
");

// Preload items
$itemsByEntry = [];
$items = db_select("
    SELECT ji.*, c.account_code, c.account_name, c.account_type
    FROM journal_items ji
    JOIN chart_of_accounts c ON c.id = ji.account_id
    ORDER BY ji.id ASC
");
foreach ($items as $it) {
    $itemsByEntry[$it['entry_id']][] = $it;
}

$accountsList = db_select("SELECT id, account_code, account_name, account_type FROM chart_of_accounts ORDER BY account_code ASC");
$orgsList = db_select("SELECT id, name, code FROM organizations ORDER BY name ASC");

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
            Immutable double-entry government general ledger tracking every debit and credit transaction.
        </p>
    </div>
    <button onclick="document.getElementById('addJournalModal').style.display='flex'" class="btn-sfgmis-green">
        <i data-lucide="plus-circle" style="width:16px;height:16px;"></i> Post Journal Entry
    </button>
</div>

<!-- ── GENERAL LEDGER ENTRIES ───────────────────────────────────── -->
<div class="sfgmis-panel">
    <div class="sfgmis-panel-head">
        <h3><i data-lucide="book-open" style="width:18px;height:18px;color:var(--sl-green-700);"></i> Double-Entry General Ledger</h3>
        <span style="font-size:0.8rem; color:#64748b; font-weight:600;"><?php echo count($entries); ?> Entries Posted</span>
    </div>
    <div>
        <?php foreach ($entries as $e): 
            $lines = $itemsByEntry[$e['id']] ?? [];
        ?>
        <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                <div>
                    <span style="font-family:monospace; font-weight:800; font-size:0.95rem; color:var(--sl-green-800); background:#dcfce7; padding:3px 8px; border-radius:4px;">
                        <?php echo htmlspecialchars($e['entry_number']); ?>
                    </span>
                    <span style="font-size:0.85rem; font-weight:700; color:#0f172a; margin-left:8px;">
                        <?php echo htmlspecialchars($e['description']); ?>
                    </span>
                </div>
                <div style="display:flex; align-items:center; gap:10px; font-size:0.75rem; color:#64748b;">
                    <span>Ref: <strong><?php echo htmlspecialchars($e['reference'] ?? 'Standard'); ?></strong></span> &bull;
                    <span>MDA: <strong><?php echo htmlspecialchars($e['org_code']); ?></strong></span> &bull;
                    <span>Date: <strong><?php echo date('M d, Y', strtotime($e['entry_date'])); ?></strong></span> &bull;
                    <span class="badge-status badge-active"><?php echo htmlspecialchars($e['status']); ?></span>
                </div>
            </div>

            <!-- Double-entry Line Items Table -->
            <table style="width:100%; border-collapse:collapse; font-size:0.82rem; background:#f8fafc; border-radius:6px; overflow:hidden;">
                <thead>
                    <tr style="border-bottom:1px solid #e2e8f0; color:#64748b; text-align:left;">
                        <th style="padding:6px 12px;">Account Code & Name</th>
                        <th style="padding:6px 12px;">Type</th>
                        <th style="padding:6px 12px; text-align:right;">Debit ($)</th>
                        <th style="padding:6px 12px; text-align:right;">Credit ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line): ?>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:6px 12px;">
                            <strong style="color:#0f172a; font-family:monospace;"><?php echo htmlspecialchars($line['account_code']); ?></strong> — <?php echo htmlspecialchars($line['account_name']); ?>
                        </td>
                        <td style="padding:6px 12px; text-transform:uppercase; font-size:0.7rem; color:#64748b;">
                            <?php echo htmlspecialchars($line['account_type']); ?>
                        </td>
                        <td style="padding:6px 12px; text-align:right; font-weight:700; color:#2563eb;">
                            <?php echo $line['debit'] > 0 ? format_currency((float)$line['debit']) : '—'; ?>
                        </td>
                        <td style="padding:6px 12px; text-align:right; font-weight:700; color:var(--sl-green-800);">
                            <?php echo $line['credit'] > 0 ? format_currency((float)$line['credit']) : '—'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── MODAL: POST JOURNAL ENTRY ────────────────────────────────── -->
<div id="addJournalModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:100; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#ffffff; border-radius:12px; max-width:540px; width:100%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2); overflow:hidden;">
        <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.1rem; color:#0f172a; font-weight:700;">Post Double-Entry Journal</h3>
            <button onclick="document.getElementById('addJournalModal').style.display='none'" style="border:none; background:transparent; cursor:pointer; color:#94a3b8;">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form method="POST" style="padding:20px;">
            <input type="hidden" name="action" value="add_journal">
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Journal Description / Voucher Narration *</label>
                <input type="text" name="description" placeholder="e.g. Reconciliation of Customs Duty with Central Bank" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Ministry / Organization</label>
                    <select name="organization_id" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                        <?php foreach ($orgsList as $o): ?>
                        <option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['name'] . ' (' . $o['code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Reference No / Audit Voucher</label>
                    <input type="text" name="reference" placeholder="e.g. TREASURY-VCH-8829" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Debit Account (Dr) *</label>
                <select name="debit_account_id" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                    <?php foreach ($accountsList as $acc): ?>
                    <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['account_code'] . ' - ' . $acc['account_name'] . ' (' . $acc['account_type'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Credit Account (Cr) *</label>
                <select name="credit_account_id" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
                    <?php foreach ($accountsList as $acc): ?>
                    <option value="<?php echo $acc['id']; ?>" <?php echo $acc['account_code'] === '1010' ? 'selected' : ''; ?>><?php echo htmlspecialchars($acc['account_code'] . ' - ' . $acc['account_name'] . ' (' . $acc['account_type'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Balanced Transaction Amount ($) *</label>
                <input type="number" step="100" name="amount" placeholder="e.g. 75000" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; box-sizing:border-box;">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" onclick="document.getElementById('addJournalModal').style.display='none'" class="btn-sfgmis-outline">Cancel</button>
                <button type="submit" class="btn-sfgmis-green">Post to General Ledger</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
