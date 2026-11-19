# View Log Details

The module provides read-only access to application activity for active Lab Staff and Super Admins, using the existing teal layout and Equipment workspace controls.

## Routes and pages

| Route | Name | Purpose |
| --- | --- | --- |
| `/activity-logs` | `activity-logs.index` | Searchable, filtered, paginated list |
| `/activity-logs/{log}` | `activity-logs.show` | Audit metadata and approved before/after values |

Both pages use Livewire single-file components under `resources/views/pages/activity-logs/`. Existing authentication, active-account, verification and activity-log policy protection remains. Each component refreshes account state and authorizes subsequent requests. Detail IDs are locked, missing records return 404, and no update/delete HTTP operations are registered.

## List behavior

- Search matches readable action labels, action references, responsible-user names, recorded names, asset tags, borrower names and numeric affected-record IDs.
- Module tabs group Equipment (including categories and locations), Borrowing (including loan items and returns), and Users.
- Filters combine responsible user, action, start date and inclusive end date. Inactive accounts remain available as historical actors; System/no user is separately filterable.
- Local calendar dates use `config('clims.display_timezone')`, defaulting to Asia/Singapore, and convert to inclusive UTC start/exclusive next-day UTC boundaries. Input dates are validated; reversed ranges show an error. Malformed URL dates do not cause a runtime error.
- Filters persist in the URL; changes reset pagination. Results show 15 records per page, ordered by creation time and then ID descending. Removable chips and Reset view clear selected filters.

Search covers values actually recorded in each audit snapshot. An update that recorded quantities alone may show only its equipment ID, rather than a name. It does not borrow current names or reference labels that may have changed since the event.

## Detail behavior and security

The detail page shows action, affected record, actor, timestamp and approved field values. Snapshot names remain visible after a category or location has been deleted. Missing actors display System; unknown legacy subject types remain readable without resolving their polymorphic relationship.

`ActivityLog::allowedFields()` is the shared allowlist used by both `ActivityLogger` when writing and the model's display methods when reading. Legacy password/token keys and other unapproved fields are excluded from rendered details. Nested payloads are omitted rather than recursively exposed. Blade escapes all displayed labels and values.

Before and After distinguish absent fields (a dash) from explicitly null values (Not set). Roles, activation status and borrowing statuses receive readable labels. Borrow/return timestamps display in the configured timezone; relationship IDs retain their recorded values. The raw JSON payload is never displayed or stored in public Livewire state.

Audit history remains append-only. There are no editing, deletion or export controls, no new dependencies, and no schema changes.

## Validation and next task

Feature tests cover both roles, guests/inactive accounts, deactivation after opening a page, all search/filter paths, local-day boundaries, pagination, missing/deleted/legacy subjects, escaping, sensitive-field suppression, timestamp formatting, locked identity and unsupported write requests. Existing logger tests also pass after sharing the field allowlist. Browser checks cover combined filters, details, mobile containment, dark mode and console errors, using an isolated temporary database.

Super Admin User Management is implemented; see USER-MANAGEMENT.md. Next: end-to-end acceptance checks and release configuration.
