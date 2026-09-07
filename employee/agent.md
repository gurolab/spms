# agent.md

## Project Overview
The Employee Module allows regular users to request supplies and acknowledge property issued to them.
Employees can track the status of their requests and the items issued under their name.

## Tech Stack
PHP, MySQL, Bootstrap
Tables: requisition_slips, ris_items, inventory_custodian_slips, property_acknowledgment_receipts

## Core Features
Create new RIS requests
View RIS request history and status
Receive property (via ICS/PAR)
Acknowledge item receipt digitally
View personal accountability records

## Deliverables
requisition.php, acknowledgment.php, assigned-property.php
Employee dashboard with pending and completed requests

## Constraints
Cannot modify approved or completed RIS
Restricted to own records

## Output Style
User-friendly dashboard with progress indicators and form-based RIS submission.
