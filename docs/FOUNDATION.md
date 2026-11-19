# CLIMS foundation

## Status and scope

The Computer Laboratory Inventory Management System foundation is implemented in the existing application. The Equipment module is now available; see [EQUIPMENT.md](EQUIPMENT.md). Borrowing/return workflows, populated activity-log listings, and user-management forms are subsequent work. Authentication was retained. The UI follows the user's supplied teal references; see [DESIGN.md](DESIGN.md) for the current visual system and welcome dashboard.

## Initial inspection

- Laravel 13.39.0, Livewire 4.4.8, Flux 2.21.0, Tailwind CSS 4.3.3, Fortify 1.41.0, and Blaze 1.0.20 were already installed. PHP CLI is 8.4.16.
- Standard Laravel directories were present, with only the User model and starter authentication/session/cache/queue migrations.
- Settings use anonymous Livewire single-file components under `resources/views/pages/settings/`, `Route::livewire()`, and the `pages::` namespace.
- The existing UI uses a responsive Flux sidebar, Instrument Sans, neutral colors, and light/dark appearance preferences.
- Public registration was already disabled. Password resets were enabled; email verification and two-factor authentication were disabled. Those feature choices were preserved.
- MySQL 8.0.30 was configured. The migration repository was absent, and a check scoped to the configured CLIMS database found no existing tables before migration.
- Pest, Pint, and Larastan were installed. Feature tests use SQLite in memory, isolated from development MySQL.
- This directory was not a Git repository. No commits, pushes, framework reinstallations, or dependency installations were performed.

## Implementation

### Domain schema

Existing starter tables are retained. The application tables are:

| Table | Purpose and important fields |
| --- | --- |
| users | Existing authentication fields plus role and is_active |
| equipment_categories | Unique name, optional description, active flag |
| equipment_locations | Unique name, optional description, active flag |
| equipment | Category/location, unique asset tag, optional serial, quantities, descriptive condition, acquisition date, remarks |
| borrow_transactions | Borrower details, exact borrowing/deadline/return timestamps, base status, recording user |
| borrow_items | Transaction/equipment pair, quantity, cumulative returned quantity |
| borrow_returns | Individual return event for one borrowing item, quantity, defective quantity, recording user, timestamp, remarks |
| activity_logs | Actor, action, stable polymorphic subject reference, allowlisted old/new values, creation timestamp |

Relationships:

- A category and a location each have many equipment records.
- Equipment belongs to its category and location and has many borrowing items.
- A borrowing transaction belongs to its recording user and has many items.
- Each borrowing item belongs to a transaction and equipment record and has many individual return events.
- Each return belongs to an item and its recording user.
- Activity logs belong to an optional actor and reference a subject through a stable morph alias.
- Users expose recorded transactions, recorded returns, and activity logs.

Foreign keys restrict deletion rather than cascading through inventory and history. A log's polymorphic subject cannot have a conventional database foreign key; its stable type aliases are registered in AppServiceProvider.

Database constraints enforce unique asset tags and transaction/equipment pairs, valid nonnegative stock, available plus defective quantities no greater than total, positive borrowed/returned quantities, cumulative returns no greater than borrowed quantities, defective returns no greater than returned quantities, serial numbers only on quantity-one equipment, and valid borrowing dates. MySQL uses CHECK constraints; SQLite uses equivalent insert/update triggers where adding a CHECK after table creation is unsupported.

Equipment condition is a descriptive grade; `defective_quantity` determines the number of units unavailable because of defects. It must not be inferred from the descriptive condition text.

### Inventory and borrowing semantics

- An equipment record represents an individual asset or a batch of identical items at one location.
- Asset tags identify records. Serial numbers identify individually tracked assets.
- Total quantity = available quantity + outstanding borrowed quantity + on-hand defective quantity.
- A defective return reduces outstanding quantity but does not replenish available quantity.
- Equipment dashboard KPIs will count physical units. Overdue totals will count transactions; locations will show equipment-record counts.
- BorrowStatus represents borrowed, partially returned, and returned. Overdue is derived from an exact deadline and an item with outstanding quantity, and is never stored as a fourth base status.
- The application timezone remains UTC. `config('clims.display_timezone')` defaults to Asia/Singapore for future date/time input and display.

The schema can enforce bounds within each row, but cannot enforce the sum across equipment, borrowing items, and individual return events with those CHECK constraints alone. Future borrowing/return services must authorize, validate, lock affected equipment/items in a deterministic order with `lockForUpdate()`, check current quantities, update all related counters/statuses, and record activity in one database transaction. No borrowing write endpoint is exposed in this foundation.

### Authentication and authorization

