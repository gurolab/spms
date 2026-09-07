# SMS Agent Guide

This document gives Codex and other automation agents enough context to work on the Supply Management System (SMS). It captures what is in the repository today, how the pieces fit, and where the gaps still are.

## Overview
- SMS is a procedural PHP 8 application that runs on Apache (XAMPP or Laragon) with a MySQL 8 database. Sessions are used for authentication and role-based routing.
- The current working module is the Administrator area. Directories for other roles exist but only contain stubs (`agent.md` files). Many legacy template pages remain in the root for UI inspiration and are not wired into the new workflow.
- Data access follows a light repository layer in `repo/` built on top of shared PDO helpers in `core/`. There is a small JSON API in `api/admin.php` that the Administrator pages call through AJAX.
- Database schema and seed data live in `spms.sql`. Import it and configure credentials through `core/config.php` or an optional `.env.php` override before running the app.

## Runtime Notes
- Requires PHP 8.x with the PDO MySQL extension and session support. The project assumes XAMPP defaults (`root` user with blank password) unless `.env.php` overrides them.
- Web root should be the repository root (`/spms` in Apache). Static assets resolve through constants defined in `core/config.php`.
- CSRF tokens are generated in `core/csrf.php` and must be embedded in forms that post to action handlers.
- Session cookie flags (httponly, optional secure, samesite) are set automatically at bootstrap.

## Application Flow
- `index.php` is the gatekeeper: it checks the logged-in session and redirects users to a role-specific dashboard. Only the Administrator destination (`administrator/dashboard.php`) exists right now; all other roles fall back to missing pages until those modules are created.
- `login.php` handles sign-in, including IP and email throttling, CSRF validation, and domain allow-list checks (`gmail.com`, `cotsu.edu.ph` by default). Successful logins populate `$_SESSION['user']` with role and department data.
- `register.php` is aimed at self-service user creation but still needs refinement: it hardcodes the role string `"Employee"` instead of an ID and therefore fails unless the handler is updated.
- Logged-in pages include `core/auth_guard.php` to enforce session presence and optional role checks via `guardRole([roleIds])`.
- Flash notifications are stored in `$_SESSION['flash']` and rendered through `partials/alert-scripts.php`.

## Directory Reference
### Core Runtime (`core/`)
- `config.php` boots the environment, loads `.env.php` overrides, defines asset paths, and starts hardened sessions.
- `helper.php` centralises request helpers (`isPost`, `action`, `redirect`), role predicates (`isAdmin`, `isSupplyOfficer`, etc.), flash messaging, simple find helpers, and date formatting.
- `csrf.php` provides token generation and validation helpers used across forms.
- `Model.php` and `BaseModel.php` wrap PDO access and the table helper pattern used in repositories.
- `auth_guard.php` enforces login and offers `guardRole()` to limit access to specific role IDs.

### Data Access (`repo/`)
- Each `*.model.php` file represents a table and encapsulates CRUD logic. Examples include `User.model.php`, `Item.model.php`, `Supplier.model.php`, `Supply_Receive.model.php`, `RIS.model.php`, and `PAR.model.php`.
- Models rely on static PDO instances from `BaseModel`. Several include business helpers such as `RIS::getNextRISNo()` or `Item::addQty()` to keep stock counts aligned.
- `Account.model.php` is used for authentication. Some legacy methods (`getProfile`, `updateProfile`, `resetPassword`) still reference `$tableName` without scope and need cleanup before use.

### API Layer (`api/`)
- `admin.php` exposes JSON endpoints for the Administrator UI. It requires an admin session and supports operations such as `getAllUsers`, `getAllDepartments`, retrieving records by ID, managing supply receipt items, and listing RIS records. All endpoints are executed via query-string actions (for example, `admin.php?action=getAllUsers`).

### Administrator Module (`administrator/`)
- Screens: `dashboard.php` (UI shell with mostly static analytics placeholders), `users.php` plus `user-actions.php`, `departments.php`, `roles.php`, `items.php` plus `item-actions.php`, `suppliers.php` plus `supplier-actions.php`, `supply_receive.php` plus `supply_receive-actions.php`, `par.php`, `ris.php`, and supporting listings (`ris_items.php`, `supply_receive_items.php`).
- Most pages share the same pattern: bootstrap helpers, fetch initial data from `repo`, render a table with modals, and submit forms to `*-actions.php` handlers that perform validation, call repository methods, set flash messages, and redirect back.
- `sidebar.php` contains the role navigation shown on Administrator pages.
- AJAX-heavy screens call the JSON API in `api/admin.php` and rely on DataTables, SweetAlert, and Bootstrap modals for UX.

### Other Role Directories
- `supplyofficer/`, `inventoryofficer/`, `propertycustodian/`, `employee/`, and `auditor/` currently only contain `agent.md` placeholders. No dashboards or handlers are implemented yet, so `index.php` redirects for those roles will 404 until the modules are built.

### Shared Partials (`partials/`)
- `head.php`, `scripts.php`, and `preload.php` provide the HTML head, global JS bundle references, and the splash screen. `alert-scripts.php` renders toast or modal messages based on the session flash data.

### Assets (`assets/`)
- Third-party front-end dependencies (Bootstrap, ApexCharts, DataTables, Quill, and others), custom styles (`css/main.css` and source `sass/`), JavaScript helpers (`js/`), and image assets (`images/`). These files mirror the design template used by the Administrator UI.

### Root PHP Pages
- `login.php`, `register.php`, `forgotpwd.php`, `resetpwd.php`, `forgot-password.html`, and `reset-password.html` deliver authentication flows. `forgotpwd.php` and `resetpwd.php` are rudimentary and still need mail or token integration.
- `logout.php` clears the session and returns the user to the login screen.
- `profile.php` is an early prototype that still references the old SB-Admin layout and should be refactored to match the new shell.
- `account.php`, `about-course.html`, `index-*.html`, and the other static HTML pages come from the original template bundle and are not part of the active runtime - they can be used for layout ideas or removed once no longer needed.
- `test.php` is a small diagnostic script currently printing PHP info and can be used for environment smoke tests.

### Database
- `spms.sql` creates the schema and seed data for tables referenced by the repositories: `users`, `roles`, `departments`, `items`, `suppliers`, `supply_receipts`, `supply_receipt_items`, `requisition_slips`, `ris_items`, `property_acknowledgment_receipts`, `par_items`, and additional tables that will support future modules (ICS, audits, disposal forms).
- The dump contains a typo when creating the database (`CREATE DATABASE smps`) but subsequently executes `USE spms;`. Adjust the database name manually if needed during import.
- mysql and execute sql commands path C:\xampp82\mysql\bin>

## Security and Validation
- Email domains are filtered (`isDomainAllowed`) before account creation or login. Update `ALLOWED_EMAIL_DOMAINS` in `core/config.php` or define it in `.env.php` for deployment.
- All mutating requests rely on CSRF tokens and server-side validation. Flash messages communicate success or failure back to the UI.
- Passwords are hashed with `password_hash`. Brute-force throttling is session-based; consider persisting attempts server-side for production.

## Known Gaps and Next Steps
- Implement the missing role-specific dashboards and action pages so `index.php` redirections resolve correctly.
- Align the registration flow with the data model by using role and department IDs and, if required, add approval steps or notifications.
- Replace the legacy `account.php` and `profile.php` implementations with pages that use the current layout and helper stack.
- Audit the repository methods that rely on undefined variables (for example, `Account::getProfile`) before they are exposed to end users.
- Introduce automated tests or seed scripts as the business logic stabilises; none are present today.
