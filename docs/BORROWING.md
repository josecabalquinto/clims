# Borrow History

The module uses the existing teal application shell, Flux controls, and Equipment workspace styles. Both active Super Admins and Lab Staff can record loans and returns. Borrowers do not need accounts.

## Pages and routes

| Route | Name | Purpose |
| --- | --- | --- |
| `/borrow-history` | `borrowing.index` | Paginated history, borrower/equipment search, borrower type and status filters |
| `/borrow-history/create` | `borrowing.create` | Borrower information, dates, notes, up to 50 distinct equipment lines |
| `/borrow-history/{transaction}` | `borrowing.show` | Borrower details, item quantities, status and attributed return history |
| `/borrow-history/{transaction}/return` | `borrowing.return` | Partial or full returns, including defective units received |

History tabs derive status from actual outstanding and returned quantities. Overdue takes precedence over Borrowed and Partially returned whenever the deadline has passed with units outstanding. Completed loans remain Returned after their deadline. Search matches borrower name, borrower identifier, equipment name and asset tag.

## Stock and history rules

`app/Services/ManageBorrowing.php` owns validation, authorization, stock changes and activity logging. Livewire pages coordinate input and navigation. Equipment is locked in ascending ID order before loan and item rows, matching the existing Equipment service's locking order. Stock validation runs against current locked rows. Database transactions and deadlock retries cover stock, loan items, returns and audit events together.

Borrowing reduces available units. A return reduces the outstanding quantity; usable units increase availability, while defective units increase defective stock. Total equipment quantity remains unchanged. Duplicate equipment lines, duplicate return lines, unavailable quantities, over-returns, cross-loan item IDs, and defective quantities greater than units received are rejected. All lines are checked before mutations.

Return quantities start at zero. Leave zero for items still with the borrower. The defective quantity is a subset of units returned, not an additional quantity. An entirely zero return is rejected. Completed loans display a completion message instead of a return form.

Loan item quantities are fixed after creation. Each return records equipment item, quantity, defective quantity, responsible account, event time and optional notes. Completion time is the latest actual return event, including when earlier events are entered later. Editing, deleting or cancelling loans and returns is outside this module's current scope.

Datetime inputs and displays use `config('clims.display_timezone')`, defaulting to Asia/Singapore. Storage uses UTC. Borrowing cannot occur in the future; deadlines must follow borrowing; returns cannot precede borrowing or occur in the future. Past borrowing and overdue deadlines can be entered for historical records.

Existing equipment associated with inactive categories or locations remains borrowable if usable stock is available. Catalog activation controls new equipment assignments, rather than invalidating existing inventory.

## Security and validation

Routes require authentication, an active account, verification and the borrowing policy. Livewire boot hooks and service authorization enforce access on subsequent actions, including account deactivation after opening a form. Detail and return page IDs are locked; submitted item IDs are independently checked against the selected loan. Responsible users, status and stock calculations come from the server. Blade escapes borrower notes and other submitted text.

The borrowing core uses the existing `borrow_transactions`, `borrow_items`, `borrow_returns`, `equipment` and `activity_logs` tables. Optional same-device QR scanning and equipment labels are now available alongside manual entry; see SCANNING.md for the browser packages and deployment requirements. No database reset or schema change is required.

## Validation

Service tests cover multi-item loans, UTC conversion, both authorized roles, validation failures, stock limits, partial/defective returns, stale submissions, cross-loan items, inactive users and audit rollback. Page tests exercise loan creation, returns through completion, search/status filters, route protection, form errors, escaping and locked transaction identity. Automated tests use the isolated test database. Browser review used a separate temporary SQLite database and covered loan creation, partial defective return, final return and the mobile layout.

The live Dashboard includes inventory totals, overdue counts, recent loans and borrowing charts; see DASHBOARD.md. View Log Details and User Management are implemented; see ACTIVITY-LOGS.md and USER-MANAGEMENT.md. Next: end-to-end acceptance checks and release configuration.
