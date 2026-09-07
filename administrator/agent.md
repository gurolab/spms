# agent.md

## Project Overview
The Administrator Module manages user accounts, roles, departments, and global system settings for the Supply Management System (SMS).
Administrators oversee user onboarding, account activation, and role assignments for departments and ensure all modules interact seamlessly.

## Tech Stack
PHP 8.x (procedural)
MySQL (tables: users, roles, departments)
HTML, Bootstrap 5, DataTables.js
JavaScript (AJAX)

## Core Features
Manage Users (CRUD)
Assign Role and Department
View All Accounts with Role Summary (using view user_role_dept)
Reset or Suspend Accounts
Create/Edit Departments
Manage Role Names (Administrator, Supply Officer, Inventory Officer, Property Custodian, Auditor, Employee)

## Deliverables
users.php, departments.php, roles.php
Dashboard summary of total users and active departments
Audit log entries for every user modification

## Constraints
Must not delete system-critical roles
Only Administrator role can manage user and role records

## Output Style
Clean tabular interface with CRUD modals using Bootstrap and DataTables.
