# App Design Blueprint — Reusable Pattern Guide

> Written from HotelPro (multi-tenant hotel management SaaS). This is the *how* — the architecture, patterns, and rules I followed — extracted so it can be applied to build any other app. Use it as a checklist, not a spec.

---

## 1. Design Philosophy

Everything in this app follows a few non-negotiable rules. Decide yours up front and write them down (see `memory.md` — I keep the current state, rules, and next steps in one file I read before every change).

**Product rules (from §8 of memory.md):**
- **3-click rule** — any action must be reachable in at most 3 clicks.
- **No jargon** — plain language ("rooms free / rooms occupied", never percentages when a count is clearer).
- **One thing per screen** — a screen does one job.
- **Status is always color + text label**, never color alone (accessibility).
- **Touch-first**: base font 18px (never below 16px), buttons min 44px tall, 52px+ for high-frequency actions.

**Engineering rules:**
- Multi-tenant from day one (never retrofit it).
- Every API call is audited automatically.
- Features ship in phases, each phase = tables + endpoints + pages + a demo/proof.
- Never leave junk files in the repo (a written cleanup rule).

---

## 2. System Architecture

```
Browser (React SPA)
   │  /api/*  (fetch/axios)
   ▼
Nginx (serves static SPA + proxies /api)
   ▼
Express API (Node.js)
   ├── middleware chain (audit → rate limit → auth → tenancy)
   ├── routes/ → controllers → services → PostgreSQL
   ├── event bus (in-process EventEmitter) → rules engine → notifications → Socket.IO
   └── Socket.IO (real-time push, JWT-authed rooms per hotel)
   ▼
PostgreSQL (single shared DB, tenant-scoped rows by hotel_id)
```

Data flows in one direction: UI action → API → DB → event emitted → other systems react (notifications, rules, socket push). This is the "every action automatically updates every other system that needs to know" idea — the app's real architecture.

---

## 3. Backend Design Patterns

### 3.1 Folder Structure
```
backend/src/
  index.js            # entry — middleware, route mounting, socket.io, rate limiters
  db/                 # pool.js, migrate.js, schema.sql, seed.sql
  middleware/         # auth.js, tenancy.js, audit.js
  routes/             # thin — one file per resource, wires controller to HTTP
  controllers/        # request/response handling, business logic
  services/           # cross-cutting logic (eventEmitter, rulesEngine, notifications, smsService)
```

### 3.2 The Request Pipeline (key insight)
Every request flows through the same middleware chain, in this order:

`auditMiddleware` → `rateLimit` → `authenticate` → `tenancy` → controller

1. **audit.js** — wraps `res.json`, records `method, path, user, ip, duration, request/response body` into `audit_logs` (strips passwords/tokens first). Runs on `res.on('finish')`, never blocks the response.
2. **rateLimit** — per-endpoint-family limiters defined in `index.js` (stricter for `/api/otp` and `/api/auth/`).
3. **authenticate** (`middleware/auth.js`) — verifies JWT Bearer token, sets `req.user`; `authorize(...roles)` guards specific roles.
4. **tenancy** (`middleware/tenancy.js`) — sets `req.hotelId` from the JWT (`null` for `platform_admin`). Every query after this uses `req.hotelId`.

**Pattern**: routes apply the middleware once at router level (`router.use(authenticate); router.use(tenancy)`) so every endpoint in that file is protected and scoped without repeating it.

### 3.3 Route File Pattern
`routes/rooms.js`:
```
router.use(authenticate);
router.use(tenancy);
router.get('/types', controller.getTypes);     // sub-resource first, before /:id
router.put('/types/:room_type/price', ...);    // or /:id routes swallow 'types'
router.get('/', controller.getAll);            //   => order matters!
router.get('/:id', ...);
router.post('/', ...);
router.put('/:id', ...);
router.delete('/:id', ...);
```
**Gotcha learned**: put static/sub-resource routes **before** `/:id` routes, or `/types` gets captured by `/:id`.

### 3.4 Controller Pattern
Every controller method is a flat `try/catch`:
- Query with parameterized SQL (`$1, $2` — never string interpolation).
- Every query filters by `req.hotelId` (tenancy enforced at the query level too, not just auth).
- Consistent responses: `201 + RETURNING *` on create, `404 { error }` when a row with matching `hotel_id` isn't found, `500 { error: 'friendly message' }` on failure.
- Handle the unique-violation code `23505` explicitly (e.g. "Room number already exists").
- Use transactions (`BEGIN/COMMIT/ROLLBACK` with a checked-out client) for multi-write operations like register-user-with-hotel.
- After a state change, fire an event (`emitEvent`) so other systems react.

### 3.5 Auth & Sessions
- `generateTokens(user)` — 15-min access JWT + 7-day refresh JWT.
- Access token payload carries `{ id, email, phone, role, hotel_id }` — this is what tenancy and role guards read, so **no DB lookup per request**.
- `refresh_tokens` table stores hashes for rotation.
- OTP-based flows (register, forgot password) with `otp_codes` + `phone_lockouts` tables — clamping + rate limiting.
- Frontend stores tokens in localStorage and refreshes automatically on 401 (see API client below).

