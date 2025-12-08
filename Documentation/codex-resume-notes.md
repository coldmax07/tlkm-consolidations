## Worklog / Notes for Codex Resume

Recent key changes:
- Added `period_number` to periods (migration `2025_11_26_000001_add_period_number_to_periods_table.php`); FiscalCalendarSeeder now seeds 1–12; period labels in UI are prefixed with `#<number> - <label>` (Templates period select, StatementView period select, FiscalCalendar display/status messages, Dashboard header). APIs now return `period_number` in period payloads (TemplateLookupController, StatementMetaController, StatementController, ReportController). A backfill may be needed for existing periods.
- Added contextual unique constraint for transaction templates’ `description` scoped to (financial_statement_id, sender_company_id, receiver_company_id) via migration `2025_11_26_000002_add_unique_description_to_transaction_templates.php`. Validation updated in Store/UpdateTransactionTemplateRequest. Seeders/templates must use unique descriptions within that scope.
- Dashboard: new `/api/dashboard` data includes ageing (cycle time), completion trend, net exposure, top accounts, status/agreement counts, variance quality, per-entity metrics. Frontend charts added with Highcharts (exporting enabled), info modals, R currency formatting. Requires `npm install` (Highcharts) if not already run.
- Reports: added Excel/PDF exports; PDF styling tuned for width; grouped column shading; admin company selection required.
- Seeders: FiscalCalendarSeeder updated with period_number; Role/User seeders expanded with company_preparer/company_reviewer roles (earlier work); TestIntercompanySeeder targets active periods.

Open reminders / potential tasks:
- Backfill `period_number` for existing periods if data already exists.
- Ensure Highcharts dependency is installed (`npm install`) in the environment.
- Consider further UI period label updates if any remain without numbering.
- PDF export styling is tight; if columns still clip, adjust margins/widths/padding further.
