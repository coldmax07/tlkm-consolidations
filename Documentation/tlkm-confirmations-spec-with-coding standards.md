
# TLKM Confirmations – Technical Specification & Implementation Plan

## 1. Purpose & Scope

This document defines the technical design and implementation plan for the **TLKM Intercompany Confirmations** system, built on **Laravel + React**.

The system replaces an Excel-based process for reconciling recurring intercompany transactions among group companies. It provides:

- Period-based **Balance Sheet** and **Income Statement** confirmations.
- Automatic creation of **counterparty legs** (Receivable ↔ Payable, Revenue ↔ Expense).
- A **two-step workflow** per company side (Preparer → Reviewer).
- **Agreement / disagreement** logic on receiver-side entries.
- **Per-transaction conversations** with file attachments.
- Role-based access control using **spatie/laravel-permission**.

This spec assumes the Laravel project skeleton you already have:

```text
tlkm-comfirmations/
├── app/
├── public/
├── resources/
│   ├── js/
│   ├── css/
│   └── views/
├── routes/
│   └── web.php
├── vite.config.js
└── package.json
```

---

## 2. Domain Model & Terminology

### 2.1 Key Concepts

- **Financial Statement** – either **Balance Sheet** or **Income Statement**.
- **Account Category** – logical grouping within a statement:
  - Balance Sheet: **Receivable**, **Payable**.
  - Income Statement: **Revenue**, **Expense**.
- **HFM Account** – specific account (e.g. *Gross Receivable*, *Trade Payable*). Each belongs to **exactly one** account category and therefore to one financial statement.
- **Company** – legal entity within the group (Sundowns, Arsenal, Pirates, Barcelona, etc.).
- **User** – employee belonging to one company. Some users are group admins.

- **Intercompany Transaction** – the **whole business transaction** between two companies in a given period and for a given HFM mapping. It always has **two legs**:
  - Sender leg: Receivable or Revenue.
  - Receiver leg: Payable or Expense.

- **Transaction Leg** – one company’s side of the transaction, with its own preparer, reviewer, amount, status, and agreement.

### 2.2 Roles (High-level)

- **Group Admin**
  - Manages master data (companies, accounts, templates, periods).
  - Sees all transactions and conversations.
- **Company User**
  - Sees only transactions where their company is either sender or receiver.
  - Acts as preparer or reviewer depending on workflow.

Detailed permissions are managed via **Spatie’s `roles` and `permissions`**.

---

## 3. Data Model

> **Important:** All “enum-like” values are implemented as lookup tables with foreign keys, not database enums.

### 3.1 Master Data Tables

#### 3.1.1 `companies`

- `id` (PK)
- `name` (string)
- `code` (string, unique)
- `is_group_company` (bool) – optional for reporting
- Timestamps

#### 3.1.2 `users`

- Laravel default plus:
- `company_id` (FK → companies.id)
- Spatie **roles** (e.g. `group_admin`, `company_user`) are stored in Spatie’s tables:
  - `roles`, `permissions`, `model_has_roles`, `model_has_permissions`.

#### 3.1.3 `financial_statements`

Seeded values:

- `1` – BALANCE_SHEET (`display_label = "Balance Sheet"`)
- `2` – INCOME_STATEMENT (`display_label = "Income Statement"`)

Columns:

- `id` (PK)
- `name` (string, system name)
- `display_label` (string)
- Timestamps

#### 3.1.4 `account_categories`

Seeded values:

- For Balance Sheet:
  - `Receivable`
  - `Payable`
- For Income Statement:
  - `Revenue`
  - `Expense`

Columns:

- `id` (PK)
- `financial_statement_id` (FK → financial_statements.id)
- `name` (string)
- `display_label` (string)
- Timestamps

**Business rule:** an account category belongs to exactly one financial statement.

#### 3.1.5 `hfm_accounts`

Seeded with values like:

- Gross Receivable
- Other Receivable
- Trade Payable
- Other Payable
- Operating Revenue
- Sundry Revenue
- Maintenance
- Rental

Columns:

- `id` (PK)
- `account_category_id` (FK → account_categories.id)
- `name` (string)
- `code` (string, optional)
- Timestamps

**Business rules:**

- Each HFM account belongs to exactly one account category.
- Because categories belong to a single statement, an HFM account implicitly belongs to one financial statement and cannot be reassigned across statements casually.

#### 3.1.6 Fiscal Time

`fiscal_years`:

- `id` (PK)
- `label` (“FY2025”)
- `starts_on` (date)
- `ends_on` (date)
- Timestamps

`periods`:

- `id` (PK)
- `fiscal_year_id` (FK → fiscal_years.id)
- `year` (int, e.g. 2025)
- `month` (tinyint 1..12)
- `label` (“2025-11”)
- `status_id` (FK → `period_statuses.id` – e.g. OPEN / CLOSED)
- Timestamps