### 3.6 Multi-Tenancy (the most transferable idea)
- One shared PostgreSQL database; **almost every table has `hotel_id`** and a `UNIQUE(hotel_id, <natural_key>)` constraint.
- The tenant comes from the JWT, is attached by middleware, and every query filters by it. `platform_admin` bypasses (gets `null` context) to see all hotels.
- Frontend equivalents: sidebar links filtered by `can('sidebar.<key>')`, role-based route guards.

### 3.7 Event System (decoupled reactions)
`eventEmitter.js`:
- Controllers call `emitEvent(hotelId, eventType, payload, source, io)`.
- It `INSERT`s into `events`, then emits on an in-process Node `EventEmitter`.
- `rulesEngine.js` listens and may trigger actions (e.g. auto-create notifications, send a rule-triggered event).
- `notificationDispatcher.js` often fires a Socket.IO `notification:new` to the hotel room.
- **Why**: domain actions (room status change, order paid) can trigger notifications, inventory auto-restock, audits, etc., without the controller knowing about those consumers.

### 3.8 Audit Everything
`audit.js` is the pattern for "observability without sprinkling logging everywhere":
- One middleware, registered once, catches every request.
- It monkey-patches `res.json` to capture the response body.
- Sensitive fields stripped from copied bodies.
- Persists async after response finishes, so it never adds latency.

### 3.9 Real-Time (Socket.IO)
- Same server as Express (http server + `io`), one shared process.
- Handshake auth: verify JWT from `socket.handshake.auth.token`.
- On connect, user joins rooms: `hotel:<hotelId>` and `hotel:<hotelId>:role:<role>`; `platform_admin` joins `platform`.
- Server emits to rooms; frontend `useSocket.js` hook wraps subscribe/emit.

---

## 4. Database Design Conventions

From `schema.sql`:

- **Single shared DB, tenant-scoped tables.** `SERIAL PRIMARY KEY` ids, `hotel_id INTEGER REFERENCES hotels(id) ON DELETE CASCADE` on (nearly) every table.
- **CHECK constraints on enum-like columns** instead of lookup tables when values are small & stable:
  `status VARCHAR(20) DEFAULT 'vacant' CHECK (status IN ('vacant','occupied','dirty','maintenance'))`.
- **Natural uniqueness**: `UNIQUE(hotel_id, room_number)` — prevents duplicates per tenant and gives a catchable `23505` error.
- **Money**: `DECIMAL(10, 2)`, never float.
- **Rows that record who did what**: `created_by`, `recorded_by`, `assigned_to` reference `users`.
- **Composite indexes for the hot query paths**: `idx_payments_hotel_created`, `idx_bookings_hotel_dates` (range scans by hotel over dates).
- **Audit table captures everything** — deliberately wide (request_body, response_body, ip, user_agent, duration_ms, error_message).

When adding a feature, the recipe is: **new table(s) → CHECKs + indexes → controller CRUD → routes → page → emit events on mutations → seed users/roles if needed**.

---

## 5. Frontend Design Patterns

### 5.1 Stack
React 19 + Vite + React Router; Axios; `lucide-react` icons; `recharts` for analytics charts; `jspdf` + `jspdf-autotable` for client PDFs; `socket.io-client`.

### 5.2 Routing & Role Shells (key insight)
`App.jsx`:
- Three layout shells = three roles: `FrontDeskLayout`, `HotelAdminLayout`, `PlatformAdminLayout`. Each is a route parent with `<Outlet />` and nested child routes — this gives each role its own nav chrome.
- `ProtectedRoute` wraps them with `allowedRoles`. Unauthorized users are redirected to their role's home route (`getHomeRoute(user)`).
- Home-route selection per role means the post-login experience is role-appropriate automatically.
- `/book` (public booking widget) lives outside the protected tree.
- Providers nest: `ThemeProvider > AuthProvider > PermissionsProvider > AppRoutes`.

### 5.3 Flow: how auth state reaches the UI
1. `AuthContext.jsx` boots by decoding the access token's payload from localStorage (`atob(token.split('.')[1])`) so the user is available immediately with zero round-trips; it also **auto-logs-out after 30 min inactivity**.
2. `api/client.js` (Axios instance): request interceptor adds `Authorization: Bearer`; response interceptor on 401 tries `/auth/refresh` once, retries the original request, and redirects to `/login` on refresh failure.

### 5.4 The Page Template (repeat this for every CRUD-ish page)
Almost every admin page follows this shape (see `AdminBookings.jsx`):
```
state: list, loading, modal, (detail), search, formData
useEffect(() => load(), [])          // load on mount
load() → api.get() → setList
actions → api.post/put → setModal(null) → load()   // refetch after mutation
render: header + action button → search bar → list/rows → modal form
```
A single `modal` state string switches which modal is open; forms are plain controlled inputs; errors surface via `alert(error.response?.data?.error)`; loading state returns early.

### 5.5 Reusable Component Library (build small, reuse)
Instead of rewriting lists/statuses/stats, extract them:
- `StatCard` — dashboard metric card.
- `StatusPill` — the "color + text label" rule in one component.
- `RoomTile` — the front-desk room grid cell.
- `ListRow` — generic list row.
- `BigActionButton` — oversized 52px+ touch action.
- `NotificationBell` — socket-fed unread count.

