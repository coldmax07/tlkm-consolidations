# Transactions Table (Balance Sheet & Income Statement)

This note explains how the transactions table works for both statements and what matters to BAs and developers.

## What you see
- Filters: statement, period (open/active), company/counterparty, status, agreement status, account category, HFM account.
- Sorting & paging: sortable headers (pair/sender/receiver/variance), server-side pagination (page/per_page), totals always reflect all filtered rows.
- Columns: Pair (sender → receiver, currency), Sender leg, Receiver leg, Variance (sender − receiver).
- Sender leg also shows:
  - Base `amount` (editable when allowed)
  - `adjustment_amount` (editable when allowed)
  - `final_amount` (read-only calculated as `amount - adjustment_amount`)

## Data model
- Each row represents an `ic_transactions` record grouped from two `ic_transaction_legs` (sender, receiver).
- Legs carry role (SENDER/RECEIVER), status (DRAFT, PENDING_REVIEW, REVIEWED, REJECTED), amount, agreement status (UNKNOWN/AGREE/DISAGREE), and HFM account/category.
- Sender legs also store `adjustment_amount`; `final_amount` is computed (not persisted).
- Transactions are created from active `transaction_templates` by period generation (`TemplateService`).

## Permissions & visibility
- Buttons are driven by backend policy flags returned per leg (`permissions` in the API response). UI only shows actions when allowed.
- Roles:
  - `group_admin`: can act on any leg but still follows status flow (no approve in Draft, etc.).
  - `company_admin`: same-company legs, all actions allowed respecting status flow.
  - `company_preparer`: edit/submit sender; edit/submit receiver (after sender reviewed).
  - `company_reviewer`: review/approve/reject sender; review/approve/reject receiver.
- Same-company requirement applies to non-group admins.

## Sender workflow
- Draft/Rejected: edit amount and adjustment amount, save, send for review.
- Pending Review: approve or reject.
- Reviewed: no actions.
- Important: existing system calculations still use base `amount`. `final_amount` is informational.

## Receiver workflow
- Prereq: sender leg must be Reviewed.
- Draft/Rejected: edit amount, select agreement (Agree/Disagree), optionally reason, submit for review.
- Pending Review: approve or reject.
- Reviewed: no actions.
- Agreement rules: must pick Agree or Disagree; Agree requires matching sender amount and clears reason; Disagree requires a reason; UNKNOWN is rejected.
- Receiver contract remains unchanged (`amount` only). No receiver adjustment/final fields are captured.

## Backend contracts
- API: `GET /api/statements` with filters + sort + pagination returns transactions, legs, permissions, agreement status names, and totals.
- Actions:
  - Sender: `PATCH /api/legs/{id}` (`amount`, optional `adjustment_amount`), `POST /api/legs/{id}/submit`, `POST /api/legs/{id}/approve`, `POST /api/legs/{id}/reject`.
  - Receiver: `PATCH /api/legs/{id}/receiver`, `POST /api/legs/{id}/receiver/submit`, `POST /api/legs/{id}/receiver/approve`, `POST /api/legs/{id}/receiver/reject`.
- Validation: receiver must decide Agree/Disagree; Agree cannot include a reason; Disagree must include a reason; submissions/reviews enforce status gates.
- Statement API sender leg payload includes `adjustment_amount` and computed `final_amount`; receiver payload is unchanged.

## BA/testing tips
- Use seeded users: `{code}.preparer@tlkm.test`, `{code}.reviewer@tlkm.test`, `{code}.admin@tlkm.test`, and group admin `admin@tlkm.test`.
- Generate data: run TestIntercompanySeeder, then generate transactions for an open period.
- Verify flows: sender Draft → Pending Review → Reviewed; receiver stays locked until sender Reviewed; receiver Agree with matching amount; Disagree requires reason.

## What else to add?
- Examples of API requests/responses for common actions.
- Error message reference (validation and authorization).
- Mapping of statuses to allowed transitions (state diagram).
- Troubleshooting: common reasons actions are hidden (role, company mismatch, status, missing agreement).
- How periods are picked and how to unlock/lock them for testing.