`period_statuses` (lookup):

- `id`
- `name` (OPEN, CLOSED)
- `display_label`

### 3.2 Lookup Tables for Leg Behaviour

#### 3.2.1 `leg_statuses`

- `id`
- `name` (DRAFT, PENDING_REVIEW, REVIEWED, REJECTED)
- `display_label`
- `is_final` (bool)

Used by: `ic_transaction_legs.status_id`.

#### 3.2.2 `leg_natures`

- `id`
- `name` (RECEIVABLE, PAYABLE, REVENUE, EXPENSE)
- `display_label`
- `financial_statement_id` (FK → financial_statements.id)

Used by: `ic_transaction_legs.leg_nature_id`.

**Examples:**

- RECEIVABLE → financial_statement_id = BALANCE_SHEET
- PAYABLE → BALANCE_SHEET
- REVENUE → INCOME_STATEMENT
- EXPENSE → INCOME_STATEMENT

#### 3.2.3 `leg_roles`

- `id`
- `name` (SENDER, RECEIVER)
- `display_label`

Used by: `ic_transaction_legs.leg_role_id`.

#### 3.2.4 `agreement_statuses`

- `id`
- `name` (UNKNOWN, AGREE, DISAGREE)
- `display_label`

Used by: `ic_transaction_legs.agreement_status_id` (nullable initially).

### 3.3 Templates (Recurring Definitions)

#### 3.3.1 `transaction_templates`

Represents a definition of a recurring intercompany transaction pair.

Columns:

- `id` (PK)
- `financial_statement_id` (FK → financial_statements.id)
- `sender_company_id` (FK → companies.id)
- `receiver_company_id` (FK → companies.id)
- `sender_account_category_id` (FK → account_categories.id)
- `sender_hfm_account_id` (FK → hfm_accounts.id)
- `receiver_account_category_id` (FK → account_categories.id)
- `receiver_hfm_account_id` (FK → hfm_accounts.id)
- `currency` (string, e.g. “ZAR”)
- `default_amount` (decimal, nullable)
- `is_active` (bool)
- Timestamps

**Key business rules:**

- `sender_account_category.financial_statement_id` = `financial_statement_id`.
- `receiver_account_category.financial_statement_id` = `financial_statement_id`.
- HFM accounts must belong to their respective categories.
- For `financial_statement = BALANCE_SHEET`:
  - Sender category must be **Receivable**.
  - Receiver category must be **Payable**.
- For `financial_statement = INCOME_STATEMENT`:
  - Sender category must be **Revenue**.
  - Receiver category must be **Expense**.
- This mapping defines both legs of each transaction generated from this template.

### 3.4 Transaction Layer

#### 3.4.1 `ic_transactions`

Represents one **intercompany transaction** for a specific period, based on a template.

Columns:

- `id` (PK)
- `period_id` (FK → periods.id)
- `transaction_template_id` (FK → transaction_templates.id)
- `financial_statement_id` (FK → financial_statements.id)
- `sender_company_id` (FK → companies.id)
- `receiver_company_id` (FK → companies.id)
- `currency` (string)
- `created_from_default_amount` (bool) – optional flag
- Timestamps

**Uniqueness constraint:**

- `UNIQUE(period_id, transaction_template_id)` – enforces “one transaction per template per period”.

#### 3.4.2 `ic_transaction_legs`

Represents each company’s side (“leg”) of an intercompany transaction.

There are always **two legs** per transaction:

- Leg A: Sender company (Receivable / Revenue)
- Leg B: Receiver company (Payable / Expense)

Columns:

- `id` (PK)
- `ic_transaction_id` (FK → ic_transactions.id)
- `company_id` (FK → companies.id)
- `counterparty_company_id` (FK → companies.id)
- `leg_role_id` (FK → leg_roles.id) – SENDER or RECEIVER
- `leg_nature_id` (FK → leg_natures.id)
- `hfm_account_id` (FK → hfm_accounts.id)

Workflow & users:

- `status_id` (FK → leg_statuses.id, default: DRAFT)
- `prepared_by_id` (FK → users.id, nullable)
- `prepared_at` (datetime, nullable)
- `reviewed_by_id` (FK → users.id, nullable)
- `reviewed_at` (datetime, nullable)

Amounts & agreement:

- `amount` (decimal, nullable at start)
- `agreement_status_id` (FK → agreement_statuses.id, nullable)
- `disagree_reason` (text, nullable)
- `counterparty_amount_snapshot` (decimal, nullable – optional to store the other side’s amount at review time)

Timestamps.

**Business rules (application-level, plus optional DB checks):**

- Each `ic_transaction` must have exactly **two** legs:
  - One with `leg_role = SENDER`.
  - One with `leg_role = RECEIVER`.
- For Balance Sheet transactions:
  - Sender leg_nature = RECEIVABLE.
  - Receiver leg_nature = PAYABLE.
