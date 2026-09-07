# agent.md

## Project Overview
The Supply Officer Module handles supply receipt, issuance, and stock monitoring.
It automates documentation using RIS and RSMI forms and maintains real-time stock updates.

## Tech Stack
PHP + MySQLi
Tables: items, suppliers, supply_receipts, supply_receipt_items, requisition_slips, ris_items, stock_cards, stock_card_transactions
JS: DataTables, SweetAlert2
PDF/Excel reporting via PHPSpreadsheet or TCPDF

## Core Features
Encode new supply deliveries
Update stock quantities and reorder points
Create and approve RIS requests
Issue supplies and generate reports
Print forms: RIS, RSMI, SLC
Supplier database management

## Deliverables
receipts.php, issuance.php, suppliers.php
Reports: Stock Card, RSMI, SLC

## Constraints
Stock cannot go below zero
Must validate item availability before issuing
All transactions logged in stock card

## Output Style
Tabular transaction log with date-based filters and export buttons.
