# agent.md

## Project Overview
The Core Module contains system-wide utilities and reusable components that power all modules.
It handles database connections, authentication, session management, and helper functions.

## Tech Stack
PHP (config + utility)
Files: db_connect.php, session.php, auth.php, functions.php

## Core Features
Centralized database connection
Role-based session validation
Global date/time, logging, and error handling functions
Reusable helper methods for formatting and validation

## Deliverables
Fully modular and reusable functions for all role modules

## Constraints
Must remain framework-independent
Must handle all DB queries using prepared statements

## Output Style
Code-only (no UI); consistent naming and minimal dependencies.