- For Income Statement transactions:
  - Sender leg_nature = REVENUE.
  - Receiver leg_nature = EXPENSE.
- `hfm_account.account_category` must be compatible with the chosen `leg_nature` and `financial_statement`.
- **Display rule:** Any values that fall under Payable account categories must be rendered in accounting format (e.g. `(1 234.00)`); treat them as negative for UI/reporting purposes even if stored as positive numbers.
- **Receiver leg is not editable** unless the sender leg’s status = REVIEWED.

#### 3.4.3 `ic_leg_status_history` (optional but recommended)

For audit trail of status transitions.

Columns:

- `id` (PK)
- `ic_transaction_leg_id` (FK → ic_transaction_legs.id)
- `from_status_id` (FK → leg_statuses.id)
- `to_status_id` (FK → leg_statuses.id)
- `changed_by_id` (FK → users.id)
- `note` (text, nullable)
- `created_at`

#### 3.4.4 `review_assignments`

If multiple reviewers can be proposed for a leg.

Columns:

- `id` (PK)
- `ic_transaction_leg_id` (FK → ic_transaction_legs.id)
- `user_id` (FK → users.id)
- `assigned_by_id` (FK → users.id)
- `created_at`

### 3.5 Conversations & Attachments

#### 3.5.1 `threads`

Conversations are one per **transaction** (not per leg).

- `id` (PK)
- `ic_transaction_id` (FK → ic_transactions.id)
- `created_by_id` (FK → users.id)
- Timestamps

#### 3.5.2 `messages`

- `id` (PK)
- `thread_id` (FK → threads.id)
- `company_id` (FK → companies.id)
- `user_id` (FK → users.id)
- `role_context_id` (FK → `message_role_contexts.id`) – PREPARER / REVIEWER / ADMIN
- `body` (text)
- Timestamps

`message_role_contexts` (lookup table):

- `id`
- `name` (PREPARER, REVIEWER, ADMIN)
- `display_label`

#### 3.5.3 `attachments`

- `id` (PK)
- `message_id` (FK → messages.id)
- `disk` (string)
- `path` (string)
- `filename` (string)
- `mime_type` (string)
- `size` (int)
- Timestamps

---

## 4. Agreement Logic (Receiver Side)

### 4.1 Rules

On **receiver leg save**:

1. `agreement_status_id` is **required**:
   - Cannot save while NULL.
2. Let `S = sender_leg.amount`, `R = receiver_leg.amount`.
3. If user selects **AGREE**:
   - Require `R == S`. If not, validation error:
     > “You can only set ‘Agree’ when your amount equals the counterparty’s amount.”
4. If user selects **DISAGREE**:
   - Allowed in all cases (even when `R == S`).  
   - Optionally require/encourage a `disagree_reason` when values differ.
5. Never auto-set AGREE when values differ.  
   - UI can suggest a default selection, but backend validation is the source of truth.

### 4.2 Implementation

- **Form Request** for updating receiver leg:
  - Validate presence of `agreement_status_id`.
  - Fetch sender leg for same `ic_transaction_id` where `leg_role = SENDER`.
  - Apply rules above.
- Frontend:
  - Always show **read-only counterparty amount** next to editable amount.
  - Show radio buttons or select for **Agree / Disagree**.

---

## 5. Workflows

### 5.1 Sender Company Workflow

**Context:** user from the sender company viewing Balance Sheet / Income Statement for a given period.

1. **Preparer** (sender leg)
   - Grid shows all sender legs for that company & period.
   - For a chosen leg:
     - Enters **amount** (starting from default if any).
     - Assigns one or more **reviewers** via modal (users in same company).
     - Clicks **Send for Review**:
       - `status` → PENDING_REVIEW.
       - `prepared_by_id` and `prepared_at` set.
       - Notification sent to assigned reviewers (email + in-app).

2. **Reviewer** (sender leg)
   - Sees legs where:
     - `company_id = their company`
     - `status = PENDING_REVIEW`
     - They are in `review_assignments` list (optional filter).
   - Options:
     - **Approve**:
       - Confirmation modal: “Are you sure? Receiver company will be notified.”
       - On confirm:
         - `status` → REVIEWED.
         - `reviewed_by_id`, `reviewed_at` set.
         - Notification to receiver preparers.
         - Receiver leg is unlocked for editing.
     - **Reject**:
       - Must provide reason.
       - `status` → REJECTED.
       - Notification to sender preparer.
       - Preparer can edit and resubmit.

**Guard:** receiver leg cannot leave DRAFT until sender leg = REVIEWED.

### 5.2 Receiver Company Workflow

