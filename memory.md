# SpareStack — Auto Parts OS & SaaS — Codebase Knowledge Base

> **Branding = SpareStack.** Infra names are intentionally UNCHANGED (branding-only rename): DB `torque_autoparts`, sqlite `database/torque.sqlite`, DB user `torque`, VPS `/var/www/torque`, nginx `sites-enabled/torque`, env vars `TORQUE_DB_*`, localStorage `torque_theme`. Only user-facing strings ("SpareStack", `@sparestack.com`, CSVs `SpareStack_*.csv`, visible settings) were renamed.
>
> **Purpose:** This file contains everything a coding agent needs to work on this codebase without searching. Update this file whenever architecture changes.
>
> **IMPORTANT:** This project is **NOT a git repo yet** — spawn it on GitHub/GitLab before first production deploy (see [Deployment](#vps--production-access)).

---

## Project Structure

```
auto parts saas/             # (REPO ROOT when pushed to git)
├── index.php                # LIVE app shell — single page (~700 lines)
│                            #  PHP-injected <title>, auth screen + 4 views + 11 modals
├── index.html               # STATIC prototype — self-contained (inline CSS, no backend,
│                            #  no external JS). Older/parallel mock of the same 4 views.
│                            #  Do NOT edit casually; index.php is the real app.
├── config/
│   ├── database.php         # Database::getConnection() — MySQL first, SQLite fallback,
│   │                        #  auto-create DB + auto-migrate/seed via SeedData
│   └── settings.php         # Settings class (key-value store), jsonResponse()/jsonError(),
│                            #  getAuthUser(), requireAuth($roles) — session auth helpers
├── api/                     # ALL backend endpoints (procedural PHP, action-based via ?action=)
│   ├── auth.php             # login, register, current, logout
│   ├── products.php         # list products/categories (search + fitment), create, set_image
│   ├── inventory.php        # restock, adjust, transfer, movements, low_stock
│   ├── sales.php            # checkout (POS), return_sale, list sales
│   ├── customers.php        # list/search customers, create, pay_credit
│   ├── branches.php         # branches+staff+today's sales, create_branch, add_staff
│   ├── purchase_orders.php  # suppliers, list POs, create, receive (auto-restock)
│   ├── shifts.php           # current shift, clock_in, clock_out (drawer reconciliation)
│   ├── analytics.php        # dashboard KPIs, payment breakdown, top parts, recent sales
│   ├── export.php           # CSVs: SpareStack_Sales_Report_*.csv, Inventory_Valuation_*.csv
│   └── settings.php         # GET/POST dealership settings (name, tagline, VAT, currency…)
├── database/
│   ├── schema.sql           # MySQL DDL (13 tables) — executed by initMysql()
│   └── seed_data.php        # SeedData::initialize(): SQLite DDL inline + seedCoreData()
│                            #  (branches, 6 users, 7 categories, 8 products, stock, etc.)
├── assets/
│   ├── css/
│   │   └── app.css          # ALL styling (~858 lines) — CSS vars, light/dark themes
│   └── js/                  # No build step. Plain <script> tags, loaded after DOM shell.
│       ├── app.js           # App controller: session, branches, nav, views, theme, api()
│       ├── pos.js           # POS engine: cart, payments, receipts, quotations, WhatsApp
│       ├── inventory.js     # Inventory table, restock, transfers, returns, audit ledger
│       ├── ops.js           # Shift clock in/out + reconcile, purchase orders
│       └── owner.js         # Multi-branch console, garage credits, settings
├── README.md                # Product overview, quick start (XAMPP / php -S)
└── memory.md                # This file
```

**Key: NO framework, NO TypeScript, NO bundler, NO Composer, NO build step. Plain PHP + plain JS module singletons.**

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | Vanilla HTML5 + CSS3 + JS (SPA: 4 hidden `.view` divs shown by class toggle) |
| Backend | PHP 8+ (procedural API files, PDO, no framework, no router) |
| Database | MySQL / MariaDB (XAMPP default `root`/``, db `torque_autoparts`), AUTO-fallback to SQLite (`database/torque.sqlite`) |
| Auth | PHP sessions (`$_SESSION['user']`) + bcrypt (`password_hash`/`password_verify`) |
| Payments | Cash / MoMo / Card / Credit (tender enum; Credit writes `customers.credit_balance`) |
| Receipts | 80mm thermal styled HTML receipt in modal; `window.print()`; WhatsApp share via `wa.me` |
| Exports | PHP `fputcsv()` streaming CSVs (sales journal, inventory valuation) |
| Hosting | Same VPS as SchoolPro — see [VPS / Production Access](#vps--production-access) |

**Key:** Database layer is **dual-driver aware**. Upserts (`ON CONFLICT` vs `ON DUPLICATE KEY UPDATE`) branch on `Database::getDriver()` (see `config/database.php:77`, `api/inventory.php:39`, `api/inventory.php:119`).

---

## Server Architecture

### Startup (no entrypoint router)

Bootstrap is always the **same three requires** at the top of every `api/*.php`:

```
config/database.php  → Database::getConnection()  (creates DB if missing, runs schema, seeds)
config/settings.php  → Settings helper + json helpers + session auth
header('Content-Type: application/json') + dispatch on $_GET['action']
```

There is **no `public/index.php` router and no framework** — `index.php` (the HTML shell) is served directly by Apache/PHP and talks to `api/*.php?action=…` via `fetch()`.

### Database init (`config/database.php`)

1. Try MySQL at `127.0.0.1:3306`, db `torque_autoparts`, user `root`, empty pass.
2. On failure, try to `CREATE DATABASE IF NOT EXISTS torque_autoparts`, then reconnect.
3. If MySQL still fails → fallback to SQLite file `database/torque.sqlite` (auto-created).
4. `checkAndMigrate()` → `SeedData::initialize($pdo, $driver)`:
   - MySQL → executes `database/schema.sql` (`CREATE TABLE IF NOT EXISTS`).
   - SQLite → runs the inline SQLite DDL block in `seed_data.php`.
   - `seedCoreData()` seeds data **only if `branches` is empty** (idempotent).

### Seed data (one-time, `database/seed_data.php`)

- 3 branches: Kumasi Main, Accra Spintex, Takoradi Depot
- 6 users (all pw = `password123`, bcrypt): Owner Efua, Mgrs Kojo/Yaw/Kwabena, Cashiers Ama/Linda
- 7 categories + 8 demo parts (with OEM numbers + vehicle fitment strings)
- `branch_stock` rows for all branch×product combos
- 3 mechanic customers (2 with open balances), 3 suppliers, 2 POs, 1 open shift, default settings

### API routes (all files dispatch on `$_GET['action']`, POST body = JSON)

**`api/auth.php`**
- `POST ?action=login` — bcrypt `password_verify` only (no magic bypass)
- `POST ?action=register` — create user (Owner also sets `dealership_name`), auto-login
- `GET ?action=current` — returns session user, or `{success:false}` if logged out
- `GET ?action=logout` — sets `is_online=0`, destroys session

**`api/products.php`**
- `GET` (default) — products + `branch_stock` joined, filter by `category` slug + `search` (name/SKU/barcode/OEM/fits)
- `GET ?action=categories` — category list
- `POST ?action=create` — new part; assigns stock row to **all branches**, logs movement
- `POST ?action=set_image` — base64 photo into `products.image_url` (LONGTEXT)

**`api/inventory.php`**
- `POST ?action=restock` — upsert `branch_stock`, bump master stock, audit movement
- `POST ?action=adjust` — set exact target qty, log diff
- `POST ?action=transfer` — **transactional** deduct source / add dest / 2 audit rows
- `GET ?action=movements` — stock_movements ledger for branch (optionally per product)
- `GET ?action=low_stock` — parts at/below reorder level

**`api/sales.php`**
- `POST ?action=checkout` — **transactional**: totals (VAT from settings), invoice `INV-YYMMDD-XXXX`, insert sale + items, decrement branch+master stock, audit `POS Sale`, credit customers on `Credit` tender, returns full receipt payload
- `POST ?action=return_sale` — mark `Refunded`, restock items, reverse customer credit
- `GET` — recent sales by branch (`limit` param)

**`api/customers.php`**
- `GET` — list/search mechanics (name/workshop/phone)
- `POST ?action=create` — register garage account with `credit_limit`
- `POST ?action=pay_credit` — `credit_balance = MAX(0, balance - amount)`

**`api/branches.php`**
- `GET` — branches + staff array + `sales_today` per branch
- `POST ?action=create_branch` — create + seed stock rows from product master (qty 10)
- `POST ?action=add_staff` — create staff user (pw `password123`), default email `name@sparestack.com`
- `POST ?action=toggle_staff_online` — flip `users.is_online`

**`api/purchase_orders.php`**
- `GET ?action=suppliers` — supplier list
- `GET` (default) — POs joined with supplier/product/branch
- `POST ?action=create` — PO number `PO-YYYY-XXXX`; cost auto-looked-up if omitted
- `POST ?action=receive` — mark Received, add to branch + master stock, audit `PO Received`

**`api/shifts.php`**
- `GET ?action=current` — open shift + `sales_summary` (total/cash/momo/card/count) + `expected_cash_drawer`
- `POST ?action=clock_in` — opens shift with float; 1 open shift per user
- `POST ?action=clock_out` — computes expected = float + cash sales, variance, closes shift

**`api/analytics.php`** — KPI block (sales today/all-time, orders, online/total staff, branches, low-stock count), payment-method breakdown, top 5 parts, recent 8 sales, settings

**`api/export.php`** — `?type=sales` and `?type=inventory` CSVs (no auth check — see caveats)

**`api/settings.php`** — GET whole key/value settings; POST writes each key

### Auth & permissions

- `Settings::getAuthUser()` returns `$_SESSION['user']` or null.
- `Settings::requireAuth($roles)` — 401 if no session, 403 if role not allowed; every `api/*.php` file calls it. Gating matrix:
  - All endpoints: any authenticated role.
  - Writes on `products.php`/`purchase_orders.php`/`inventory.php` POST: `Owner`+`Manager`.
  - `customers.php` / `branches.php` / `settings.php` POST: `Owner` only.
  - `export.php`: `Owner`+`Manager`.
- Frontend also gates views via `App.navDefs[].roles` and only calls `App.loadBranches()` after login (`enterApp`).
- **Remaining security debt:** CSRF protection for session auth, rate limiting/lockout, move DB creds out of source.

---

## Database Schema (13 tables)

Shared by MySQL (`schema.sql`) and SQLite (`seed_data.php`). Money = DECIMAL/REAL(10,2), ids auto-increment, timestamps default `CURRENT_TIMESTAMP`.

- `branches` — id, name, location, phone, is_active
- `users` — id, branch_id(FK), name, email(unique), password_hash, role(**Owner|Manager|Cashier**), is_online
- `categories` — id, name(unique), slug(unique), icon
- `products` — id, category_id(FK), name, **sku(unique)**, **barcode**, **oem_number**, **fits_vehicles**, cost_price, selling_price, stock_quantity (master), reorder_level, **image_url** (LONGTEXT/base64), created_at
- `branch_stock` — id, branch_id(FK), product_id(FK), quantity, reorder_level; **UNIQUE(branch_id, product_id)**
- `stock_movements` — ledger: product_id, branch_id, user_id, change_qty, previous_qty, new_qty, reason (`POS Sale`, `Initial Catalog Add`, `Inter-Branch Transfer Out/In`, `PO Received`, `Sales Return / Refund`, custom restock/adjust), notes, created_at
- `customers` — id, name, phone, workshop_name, credit_balance, credit_limit (default 2000)
- `suppliers` — id, name, contact_person, phone, email, address
- `sales` — id, invoice_number(unique, `INV-yymmdd-XXXX`), branch_id, user_id, customer_id, subtotal, vat_amount, discount_amount, grand_total, payment_method(**Cash|Card|MoMo|Transfer|Credit**), payment_ref, status(Completed|Refunded), created_at
- `sale_items` — id, sale_id(FK, cascade), product_id, product_name, sku, unit_price, cost_price, quantity, total_price
- `purchase_orders` — id, po_number(unique, `PO-YYYY-XXXX`), supplier_id, branch_id, product_id, quantity, unit_cost, total_cost, status(**Draft|Ordered|Received|Cancelled**), created_at, received_at
- `shifts` — id, user_id, branch_id, opened_at, closed_at, opening_float (300 default), closing_cash_counted, expected_cash, cash_variance, status(**Open|Closed**)
- `settings` — setting_key(PK), setting_value; defaults in `Settings::getAll()` (dealership_name, tagline, phone, email, address, currency_symbol/name, vat_rate, receipt_footer)

---

## Client Architecture

### Routing (view switching, no URL router)

4 views live in `index.php` as hidden `<div class="view">`s inside `#appShell`. `App.showView(id, btn)` toggles `.active`. No hash/URL routing — refresh always lands on Dashboard.

| View id | Title in UI | Roles (front-end gate) | Module |
|---|---|---|---|
| `dashboard` | Dashboard Overview | Owner, Manager, Cashier | `App` (KPIs) |
| `pos` | Point of Sale / Checkout | Owner, Manager, Cashier | `POS` |
| `ops` | Shop Operations & Stock | **Owner, Manager** | `Inventory` + `Ops` |
| `owner` | Owner Multi-Branch Console | **Owner** | `Owner` |

### Guard system

- **Nav guard:** `App.navDefs` each declare `roles: [...]`; `App.buildNav()` filters by `App.currentUser.role` (app.js:138). No server-side enforcement (see caveats ↑).

### Shell layout (index.php + app.css)

- **Auth screen** (`#authScreen`): left desktop hero brand panel (gauge graphic), right form card; sign-in/signup tabs; 1-tap demo chips (Owner/Manager/Cashier).
- **Sidebar** (`#appSidebar`): brand, nav buttons (`#sideNav`), branch switcher (`#globalBranchSelect`), user chip with avatar/name/role + logout.
- **Topbar**: mobile menu button, page title, current-branch badge, theme toggle (light/dark).
- **Main workspace**: 4 `.view` panels swapped by class.
- **Modals**: 11 `.modal-backdrop` overlays toggled with `.show` (restock, transfer, return, audit, add-part, receipt, clock-in, clock-out, PO, branch, customer).
- **Toast notifications**: `#toastContainer`, appended by `App.toast()`.
- **Mobile**: sidebar collapses behind `#sidebarBackdrop`, `.mobile-open` class; topbar hamburger.

### Auth state ("AuthContext" but no React)

`App` singleton holds `currentUser`, `currentBranchId`, `branches`, `settings`.
- `App.checkSession()` → `GET api/auth.php?action=current` → `enterApp()` or `showAuth()`.
- Login/signup/logout also mutate `App.currentUser` and swap screens.
- Initial nav view = first allowed item; POS/Ops/Owner modules load lazily when their view opens (`App.showView`, app.js:184).

### API layer

`App.api(endpoint, method = 'GET', data = null)` (app.js:24) — thin `fetch()` wrapper:
- JSON body when `data` present; parses JSON; throws `Error(json.error)` on `!res.ok`; toasts errors automatically; returns the parsed JSON.

### Component patterns

- **Module singletons:** `const App/POS/Inventory/Ops/Owner = { state, async load(), render…() }` — attached to `window`, invoked from inline `onclick` attributes.
- **State:** object properties (e.g. `POS.cart`), re-render by rebuilding innerHTML from template literals.
- **Data lifecycle:** `View load()` → `App.api(...)` → set state → `render*()` into a tbody/grid/list.
- **Branch refresh:** `App.switchBranch()` re-calls each module's `.load()` that exists (app.js:89).
- **Forms:** read inputs by id, `trim()`, manual validation with `App.toast(msg,'error')`, then POST.
- **Loading/empty states:** plain inline strings ("No inventory items recorded.", "No automotive parts found…").

### Custom hook (analog)

No React hooks. The pattern is: `App.init()` on `DOMContentLoaded` (theme → session → branches → which triggers `enterApp()` → nav + first view). Each module exposes `load()`/`init()` idempotently.

---

## Key Patterns

### Auth flow (session-based, NOT token-based)

1. Login POSTs email+password → `password_verify` bcrypt → session set → `users.is_online=1`.
2. Every later page load: `auth.php?action=current` reads `$_SESSION['user']`; if none, frontend shows the auth screen.
3. Logout clears `is_online`, destroys session, shows auth screen.
4. No token expiry, no lockout, no rate limit (debt).

### Inventory consistency rules

- Every mutation writes: `branch_stock` (per-branch) **and** `products.stock_quantity` (master) **and** a `stock_movements` audit row.
- Sales checkout, returns, transfers, PO-receive are wrapped in `$db->beginTransaction()`/commit/rollback.
- POS reads `COALESCE(bs.quantity, p.stock_quantity)` so master stock acts as branch-1 fallback.

### Backup system

**None.** No backup tooling. The SQLite file (`database/torque.sqlite`) is the single-store; back it up by copying the file (or MySQL `mysqldump torque_autoparts`). Suggested prod routine: nightly `mysqldump > /var/www/torque/backups/`.

### File uploads

- **Product photos:** base64 data-URL via FileReader → stored in `products.image_url` (LONGTEXT/TEXT). No size/type/magic-byte validation. **Debt:** move to real file storage + validation.

### Permission system

- **Single tier:** `users.role` char (`Owner`/`Manager`/`Cashier`) gating front-end nav items only.
- Server endpoints assume demo permission. No per-user privilege granularity.

### Styling system

- Single stylesheet `assets/css/app.css`, CSS variables in `:root`, dark overrides in `[data-theme="dark"]`.
- Theme persisted as `localStorage.torque_theme`; auto-follows OS `prefers-color-scheme` when not set manually (`App.initTheme`, app.js:202).
- Tokens: `--accent:#6C3CE9` (purple, primary), `--lime:#7EA600` (success/stock), `--coral:#E23A5A` (danger/low stock), `--radius:12px`, shadows `--shadow-sm/md/lg`. Fonts: **Space Grotesk** (headings), **Inter** (body), **IBM Plex Mono** (`.mono` numbers). **No orange/amber** — `--amber`/`--amber-tint` tokens were removed (unused); palette stays purple/lime/coral.

---

## Testing

### Test accounts (local XAMPP / `php -S`)

| Role | Email | Password |
|---|---|---|
| Business Owner | `efua@asanteautoparts.com` | `password123` |
| Branch Manager | `kojo@asanteautoparts.com` | `password123` |
| Cashier | `ama@asanteautoparts.com` | `password123` |
| Manager (Accra) | `yaw@asanteautoparts.com` | `password123` |
| Cashier (Accra) | `linda@asanteautoparts.com` | `password123` |
| Manager (Takoradi) | `kwabena@asanteautoparts.com` | `password123` |

Magic bypass removed — login requires the real bcrypt password. **No role switcher / demo chips** in the UI (removed): sign in with an account's email+password and the app auto-detects the role from the session, showing only that role's views (sidebar built from `App.navDefs[].roles`).

### Quick local run

```bash
php -S localhost:8000        # from project root (no build step)
# then open http://localhost:8000
```

Alternatively XAMPP: put folder under `C:\xampp\htdocs\torque`, start Apache+MySQL, open `http://localhost/torque/`. DB auto-creates + seeds on first request.

### Smoke test (curl)

```bash
curl -X POST "http://localhost:8000/api/auth.php?action=login" -H "Content-Type: application/json" -d "{\"email\":\"efua@asanteautoparts.com\",\"password\":\"password123\"}"
curl "http://localhost:8000/api/analytics.php"
curl "http://localhost:8000/api/products.php?branch_id=1"
```

### Lessons learned — do NOT repeat (2026-08-29 blueprint deploy, commit `d09d2a7`)

What went wrong and how to avoid it next time:

- **Local verification was incomplete before deploy.** I ran `node --check` on the JS controllers (all OK) but **did NOT lint `index.php`** because "no local PHP". Line-only, the HTML/JS-in-PHP edits are low-risk, but the rule is: always lint every changed PHP file via the plink pipe (`Get-Content -Raw index.php | plink ... "cat > /tmp/lintcheck.php && php -l /tmp/lintcheck.php"`) BEFORE committing/pushing the deploy. Never skip it just because local PHP is missing.
- **Committed + pushed the deploy WITHOUT confirming the server was reachable first.** The whole reason we have memory.md is to verify-then-deploy. New rule: **before any deploy, confirm the VPS is up** (a quick `plink ... "echo UP"` and/or `Invoke-WebRequest` on one of the prod URLs). If the server is down, do NOT push-and-assume; keep the commit ready but mark deploy PENDING in the log.
- **Stale site-state assumption.** I treated the VPS as reliably up because it was live 2 days earlier. Uptime is not guaranteed. Always do a fresh reachability check rather than assuming the last-known-good state persists.
- **Diagnostic ordering was fine but could be faster.** Efficient triage in one batch: (1) `Resolve-DnsName` → (2) TCP test of ports 22/80/443/8081/8444 → (3) `plink echo`. That instantly distinguishes "DNS gone" vs "host dead" vs "just our port blocked". Do this in ONE parallel call instead of retrying SSH repeatedly.
- **Do not burn time on repeated SSH retries.** Once a TCP port test shows 22 as closed/timeout, further `plink` retries will also fail — stop retrying locally and escalate to Hostinger hPanel (power/boot/suspend status), then retry only after the user confirms the server is back.

In short for the deploy checklist (apply before EVERY deploy): lint all changed PHP on the server → confirm VPS reachability → push → pull on VPS → restart php8.3-fpm → curl-verify live. If any step can't run (server down), record the commit as ⏳ PENDING in the deploy log and move on.

---

## VPS / Production Access

**Hosting: SpareStack will live on the SAME VPS as SchoolPro** (deployment workflow below is therefore shared).

### Server Details

| Field | Value |
|---|---|
| VPS IP | `187.124.215.68` |
| Hostinger hostname | `srv1810937.hstgr.cloud` |
| Production URL | `http://srv1810937.hstgr.cloud:8081/` and `https://srv1810937.hstgr.cloud:8444/` (dedicated ports; **must open both in Hostinger hPanel firewall**) |
| SSH user | `root` |
| SSH password | `vlVvM5@r5oT/Tg-)` |
| SSH host key | `ssh-ed25519 255 SHA256:f9nGShsKpn0oiZA47eqm4UMTShEYl+hGKpAH0VEobPc` |
| App path on VPS | `/var/www/torque` (cloned from GitHub, sibling of `/var/www/sms-web`) |
| Web server | nginx (site file `/etc/nginx/sites-enabled/torque`) → PHP 8.3-FPM (`unix:/run/php/php8.3-fpm.sock`) |
| Database | MariaDB 10.11 on VPS, DB `torque_autoparts`, user `torque` (pw recorded in that nginx file's `fastcgi_param TORQUE_DB_PASS`) |
| Existing sites (untouched) | hotelpro (80 / 8080 / 8443), sms-web (443) |
| PHP-FPM site port conflict warning | two `server_name srv1810937.hstgr.cloud` blocks exist on 8080/8443 (hotelpro) and 443 (sms-web) — do NOT reuse those serving SpareStack |

### Spawning git (first step before deploy)

Not yet a repo. Do once:

```bash
git init
git add .
git commit -m "Initial SpareStack Auto Parts OS"
git remote add origin <your-github-repo-url>
git branch -M main
git push -u origin main
```

### Deployment workflow (PHP — no build, no bundler, no PM2)

**There is no client build and no long-running Node process.** Shipping = getting updated files onto the VPS and letting Apache serve PHP. For a pure-file change this is just:

```bash
ssh root@187.124.215.68 "cd /var/www/torque && git pull origin main"
```

**First-time server setup (one-off):**
```bash
ssh root@187.124.215.68
mkdir -p /var/www/torque
mysql -u root -p -e "CREATE DATABASE torque_autoparts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# configure MySQL root creds in /var/www/torque/config/database.php
# optionally: Apache vhost + SSL for /torque/
```

**Full deploy after code changes:**
1. `git push origin main` from local.
2. `ssh root@187.124.215.68 "cd /var/www/torque && git pull origin main"`.
3. If `config/database.php` or `database/*` changed, verify DB migration ran OK on first request.
4. Test on production (below).

### Using plink.exe (PuTTY CLI — same tool used for SchoolPro)

`plink.exe` lives at the SchoolPro repo root: `C:\Users\USER\Desktop\sms web\plink.exe`. Copy it into this repo root (or reference by full path) — it's the persistent SSH utility.

**Run remote command:**
```powershell
$PLINK = "C:\Users\USER\Desktop\sms web\plink.exe"
& $PLINK -ssh -pw "vlVvM5@r5oT/Tg-)" root@187.124.215.68 -hostkey "ssh-ed25519 255 SHA256:f9nGShsKpn0oiZA47eqm4UMTShEYl+hGKpAH0VEobPc" "cd /var/www/torque && git pull origin main"
```
(`echo y` once to trust the host key.)

**Upload a single file via plink (pipe stdin):**
```powershell
Get-Content -Raw "api\sales.php" | & $PLINK -ssh -pw "vlVvM5@r5oT/Tg-)" root@187.124.215.68 -hostkey "ssh-ed25519 255 SHA256:f9nGShsKpn0oiZA47eqm4UMTShEYl+hGKpAH0VEobPc" "cat > /var/www/torque/api/sales.php"
```

**Alternative — Node ssh2 helper** (same pattern as SchoolPro `deploy.js`):
```javascript
const { Client } = require('ssh2');
const conn = new Client();
conn.on('ready', () => {
  conn.exec('cd /var/www/torque && git pull origin main', (e, stream) => {
    stream.on('data', d => process.stdout.write(d.toString()));
    stream.stderr.on('data', d => process.stderr.write(d.toString()));
    stream.on('close', () => conn.end());
  });
}).connect({ host: '187.124.215.68', port: 22, username: 'root',
             password: 'vlVvM5@r5oT/Tg-)', readyTimeout: 30000 });
```

### Testing on Production

1. URL: `https://srv1810937.hstgr.cloud/torque/` (or vhost path).
2. Login smoke test (curl):
```bash
curl -X POST https://srv1810937.hstgr.cloud/torque/api/auth.php?action=login \
  -H "Content-Type: application/json" \
  -d '{"email":"efua@asanteautoparts.com","password":"password123"}'
```
3. Run a full POS checkout against prod, verify `api/analytics.php` KPIs update.
4. Verify mobile responsiveness at ≤900px viewport (sidebar/topbar collapse).

---

## Git Ignore Rules

Add to `.gitignore` at repo root:

```
database/torque.sqlite        # generated SQLite fallback DB — NEVER commit real data
*.log
backups/
.plink-*
.DS_Store
Thumbs.db
```

Note: MySQL mode writes no local files. `database/schema.sql` and `database/seed_data.php` MUST stay tracked (they hold the schema).

---

## Scripts

There is **no `package.json`** (non-Node stack). Equivalent tasks:

| Task | Command |
|---|---|
| Local dev server | `php -S localhost:8000` (from project root) |
| XAMPP local | folder → `C:\xampp\htdocs\torque`, open `http://localhost/torque/` |
| DB reset (local) | delete `database/torque.sqlite` (SQLite) OR `DROP DATABASE torque_autoparts` (MySQL) → next request re-creates + re-seeds |
| Smoke test | curl against `api/*.php` (see Testing) |
| Deploy (file change) | `git push` → `ssh … git pull origin main` |
| Deploy helper | plink.exe (path from SchoolPro root — see VPS section) |

---

## Environment Variables

**None — configuration is hardcoded PHP.** The production-relevant values live in `config/database.php:17-21`:

```
MySQL host   = 127.0.0.1        (prod: localhost)
MySQL port   = 3306
DB name      = torque_autoparts
MySQL user   = root
MySQL pass   = ''               (prod: set a real password)
```

Before prod, update these to real credentials and consider extracting to an ignored `config/.env` / server environment reads so secrets aren't committed.

---

## Agent Conventions

- **Delete one-off scripts after use.** Do not leave temp files (`_tmp*.js`, test helpers) in the workspace root. Delete them immediately after the task completes. Exception: `plink.exe` is a persistent SSH utility — keep it (copy in from SchoolPro root if needed).
- Always run changes against BOTH drivers — if you touch SQL, verify it works on MySQL **and** the SQLite fallback (upserts need both `ON CONFLICT` and `ON DUPLICATE KEY UPDATE` branches).
- `index.html` is a static prototype — prefer editing `index.php` unless the user explicitly says otherwise.
- Keep the two-source inventory invariant: `branch_stock` + `products.stock_quantity` + `stock_movements` audit row on every mutation.

---

## UI/UX Design Rules

### Forms & Inputs
- Don't use placeholder as label — always use proper `<label>` elements (the app does this in modals).
- Add `maxLength` to text inputs (name: 100, email: 150, phone: 20, password: 72).
- Placeholders are hints only (e.g. "e.g. Corolla, Civic, Elantra…", "e.g. BP-8842").
- Group related fields, align by information type (2-col grid for cost/price in add-part modal).

### Interaction Patterns
- Every user action must give feedback: `App.toast(message, 'success'|'error'|'info')` (3.5s auto-dismiss, colored left border).
- Buttons say what they do: "Sell Now (Cash) · GHS 1,234.00", "Receive & Add to Stock", "Start My Shift".
- One primary button per section/modal; secondary actions muted (`btn-dark`).
- Hover/active affordance on all tappable tiles, cards, chips.
- Live search-as-you-type in POS (`input` listener), category + vehicle fitment filter chips.
- Offline-friendly: module loads must not crash when an endpoint 401s (fallbacks already in place).

### Blueprint product rules (HotelPro app-design-blueprint, applied 2026-08-29)

Treat users like laymen with no tech background. Every screen obeys:
- **3-click limit:** most jobs reachable in ≤3 taps; nothing deeper than 4.
- **No jargon:** "Close My Shift" not "Reconcile & Clock Out", "Order Parts From Supplier" not "Purchase Order", "Fits All Cars" not "Universal Fitment", "Price Quote" not "Proforma Quotation", "Mechanic Price (15% off)" not "Wholesale", "Move Stock Between Branches" not "Execute Transfer".
- **One job per screen:** a modal/section does one thing ("Add a New Part", "Start My Shift", "Close My Shift & Count the Till").
- **Manual POS sale (no scan):** "Add Custom Item (no scanning)" modal — seller types the item name + sets the price per unit + quantity; system auto-computes the line total (price × qty) and the VAT/grand total. Backed by `manual:true` items in `sales.php` checkout (`product_id NULL`, no stock deducted, `sku MANUAL`).
- **Cash change calculator:** for Cash payments, seller enters the amount the customer gave → live "Change to give the customer" (green) or "Still owed" (red) at the bottom of the POS cart.
- **Status = color + text always:** never a bare indicator — every dot also has words ("In Stock", "Low on Stock", "Active Now / Off Shift", "Ordered / Received").
- **Touch-first:** base font ≥16px (never below 16 for reading text, small labels may be smaller), buttons/inputs ≥44px tall, high-frequency actions (Sell, Start Shift, Add to Stock, Save) ≥52px. Apply via `min-height` on `.btn-primary/.btn-dark` (44px), `.completebtn`/`.authbtn` (52px), 44px `.btn-add-cart` and `.qty-btn`.

### Visual Design
- Use the token palette, don't invent colors: `--accent` (purple, primary), `--lime` (success/stock), `--coral` (danger/low stock). No orange anywhere.
- Mono font (`IBM Plex Mono`) for money, SKUs, invoice/payment refs.
- Muted status pills (`status-pill ok/low/received/ordered`), stock badges on product cards.
- Keep border-radius consistent (`--radius:12px` cards, buttons ~9px), soft shadows.
- Both themes must render the same layout correctly (light default, dark override).

### Consistency
- Keep one visual system across all 4 views; reuse shared classes (`.kpi-card`, `.data-table`, `.ops-section`, `.btn-primary/.btn-dark`, `.modal`).
- Inline `onclick` attribute calls are the established pattern — use global singletons (`App`, `POS`, `Inventory`, `Ops`, `Owner`) so inline handlers resolve.
- Money always formatted `GHS <toFixed(2)>`; empty states always friendly copy + centered.
- Ensure mobile: sidebar→drawer, tables→scroll, grids→wrap at ≤900px.

---

## Deployment Log

| Date | Commit | Change | Deployed |
|---|---|---|---|
| 2026-08-27 | `1eff0f8` | Initial commit (26 files) | ✅ VPS `/var/www/torque` |
| 2026-08-27 | `eb43b85` | MySQL-compat fix (MAX→CASE WHEN), env-overridable DB creds | ✅ VPS |
| 2026-08-27 | — | VPS setup: PHP 8.3-FPM + MariaDB 10.11 installed, DB `torque_autoparts` + `torque` user, nginx site on **8081/8444**, seed data auto-loaded (verified via `api/analytics.php`) | ✅ **LIVE** — https://srv1810937.hstgr.cloud:8444/ |
| 2026-08-27 | `4f7f8b5` | **Auth hardening:** `requireAuth()` on every `api/*.php` (401/403), role gates (POST catalog/POs/inventory → Manager+; customers/branches/settings POST → Owner; export → Manager+), magic `password123`/`admin` bypass + demo auto-login + `switch_role` removed, `loadBranches()` moved to post-login only. Verified live: no-auth 401s, bad-password rejected, role gates 403, Owner/cashier logins OK. | ✅ VPS |
| 2026-08-27 | `46628d6`, `0493a73` | **Rebrand Torque → SpareStack** (branding only). User-facing strings, CSVs `SpareStack_*.csv`, defaults (`SpareStack Auto Parts`, `sales@sparestack.com`), docs. DB settings rows updated in place. Infra names intentionally unchanged (DB `torque_autoparts`, user `torque`, `/var/www/torque`, nginx `torque`, `TORQUE_DB_*`, `torque_theme`, `torque.sqlite`). Verified live. | ✅ VPS |
| 2026-08-29 | `d09d2a7` | **Blueprint laymen pass** (app-design-blueprint.md, now tracked in repo): plain-language labels across `index.php` + `pos.js`/`inventory.js`/`ops.js`/`owner.js` ("Close My Shift", "Price Quote", "Sell Now", "Fits All Cars", "Add Stock"/"History", no OEM code shown, "Full Price"/"Mechanic Price", stock movement headers plain); touch-first CSS in `app.css` (base 16px, `.btn-primary/.btn-dark`/modal buttons ≥44px, `.completebtn`/`.authbtn` 52px, 44px add-to-cart/qty buttons, bigger cart/table/modal fonts); `--amber`/`--amber-tint` tokens removed (light+dark). Verified: node --check on all JS OK; PHP lint deferred. **Deploy PENDING — VPS unreachable (SSH + HTTP/HTTPS all timeout).** Deploy: `git pull origin main` in `/var/www/torque`, restart php8.3-fpm, then re-verify. | ⏳ PENDING |
| 2026-08-29 | `d09d2a7` | **Deployed** (VPS was back up 2026-08-30). Per lessons-learned: linted all changed PHP on the server (clean), confirmed port 22/8444 reachable first, pulled, restarted php8.3-fpm, verified live HTML includes new elements. | ✅ VPS |
| 2026-08-30 | `4b03f22` | **POS manual-sale + change calc.** Manual/custom items: "Add Custom Item (no scanning)" button + modal (name, seller-entered price, qty) → system computes line total; backend `sales.php` accepts `{manual:true,name,unit_price,qty}`, no product lookup/stock deduction, `product_id=NULL` (schema + SQLite def + idempotent `ALTER` migration in `config/database.php::migrateManualItems`). Cash change: "how much cash did the customer give you?" input (`.cart` Cash only) → live "change to give" (lime) or "still owed" (coral). Tested live: manual checkout via API (subtotal 800, VAT 120, total 920, product_id null, SKU MANUAL); cleaned up test invoice. | ✅ VPS |

**Production URLs (active 2026-08-27):**
- `https://srv1810937.hstgr.cloud:8444/` — HTTPS (uses letsencrypt cert)
- `http://srv1810937.hstgr.cloud:8081/` — HTTP
- Firewall group on Hostinger allows TCP `22, 80, 443, 8080, 8443, 8081, 8444`. CyberPanel 8090 / Web Terminal 8888 intentionally NOT opened (not used).
- Daily reminder: **3306 (MariaDB) is closed to the internet** — do not open it.

*Append rows here on every production deploy, mirroring the SchoolPro deploy-log practice.*