# agent.md

## Project Overview
The Inventory Officer Module focuses on the accurate recording and monitoring of physical supplies and materials.
Responsible for maintaining up-to-date stock levels and verifying all RIS and receipt data.

## Tech Stack
PHP + MySQLi
Tables: items, stock_cards, stock_card_transactions, supply_receipt_items, requisition_slips
Data visualization with Chart.js

## Core Features
Maintain stock card records
Track receipts and issuances
Reconcile stock balance and reorder points
Verify physical counts with system records
Generate RSMI and SLC reports

## Deliverables
stockcard.php, reconciliation.php
Dashboard showing low-stock alerts

## Constraints
Must reconcile any mismatch before new issuance
Stock card entry must match corresponding document reference

## Output Style
Audit-friendly tables and printable ledgers (A4 report layout).