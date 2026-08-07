# MADAPT ULDs Professional

Professional ULD Inventory Management System for MADAPT / Ethiopian Airlines.

## Current recovery

This repository is being reconstructed from the working ULDs application snapshot from 5–6 August 2026 and the later Portals update.

### Target deployment

- Hostinger path: `public_html/uldspro`
- Database: `u619448402_uldspro`
- Default branch: `main`

## Sprint 1 recovered components

- Login and session authentication
- ULD stock dashboard
- IN / OUT movements
- Inventory and history
- Printable stock report
- User approval and role management
- Settings
- Low-stock alert configuration
- Flight management (ET740 / ET741)
- Portals
- Initial audit-log database structure

## Installation

1. Create the database `u619448402_uldspro` in Hostinger.
2. Import `database/install.sql` with phpMyAdmin.
3. Set the real database password in `config.php` (do not commit the password).
4. Deploy the project to `public_html/uldspro`.
5. Log in with the initial administrator account and immediately change the password.

Initial administrator:

- Username: `admin`
- Temporary password: `ChangeMe123!`

## Important

The database password must never be committed to GitHub. Keep it in the Hostinger deployment configuration or in an ignored local configuration file.
