# SpareStack — Auto Parts OS & SaaS

SpareStack is a production-ready **Auto Parts Dealership Operating System** built with **HTML5, CSS3, JavaScript, PHP 8+, and MySQL / SQLite**.

---

## Quick Start Guide

### Option A: Running with XAMPP (Recommended)
1. Copy or move this project folder into `C:\xampp\htdocs\torque` (or keep it here and create a symlink / virtual host).
2. Open **XAMPP Control Panel** and click **Start** next to **Apache** and **MySQL**.
3. *(Optional)* Import `database/schema.sql` into **phpMyAdmin** (`http://localhost/phpmyadmin`) — the app will also automatically auto-create and seed tables on first run!
4. Open your browser and visit:
   ```
   http://localhost/torque/
   ```

### Option B: Running with Built-In PHP Server
From the project folder, open terminal and run:
```bash
php -S localhost:8000
```
Then visit `http://localhost:8000` in your web browser.

---

## Default Login Credentials

| Role | Email | Password |
|---|---|---|
| **Business Owner** | `efua@asanteautoparts.com` | `password123` |
| **Branch Manager** | `kojo@asanteautoparts.com` | `password123` |
| **Cashier** | `ama@asanteautoparts.com` | `password123` |

---

## Core Product Features

1. **Point of Sale (POS) & Checkout**:
   - Fast part search by name, SKU, OEM number, and vehicle compatibility.
   - Quick vehicle make/model fitment filter (e.g., Toyota Corolla, Honda Civic, Hyundai Elantra).
   - Barcode scanning support.
   - Multi-tender payment methods: **Cash, Mobile Money (MoMo), Card, Mechanic Credit Tab**.
   - Instant **80mm Thermal Receipt Generator** with standard POS printer styling.

2. **Auto Parts Inventory & Stock Movements**:
   - Multi-branch stock allocation with real-time quantities and low-stock reorder alerts.
   - Restock workflow with source logging.
   - Complete **Stock Movement Audit Ledger** tracking every sale, restock, and adjustment.
   - Master catalog creation with vehicle fitment notes and photo upload support.

3. **Cashier Shifts & Cash Drawer Reconciliation**:
   - Shift opening with initial cash float.
   - Real-time sales tracking by payment tender.
   - End-of-shift closing with expected vs. physical cash counted and variance calculation.

4. **Suppliers & Purchase Orders (PO)**:
   - Auto parts distributor directory.
   - PO creation and tracking.
   - One-click shipment receiving with automatic inventory stock replenishment.

5. **Mechanic & Workshop Accounts (Credit Ledger)**:
   - Garage directory with custom credit limits.
   - Unpaid balance tracking and payment collection.

6. **Executive Multi-Branch Telemetry & Settings**:
   - Multi-branch switcher and live telemetry.
   - Staff member directory with real-time online/shift status.
   - Configurable currency, tax/VAT rates, and receipt branding.