### 5.6 Styling System (design tokens, dark mode for free)
`index.css` holds **all** tokens as CSS variables on `:root` (`--bg`, `--panel`, `--text`, `--sub`, `--ember`, `size-button-min: 44px`, fonts, spacing, radii). A `body.light` block overrides the same variables for light mode. ThemeContext toggles the `dark`/`light` class and persists choice in localStorage.

Rules that keep it consistent:
- One global CSS file for tokens + modal base; smaller per-area CSS files (`Layout.css`, `FrontDesk.css`, `Auth.css`, `AdminPages.css`, `Finance.css`).
- Per-area components import only the CSS files they need.

### 5.7 Sidebar Navigation Pattern (`HotelAdminLayout.jsx`)
- Nav config is **data**, not markup: a `SECTIONS` array of `{ title, key, links: [{ to, icon, label }] }`. Render it with `.map()`.
- Section expansion state persisted to localStorage (`hotelpro-nav-sections`).
- Links filtered by role/permission: `filterLinks()` checks `can('sidebar.<key>')` (PermissionsContext) or role shortcuts for `chef`/`order_taker`.
- Responsive: sidebar overlays with `nav-overlay` on small screens; hamburger in topbar.

---

## 6. API Surface Design

- All routes namespaced under `/api/<resource>` with plural resource names.
- Consistent HTTP semantics: GET list/one, POST create, PUT update, DELETE delete; sub-mutations as POST/PUT on sub-paths (`/bookings/:id/checkin`, `/orders/:id/items/:itemId/stage`, `/orders/:id/pay`).
- Ship a health endpoint (`/api/health`).
- Every response is uniform JSON `{ data }` or `{ error: 'friendly message' }`.

---

## 7. Feature Phase Recipe (how each feature actually got built)

1. **Design the domain tables** in `schema.sql` (tenant-scoped, CHECKs, indexes, money as DECIMAL).
2. **Create route file** (middleware chain + sub-route ordering) **and controller** (CRUD + events on mutations).
3. **Mount route** in `index.js`.
4. **Create frontend route** in `App.jsx` under the right role shell.
5. **Build the page** from the page template; reuse `StatCard`/`StatusPill`/`ListRow`; add search where lists grow.
6. **Emit events** for anything other systems should react to (notifications, socket refresh, auto-restock).
7. **Migrate + seed** on the VPS, rebuild frontend, restart API.
8. **Verify** flows (plus a `.last-run.json` test result file pattern if tests exist).

---

## 8. Deployment Recipe (VPS)

```
plink -pw <pw> root@<ip>            # SSH
cd /var/www/<app> && git pull origin main
# run only new migrations from schema.sql (ALTER TABLE / CREATE TABLE for new columns)
cd frontend && npm install && npm run build    # static dist served by nginx
cd backend && npm install && pm2 restart <name>  # PM2 keeps API alive
```

- Frontend built to `dist/`, nginx serves it + proxies `/api` to the API process.
- Processes are run under **PM2** so they restart on crash.
- Config/credentials live in `backend/.env` (never committed).

---

## 9. Checklist for Designing Your Next App

**Product**
- [ ] Write one `memory.md`-style doc: current state, decisions, rules, credentials (read it first every session).
- [ ] Define and freeze your simplicity rules (click count, touch size, plain language, status color+text).
- [ ] Break the product into phases; each phase = tables + endpoints + pages + proof.

**Backend**
- [ ] Express + params everywhere + `middleware/`, `routes/`, `controllers/`, `services/`, `db/`.
- [ ] Middleware chain: audit → rate limit → authenticate → tenancy → controller (register once).
- [ ] Multi-tenancy from day one: tenant id in JWT + middleware + every query filtered.
- [ ] JWT access (short) + refresh (long), refresh rotation; OTP via SMS if phone is the identity.
- [ ] Event emitter on state changes → rules/notifications/socket.
- [ ] Audit middleware capturing every call without blocking responses.
- [ ] Consistent CRUD controller template, `23505` handled, transactions for multi-writes.

**Database**
- [ ] Tenant-scoped tables, `SERIAL` ids, CHECK-constrained enums, `DECIMAL` money, `created_by` audit columns, composite indexes on hot queries.

**Frontend**
- [ ] React + Vite + Router; one layout shell per role with nested `<Outlet/>` routes.
- [ ] `ProtectedRoute` with `allowedRoles` + per-role home route.
- [ ] Auth context that boots from the JWT payload; Axios client with token injection + 401-refresh-retry.
- [ ] Reusable primitives (StatCard, StatusPill, ListRow) + the standard page template (load → list → modal → refetch).
- [ ] CSS variables for all tokens; dark mode = class override; touch targets ≥44px.

**Ship**
- [ ] npm scripts in root to run frontend+backend together (`concurrently`).
- [ ] Build dist, nginx serve/proxy, PM2 process, `.env` on server, migrate-then-restart.
- [ ] Clean up all one-off scripts/migrations after use.