1. **Preparer** (receiver leg)
   - After sender leg is REVIEWED, receiver leg becomes editable.
   - Sees:
     - Sender amount (read-only).
     - Editable receiver amount.
     - Agreement control (Agree / Disagree).
   - Logic:
     - If they choose **Agree**, amount must equal sender’s amount (backend enforced).
     - If they choose **Disagree**, can set different amount.
   - Selects reviewer(s) in their company.
   - Clicks **Send for Review** → `status` → PENDING_REVIEW.

2. **Reviewer** (receiver leg)
   - Similar options as sender reviewer:
     - Approve → `status` → REVIEWED; notify both sides / group admin.
     - Reject → `status` → REJECTED; notify receiver preparer.

### 5.3 Variance & Totals

For a given statement & period & company:

- **Balance Sheet**
  - Top group: **Receivables** (sender or receiver legs depending on role).
  - Bottom group: **Payables**.
  - Variance row: `Σ(Receivables) − Σ(Payables)`.

- **Income Statement**
  - Top group: **Revenues**.
  - Bottom group: **Expenses**.
  - Variance row: `Σ(Revenue) − Σ(Expenses)`.

The grid must display group totals and a final variance line, styled according to design.

---

## 6. UI & Frontend Structure (React)

### 6.1 Main Pages

- `/statements/balance-sheet`
- `/statements/income-statement`

Both pages share a similar structure:
- Period & FY selector.
- Filter panel:
  - Sender (multi-select)
  - Receiver (multi-select)
  - HFM Account (multi-select)
  - Account Category (multi-select)
  - Status (multi-select)
- Data grid with groups, totals and variance.
- Action buttons per row (leg).

### 6.2 Components

Suggested React components (under `resources/js`):

- `pages/BalanceSheet.jsx`
- `pages/IncomeStatement.jsx`
- `components/StatementFilters.jsx`
- `components/StatementGrid.jsx`
- `components/LegActionsMenu.jsx`
- `components/PeriodPicker.jsx`
- `components/ReviewerPickerModal.jsx`
- `components/ConfirmDialog.jsx`
- `components/ThreadDrawer.jsx` (slide-out drawer for chat)
- `components/MessageList.jsx`
- `components/MessageInput.jsx`
- `components/AttachmentUploader.jsx`

### 6.3 Behavioural Details

- When a user lands on a statement page:
  - If current period has transactions → load them.
  - If not:
    - Load latest previous period with data.
    - Display button “Create current period’s transactions” if user has permission (admin/company admin).
- Grid rows are grouped by **Account Category** with group total row after each category.
- Final **Variance** row is displayed at the bottom.

---

## 7. Backend: Services, Controllers & APIs

### 7.1 Service Classes

#### 7.1.1 `TemplateService`

- `generateTransactionsForPeriod(Period $period)`:
  - Fetch active templates applicable to the period.
  - For each template:
    - If no `ic_transactions` exists for (period, template):
      - Create `ic_transactions` row.
      - Create **two** `ic_transaction_legs`:
        - Sender leg: using sender HFM account, `leg_role = SENDER`, appropriate `leg_nature`.
        - Receiver leg: using receiver HFM account, `leg_role = RECEIVER`, appropriate `leg_nature`.
      - Initialize sender amount from `default_amount` if any.

#### 7.1.2 `WorkflowService`

- `submitForReview(IcTransactionLeg $leg, User $by)`:
  - Validate leg belongs to user’s company.
  - Set `status = PENDING_REVIEW`, `prepared_by_id`, `prepared_at`.
  - Create `ic_leg_status_history` entry.
  - Notify reviewers.

- `approve(IcTransactionLeg $leg, User $by)`:
  - Ensure leg is PENDING_REVIEW and user is allowed reviewer.
  - Set `status = REVIEWED`, `reviewed_by_id`, `reviewed_at`.
  - Save snapshot of counterparty amount.
  - If leg is sender leg → unlock receiver leg for editing.
  - Notify counterparty preparers.
  - Add status history.

- `reject(IcTransactionLeg $leg, User $by, string $reason)`:
  - Set `status = REJECTED`, record history, notify preparer.

#### 7.1.3 `AgreementService`

- `validateReceiverAgreement(IcTransactionLeg $receiverLeg, AgreementStatus $agreementStatus, Money $amount)`:
  - Fetch sender leg.
  - Apply the rules from section 4.
  - Throw validation exception if violated.

### 7.2 Controllers & API Routes

Example API endpoints (to be mounted under `/api`):

- **Statements**
  - `GET /api/statements/{type}/period/{period}`
    - Returns legs for current user with filters.
  - `POST /api/statements/{type}/period/{period}/generate`
    - Admin/company-admin only. Invokes `TemplateService::generateTransactionsForPeriod`.

- **Legs (CRUD-ish)**
  - `PATCH /api/legs/{id}`
    - Update amount, agreement status (receiver only), comments on leg (if any non-chat fields).
  - `POST /api/legs/{id}/submit`
    - DRAFT → PENDING_REVIEW.
  - `POST /api/legs/{id}/approve`
    - PENDING_REVIEW → REVIEWED.
  - `POST /api/legs/{id}/reject`
    - PENDING_REVIEW → REJECTED.

