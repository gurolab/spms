# agent.md

## Project Overview
The Property Custodian Module manages non-consumable properties, assets, and equipment accountability using ICS and PAR forms.
It tracks ownership, transfers, and the condition of each property item.

## Tech Stack
PHP + MySQL
Tables: inventory_custodian_slips, ics_items, property_acknowledgment_receipts, par_items, items

## Core Features
Record property issuance via ICS or PAR
Manage transfers and returns
Track item status (Active, Returned, Transferred, Disposed)
Generate property card history per item

## Deliverables
ics.php, par.php, propertycard.php
ICS and PAR printable reports

## Constraints
All assets must reference a valid employee recipient
Cannot issue property without corresponding item in inventory

## Output Style
Form-driven pages with tables showing property numbers, recipients, and status.