- Roles use the UserRole enum: `super_admin` and `lab_staff`.
- Existing users default to active Lab Staff when the additive migration is applied.
- Fortify rejects inactive accounts at login while preserving the existing login throttling and password-reset flows.
- Protected module and settings routes apply `auth` and `active`; existing `verified` middleware remains where present. Email verification is still disabled, and User does not implement MustVerifyEmail.
- EnsureAccountIsActive refreshes account state, signs inactive users out, invalidates the session, and refuses access.
- That middleware is registered as persistent Livewire middleware. Native `can` middleware is also reapplied to Livewire requests.
- Module components authorize during boot; settings authorize during boot and before write actions.
- Policies allow both active roles to operate inventory and borrowing, restrict personnel management to active Super Admins, and make activity logs read only.
- Role and active status are excluded from User mass assignment. Inventory counters and transaction control fields are also guarded against mass assignment.
- ManageUserAccess authorizes the actor inside a transaction, locks administrator rows in ID order, protects the last active administrator, updates the target, and writes an activity record atomically.
- Future account forms must use ManageUserAccess for role/activation changes rather than updating those fields directly.
- Account deletion is denied by policy and Eloquent. The starter profile deletion components were removed by agreement; profile edits, password changes, password confirmation, and appearance settings remain available.

### Activity logging

ActivityLogger uses explicit per-model attribute allowlists rather than logging entire model/request payloads. Passwords, remember tokens, authentication secrets, and unapproved fields are excluded. Initial admin provisioning and access changes already use the logger; future module operations will call it within their business transactions.

ActivityLog rejects Eloquent update/delete operations, has no updated_at column, and has read-only policies. There are no log-editing routes.

### Initial administrator and development data

Run this interactively from a terminal:

```powershell
php artisan clims:provision-admin
```

The command prompts for name, email, password, and confirmation. Password entry is hidden, validated, and hashed. It refuses initial provisioning when an active Super Admin already exists and accepts no plaintext password command-line option.

No real accounts or default credentials were created. The development seeder inserts nine example categories and four locations only in local/testing environments. It is repeatable, preserves existing values and inactive flags, and creates no equipment loans or users.

```powershell
php artisan db:seed
```

### Routes and placeholders

All listed module routes use GET/HEAD and require an active authenticated account.

| Path | Route name | Page/access |
| --- | --- | --- |
| /dashboard | dashboard | Existing dashboard placeholder; both roles |
| /equipment | equipment.index | Equipment placeholder and catalog links; both roles |
| /equipment/categories | equipment.categories | Category placeholder; both roles |
| /equipment/locations | equipment.locations | Location placeholder; both roles |
| /borrow-history | borrowing.index | Borrow History placeholder; both roles |
| /activity-logs | activity-logs.index | View Log Details placeholder; both roles |
| /users | users.index | User Management placeholder; Super Admin only |
| /settings | existing redirect | Redirect to profile |
| /settings/profile | profile.edit | Existing profile settings |
| /settings/security | security.edit | Existing password settings; password confirmation required |
| /settings/appearance | appearance.edit | Existing appearance settings |

The public welcome page, login/logout, password reset, password confirmation, and framework routes remain. Public registration remains unavailable.

Sidebar order is Dashboard, Equipment, Borrow History, View Log Details, Settings, and User Management. The final item is authorized with the same user policy that protects the route/component. Categories and locations appear within Equipment rather than as main sidebar entries. Starter repository/documentation links were removed from the application sidebar.

## Directory structure

```text
app/
  Console/Commands/ProvisionSuperAdmin.php
  Enums/
  Http/Middleware/EnsureAccountIsActive.php
  Models/
  Policies/
  Services/
    ActivityLogger.php
    ManageUserAccess.php
config/clims.php
database/
  factories/
  migrations/
  seeders/
resources/views/
  components/module-placeholder.blade.php
  layouts/app/sidebar.blade.php
  pages/
    equipment/
    borrowing/
    activity-logs/
    users/
    settings/
routes/
tests/Feature/
  Console/
  Foundation/
  Services/
docs/adr/
GLOSSARY.md
```

## Verification

- All five migrations ran successfully against the configured development MySQL database; no reset or destructive migration command was used.
- Development reference data seeded successfully: nine categories, four locations, zero accounts.
- Full Pest suite: **110 tests passed, 237 assertions**.
- Pint and Larastan checks passed. The Composer static-analysis script now explicitly permits 512 MB because the CLI's 128 MB default caused a worker to exhaust memory.
- Frontend production build passed. The existing font build reports an optional optimized-fallback package notice; no extra dependency was installed.
- Direct MySQL verification checked relationships, quantity calculations, actual CHECK enforcement, foreign-key deletion restrictions, last-admin protection, and access-change logging. All temporary records were rolled back; zero accounts remained.
- Actual HTTP Livewire update tests prove that deactivation and role revocation block updates from already-open pages.
- Existing authentication, password-reset, profile, password-change, and password-confirmation tests continue to pass. Account-deletion tests were changed to assert the agreed retention behavior.

