<?php
/**
 * SFGMIS Database Initializer & Seed Generator
 * Somaliland Government Financial Management Platform
 * 
 * Supports both MySQL (when running) and SQLite (automatic zero-config fallback)
 */

function sfgmis_init_database(PDO $pdo, string $driver = 'mysql') {
    // Check if tables already exist
    $checkSql = ($driver === 'sqlite') 
        ? "SELECT count(*) FROM sqlite_master WHERE type='table' AND name='organizations'"
        : "SHOW TABLES LIKE 'organizations'";
    
    $exists = false;
    try {
        $stmt = $pdo->query($checkSql);
        $exists = (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    if ($exists) {
        return; // Already initialized
    }

    // SQLite or MySQL table creation
    if ($driver === 'sqlite') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT UNIQUE,
                name TEXT,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS organizations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT UNIQUE,
                name TEXT,
                type TEXT DEFAULT 'mda',
                parent_id INTEGER,
                responsible_officer TEXT,
                email TEXT,
                phone TEXT,
                budget_ceiling REAL DEFAULT 0.00,
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT UNIQUE,
                name TEXT,
                email TEXT UNIQUE,
                password_hash TEXT,
                role_id INTEGER,
                organization_id INTEGER,
                designation TEXT,
                phone TEXT,
                status TEXT DEFAULT 'active',
                last_login_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS fiscal_years (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE,
                start_date DATE,
                end_date DATE,
                total_budget REAL DEFAULT 0.00,
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS budget_cycles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                fiscal_year_id INTEGER,
                cycle_code TEXT UNIQUE,
                name TEXT,
                start_date DATE,
                end_date DATE,
                ceiling_amount REAL DEFAULT 0.00,
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS budget_proposals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                proposal_code TEXT UNIQUE,
                organization_id INTEGER,
                fiscal_year_id INTEGER,
                department TEXT,
                program TEXT,
                activity TEXT,
                total_requested REAL DEFAULT 0.00,
                approved_amount REAL DEFAULT 0.00,
                status TEXT DEFAULT 'submitted',
                submission_date DATE,
                reviewed_by INTEGER,
                reviewer_comments TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS budget_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                proposal_id INTEGER,
                category TEXT,
                line_item_code TEXT,
                description TEXT,
                requested_amount REAL DEFAULT 0.00,
                approved_amount REAL DEFAULT 0.00,
                justification TEXT
            );

            CREATE TABLE IF NOT EXISTS budget_allocations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER,
                fiscal_year_id INTEGER,
                proposal_id INTEGER,
                approved_budget REAL DEFAULT 0.00,
                allocated_amount REAL DEFAULT 0.00,
                committed_amount REAL DEFAULT 0.00,
                spent_amount REAL DEFAULT 0.00,
                remaining_balance REAL DEFAULT 0.00,
                allocation_date DATE,
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS revenue_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT UNIQUE,
                name TEXT,
                type TEXT DEFAULT 'tax',
                description TEXT
            );

            CREATE TABLE IF NOT EXISTS revenue_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reference_no TEXT UNIQUE,
                category_id INTEGER,
                organization_id INTEGER,
                payer_name TEXT,
                payer_tin TEXT,
                amount REAL,
                payment_method TEXT,
                date DATE,
                payment_status TEXT DEFAULT 'completed',
                notes TEXT,
                recorded_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS expenditure_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                request_no TEXT UNIQUE,
                organization_id INTEGER,
                fiscal_year_id INTEGER,
                budget_allocation_id INTEGER,
                budget_code TEXT,
                category TEXT,
                description TEXT,
                requested_amount REAL,
                available_budget_at_request REAL,
                status TEXT DEFAULT 'submitted',
                requesting_officer_id INTEGER,
                approved_by INTEGER,
                approval_date DATE,
                rejection_reason TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS vendors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                vendor_code TEXT UNIQUE,
                name TEXT,
                registration_no TEXT,
                tax_id TEXT,
                contact_person TEXT,
                email TEXT,
                phone TEXT,
                address TEXT,
                bank_name TEXT,
                bank_account TEXT,
                status TEXT DEFAULT 'vetted',
                category TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS purchase_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                request_no TEXT UNIQUE,
                organization_id INTEGER,
                title TEXT,
                department TEXT,
                estimated_cost REAL,
                budget_code TEXT,
                justification TEXT,
                status TEXT DEFAULT 'pending',
                requested_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS purchase_orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                po_number TEXT UNIQUE,
                purchase_request_id INTEGER,
                vendor_id INTEGER,
                organization_id INTEGER,
                total_amount REAL,
                payment_terms TEXT,
                delivery_date DATE,
                status TEXT DEFAULT 'issued',
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_number TEXT UNIQUE,
                po_id INTEGER,
                vendor_id INTEGER,
                amount REAL,
                tax_amount REAL DEFAULT 0.00,
                total_amount REAL,
                invoice_date DATE,
                due_date DATE,
                status TEXT DEFAULT 'received',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                payment_no TEXT UNIQUE,
                expenditure_id INTEGER,
                po_id INTEGER,
                vendor_id INTEGER,
                organization_id INTEGER,
                amount REAL,
                payment_method TEXT,
                status TEXT DEFAULT 'pending',
                approver_id INTEGER,
                approval_date DATE,
                paid_date DATE,
                voucher_reference TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS chart_of_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_code TEXT UNIQUE,
                account_name TEXT,
                account_type TEXT,
                is_active INTEGER DEFAULT 1
            );

            CREATE TABLE IF NOT EXISTS journal_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entry_number TEXT UNIQUE,
                entry_date DATE,
                reference TEXT,
                description TEXT,
                organization_id INTEGER,
                status TEXT DEFAULT 'posted',
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS journal_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entry_id INTEGER,
                account_id INTEGER,
                debit REAL DEFAULT 0.00,
                credit REAL DEFAULT 0.00,
                line_description TEXT
            );

            CREATE TABLE IF NOT EXISTS assets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                asset_code TEXT UNIQUE,
                asset_name TEXT,
                category TEXT,
                organization_id INTEGER,
                department TEXT,
                purchase_date DATE,
                purchase_cost REAL,
                current_value REAL,
                location TEXT,
                condition_status TEXT DEFAULT 'good',
                responsible_officer TEXT,
                status TEXT DEFAULT 'in_use',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                user_name TEXT,
                user_role TEXT,
                organization_id INTEGER,
                action TEXT,
                module TEXT,
                record_type TEXT,
                record_id TEXT,
                previous_value TEXT,
                new_value TEXT,
                ip_address TEXT DEFAULT '127.0.0.1',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS audit_findings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                finding_code TEXT UNIQUE,
                title TEXT,
                module TEXT,
                severity TEXT DEFAULT 'medium',
                organization_id INTEGER,
                description TEXT,
                recommendation TEXT,
                corrective_action TEXT,
                status TEXT DEFAULT 'open',
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS app_settings (
                setting_key TEXT PRIMARY KEY,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                token TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document_no TEXT UNIQUE,
                title TEXT,
                category TEXT DEFAULT 'general',
                organization_id INTEGER,
                fiscal_year_id INTEGER,
                reference_module TEXT,
                reference_id TEXT,
                file_name TEXT,
                file_size TEXT DEFAULT '1.2 MB',
                file_type TEXT DEFAULT 'application/pdf',
                description TEXT,
                uploaded_by INTEGER,
                security_classification TEXT DEFAULT 'internal',
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                target_role TEXT,
                title TEXT,
                message TEXT,
                type TEXT DEFAULT 'system_alert',
                link_url TEXT,
                is_read INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS publications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pub_code TEXT UNIQUE,
                title TEXT NOT NULL,
                category TEXT DEFAULT 'general',
                subcategory TEXT,
                description TEXT,
                file_name TEXT,
                file_path TEXT,
                file_size TEXT DEFAULT '0 KB',
                file_type TEXT DEFAULT 'application/pdf',
                cover_image TEXT,
                fiscal_year TEXT,
                author TEXT,
                published_date DATE,
                is_featured INTEGER DEFAULT 0,
                download_count INTEGER DEFAULT 0,
                status TEXT DEFAULT 'published',
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS news_articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT UNIQUE,
                title TEXT NOT NULL,
                category TEXT DEFAULT 'general',
                excerpt TEXT,
                body TEXT,
                featured_image TEXT,
                author TEXT DEFAULT 'Communications Office',
                published_date DATE,
                is_featured INTEGER DEFAULT 0,
                is_published INTEGER DEFAULT 1,
                views INTEGER DEFAULT 0,
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS contact_inquiries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                inquiry_no TEXT UNIQUE,
                full_name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT,
                subject TEXT NOT NULL,
                category TEXT DEFAULT 'general',
                message TEXT NOT NULL,
                attachment_file TEXT,
                ip_address TEXT DEFAULT '127.0.0.1',
                status TEXT DEFAULT 'new',
                assigned_to INTEGER,
                response_notes TEXT,
                responded_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS eservices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                service_code TEXT UNIQUE,
                title TEXT NOT NULL,
                description TEXT,
                icon TEXT DEFAULT 'file-text',
                category TEXT DEFAULT 'general',
                url TEXT,
                is_external INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                badge TEXT,
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    // Now populate seed data
    $password_hash = password_hash('password', PASSWORD_DEFAULT);

    // 1. Roles
    $roles = [
        ['super_admin', 'Super Administrator', 'Full system access and configuration'],
        ['minister', 'Minister of Finance', 'National fiscal overview and strategic reporting'],
        ['state_minister', 'State Minister', 'Departmental monitoring and management oversight'],
        ['director_general', 'Director General', 'Ministry-wide approvals and operational monitoring'],
        ['budget_officer', 'Budget Officer', 'Budget cycles, ceilings, proposals, and allocations'],
        ['finance_officer', 'Finance Officer', 'Expenditures, commitments, and payment requests'],
        ['accountant', 'Accountant General', 'General ledger, chart of accounts, and financial reconciliation'],
        ['procurement_officer', 'Procurement Officer', 'Vendors, requisitions, and purchase orders'],
        ['auditor', 'Auditor General', 'Oversight, transaction monitoring, and audit findings'],
        ['mda_officer', 'MDA Finance Officer', 'Line ministry proposals, requests, and tracking']
    ];

    $stmtRole = $pdo->prepare("INSERT INTO roles (slug, name, description) VALUES (?, ?, ?)");
    foreach ($roles as $r) {
        $stmtRole->execute($r);
    }

    // 2. Organizations
    $orgs = [
        ['GOV-SL', 'Government of Somaliland', 'government', null, 'President of Somaliland', 'contact@somalilandgov.com', '+252 63 4400001', 350000000.00],
        ['MOFED', 'Ministry of Finance & Economic Development', 'ministry', 1, 'Dr. Saad Ali Shire', 'minister@mof-sl.org', '+252 63 4410001', 85000000.00],
        ['MOH', 'Ministry of Health Development', 'mda', 1, 'Dr. Hassan Ismail', 'finance@health-sl.org', '+252 63 4420002', 42000000.00],
        ['MOES', 'Ministry of Education and Science', 'mda', 1, 'Prof. Ahmed Mohamed', 'finance@education-sl.org', '+252 63 4430003', 48000000.00],
        ['MOTRD', 'Ministry of Transport & Roads Development', 'mda', 1, 'Eng. Abdirashid Muse', 'finance@transport-sl.org', '+252 63 4440004', 38000000.00],
        ['SRA', 'Somaliland Revenue Authority (Inland Revenue & Customs)', 'department', 2, 'Khadar Omer Hashi', 'director@sra-sl.org', '+252 63 4450005', 18000000.00],
        ['CBS', 'Central Bank of Somaliland (Baanka Dhexe)', 'department', 2, 'Ali Ibrahim Jama', 'governor@centralbank-sl.org', '+252 63 4460006', 15000000.00],
        ['BERBERA-PORT', 'Berbera Port & Customs Authority', 'mda', 1, 'Said Hassan Abdillahi', 'finance@berberaport.org', '+252 63 4470007', 22000000.00]
    ];

    $stmtOrg = $pdo->prepare("INSERT INTO organizations (code, name, type, parent_id, responsible_officer, email, phone, budget_ceiling) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($orgs as $o) {
        $stmtOrg->execute($o);
    }

    // 3. Users
    $users = [
        ['USR-001', 'Abdirahman Dahir (Admin)', 'admin@sfgmis.gov.so', $password_hash, 1, 2, 'Chief Systems Administrator', '+252 63 4400100'],
        ['USR-002', 'Dr. Saad Ali Shire', 'minister@sfgmis.gov.so', $password_hash, 2, 2, 'Minister of Finance & Economic Dev.', '+252 63 4400101'],
        ['USR-003', 'Roda Jama Elmi', 'state.minister@sfgmis.gov.so', $password_hash, 3, 2, 'State Minister of Finance', '+252 63 4400102'],
        ['USR-004', 'Mohamed Abdi Gurhan', 'dg@sfgmis.gov.so', $password_hash, 4, 2, 'Director General of Ministry of Finance', '+252 63 4400103'],
        ['USR-005', 'Faisal Ahmed Warsame', 'budget@sfgmis.gov.so', $password_hash, 5, 2, 'Director of National Budget', '+252 63 4400104'],
        ['USR-006', 'Ayanle Muse Dualeh', 'finance@sfgmis.gov.so', $password_hash, 6, 2, 'Senior Treasury Finance Officer', '+252 63 4400105'],
        ['USR-007', 'Khadra Ismail Bihi', 'accountant@sfgmis.gov.so', $password_hash, 7, 2, 'Accountant General', '+252 63 4400106'],
        ['USR-008', 'Mustafe Nuur Farah', 'procurement@sfgmis.gov.so', $password_hash, 8, 2, 'Director of Public Procurement', '+252 63 4400107'],
        ['USR-009', 'Abdishakur Hassan Ali', 'auditor@sfgmis.gov.so', $password_hash, 9, 2, 'Senior State Auditor', '+252 63 4400108'],
        ['USR-010', 'Dr. Sahra Jama Qasim', 'mda.health@sfgmis.gov.so', $password_hash, 10, 3, 'Director of Finance - Ministry of Health', '+252 63 4400109'],
        ['USR-011', 'Ibrahim Yasin Guleid', 'mda.edu@sfgmis.gov.so', $password_hash, 10, 4, 'Director of Finance - Ministry of Education', '+252 63 4400110']
    ];

    $stmtUser = $pdo->prepare("INSERT INTO users (code, name, email, password_hash, role_id, organization_id, designation, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($users as $u) {
        $stmtUser->execute($u);
    }

    // 4. Fiscal Years
    $pdo->exec("INSERT INTO fiscal_years (name, start_date, end_date, total_budget, status) 
        VALUES ('FY 2026-2027', '2026-01-01', '2027-12-31', 350000000.00, 'active')");
    $pdo->exec("INSERT INTO fiscal_years (name, start_date, end_date, total_budget, status) 
        VALUES ('FY 2024-2025', '2024-01-01', '2025-12-31', 315000000.00, 'closed')");

    // 5. Budget Cycles
    $pdo->exec("INSERT INTO budget_cycles (fiscal_year_id, cycle_code, name, start_date, end_date, ceiling_amount, status)
        VALUES (1, 'CYCLE-2026-MTEF', '2026 National Budget Cycle (MTEF)', '2026-01-01', '2026-12-31', 185000000.00, 'active')");

    // 6. Budget Proposals
    $proposals = [
        ['PROP-2026-001', 3, 1, 'Primary Healthcare Services', 'National Health Strategy', 'Regional Clinics & Medical Supplies', 12500000.00, 11800000.00, 'allocated', '2026-01-10', 5, 'Approved with minor operating cost alignment to national ceiling.'],
        ['PROP-2026-002', 4, 1, 'Teacher Training & Curriculum', 'Education For All', 'Secondary Schools Textbook & ICT Expansion', 14200000.00, 13500000.00, 'approved', '2026-01-14', 5, 'Approved by Budget Committee.'],
        ['PROP-2026-003', 5, 1, 'Hargeisa-Berbera Highway Rehabilitation', 'Infrastructure Dev', 'Road Resurfacing & Bridge Reinforcement', 18000000.00, 16000000.00, 'under_review', '2026-02-01', 5, 'Engineering assessment under review.'],
        ['PROP-2026-004', 3, 1, 'Mother & Child Care Centers', 'Maternal Health', 'Establishment of 4 District Maternity Units', 4500000.00, 0.00, 'submitted', '2026-03-05', null, 'Awaiting initial technical scrutiny.'],
        ['PROP-2026-005', 4, 1, 'Technical Vocational Institutes', 'Skills Development', 'Burao & Borama Vocational Upgrades', 6200000.00, 0.00, 'revision_required', '2026-02-20', 5, 'Please provide detailed asset depreciation and staffing schedule.']
    ];

    $stmtProp = $pdo->prepare("INSERT INTO budget_proposals (proposal_code, organization_id, fiscal_year_id, department, program, activity, total_requested, approved_amount, status, submission_date, reviewed_by, reviewer_comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($proposals as $pr) {
        $stmtProp->execute($pr);
    }

    // 7. Budget Items for Proposal 1
    $items = [
        [1, 'personnel', '211101', 'Salaries of 350 Clinical Staff & Nurses', 6200000.00, 6200000.00, 'Standard civil service scale'],
        [1, 'operating_expenses', '221104', 'Essential Medicines & Vaccines Procurement', 3800000.00, 3600000.00, 'Critical regional supply'],
        [1, 'capital_expenditure', '311102', 'Medical Equipment (Ultrasound & Cold Chain)', 2500000.00, 2000000.00, 'Refrigerated vaccine storage units']
    ];
    $stmtItem = $pdo->prepare("INSERT INTO budget_items (proposal_id, category, line_item_code, description, requested_amount, approved_amount, justification) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($items as $it) {
        $stmtItem->execute($it);
    }

    // 8. Budget Allocations
    $allocations = [
        [3, 1, 1, 11800000.00, 11800000.00, 3200000.00, 7450000.00, 4350000.00, '2026-01-20', 'active'],
        [4, 1, 2, 13500000.00, 13500000.00, 2100000.00, 8900000.00, 4600000.00, '2026-01-22', 'active'],
        [5, 1, 3, 16000000.00, 16000000.00, 4500000.00, 9200000.00, 6800000.00, '2026-02-05', 'active'],
        [2, 1, null, 25000000.00, 25000000.00, 1800000.00, 14200000.00, 10800000.00, '2026-01-15', 'active']
    ];
    $stmtAlloc = $pdo->prepare("INSERT INTO budget_allocations (organization_id, fiscal_year_id, proposal_id, approved_budget, allocated_amount, committed_amount, spent_amount, remaining_balance, allocation_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($allocations as $al) {
        $stmtAlloc->execute($al);
    }

    // 9. Revenue Categories
    $revCats = [
        ['REV-CAT-01', 'Inland Customs & Import Duties', 'customs', 'Duties collected at Berbera port and land customs border posts.'],
        ['REV-CAT-02', 'Corporate Income Tax & Withholding', 'tax', 'Direct tax levied on registered companies, financial institutions and telecoms.'],
        ['REV-CAT-03', 'Sales Tax & VAT (Goods & Services)', 'tax', 'Consumption taxes on wholesale and retail transactions.'],
        ['REV-CAT-04', 'Berbera Port Handling & Maritime Fees', 'port_duties', 'Concession and cargo clearance revenue from maritime terminal operations.'],
        ['REV-CAT-05', 'Commercial Business Licenses & Registrations', 'fees_licenses', 'Annual licensing fees for trading, commerce, and mining concessions.'],
        ['REV-CAT-06', 'Livestock Export Levies', 'non_tax', 'Veterinary quarantine and livestock transit fees.']
    ];
    $stmtRevCat = $pdo->prepare("INSERT INTO revenue_categories (code, name, type, description) VALUES (?, ?, ?, ?)");
    foreach ($revCats as $rc) {
        $stmtRevCat->execute($rc);
    }

    // 10. Revenue Transactions
    $revTx = [
        ['REV-2026-0001', 1, 6, 'Dahabshiil Group Customs Clearance', 'TIN-908231', 485000.00, 'bank_transfer', '2026-03-01', 'completed', 'Containerized freight clearance Berbera Port', 6],
        ['REV-2026-0002', 2, 6, 'Telesom Somaliland Corporate Q1 Tax', 'TIN-104928', 1250000.00, 'bank_transfer', '2026-03-04', 'completed', 'Quarterly corporate income tax remittance', 6],
        ['REV-2026-0003', 3, 6, 'Somcable Telecom Goods & Services Tax', 'TIN-394821', 340000.00, 'edahab', '2026-03-10', 'completed', 'Monthly telecommunications sales tax', 6],
        ['REV-2026-0004', 4, 8, 'Berbera Maritime Port Terminal Levy', 'TIN-884920', 890000.00, 'bank_transfer', '2026-03-14', 'completed', 'DP World Berbera terminal concession fee share', 6],
        ['REV-2026-0005', 5, 2, 'Hargeisa Commercial Chamber Trade Licenses', 'TIN-773821', 185000.00, 'zaad', '2026-03-18', 'completed', 'Batch business license renewals', 6],
        ['REV-2026-0006', 6, 6, 'Berbera Livestock Export Health Clearance', 'TIN-552910', 275000.00, 'treasury_check', '2026-03-22', 'completed', 'Quarantine and transit inspection levy', 6]
    ];
    $stmtRevTx = $pdo->prepare("INSERT INTO revenue_transactions (reference_no, category_id, organization_id, payer_name, payer_tin, amount, payment_method, date, payment_status, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($revTx as $rt) {
        $stmtRevTx->execute($rt);
    }

    // 11. Expenditure Requests (with Automatic Budget Checking!)
    $expRequests = [
        ['EXP-2026-0001', 3, 1, 1, '221104', 'Medical Supplies', 'Procurement of critical emergency medical kits for Hargeisa Group Hospital', 480000.00, 4350000.00, 'paid', 10, 4, '2026-02-12', null],
        ['EXP-2026-0002', 4, 1, 2, '221108', 'Textbooks & Learning Aids', 'Printing of 120,000 national curriculum primary textbooks for schools', 320000.00, 4600000.00, 'approved', 11, 4, '2026-02-28', null],
        ['EXP-2026-0003', 5, 1, 3, '311101', 'Highway Construction', 'Asphalt paving contractor milestone payment for Berbera Corridor Section 3', 850000.00, 6800000.00, 'checked', 1, 4, '2026-03-15', null],
        ['EXP-2026-0004', 3, 1, 1, '311102', 'Hospital Diagnostic Units', 'Procurement of 2 Digital X-Ray machines for Burao General Hospital', 220000.00, 3870000.00, 'submitted', 10, null, null, null],
        ['EXP-2026-0005', 4, 1, 2, '211101', 'Special Allowance', 'Supplemental training stipends exceeding designated sub-allocation', 150000.00, 120000.00, 'rejected', 11, 4, '2026-03-10', 'Insufficient allocated balance in budget line item. Reallocate before resubmission.']
    ];
    $stmtExp = $pdo->prepare("INSERT INTO expenditure_requests (request_no, organization_id, fiscal_year_id, budget_allocation_id, budget_code, category, description, requested_amount, available_budget_at_request, status, requesting_officer_id, approved_by, approval_date, rejection_reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($expRequests as $er) {
        $stmtExp->execute($er);
    }

    // 12. Vendors
    $vendors = [
        ['VND-001', 'Red Sea Medical & Pharma Ltd', 'CR-48291', 'TIN-482910', 'Dr. Abdiwali Farah', 'procurement@redseamedical.so', '+252 63 4429100', 'Airport Road, Hargeisa', 'Dahabshiil Bank', 'DIB-8920192', 'vetted', 'Healthcare & Pharmaceuticals'],
        ['VND-002', 'Horn of Africa Printing & Publishing', 'CR-10492', 'TIN-104928', 'Mustafa Ahmed Omar', 'info@hornprinting.so', '+252 63 4481029', 'Freedom Square, Hargeisa', 'Salaam Bank', 'SAL-4820194', 'vetted', 'Educational Supplies & Printing'],
        ['VND-003', 'Somaliland National Engineering & Construction', 'CR-88392', 'TIN-883921', 'Eng. Hassan Nur', 'contact@snec-roads.com', '+252 63 4492018', 'Industrial Zone, Berbera Road', 'Premier Bank', 'PRM-9920145', 'vetted', 'Civil Infrastructure & Roads'],
        ['VND-004', 'Golis Tech & Hardware Solutions', 'CR-77291', 'TIN-772910', 'Nasir Jama', 'sales@golistech.so', '+252 63 4410294', 'Bridge 1, Hargeisa', 'Dahabshiil Bank', 'DIB-1102948', 'active', 'IT Hardware & Telecom'],
        ['VND-005', 'Al-Baraka Petroleum & Fleet Services', 'CR-33920', 'TIN-339201', 'Saeed Mohamed', 'energy@albaraka.so', '+252 63 4430192', 'Berbera Oil Depot Terminal', 'Dahabshiil Bank', 'DIB-7720194', 'vetted', 'Fuel & Logistics']
    ];
    $stmtVend = $pdo->prepare("INSERT INTO vendors (vendor_code, name, registration_no, tax_id, contact_person, email, phone, address, bank_name, bank_account, status, category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($vendors as $v) {
        $stmtVend->execute($v);
    }

    // 13. Purchase Requests
    $prReqs = [
        ['PR-2026-001', 3, 'Emergency Medical Equipment & PPE', 'Procurement', 480000.00, '221104', 'Critical replenishment for regional trauma response centers.', 'approved', 10],
        ['PR-2026-002', 4, 'National Curriculum Grade 1-8 Books', 'Curriculum Dept', 320000.00, '221108', 'Primary school national syllabus rollout for 2026 school year.', 'approved', 11],
        ['PR-2026-003', 5, 'Heavy Duty Road Compactor & Grader Spares', 'Engineering', 240000.00, '311101', 'Routine maintenance of state road engineering machinery.', 'under_review', 1]
    ];
    $stmtPR = $pdo->prepare("INSERT INTO purchase_requests (request_no, organization_id, title, department, estimated_cost, budget_code, justification, status, requested_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($prReqs as $p) {
        $stmtPR->execute($p);
    }

    // 14. Purchase Orders
    $poList = [
        ['PO-2026-001', 1, 1, 3, 480000.00, 'Net 30 Days upon delivery and inspection', '2026-03-25', 'completed', 8],
        ['PO-2026-002', 2, 2, 4, 320000.00, '50% upon proof of print, 50% on delivery', '2026-04-10', 'issued', 8],
        ['PO-2026-003', null, 3, 5, 850000.00, 'Milestone completion certified by independent engineer', '2026-05-15', 'issued', 8]
    ];
    $stmtPO = $pdo->prepare("INSERT INTO purchase_orders (po_number, purchase_request_id, vendor_id, organization_id, total_amount, payment_terms, delivery_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($poList as $po) {
        $stmtPO->execute($po);
    }

    // 15. Invoices
    $invoices = [
        ['INV-2026-001', 1, 1, 480000.00, 0.00, 480000.00, '2026-02-15', '2026-03-15', 'paid'],
        ['INV-2026-002', 2, 2, 320000.00, 0.00, 320000.00, '2026-03-01', '2026-03-31', 'approved_for_payment']
    ];
    $stmtInv = $pdo->prepare("INSERT INTO invoices (invoice_number, po_id, vendor_id, amount, tax_amount, total_amount, invoice_date, due_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($invoices as $inv) {
        $stmtInv->execute($inv);
    }

    // 16. Payments
    $payments = [
        ['PAY-2026-0001', 1, 1, 1, 3, 480000.00, 'central_bank_eft', 'paid', 4, '2026-02-16', '2026-02-18', 'TREASURY-EFT-882910'],
        ['PAY-2026-0002', 2, 2, 2, 4, 320000.00, 'central_bank_eft', 'approved', 4, '2026-03-12', null, 'TREASURY-EFT-882911'],
        ['PAY-2026-0003', 3, 3, 3, 5, 850000.00, 'treasury_direct', 'under_review', 6, null, null, 'VCH-PENDING-003']
    ];
    $stmtPay = $pdo->prepare("INSERT INTO payments (payment_no, expenditure_id, po_id, vendor_id, organization_id, amount, payment_method, status, approver_id, approval_date, paid_date, voucher_reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($payments as $pay) {
        $stmtPay->execute($pay);
    }

    // 17. Chart of Accounts
    $coa = [
        ['1010', 'Treasury Single Account (Central Bank)', 'asset'],
        ['1020', 'Commercial Bank Accounts (Dahabshiil / Salaam)', 'asset'],
        ['1030', 'Accounts Receivable (Revenue in Transit)', 'asset'],
        ['1050', 'Government Physical Capital Assets', 'asset'],
        ['2010', 'Accounts Payable (Vetted Vendors)', 'liability'],
        ['2020', 'Withholding Tax Payable', 'liability'],
        ['3010', 'Consolidated Government Fund Reserve', 'equity'],
        ['4010', 'Inland Customs & Port Tariffs', 'revenue'],
        ['4020', 'Direct Taxes & Corporate Remittances', 'revenue'],
        ['4030', 'Government Service Fees & Royalties', 'revenue'],
        ['5010', 'Public Personnel Salaries & Benefits', 'expense'],
        ['5020', 'Operational Goods & Services', 'expense'],
        ['5030', 'Capital Infrastructure & Equipment', 'expense']
    ];
    $stmtCOA = $pdo->prepare("INSERT INTO chart_of_accounts (account_code, account_name, account_type) VALUES (?, ?, ?)");
    foreach ($coa as $c) {
        $stmtCOA->execute($c);
    }

    // 18. Journal Entries
    $pdo->exec("INSERT INTO journal_entries (entry_number, entry_date, reference, description, organization_id, status, created_by)
        VALUES ('JRN-2026-0001', '2026-02-18', 'TREASURY-EFT-882910', 'Disbursement of medical supplies procurement for Ministry of Health', 3, 'posted', 7)");

    $pdo->exec("INSERT INTO journal_items (entry_id, account_id, debit, credit, line_description)
        VALUES (1, 12, 480000.00, 0.00, 'Operating Expense - Medical Supplies')");
    $pdo->exec("INSERT INTO journal_items (entry_id, account_id, debit, credit, line_description)
        VALUES (1, 1, 0.00, 480000.00, 'Treasury Single Account (CBS) Outflow')");

    // 19. Assets
    $assets = [
        ['AST-MOF-001', 'Ministry of Finance Headquarters Complex', 'buildings', 2, 'Administration', '2019-06-15', 3800000.00, 3600000.00, 'Hargeisa Government District', 'good', 'Director of Administration', 'in_use'],
        ['AST-MOH-002', 'Mobile Surgical Unit & Field Ambulances (10 units)', 'vehicles', 3, 'Emergency Services', '2023-04-10', 850000.00, 680000.00, 'Regional Health Directorates', 'good', 'Fleet Manager', 'in_use'],
        ['AST-MOT-003', 'CAT 140K Heavy Motor Grader', 'infrastructure', 5, 'Road Maintenance Division', '2022-11-20', 320000.00, 260000.00, 'Burao Depot', 'fair', 'Chief Mechanical Engineer', 'in_use'],
        ['AST-MOF-004', 'National Data Center Enterprise Server Rack & UPS', 'it_equipment', 2, 'ICT Department', '2024-02-01', 450000.00, 390000.00, 'MoFED Data Center Hargeisa', 'new', 'ICT Director', 'in_use']
    ];
    $stmtAsset = $pdo->prepare("INSERT INTO assets (asset_code, asset_name, category, organization_id, department, purchase_date, purchase_cost, current_value, location, condition_status, responsible_officer, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($assets as $a) {
        $stmtAsset->execute($a);
    }

    // 20. Audit Logs
    $logs = [
        [5, 'Faisal Ahmed Warsame', 'budget_officer', 2, 'Approved Proposal', 'Budget Management', 'budget_proposals', 'PROP-2026-001', 'status: under_review', 'status: approved, amount: 11800000.00', '192.168.10.42'],
        [5, 'Faisal Ahmed Warsame', 'budget_officer', 2, 'Released Budget Allocation', 'Budget Management', 'budget_allocations', 'ALLOC-001', 'allocated: 0.00', 'allocated: 11800000.00', '192.168.10.42'],
        [4, 'Mohamed Abdi Gurhan', 'director_general', 2, 'Approved Expenditure Request', 'Expenditure Management', 'expenditure_requests', 'EXP-2026-0001', 'status: checked', 'status: approved, amount: 480000.00', '192.168.10.15'],
        [6, 'Ayanle Muse Dualeh', 'finance_officer', 2, 'Executed Central Bank Payment', 'Payment Management', 'payments', 'PAY-2026-0001', 'status: approved', 'status: paid, voucher: TREASURY-EFT-882910', '192.168.10.28'],
        [7, 'Khadra Ismail Bihi', 'accountant', 2, 'Posted General Ledger Journal Entry', 'Accounting', 'journal_entries', 'JRN-2026-0001', 'status: draft', 'status: posted, balance: $480,000.00', '192.168.10.33']
    ];
    $stmtLog = $pdo->prepare("INSERT INTO audit_logs (user_id, user_name, user_role, organization_id, action, module, record_type, record_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($logs as $l) {
        $stmtLog->execute($l);
    }

    // 21. Audit Findings
    $findings = [
        ['FND-2026-001', 'Vehicle Fleet Fuel Log Variance in Ministry of Transport', 'Expenditure Management', 'medium', 5, 'Discrepancy of 4,200 Liters between bulk fuel distribution vouchers and vehicle mileage logs.', 'Institute automated fuel coupon barcode scanning linked to vehicle asset codes.', 'New digital fuel disbursement system ordered for Q2.', 'in_progress', 9],
        ['FND-2026-002', 'Untallied Withholding Tax Deduction for Vendor VND-004', 'Procurement Management', 'low', 2, 'Withholding tax documentation for IT equipment spares delayed in transmission.', 'Enforce electronic tax clearance verification before invoice clearance in payment workflow.', 'Finance Officer updated checklist.', 'resolved', 9],
        ['FND-2026-003', 'Delayed Budget Reallocation Filing for Vocational Unit', 'Budget Management', 'high', 4, 'Ministry of Education reallocated operating funds prior to formal Budget Committee sign-off.', 'Freeze unauthorized expenditure account line until formal Council of Ministers endorsement.', 'Pending review by Director General.', 'open', 9]
    ];
    $stmtFind = $pdo->prepare("INSERT INTO audit_findings (finding_code, title, module, severity, organization_id, description, recommendation, corrective_action, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($findings as $f) {
        $stmtFind->execute($f);
    }

    // 22. App Settings
    $pdo->exec("INSERT INTO app_settings (setting_key, setting_value) VALUES ('system_name', 'Somaliland Government Financial Management Platform')");
    $pdo->exec("INSERT INTO app_settings (setting_key, setting_value) VALUES ('system_acronym', 'SFGMIS')");
    $pdo->exec("INSERT INTO app_settings (setting_key, setting_value) VALUES ('ministry_name', 'Ministry of Finance & Economic Development')");
    $pdo->exec("INSERT INTO app_settings (setting_key, setting_value) VALUES ('national_currency', 'USD / SLSH')");
    $pdo->exec("INSERT INTO app_settings (setting_key, setting_value) VALUES ('fiscal_year_active', 'FY 2026-2027')");

    // 23. Publications (Portal CMS Seed)
    $publications = [
        ['PUB-2026-001', 'National Appropriation Bill FY 2026-2027', 'budget', 'approved_budget', 'Official parliamentary appropriation act — Full Gazette Publication.', 'budget-bill-2026-27.pdf', 'uploads/publications/budget-bill-2026-27.pdf', '3.8 MB', 'application/pdf', null, 'FY 2026-2027', 'Budget Department', '2026-01-15', 1, 0, 'published', 1],
        ['PUB-2026-002', "Minister's Annual Budget Speech", 'budget', 'budget_speech', 'Fiscal policy statement delivered before the Parliament of Somaliland.', 'budget-speech-2026.pdf', 'uploads/publications/budget-speech-2026.pdf', '1.6 MB', 'application/pdf', null, 'FY 2026-2027', 'Office of the Minister', '2026-01-16', 1, 0, 'published', 1],
        ['PUB-2026-003', "Citizen's Budget Guide FY 2026-2027", 'budget', 'citizens_guide', 'Simplified public-friendly summary of revenues, expenditures, and development goals.', 'citizens-budget-2026.pdf', 'uploads/publications/citizens-budget-2026.pdf', '2.1 MB', 'application/pdf', null, 'FY 2026-2027', 'Communications Office', '2026-01-20', 0, 0, 'published', 1],
        ['PUB-2026-004', 'Medium-Term Fiscal Framework (MTFF) 2026-2029', 'budget', 'mtff', 'Macroeconomic forecasts, revenue mobilization strategies, and ceiling estimates.', 'mtff-2026-2029.pdf', 'uploads/publications/mtff-2026-2029.pdf', '4.2 MB', 'application/pdf', null, 'FY 2026-2027', 'Planning & Economic Policy Dept', '2026-02-01', 0, 0, 'published', 1],
        ['PUB-2026-005', 'Public Financial Management Act (Amended 2024)', 'acts_regulations', 'legislation', 'Statutory legal framework governing public finances, treasury operations, and audit oversight.', 'pfm-act-2024.pdf', 'uploads/publications/pfm-act-2024.pdf', '1.1 MB', 'application/pdf', null, null, 'Legal Department', '2024-06-01', 1, 0, 'published', 1],
        ['PUB-2026-006', 'Danjire Magazine — Q1 2026 Edition', 'magazines', 'quarterly', 'Ministry activities, events, and financial highlights from January to March 2026.', 'danjire-q1-2026.pdf', 'uploads/publications/danjire-q1-2026.pdf', '8.4 MB', 'application/pdf', null, 'FY 2026-2027', 'Communications Office', '2026-04-01', 1, 0, 'published', 1],
        ['PUB-2026-007', 'Inland Revenue Tax Guide 2026', 'taxes', 'taxpayer_guide', 'A comprehensive guide for taxpayers on rates, procedures, and obligations.', 'tax-guide-2026.pdf', 'uploads/publications/tax-guide-2026.pdf', '2.9 MB', 'application/pdf', null, 'FY 2026-2027', 'Inland Revenue Department', '2026-03-01', 0, 0, 'published', 1],
        ['PUB-2026-008', 'Customs Tariff Schedule 2026', 'taxes', 'customs', 'Official harmonized import/export tariff schedule and duty exemption criteria.', 'customs-tariff-2026.pdf', 'uploads/publications/customs-tariff-2026.pdf', '5.2 MB', 'application/pdf', null, 'FY 2026-2027', 'Customs Department', '2026-01-05', 0, 0, 'published', 1]
    ];
    $stmtPub = $pdo->prepare("INSERT INTO publications (pub_code, title, category, subcategory, description, file_name, file_path, file_size, file_type, cover_image, fiscal_year, author, published_date, is_featured, download_count, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($publications as $pub) { $stmtPub->execute($pub); }

    // 24. News Articles (Portal CMS Seed)
    $news = [
        ['slmof-advances-pfm-reform-sfgmis', 'SLMOF Advances PFM Reform with New Integrated Financial System', 'budget_finance', 'The Ministry of Finance launched the upgraded SFGMIS platform enabling real-time budget tracking across all line ministries and government agencies.', '<p>The Ministry of Finance &amp; Economic Development has successfully deployed Phase II of the Somaliland Government Financial Management Information System (SFGMIS), enabling real-time tracking of budget allocations across all line ministries.</p><p>The system now covers 28 government entities and processes over $350 million in annual appropriations through automated commitment controls, warrant management, and digital payments.</p>', null, 'Communications Office', '2026-10-01', 1, 1, 0, 1],
        ['domestic-revenue-exceeds-quarterly-target', 'Domestic Revenue Collection Exceeds Quarterly Target', 'revenue', 'Inland Revenue and Customs departments report above-target revenue collection, contributing to a strengthened national fiscal position.', '<p>The Somaliland Revenue Authority confirmed that Q3 2026 domestic revenue collections exceeded the quarterly target by 12.4%, driven by increased customs activity at Berbera Port and improved corporate tax compliance.</p><p>Total collections for the quarter reached USD 89.3 million against a projected target of USD 79.5 million.</p>', null, 'Communications Office', '2026-09-15', 1, 1, 0, 1],
        ['economic-partnership-infrastructure', 'New Economic Partnership to Support Infrastructure Investment', 'economic_development', 'A new economic partnership agreement signed to support infrastructure investment and capacity building for Somaliland public financial management.', '<p>The Ministry of Finance signed a memorandum of understanding with international development partners to support infrastructure investment and public sector capacity building over the next three fiscal years.</p>', null, 'Communications Office', '2026-08-20', 0, 1, 0, 1]
    ];
    $stmtNews = $pdo->prepare("INSERT INTO news_articles (slug, title, category, excerpt, body, featured_image, author, published_date, is_featured, is_published, views, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($news as $n) { $stmtNews->execute($n); }

    // 25. E-Services
    $services = [
        ['SVC-001', 'SFGMIS Officer Portal', 'Login to the Integrated Financial Management System for government officers.', 'monitor', 'digital_systems', 'login.php', 0, 1, '24/7 Available', 1],
        ['SVC-002', 'Tax Calculator', 'Estimate PAYE payroll withholding tax based on published brackets.', 'calculator', 'taxes', 'portal.php?page=taxes#calculator', 0, 1, 'Free Tool', 2],
        ['SVC-003', 'Budget Documents', 'Download official budget acts, speeches, and financial statements.', 'file-text', 'publications', 'portal.php?page=publications', 0, 1, 'Updated FY2027', 3],
        ['SVC-004', 'Public Procurement Registry', 'View awarded public contracts and vendor registrations.', 'shopping-bag', 'procurement', 'portal.php#live-data', 0, 1, 'Open Data', 4],
        ['SVC-005', 'TIN Registration', 'Apply for a Taxpayer Identification Number online.', 'receipt', 'taxes', 'portal.php?page=taxes#taxpayer', 0, 1, 'Online', 5],
        ['SVC-006', 'Contact & Inquiries', 'Submit inquiries to the Ministry of Finance communications desk.', 'mail', 'contact', 'portal.php?page=contact', 0, 1, 'Respond 48h', 6]
    ];
    $stmtSvc = $pdo->prepare("INSERT INTO eservices (service_code, title, description, icon, category, url, is_external, is_active, badge, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($services as $svc) { $stmtSvc->execute($svc); }
}
