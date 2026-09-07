# agent.md

## Project Overview
The Auditor Module ensures all transactions, issuances, and receipts comply with institutional policies and financial audit standards.
Responsible for verifying, generating, and certifying inventory and property reports.

## Tech Stack
PHP + MySQL + TCPDF
Tables: requisition_slips, supply_receipts, inventory_custodian_slips, property_acknowledgment_receipts
Forms: RPCI, RPCPPE

## Core Features
Review and validate supply and property transactions
Generate audit trail reports
Prepare RPCI (Inventory) and RPCPPE (Property) forms
Certify physical inventory counts

## Deliverables
audit.php, rpci.php, rpcppe.php
Official audit summary per fiscal year

## Constraints
Read-only access to transactional modules
Must log every verification action

## Output Style
Formal document-like printable layouts with signature fields.