Repeat normal checks with:

```powershell
composer test
npm run build
```

## Created files

```text
GLOSSARY.md
docs/FOUNDATION.md
docs/adr/0001-equipment-record-identity.md
docs/adr/0002-retain-return-events.md
docs/adr/0003-retain-personnel-accounts.md
docs/adr/0004-borrowing-deadlines.md
docs/adr/0005-track-defective-quantities.md
docs/adr/0006-count-dashboard-equipment-units.md
app/Console/Commands/ProvisionSuperAdmin.php
app/Enums/BorrowerType.php
app/Enums/BorrowStatus.php
app/Enums/UserRole.php
app/Http/Middleware/EnsureAccountIsActive.php
app/Models/ActivityLog.php
app/Models/BorrowItem.php
app/Models/BorrowReturn.php
app/Models/BorrowTransaction.php
app/Models/Equipment.php
app/Models/EquipmentCategory.php
app/Models/EquipmentLocation.php
app/Policies/ActivityLogPolicy.php
app/Policies/BorrowTransactionPolicy.php
app/Policies/EquipmentPolicy.php
app/Policies/EquipmentReferencePolicy.php
app/Policies/UserPolicy.php
app/Services/ActivityLogger.php
app/Services/ManageUserAccess.php
config/clims.php
database/factories/ActivityLogFactory.php
database/factories/BorrowItemFactory.php
database/factories/BorrowReturnFactory.php
database/factories/BorrowTransactionFactory.php
database/factories/EquipmentCategoryFactory.php
database/factories/EquipmentFactory.php
database/factories/EquipmentLocationFactory.php
database/migrations/2026_10_31_102938_add_inventory_access_fields_to_users_table.php
database/migrations/2026_10_31_102939_create_inventory_foundation_tables.php
database/seeders/InventoryReferenceSeeder.php
resources/views/components/module-placeholder.blade.php
resources/views/pages/equipment/⚡index.blade.php
resources/views/pages/equipment/⚡categories.blade.php
resources/views/pages/equipment/⚡locations.blade.php
resources/views/pages/borrowing/⚡index.blade.php
resources/views/pages/activity-logs/⚡index.blade.php
resources/views/pages/users/⚡index.blade.php
tests/Feature/Console/ProvisionSuperAdminTest.php
tests/Feature/Foundation/AccessTest.php
tests/Feature/Foundation/AuthorizationTest.php
tests/Feature/Foundation/InventorySchemaTest.php
tests/Feature/Services/ActivityLoggerTest.php
tests/Feature/Services/ManageUserAccessTest.php
```

## Modified files

```text
.env (local application name only)
.env.example
composer.json (static-analysis memory limit only)
app/Models/User.php
app/Providers/AppServiceProvider.php
app/Providers/FortifyServiceProvider.php
bootstrap/app.php
config/app.php
database/factories/UserFactory.php
database/seeders/DatabaseSeeder.php
resources/views/dashboard.blade.php
resources/views/layouts/app/sidebar.blade.php
resources/views/pages/settings/⚡appearance.blade.php
resources/views/pages/settings/⚡profile.blade.php
resources/views/pages/settings/⚡security.blade.php
routes/settings.php
routes/web.php
tests/Feature/Settings/ProfileUpdateTest.php
```

Removed by agreement:

```text
resources/views/pages/settings/⚡delete-user-form.blade.php
resources/views/pages/settings/⚡delete-user-modal.blade.php
```

Build output and framework/test caches were regenerated as ignored development artifacts. No dependencies were added or upgraded.

## Remaining setup and next development task

1. Run `php artisan clims:provision-admin` yourself to choose the initial administrator credentials.
2. Configure working mail delivery before relying on password-reset email; the local environment currently uses the log mailer.
3. Confirm application URL, database credentials, and HTTPS/session settings for the deployment environment. The local application name is now CLIMS; display timezone defaults to Asia/Singapore.
4. Initialize version control when ready; the supplied directory has no .git repository.
5. Equipment management, borrowing/returns, the live Dashboard, View Log Details, Super Admin User Management, and optional equipment QR scanning are implemented; see EQUIPMENT.md, BORROWING.md, DASHBOARD.md, ACTIVITY-LOGS.md, USER-MANAGEMENT.md, and SCANNING.md. Next, complete end-to-end and device acceptance checks, MySQL concurrency verification, and release configuration.

