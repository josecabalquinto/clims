# NativePHP Desktop packaging

CLIMS uses MySQL during normal web development and NativePHP's managed SQLite database in the packaged desktop application.

## Database environments

- `composer dev` and the normal Laravel server use the MySQL settings in `.env`.
- `composer native:dev` runs the desktop shell. NativePHP automatically selects its `nativephp` SQLite connection and stores the database separately from the project database.
- A production Windows installation stores its SQLite database under the application's Windows app-data directory. NativePHP creates it and runs pending Laravel migrations at launch.
- Application updates preserve the SQLite database. The NSIS configuration intentionally keeps app data during uninstall.

Never add `NATIVEPHP_RUNNING=true` or SQLite settings to the normal development `.env`. NativePHP supplies its runtime variables itself.

## First run

An empty NativePHP database redirects to `/setup`. The operator creates the first Super Admin without a shipped default password. The setup route returns 404 outside NativePHP and permanently closes after the first account is created.

## Development commands

```powershell
composer native:dev
php artisan native:migrate
```

The first command runs NativePHP and Vite together. The second applies migrations to the NativePHP development SQLite database.

## Windows build

1. Increment `NATIVEPHP_APP_VERSION` for every release.
2. Run the full test suite and production asset build.
3. Add `public/icon.png` and `public/icon.ico` before the release build.
4. Build the 64-bit Windows installer:

```powershell
php artisan native:build win x64
```

Build artifacts are written to `nativephp/electron/dist`. Test the installer on a clean Windows account, including first-run setup, QR camera permission, borrowing, restart persistence, upgrade migration, and uninstall/reinstall behavior.

## Release safety

- MySQL, Redis, mail, and cloud credentials are removed from the packaged environment.
- The updater is disabled until a real signed update provider is configured.
- The installer does not delete user app data on uninstall.
- Local `native:build` output is unsigned and contains exposed PHP source unless a NativePHP secure app bundle and Windows code-signing certificate are configured. Do not distribute that test artifact as a production release.
- Review NativePHP's managed Electron dependency audit before every release; do not run `npm audit fix --force` against its generated runtime.
- Do not use `native:reset --with-app-data` against a database that must be retained.
- Back up the app-data SQLite database before testing upgrade or recovery procedures.