- **Reviewer assignments**
  - `POST /api/legs/{id}/reviewers`
    - Set reviewers for a leg.
  - `GET /api/legs/{id}/reviewers`

- **Templates**
  - `GET /api/templates`
  - `POST /api/templates`
  - `PATCH /api/templates/{id}`
  - `DELETE /api/templates/{id}`

- **Threads & Messages**
  - `GET /api/transactions/{id}/thread`
  - `POST /api/transactions/{id}/messages`
  - `POST /api/messages/{id}/attachments`

### 7.3 Policies & Authorization

Using Laravel Policies + Spatie roles:

- `IcTransactionLegPolicy`:
  - `view(User $user, Leg $leg)` → user is `group_admin` OR `$leg->company_id == $user->company_id`.
  - `update(User $user, Leg $leg)` → same company + leg status not final + role-based restrictions (sender vs receiver, preparer vs reviewer).
  - `review(User $user, Leg $leg)` → user is assigned as reviewer & leg is PENDING_REVIEW.

- `IcTransactionPolicy`:
  - `view(User $user, Transaction $tx)` → user is `group_admin` OR user’s company is sender/receiver.

---

## 8. Implementation Plan (Phases)

### Phase 0 – Foundations & Master Data

**Goal:** Establish all lookup tables and core master data.

Tasks:

1. Create migrations & models for:
   - `companies`, `users` (with `company_id` FK).
   - `financial_statements`, `account_categories`, `hfm_accounts`.
   - `fiscal_years`, `periods`, `period_statuses`.
   - Lookup tables: `leg_statuses`, `leg_natures`, `leg_roles`, `agreement_statuses`, `message_role_contexts`.
2. Integrate **Spatie Laravel Permission**:
   - Seed roles: `group_admin`, `company_admin`, `company_user`.
3. Seed initial data:
   - Financial statements (BS, IS).
   - Account categories (Receivable, Payable, Revenue, Expense).
   - HFM accounts list from business.
   - Leg statuses/natures/roles, agreement statuses.

**Deliverables:**
- All master & lookup tables in DB.
- Seeder classes for default data.
- Ability to log in and see which company the user belongs to.

---

### Phase 1 – Templates & Period Generation

**Goal:** Allow admin to define recurring intercompany templates and generate transactions for periods.

Tasks:

1. Create `transaction_templates` table + model + migrations.
2. Add admin UI:
   - Template list with filters (statement, companies, accounts).
   - Template create/edit screens following the flow in section 3.3.1.
3. Implement server-side validation rules ensuring correct category/statement mapping.
4. Implement `TemplateService::generateTransactionsForPeriod`:
   - Create `ic_transactions` and two `ic_transaction_legs` as per template.
5. Add API endpoint + frontend button:
   - On statement pages: “Generate current period’s transactions”.
6. Respect rule:
   - **If current period has no transactions, show previous period’s data and expose the “Create current period” button**.

**Deliverables:**
- Templates CRUD working.
- Generation of transactions and legs for a period without workflow yet.

---

### Phase 2 – Statement Pages (Read-only View)

**Goal:** Provide initial Balance Sheet & Income Statement views, grouped correctly, but without full workflow.

Tasks:

1. Implement queries in a `StatementsController`:
   - Filter by statement, period, company, sender, receiver, account, category, status.
2. Implement React pages:
   - `/statements/balance-sheet`
   - `/statements/income-statement`
3. Implement `StatementFilters` and `StatementGrid` components:
   - Group by Account Category (top vs bottom sections).
   - Compute totals and variance on backend and/or frontend.
4. Apply authorization:
   - Group admins see all legs for the statement & period.
   - Company users see only legs for their company.

**Deliverables:**
- Users can open statement pages and see correct period rows, grouped with totals and variance, but cannot yet edit amounts or change statuses.

---

### Phase 3 – Sender-side Workflow (Preparer & Reviewer)

**Goal:** Enable sender company preparer and reviewer workflow on each leg.

Tasks:

1. Implement edits on sender legs:
   - Form to update `amount` in DRAFT or REJECTED status.
2. Implement “Send for review”:
   - API: `POST /api/legs/{id}/submit`.
   - Validate company ownership & current status.
   - Set `status = PENDING_REVIEW`, `prepared_by_id`, `prepared_at`.
   - Create `ic_leg_status_history` row.
   - Notification to reviewers.
3. Implement reviewer actions:
   - API: `POST /api/legs/{id}/approve`, `POST /api/legs/{id}/reject`.
   - Confirmation modals.
   - On approve:
     - `status = REVIEWED`, set reviewed fields, history.
     - Notify receiver side + unlock receiver leg for editing.
   - On reject:
     - `status = REJECTED`, require reason, notify preparer.
