# agent.md

## Project Overview
The Repository Module serves as the data access layer of SMS.
All database CRUD operations across modules are handled here for consistency and security.

## Tech Stack
PHP + MySQLi (Prepared Statements)
Folder: /repo/
Related tables: All transactional and master tables

## Core Features
Query abstraction for select, insert, update, delete
Repository pattern for each entity (UserRepo, ItemRepo, SupplierRepo, etc.)
Error and exception handling with logging

## Deliverables
UserRepo.php, ItemRepo.php, RequisitionRepo.php, etc.

## Constraints
No direct SQL execution in UI scripts
Must always sanitize and validate inputs

## Output Style
Functional PHP classes or procedural functions returning associative arrays.