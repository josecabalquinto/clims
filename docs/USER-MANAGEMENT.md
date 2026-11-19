# User Management

Active Super Admins can search, create and edit laboratory personnel accounts, assign either existing role, and activate/deactivate accounts. Lab Staff and inactive administrators cannot access these routes or Livewire actions. The interface reuses the Equipment workspace styling, existing teal shell and bottom-center Toastr feedback.

## Routes

| Route | Name | Purpose |
| --- | --- | --- |
| `/users` | `users.index` | Paginated account list and activation controls |
| `/users/create` | `users.create` | Create personnel credentials and access |
| `/users/{user}/edit` | `users.edit` | Edit name, email, role and activation |

The account list has name/email search, role filtering and All/Active/Inactive tabs. Filters persist in the URL and reset pagination on change. Results show 15 users per page, ordered by name and then ID. Account rows identify the current user and expose Edit plus the applicable activation action. Deactivation requires confirmation; activation and deactivation buttons have distinct Livewire keys so confirmation handlers cannot carry over between states.

## Creation and editing

New accounts default to active Lab Staff. Creation requires name, a unique email address, selected role/status, and a confirmed password of at least 12 characters including uppercase, lowercase, a number and a symbol. Names/emails are trimmed and email addresses are normalized to lowercase. Password hashing uses the existing User cast.

Creation passwords are passed from Alpine input references as Livewire action arguments, rather than public component properties. They are not rendered into server responses, stored in Livewire snapshots or included in audit records. Validation retains only an error message. The browser form uses POST as its fallback method and the normal CSRF protection. No account email is sent automatically; the administrator shares credentials securely with the account holder.

Editing never displays or replaces the account password. Account holders retain the starter kit's personal password settings and reset flow. Changing an email clears its verification timestamp; the existing authentication configuration remains in place. Password reset email still requires working mail configuration.

## Access integrity and auditing

`ManageUserAccess::save()` coordinates validated profile/access changes in one database transaction. It reuses the existing access-update operation and its last-active-admin protection. Administrators are locked in ID order before the target account; authorization checks use refreshed actor state. Activation-only operations preserve the current locked role, avoiding accidental overwrites from stale list data.

Profile changes and role/status changes are committed atomically with their audit events. Duplicate-email races and audit failures cannot leave a partly saved account. Unchanged submissions produce no misleading activity. Creation logs approved account fields; profile and access edits use `user.updated` and `user.access_updated`. Passwords, tokens and authentication secrets are excluded.

The final active Super Admin cannot be demoted or deactivated. This is enforced on the server for both the form and list actions. When another active administrator remains, self-demotion redirects to Dashboard and removes User Management access; self-deactivation signs the user out and invalidates their session.

Accounts are retained for history and deactivated rather than deleted. No delete endpoint or control exists. Existing active-account middleware blocks protected requests from deactivated accounts, and the login callback rejects inactive accounts. Component boot hooks also refresh and authorize the acting account, protecting already opened forms from later demotion/deactivation. Editable target IDs are locked.

## Validation and remaining work

Service/page tests cover creation, strong-password validation, duplicate email, normalization, both roles, inactive actors, profile/access edits, password preservation, forbidden password replacement, combined transaction rollback, safe audits, stale-role activation, last-admin protection, search/filter pagination, self-demotion, self-deactivation, locked identity and escaped names. Browser checks use a separate temporary database and cover account creation, role editing, activation cycles, last-admin feedback, mobile layout, dark mode and password submission.

No migrations or dependencies were added. Initial provisioning remains `php artisan clims:provision-admin` when no active Super Admin exists. Public registration stays disabled.

All six planned modules now have working interfaces. Next: run school-specific end-to-end acceptance checks, verify borrowing concurrency against a dedicated MySQL test database, and complete mail/deployment configuration before release.