4. Enforce sender vs receiver EDIT rules via `IcTransactionLegPolicy`.

**Deliverables:**
- Full sender workflow from DRAFT → PENDING_REVIEW → REVIEWED/REJECTED.
- Receiver leg remains visible but not editable until sender leg = REVIEWED.

---

### Phase 4 – Receiver-side Workflow & Agreement

**Goal:** Enable receiver company preparer & reviewer to confirm and agree/disagree.

Tasks:

1. Implement update endpoint for receiver legs:
   - Allows editing `amount` only when sender leg is REVIEWED and receiver leg is not final.
   - Exposes `agreement_status_id` and `disagree_reason` fields.
2. Add server-side validation using `AgreementService`:
   - Enforce rules in section 4.
3. Extend React UI:
   - Show read-only **sender amount** in receiver rows.
   - Show inputs for receiver amount + Agree/Disagree choice.
4. Implement receiver-side “Send for review”, “Approve”, and “Reject” flows:
   - Similar to sender, but with agreement validation.
5. Update variance calculations to reflect receiver-side changes.

**Deliverables:**
- End-to-end workflow for both legs of an intercompany transaction.
- Agreement/disagreement captured correctly and respected.

---

### Phase 5 – Conversations, Attachments & UX Polish

**Goal:** Provide per-transaction chat and refine user experience.

Tasks:

1. Implement `threads`, `messages`, and `attachments` tables + models.
2. Implement backend routes:
   - `GET /api/transactions/{id}/thread`
   - `POST /api/transactions/{id}/messages`
   - `POST /api/messages/{id}/attachments`
3. React UI:
   - `ThreadDrawer` that opens from a “Comment” button in each row.
   - Messages shown with:
     - Company name & color coding.
     - Role context (Preparer / Reviewer / Admin).
   - File attachment support.
4. Add in-app notifications on new messages (optional for Phase 5).
5. Polish & harden UX:
   - Better empty-state messaging.
   - Loading and error states.
   - Highlighting totals and variance rows.
   - Accessibility and responsive layout.

**Deliverables:**
- Robust per-transaction chat with file attachments.
- Polished Balance Sheet & Income Statement experiences for end users and admins.

---

## 9. Testing & Quality

### 9.1 Unit & Feature Tests

- Template validation (correct categories & statements).
- Period generation (creates exactly one transaction and two legs per template).
- Sender workflow transitions & authorization.
- Receiver workflow, agreement validation, and variance computation.
- Visibility rules (company vs group admin).

### 9.2 UAT Scenarios

- Full lifecycle for:
  - Receivable ↔ Payable (Balance Sheet).
  - Revenue ↔ Expense (Income Statement).
- Case where receiver **agrees** and case where receiver **disagrees**.
- Rejections from both sender and receiver reviewers.
- Period with no current transactions → previous period + “Create current period” flow.

---


## 10. Coding Standards & Patterns (with OWASP Considerations)

This section defines **how** we write code in this project, so that:

- The codebase is consistent and maintainable.
- Business rules are centralized and testable.
- We follow **OWASP Secure Coding Practices** for input validation, authentication, authorization, error handling, and logging.

These are conventions, not rigid laws—but any deviation should be discussed in code review.

---

### 10.1 High-level Architectural Principles

1. **Layered Architecture**
   - **Controllers**: translate HTTP requests into service calls; no business logic, minimal query building.
   - **Form Requests**: handle validation and authorization for specific actions.
   - **Services**: encapsulate use cases and workflows (e.g. `TemplateService`, `WorkflowService`, `AgreementService`).
   - **Models/Eloquent**: represent persistence; keep them light (relationships, accessors, scopes).
   - **Policies**: enforce authorization at the domain level (who can do what on which object).
   - **Notifications**: encapsulate outbound email/in-app alerts.

2. **Fat Services, Thin Controllers**
   - Most complex logic (status transitions, amount/agreement checks, generation) lives in services.
   - Controllers should be easily reviewable and predictable: authentication, basic authorization, call service, return JSON/response.

3. **Explicit Dependencies**
   - Use constructor injection for services and repositories.
   - Avoid global helpers except for simple tasks (`now()`, `auth()`), and avoid calling facades directly inside business logic where possible.

4. **DDD-lite Ubiquitous Language**
   - Use domain terms consistently: `IntercompanyTransaction`, `TransactionLeg`, `Template`, `AgreementStatus`, etc.
   - Keep naming aligned with the spec and UI to reduce mental overhead.

---

### 10.2 DTOs (Data Transfer Objects)

We use DTOs to pass structured data into services instead of raw arrays, especially for **critical actions** (workflow, agreement, template creation).

**Guidelines**:

- DTOs are simple PHP classes (or `readonly` where possible) that contain public properties or accessors only.
- No DB queries inside DTOs.
- Validation happens in **Form Requests**; DTOs assume they are receiving validated data.

