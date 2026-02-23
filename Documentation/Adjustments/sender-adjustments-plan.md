## Sender Adjustments: `adjustment_amount` and `final_amount`

### Objective
- Add two sender-side fields on transaction legs:
  - `adjustment_amount` (captured by sender)
  - `final_amount` (calculated as `amount - adjustment_amount`)
- Keep all existing processing semantics unchanged:
  - receiver contract remains `amount`
  - variance and totals remain based on `amount`
  - receiver sync behavior remains based on sender `amount`

### Confirmed business rules
- `final_amount` is calculated only; it is not persisted as a DB column.
- New fields are visible/captured on sender side only in statement transaction tables.
- Receiver side does not get new fields and does not change workflow.
- Receiver `amount` handling remains as-is in the current system.
- Existing agreement/approval/variance/totals behavior remains unchanged.

### Implemented scope
1. Schema and model
- Added `adjustment_amount` to `ic_transaction_legs`:
  - `database/migrations/2026_02_22_000001_add_adjustment_amount_to_ic_transaction_legs_table.php`
- Updated model:
  - `app/Models/IcTransactionLeg.php`
  - added `adjustment_amount` to `$fillable`
  - added `adjustment_amount` cast (`decimal:2`)
  - added computed accessor `final_amount`

2. Sender update API flow
- Request validation:
  - `app/Http/Requests/UpdateSenderLegRequest.php`
  - now accepts `adjustment_amount` as `nullable|numeric`
- Controller wiring:
  - `app/Http/Controllers/Api/LegWorkflowController.php`
  - passes both `amount` and `adjustment_amount` to service
  - sender update response now includes `adjustment_amount` and `final_amount`
- Service persistence:
  - `app/Services/WorkflowService.php`
  - `updateSenderAmount` now saves `amount` + `adjustment_amount`
  - receiver sync logic remains unchanged (syncs sender `amount`)

3. Statement API payload
- Sender leg now exposes adjustment fields:
  - `app/Http/Controllers/Api/StatementController.php`
  - `transformLeg` includes sender-only:
    - `adjustment_amount`
    - `final_amount`

4. Statement UI (both balance sheet and income statement routes)
- Sender-side capture and display added:
  - `resources/js/pages/StatementView.jsx`
  - new sender adjustment input
  - local sender final amount auto-calculation (`final = amount - adjustment`)
  - read-only sender final amount display in sender leg cell
- Receiver UI remains unchanged.

### Explicitly unchanged
- Receiver API/UI contract (`amount` field only).
- Agreement validation semantics.
- Variance formula and summary totals.
- Dashboard/report calculations.
- Receiver approval snapshot behavior.

### Blindspots and guardrails
- User interpretation risk:
  - `final_amount` is informational and does not drive variance/totals.
  - UI labels should keep this clear.
- Input quality:
  - `adjustment_amount` is numeric; apply tighter bounds later if required by policy.
- Future extension:
  - If business later wants `final_amount` to drive core calculations, that is a separate change request.
