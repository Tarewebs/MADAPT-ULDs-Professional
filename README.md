# MADAPT ULDs Professional

Professional ULD Inventory Management System for MADAPT / Ethiopian Airlines.

## Sprint 1 — recovery build

This repository contains the recovered PHP application for Hostinger.

### Target deployment

- Hostinger web root: `public_html`
- Database: `u619448402_uldspro`
- Default branch: `main`

### Included

- Login and session authentication
- ULD stock dashboard
- IN / OUT movements
- Inventory and history
- Printable stock report
- User approval and role management
- Settings
- Low-stock alert configuration
- Flight management
- Portals
- Audit log structure

## Database

Import `database/install.sql` once into `u619448402_uldspro` with phpMyAdmin.

## Hostinger configuration

The application never stores the real MySQL password in GitHub. Create `config.local.php` in `public_html` on the server, or provide the four `MADAPT_DB_*` environment variables through the hosting configuration.

Example `config.local.php`:

```php
<?php
define('MADAPT_DB_HOST', 'localhost');
define('MADAPT_DB_NAME', 'u619448402_uldspro');
define('MADAPT_DB_USER', 'YOUR_DATABASE_USER');
define('MADAPT_DB_PASS', 'YOUR_DATABASE_PASSWORD');
```

Do not commit that file.

Initial administrator:

- Username: `admin`
- Temporary password: `ChangeMe123!`

Change the administrator password immediately after first login.