**Examples**:

- `CreateTemplateData`
  - `financialStatementId`, `senderCompanyId`, `receiverCompanyId`, `senderAccountCategoryId`, `senderHfmAccountId`, `receiverAccountCategoryId`, `receiverHfmAccountId`, `currency`, `defaultAmount`, `startPeriodId`, `endPeriodId`, `isActive`.

- `UpdateLegAmountData`
  - `legId`, `amount`, `agreementStatusId`, `disagreeReason`, `actingUserId`.

- `StatusTransitionData`
  - `legId`, `fromStatusId`, `toStatusId`, `reason`, `actedByUserId`.

DTOs make unit tests easier: you can call services with DTO instances without faking HTTP.

---

### 10.3 Services

Services encapsulate **use cases** and must be:

- Stateless (no per-request data stored as properties beyond constructor dependencies).
- Transaction-safe (wrap DB writes in `DB::transaction()` where multiple tables are updated).
- Guarded by policies and validations **before** service execution.

Core services:

1. **`TemplateService`**
   - `generateTransactionsForPeriod(Period $period)`
   - `createTemplate(CreateTemplateData $data)`
   - Handles business rules around financial statements, categories and HFM accounts.

2. **`WorkflowService`**
   - `submitForReview(StatusTransitionData $data)`
   - `approve(StatusTransitionData $data)`
   - `reject(StatusTransitionData $data)`
   - Ensures status transitions are valid and consistent, logs history, and sends notifications.

3. **`AgreementService`**
   - `validateReceiverAgreement(IcTransactionLeg $receiver, Money $amount, int $agreementStatusId)`
   - Encapsulates all rules from the **Agreement Logic** section.

**OWASP Alignment**:

- Input to services is validated & authorized before invocation.
- Services always check current persisted state to avoid **Insecure Direct Object Reference (IDOR)** issues (never trust client status).

---

### 10.4 Policies & Authorization

We use **Laravel Policies** as the primary authorization mechanism, with Spatie roles for coarse-grained access.

**Principles**:

1. **Deny by default**:
   - If a policy method is not explicitly allowing an action, it should return `false`.

2. **Company Isolation**:
   - Non-group-admins can only access legs/transactions where their `company_id` is either sender or receiver.
   - Where relevant, we restrict to the leg’s `company_id` instead of the transaction’s companies for extra safety.

3. **Business Rules in Policies**:
   - `IcTransactionLegPolicy@update` must check:
     - User’s company.
     - Leg status (must not be final for amount edits).
     - Role context (sender vs receiver, workflow stage).

4. **Use `authorize()` and `Gate::allows()`** in controllers, not ad-hoc `if` checks.

**OWASP Alignment**:

- Prevents **Broken Access Control** by centralizing rules.
- All endpoints guard access at *both* route level (middleware) and object level (policies).

---

### 10.5 Validation & Form Requests

Every mutating endpoint uses a **Form Request** class that handles:

- Input validation rules.
- `authorize()` method calling the relevant policy.

**Guidelines**:

- Validate all IDs with `exists:` rules and numeric constraints to avoid invalid foreign keys.
- No business logic in controllers—complex sanity checks live in services but triggered after validation.
- For agreement logic, validation ensures `agreement_status_id` is present for receiver legs and triggers service rules.

**OWASP Alignment**:

- Validates all inputs (including from authenticated users).
- Prevents misuse of endpoints with malformed or missing data.

---

### 10.6 Eloquent Models & Query Patterns

1. **No Mass Assignment on Sensitive Fields**
   - Use `$fillable` explicitly and avoid `$guarded = []` for transactional models.
   - Never allow direct client control over fields like `status_id`, `company_id`, `prepared_by_id`, `reviewed_by_id`.

2. **Use Query Scopes**
   - `scopeForCompany($query, Company $company)` to filter legs by `company_id`.
   - `scopeForStatement($query, FinancialStatement $statement)`.

3. **Avoid N+1 Queries**
   - Use `->with()` eager loading for relationships referenced in grids (e.g., companies, accounts, statuses, users).

4. **Soft Deletes Carefully**
   - Consider not using soft deletes for core transactional tables (or restrict hard deletes entirely) to preserve audit trail.

**OWASP Alignment**:

- Prevents unauthorized mass-modification of sensitive fields.
- Supports auditability and traceability of changes.

---

### 10.7 Error Handling & Logging

1. **User-facing Errors**
   - Return standardized error structures for API (e.g., `{ message, errors }`).
   - Avoid leaking internal exception messages (stack traces, SQL details) to the client.

2. **Logging**
   - Log security-relevant events:
     - Failed authorizations.
     - Abnormal status transitions.
     - Repeated validation failures that may indicate abuse.
   - Use Laravel’s logging channels; never log secrets, full access tokens or passwords.

