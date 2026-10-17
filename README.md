# Somaliland Government Financial Management Platform (SFGMIS)

> **Project Type:** Government Financial Management Information System Prototype  
> **Target Organization:** Somaliland Ministry of Finance & Economic Development  
> **System Name:** SFGMIS  
> **Full Name:** Somaliland Government Financial Management Platform

## Table of Contents

- [Project Overview](#project-overview)
- [Problem Statement](#problem-statement)
- [Project Goal](#project-goal)
- [Objectives](#objectives)
- [Target Users and Roles](#target-users-and-roles)
- [Core Modules](#core-modules)
- [Key Features and Functional Requirements](#key-features-and-functional-requirements)
- [Core Financial Workflow](#core-financial-workflow)
- [Proposed Technology Stack](#proposed-technology-stack)
- [Proposed Database Entities](#proposed-database-entities)
- [Security and Non-Functional Requirements](#security-and-non-functional-requirements)
- [Implementation Roadmap](#implementation-roadmap)
- [Success Criteria](#success-criteria)
- [Project Scope and Disclaimer](#project-scope-and-disclaimer)

## Project Overview

The **Somaliland Government Financial Management Platform (SFGMIS)** is a proposed integrated, web-based financial management platform designed to support government institutions in planning, budgeting, recording, monitoring, managing, and reporting public financial resources.

The prototype is based on publicly documented responsibilities and requirements of the Somaliland Ministry of Finance & Economic Development. It is intended to demonstrate how authorized government users could manage financial processes through a centralized platform.

SFGMIS focuses on improving the flow of financial information between the Ministry of Finance, government ministries and agencies, departments, finance officers, procurement officers, accountants, auditors, and authorized decision-makers.

## Problem Statement

Government financial management involves many connected processes, including budgeting, financial transactions, procurement, expenditure controls, accounting, approvals, and reporting. When these processes rely on disconnected systems, spreadsheets, paper documents, or fragmented communication, common challenges may include:

- Delayed access to financial information.
- Duplicate data entry.
- Difficulty tracking budget utilization.
- Limited visibility into expenditure.
- Difficulty monitoring approval processes.
- Inconsistent financial records.
- Delays in producing financial reports.
- Limited traceability of financial actions.
- Difficulty monitoring public resources.
- Limited centralized information for decision-making.

SFGMIS proposes a centralized platform for managing financial information and tracking workflows, subject to appropriate authorization and controls.

## Project Goal

The main goal is to design and develop an integrated financial management platform that supports:

- Government budget planning and allocation.
- Budget proposal submission, review, and approval.
- Revenue recording and reporting.
- Expenditure management.
- Procurement management.
- Payment processing and tracking.
- Accounting and financial records.
- Asset management.
- Financial reporting and analysis.
- Audit monitoring and accountability.
- Role-based access to sensitive financial information.

## Objectives

1. Digitize government financial management workflows.
2. Centralize financial information.
3. Improve budget planning and monitoring.
4. Improve expenditure tracking and budget control.
5. Support government procurement workflows.
6. Provide controlled financial approval processes.
7. Maintain accurate financial records.
8. Provide timely financial reports.
9. Improve accountability through audit trails.
10. Provide dashboards for financial decision-making.
11. Apply role-based access to system functionality and sensitive information.
12. Reduce unnecessary paper-based processes.
13. Improve traceability of financial activities.
14. Establish a foundation for potential future integration with other government systems.

## Target Users and Roles

SFGMIS is designed around role-based access control (RBAC). Users should only access the modules and actions permitted by their assigned role.

| Role | Main responsibilities |
|---|---|
| **Super Administrator** | Manage system configuration, user access, roles, and permissions. |
| **Minister** | View high-level national budget information, expenditure summaries, revenue performance, reports, and key indicators. |
| **State Minister** | Review departmental financial information and management reports. |
| **Director General** | Monitor ministry-wide financial activities, departmental performance, reports, and pending approvals. |
| **Budget Officer** | Create budget cycles, define ceilings, review proposals, manage revisions, approve proposals according to authority, and monitor utilization. |
| **Finance Officer** | Submit expenditure requests, record financial transactions, view approved budgets, and monitor balances and payments. |
| **Accountant** | Maintain accounting records, account classifications, transaction reconciliation, and accounting reports. |
| **Procurement Officer** | Manage purchase requests, vendors, purchase orders, contracts, and procurement status. |
| **Auditor** | Review financial transactions and audit logs, record findings, and monitor corrective actions. |
| **MDA Finance Officer** | Submit budget proposals and expenditure or procurement requests, view allocations, and track request status. |
| **Report Viewer** | Access reports permitted by assigned permissions. |

*MDA refers to ministries, departments, and agencies.*

## Core Modules

The proposed platform is organized into the following modules:

1. **Dashboard** — financial summaries, budget utilization, pending approvals, procurement and payment activity.
2. **Organization Management** — government institutions, ministries, departments, agencies, and offices.
3. **User and Role Management** — user accounts, roles, permissions, and account status.
4. **Budget Management** — fiscal years, budget cycles, proposals, reviews, approvals, allocations, and utilization.
5. **Revenue Management** — revenue categories, revenue transactions, and revenue reports.
6. **Expenditure Management** — expenditure requests, budget availability checks, approval workflows, and transaction history.
7. **Procurement Management** — purchase requests, vendors, purchase orders, contracts, invoices, and procurement tracking.
8. **Payment Management** — payment requests, authorization, processing, and payment status.
9. **Accounting** — chart of accounts, journal entries, debit and credit records, and transaction history.
10. **Asset Management** — asset registration, classification, valuation, location, responsibility, and disposal records.
11. **Financial Reporting** — budget, revenue, expenditure, procurement, payment, asset, and organizational reports.
12. **Audit and Compliance** — audit logs, findings, recommendations, and corrective-action tracking.
13. **Notifications** — alerts for submissions, approvals, rejections, revisions, and deadlines.
14. **Document Management** — controlled storage of supporting financial documents.
15. **System Administration** — system-level configuration and administration.

## Key Features and Functional Requirements

### 1. Authentication and Access Control

- Authorized administrators can create and manage user accounts.
- Secure sign-in and session management.
- Password reset and account activation or deactivation.
- Optional multi-factor authentication (MFA).
- Role-based access control for pages, records, and actions.
- Permission checks on the server, not only in the user interface.

### 2. Organization Management

Manage the government organizational structure:

```text
Government
└── Ministry
    └── Department
        └── MDA
            └── Office / Unit
```

Organization records may include the name, code, type, parent organization, responsible officer, and status.

### 3. Budget Management

- Configure fiscal years and budget cycles.
- Define budget ceilings.
- Submit and review budget proposals.
- Include departments, programs, activities, budget categories, requested amounts, justifications, and supporting documents.
- Add review comments and request revisions.
- Approve proposals according to assigned authority.
- Allocate approved budgets to MDAs and departments.
- Monitor approved, allocated, committed, spent, and remaining amounts.
- Calculate budget utilization.

**Example budget approval workflow:**

```text
Draft
  ↓
Submitted
  ↓
Under Review
  ├──→ Revision Required → Resubmitted → Under Review
  └──→ Approved
          ↓
       Allocated
```

Budget calculations should be validated consistently. For example:

```text
Remaining Budget = Approved Budget - Spent Amount
```

The implementation should distinguish approved, allocated, committed, and spent values according to the accounting rules defined for the system.

### 4. Revenue Management

- Manage tax revenue, non-tax revenue, fees, licenses, and other government revenue categories.
- Record revenue source, reference or payer, amount, date, category, organization, and payment status.
- Generate daily, monthly, and annual revenue reports.
- Compare revenue by category, organization, and period.

The prototype focuses on financial recording and reporting rather than attempting to reproduce a complete national tax administration system.

### 5. Expenditure Management

- Create expenditure requests linked to an organization, department, budget code, amount, and supporting documents.
- Record the requesting officer and submission date.
- Check available approved budget before an expenditure request proceeds.
- Support configurable approval levels.
- Track the status and history of each request.

**Budget-control concept:**

```text
Expenditure Request
        ↓
Check Available Budget
        ↓
  ┌─────┴─────┐
  │           │
Enough       Insufficient
Budget       Budget
  │           │
Continue    Reject / Return
```

### 6. Procurement Management

- Create and review purchase requests.
- Maintain vendor records and supporting documents.
- Create purchase orders.
- Manage contracts and invoices.
- Track procurement status.
- Link procurement requests to approved budgets.
- Maintain records of procurement decisions and approvals.

**Procure-to-Pay workflow:**

```text
Purchase Request
       ↓
Procurement Review
       ↓
Vendor Selection
       ↓
Purchase Order
       ↓
Goods / Services Received
       ↓
Invoice
       ↓
Approval
       ↓
Payment
```

### 7. Payment Management

- Create payment requests linked to approved expenditure, vendors, purchase orders, invoices, and budget allocations.
- Apply configured payment approval workflows.
- Track payment status and history.

Possible statuses include:

`Pending` · `Under Review` · `Approved` · `Rejected` · `Processing` · `Paid` · `Cancelled`

### 8. Accounting

- Maintain a configurable chart of accounts.
- Record financial transactions and journal entries.
- Capture debit, credit, account, amount, date, reference, organization, and description.
- Search and filter transaction history.
- Support reconciliation and reporting.

Financial posting rules and validation should preserve accounting integrity.

### 9. Asset Management

- Register assets and assign asset codes.
- Categorize assets.
- Record purchase date, purchase value, current value, location, and responsible department.
- Track asset status and disposal records.

### 10. Financial Reporting and Dashboard Analytics

The dashboard may display:

- Total approved budget.
- Allocated and remaining budget.
- Total revenue.
- Total expenditure.
- Budget utilization.
- Pending approvals.
- Procurement activity.
- Payment activity.
- Financial summaries by organization and period.

Reports may include budget, revenue, expenditure, procurement, payment, asset, department, MDA, and annual financial reports. Export to PDF and Excel is part of the proposed roadmap.

### 11. Audit and Accountability

Record important user actions, including:

- User and action.
- Module and affected record.
- Previous and new values, where applicable.
- Date and time.
- IP address, where appropriate and legally permitted.
- Reason for sensitive changes, when required.

Auditors should be able to record findings, assign corrective actions, set severity, add recommendations, and track resolution.

Financial records should not be silently modified or deleted. Important corrections should use controlled correction or reversal procedures.

### 12. Document Management and Notifications

Document management may support budget documents, invoices, contracts, purchase orders, receipts, payment records, and audit documents. Access must follow assigned permissions.

Notifications may be generated when:

- A request is submitted.
- A request is approved or rejected.
- A revision is required.
- A payment is approved.
- A budget proposal deadline is approaching.
- An assigned task is pending.

### 13. Search and Filtering

Provide global and module-specific search using relevant fields, including reference number, organization, user, date, amount, status, budget code, vendor, and transaction type.

## Core Financial Workflow

SFGMIS is designed around an integrated public financial management workflow rather than treating each module as an isolated CRUD application.

```text
Planning
   ↓
Budget
   ↓
Allocation
   ↓
Commitment
   ↓
Procurement
   ↓
Expenditure
   ↓
Payment
   ↓
Accounting
   ↓
Reporting
   ↓
Audit and Oversight
```

The workflow should maintain traceability between related records and enforce approval rules, budget controls, and audit logging at appropriate stages.

## Proposed Technology Stack

The following stack is proposed in the project specification. These are planned technologies, not a claim that every component has already been implemented.

| Layer | Proposed technology | Purpose |
|---|---|---|
| Frontend framework | Next.js + TypeScript | Web application and user interface |
| UI styling and components | Tailwind CSS + shadcn/ui | Consistent, responsive interface |
| Server state | TanStack Query | Fetching and caching server data |
| Forms and validation | React Hook Form + Zod | Form handling and input validation |
| Client state | Zustand | Shared client-side state where needed |
| Charts | Recharts | Financial analytics and visualizations |
| Backend | NestJS | Modular API and business logic |
| ORM | Prisma | Database access and data modeling |
| Database | PostgreSQL | Relational financial and organizational records |
| Cache and queues | Redis + BullMQ | Caching and background jobs where needed |
| Authentication | Passport / NestJS authentication | Authentication integration |
| File storage | MinIO | Document storage |
| Logging | Pino | Application logging |
| Containers | Docker Compose | Local multi-service development |
| CI/CD | GitHub Actions | Automated checks and deployment workflows |
| Reverse proxy | Nginx | Reverse proxy and deployment routing |
| Testing | Jest + Playwright | Unit, integration, and browser testing |

### Authentication and Authorization

The proposed design includes secure sessions or a suitable JWT architecture, role-based access control, and an MFA-ready foundation. The final approach should be selected and implemented consistently across the frontend and backend.

## Proposed Database Entities

The initial data model may include the following entities. The final schema will depend on detailed business rules and relationships.

**Identity and organization**
- `users`
- `roles`
- `permissions`
- `organizations`
- `departments`
- `fiscal_years`

**Budget**
- `budget_cycles`
- `budget_proposals`
- `budget_items`
- `budget_allocations`

**Revenue and expenditure**
- `revenue_categories`
- `revenue_transactions`
- `expenditure_requests`
- `expenditure_items`

**Procurement and payments**
- `vendors`
- `purchase_requests`
- `purchase_orders`
- `contracts`
- `invoices`
- `payments`

**Accounting and assets**
- `accounts`
- `journal_entries`
- `journal_entry_items`
- `assets`
- `asset_categories`

**Platform support, documents, and audit**
- `documents`
- `notifications`
- `audit_logs`
- `audit_findings`
- `approval_workflows`
- `approval_steps`
- `approval_actions`

## Security and Non-Functional Requirements

The prototype should be designed with the following requirements in mind.

### Security

- Use HTTPS in deployed environments.
- Hash passwords with a secure password-hashing algorithm.
- Enforce authorization on the server for protected actions and records.
- Validate and sanitize input appropriately.
- Use parameterized database access to mitigate SQL injection.
- Protect against cross-site scripting (XSS).
- Apply CSRF protection where applicable to the chosen authentication model.
- Configure secure session and cookie handling.
- Record security-sensitive activities in audit logs.
- Restrict access to confidential documents and financial data.
- Keep secrets and credentials out of source control.

### Reliability and Data Integrity

- Use database transactions for operations that must succeed or fail together.
- Validate financial amounts and business rules.
- Preserve audit history for sensitive actions.
- Use backups and a tested recovery procedure.
- Handle errors without exposing sensitive details.
- Use controlled correction or reversal procedures for posted financial records.

### Performance, Scalability, and Maintainability

- Keep the application modular.
- Use appropriate database indexes and pagination for large lists.
- Design the data model to support additional ministries, departments, users, and transactions.
- Keep frontend, API, and business logic responsibilities clear.
- Test important financial calculations and approval workflows.

### Usability and Accessibility

- Provide a consistent, responsive interface.
- Use clear labels, readable tables, and understandable status indicators.
- Support users with different levels of technical experience.
- Maintain consistent navigation and accessible color contrast.

## Implementation Roadmap

The project specification recommends building a minimum viable product (MVP) in phases rather than implementing every module at once.

### Phase 1 — Foundation

- Authentication.
- Role-based access control.
- Organization and department management.
- User management.
- Audit logs.
- Main dashboard.

### Phase 2 — Budget Management

- Fiscal years and budget cycles.
- Budget proposals and items.
- Review and revision workflows.
- Approval and allocation.
- Budget monitoring.

### Phase 3 — Expenditure and Payments

- Expenditure requests.
- Budget availability checks.
- Approval workflows.
- Payment requests.
- Transaction history.

### Phase 4 — Procurement

- Vendor management.
- Purchase requests.
- Purchase orders.
- Invoices and contracts.
- Procurement workflow.

### Phase 5 — Reporting

- Budget and expenditure reports.
- Procurement reports.
- Financial dashboard analytics.
- PDF and Excel export.

### Phase 6 — Advanced Modules

- Revenue management.
- Asset management.
- Accounting.
- Audit findings and corrective actions.
- Public transparency portal.
- Notifications and API integrations.

## Success Criteria

The prototype can be evaluated by whether an authorized simulated government organization can:

1. Sign in with an authorized account.
2. Create an organization and fiscal year.
3. Create a budget cycle.
4. Submit and review a budget proposal.
5. Approve or return the proposal.
6. Allocate an approved budget.
7. Submit an expenditure request and check budget availability.
8. Submit a procurement request and manage a vendor.
9. Create a purchase order.
10. Submit and approve a payment request.
11. Record related financial transactions.
12. Generate financial reports.
13. View dashboard analytics.
14. Review the audit history of important actions.

## Project Scope and Disclaimer

**SFGMIS is an independent prototype / academic software project.**

It must not be represented as:

- The official Somaliland government financial system.
- An official replacement for SLFMIS.
- Connected to real government financial databases.
- Processing real government funds.
- Approved, endorsed, or operated by the Ministry unless such authorization is formally obtained.

The project is described as:

> An independent prototype inspired by publicly available information about Somaliland's public financial management structure, workflows, and digital-finance objectives.

The Ministry already operates SLFMIS, which publicly describes financial management modules. SFGMIS should therefore remain clearly distinguished from that existing system.

## Design Principle

The central design principle is:

**Budget → Allocation → Commitment → Procurement → Expenditure → Payment → Accounting → Reporting → Audit**

This integrated workflow is intended to make SFGMIS a financial management platform rather than simply a collection of separate administration pages.
