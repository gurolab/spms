# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

**SPMS** (Supply & Property Management System) is a role-based PHP web application for Cotabato State University (CotSU) that digitizes supply chain management, property accountability, inventory tracking, and compliance auditing.

## Running the Application

There is no build step — this is a plain PHP application served directly by a web server (Apache/Nginx).

- **Live URL**: `https://spms.cotsu.live/`
- **Entry point**: `index.php` redirects to the role-specific dashboard based on session
- **Login page**: `login.php`
- **Config**: `core/config.php` (DB credentials, base URL, session flags, email)
- **Database**: MySQL, database name `cotsu-spms`

To seed demo data, run the scripts in [scripts/](scripts/) directly via PHP CLI or browser.

## Architecture

### Role-Based Directory Structure

Each role has its own top-level directory with its own dashboard and workflow pages:

| Directory | Role ID | Responsibilities |
|---|---|---|
| `administrator/` | 1 | User/role/department management |
| `supplyofficer/` | 2 | Receive deliveries, issue supplies, stock management |
| `inventoryofficer/` | 3 | Stock cards, reorder points, reconciliation |
| `propertycustodian/` | 4 | ICS/PAR records, property transfers |
| `auditor/` | 5 | RPCI/RPCPPE/IIRUP compliance reports |
| `employee/` | 6 | Self-service RIS requests and acknowledgments |

### Core Layer (`core/`)

- **`config.php`** — DB connection, email config, session hardening, base URL constant
- **`helper.php`** — Global utility functions: `requireAuth()`, `requireRole()`, `writeAuditLog()`, `setFlash()`/`getFlash()`, input validation
- **`auth_guard.php`** — Authentication middleware included at the top of protected pages
- **`csrf.php`** — CSRF token generation and verification
- **`BaseModel.php`** / **`Model.php`** — PDO wrapper base classes; all models extend `BaseModel`
- **`pdf/`** — PDF generation helpers wrapping TCPDF

### Data Models (`repo/`)

Models extend `BaseModel` and use PDO prepared statements. Key models:
- `RIS.model.php` — Requisition and Issuance Slips
- `ICS.model.php` / `PAR.model.php` — Inventory Custodian Slip / Property Acknowledgment Receipt
- `Item.model.php`, `StockCard.model.php`, `StockInventory.model.php`
- `PropertyCard.model.php`, `UnserviceableProperty.model.php`
- `AuditLog.model.php` — Audit trail records

### API (`api/`)

`api.php` handles GET requests with action-based routing: `?action=someAction`. Used for AJAX calls from the frontend.

### Frontend (`assets/`)

Static files: CSS (compiled from SASS), JavaScript (jQuery + Bootstrap + custom scripts), images. No bundler — files are included directly in PHP templates.

### Reusable Templates (`partials/`)

Shared HTML fragments (navigation, headers, modals) included via `require`/`include`.

## Key Conventions

**Authentication guard**: Every protected page starts with:
```php
require_once '../core/auth_guard.php';
requireRole([ROLE_ADMIN]); // or whatever roles are permitted
```

**Flash messages**: One-time session messages for the redirect-then-display pattern:
```php
setFlash('success', 'Record saved.');
header('Location: ...');
// then on the next page:
echo getFlash('success');
```

**Audit logging**: Call `writeAuditLog($action, $details)` for any significant create/update/delete or auth event. The function captures IP, user agent, and timestamp automatically.

**CSRF**: All POST forms include a CSRF token field; `csrf.php` verifies it on submit.

**Department scoping**: User data is filtered by `departmentid` — queries must include this constraint when listing department-level records.

**No ORM**: Raw PDO with named parameters. Always use prepared statements.

## Security Constraints

- Passwords hashed with `password_hash()` / `PASSWORD_DEFAULT` (bcrypt)
- Login rate-limited: max 10 attempts per 15 min per IP/email
- Email registration whitelist: only `@gmail.com` and `@cotsu.edu.ph` by default (configurable in `config.php`)
- Session cookies: `HttpOnly`, `Secure` (auto-detected by HTTPS), `SameSite=Strict` on HTTPS / `Lax` on HTTP

## PDF Generation

Uses TCPDF 6.7.4 in `vendor/`. PDF helpers in `core/pdf/` wrap TCPDF for generating official government forms (RPCI, RPCPPE, IIRUP, ICS, PAR, RIS).