3. **Exceptions**
   - Use custom exceptions for domain errors where appropriate (`InvalidStatusTransitionException`, `AgreementValidationException`), caught and transformed into safe responses.

**OWASP Alignment**:

- Avoids **Information Leakage** via detailed error messages.
- Promotes proper logging of security events without storing sensitive data.

---

### 10.8 Notifications & Email Security

1. **Avoid Sensitive Data in Emails**
   - Emails should refer to transaction IDs and high-level info but not full internal details.
   - Use generic wording without exposing internal URLs publicly accessible to unauthenticated users.

2. **Use Signed URLs (Optional)**
   - If including direct-action links, use Laravel signed routes or authenticated routes with CSRF protection.

3. **Rate Limiting**
   - Avoid email flooding by debouncing repeated notifications for the same event if necessary.

**OWASP Alignment**:

- Reduces risk where email is intercepted or forwarded.
- Prevents exploitation via open action links.

---

### 10.9 Frontend Security & Patterns

1. **API Consumption**
   - Use centralized HTTP client wrapper that automatically attaches CSRF token and Authorization headers.
   - Handle 401/403 consistently (e.g. redirect to login or show “Not authorized”).

2. **XSS Prevention**
   - Never dangerously set HTML from message bodies or comments without sanitization.
   - Treat all user content as text; escape or sanitize before rendering.

3. **State Management**
   - Keep sensitive tokens only in secure cookies (Laravel Sanctum / session), not localStorage.

4. **File Uploads**
   - Restrict allowed MIME types and file size on frontend **and** backend.
   - Show sanitized filenames in UI.

**OWASP Alignment**:

- Prevents common issues such as **Cross-Site Scripting (XSS)** and token leakage.
- Ensures defence-in-depth with both client and server validations.

---

### 10.10 Database & Migration Practices

1. **Explicit Foreign Keys**
   - All relationships in the spec use foreign keys with `on delete restrict` (or `cascade` only where safe).
   - Use indices on foreign keys used in filters (company_id, period_id, statement_id).

2. **Constraints as Guards**
   - Unique constraints (e.g. `(period_id, transaction_template_id)`) enforce business rules at DB level.
   - Where possible, use DB `CHECK` constraints to enforce status ranges or numeric limits.

3. **Migrations**
   - Each migration is idempotent and reversible (`down()` implemented).
   - Never modify an existing migration in a way that breaks already-deployed environments.

**OWASP Alignment**:

- Enforces data integrity at the lowest level.
- Reduces surface area for injection or inconsistent states.

---

### 10.11 Secure Coding Checklist (Project-specific)

This is a **practical subset** of OWASP Secure Coding practices tailored to this project:

1. **Authentication & Sessions**
   - Use Laravel’s built-in auth; do not roll your own.
   - Force HTTPS in production; set secure cookies and appropriate session lifetime.

2. **Authorization**
   - Every controller method that accesses/modifies `ic_transactions`, `ic_transaction_legs`, `threads`, or `messages` must call `authorize()` or attach a policy middleware.
   - Never rely solely on front-end checks for access control.

3. **Input Validation**
   - Validate all external input via Form Requests.
   - Use strict validation for IDs and enums (via lookup IDs).
   - Reject unexpected parameters and ignore client-sent IDs that shouldn’t be changeable.

4. **Output Encoding**
   - Escape all user-supplied text in Blade and React components by default.
   - Do not render HTML from user input unless properly sanitized.

5. **Sensitive Data**
   - Do not log amounts together with user identifiers in debug logs unless needed; prefer anonymized logging if possible.
   - Keep DB credentials, mail credentials, and secrets in `.env` only.

6. **Error Handling**
   - Generic error responses to clients; detailed errors only in logs.
   - Use centralized exception handler (`app/Exceptions/Handler.php`) to transform exceptions into safe HTTP responses.

7. **Rate Limiting & Abuse Protection**
   - Apply rate limiting to login endpoints and possibly message creation endpoints to reduce brute-force and spam.

8. **Dependencies & Updates**
   - Use `composer audit` and `npm audit` periodically.
   - Pin dependency versions and apply security updates regularly.

9. **Testing & Code Review**
   - Security-sensitive code (auth, policies, workflow transitions, agreement calculations) always requires at least one peer review.
   - Key workflows covered by feature tests to avoid regressions.

---

## 11. Summary
## 10. Summary

This design gives a clean separation between:

- **Master data** (companies, statements, categories, HFM accounts).
- **Definitions** (`transaction_templates`).
- **Periodic transactions** (`ic_transactions` + `ic_transaction_legs`).
- **Workflow state** (leg statuses, agreements).
- **Collaboration** (threads, messages, attachments).

All enum-like concepts are stored as lookup tables with foreign keys, and each intercompany transaction is modeled as a **single record with exactly two legs**, aligning with your business language of “one transaction per pair” while supporting the required workflows on each company’s side.
