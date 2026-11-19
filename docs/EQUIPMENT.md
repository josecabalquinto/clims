# Equipment module

The Equipment module now supports equipment registration, details, editing, searchable paginated listings, and dynamic category/location management. It follows the approved teal interface and keeps category/location pages within Equipment.

## Available pages

| Path | Route name | Function |
| --- | --- | --- |
| /equipment | equipment.index | Search by asset tag, name, or serial; filter by category, location, availability, and condition |
| /equipment/create | equipment.create | Register equipment |
| /equipment/{equipment} | equipment.show | Identity, assignments, quantities, condition, acquisition date, description, remarks |
| /equipment/{equipment}/edit | equipment.edit | Edit metadata and adjust stock |
| /equipment/categories | equipment.categories | Search, create, edit, activate, deactivate, and delete unused categories |
| /equipment/locations | equipment.locations | Search, create, edit, activate, deactivate, and delete unused locations; see assigned equipment-record counts |

Both active roles can use these pages. Routes, Livewire components, and write services enforce the existing policies. Guests and inactive accounts are refused, and record identities stored in component state are locked.

## Inventory behavior

- Asset tags are trimmed, normalized to uppercase, and unique.
- Serial numbers are optional and only permitted when total quantity is 1.
- Condition grades are Good, Fair, and Poor. Defective quantity separately identifies units unfit for borrowing.
- Total quantity must be a positive integer; defective quantity must be a nonnegative integer.
- Available quantity is calculated on the server as total minus outstanding borrowed units minus defective units. It is never accepted as a form input.
- Total quantity cannot drop below outstanding loans; defective units cannot exceed units on hand.
- New assignments require active categories and locations. An equipment record may retain its current inactive assignments.
- Renaming a location/category updates the relationship display automatically.
- Catalog deactivation retains existing equipment and borrowing history. Unused categories and locations can be permanently deleted after confirmation; assigned entries have disabled delete controls and are protected again on the server. Deletion and its audit snapshot are recorded in one transaction.

ManageEquipment validates and writes within a database transaction. It locks the equipment record, selected catalog rows, and outstanding borrowing items, then calculates availability from the current locked loan rows. Locking loan reads avoid stale MySQL repeatable-read snapshots. Future borrowing and return services must lock equipment first as well.

ManageEquipmentReference provides the shared validated, authorized catalog write operation. ReferenceManager shares the real listing/editor behavior between the two anonymous Livewire page components.

## Activity history

Equipment and catalog creation/updates are logged in the same transaction as the change. Logs include the responsible user, subject, and relevant before/after values. Unchanged saves do not create misleading activity records. If audit recording fails, the inventory write rolls back.

## Implementation files

Created:

- app/Enums/EquipmentCondition.php
- app/Services/ManageEquipment.php
- app/Services/ManageEquipmentReference.php
- app/Livewire/Equipment/ReferenceManager.php
- resources/views/components/equipment/navigation.blade.php
- resources/views/components/equipment/reference-manager.blade.php
- resources/views/pages/equipment/⚡form.blade.php
- resources/views/pages/equipment/⚡show.blade.php
- tests/Feature/Equipment/EquipmentPagesTest.php
- tests/Feature/Services/ManageEquipmentTest.php
- tests/Feature/Services/ManageEquipmentReferenceTest.php
- docs/EQUIPMENT.md

Updated:

- resources/views/pages/equipment/⚡index.blade.php
- resources/views/pages/equipment/⚡categories.blade.php
- resources/views/pages/equipment/⚡locations.blade.php
- resources/css/app.css
- routes/web.php
- app/Services/ActivityLogger.php
- docs/FOUNDATION.md

No new dependencies or schema migrations were required.

## Verification

- Focused equipment-page and catalog-service checks: 48 tests passed, with 232 assertions.
- Full suite: 187 tests passed, with 584 assertions.
- Pint, Larastan, and the production frontend build passed.
- Direct development MySQL checks passed for creation, stock adjustments, outstanding loans, retaining inactive assignments, rejecting new inactive assignments, and audit creation. All temporary records were rolled back.
- Tests also cover filters, pagination, duplicate tags/names, validation errors, escaped content, forged component identities, inactive sessions, and audit failure rollback.
- Deletion tests cover both roles and both catalogs, inactive unused entries, newly assigned equipment on an already-open page, deactivated actors, pagination reset, retained audit snapshots, and rollback when audit storage fails.
- Interactive browser verification was subsequently completed during the Equipment redesign: filters, removable filter chips, registration, condition selection, stock preview and validation, category creation/activation, phone layouts, and dark mode. A separate temporary SQLite database was used; preview data and its server were removed.

The redesigned filters and forms are documented in DESIGN.md. Equipment-specific styling is isolated in resources/css/equipment.css; backend authorization, validation, inventory services, and audit behavior retain their existing implementation.

Recommended next module: borrowing transactions and returns, using the established quantity, transaction, and lock-order rules.
