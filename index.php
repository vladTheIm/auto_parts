<?php
/**
 * SpareStack — Auto Parts OS
 * Complete Auto Parts Dealership & Multi-Branch Operating System
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/settings.php';
$settings = Settings::getAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= htmlspecialchars($settings['dealership_name']) ?> — Auto Parts OS</title>
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<!-- ===================== TOAST NOTIFICATIONS ===================== -->
<div id="toastContainer"></div>

<!-- ===================== AUTH SCREEN ===================== -->
<div id="authScreen">
  <!-- Desktop Hero -->
  <div class="auth-brand">
    <div class="top"><span class="mark">S</span>SpareStack OS</div>
    <div class="pitch">
      <h2>Run every branch of your auto parts business from one screen.</h2>
      <p>Instant POS checkout, vehicle part fitment lookup, live branch inventory, and supplier purchase orders.</p>
    </div>
    <div class="gaugewrap" id="brandGauge">
      <svg width="180" height="180" viewBox="0 0 180 180">
        <circle cx="90" cy="90" r="75" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="10"/>
        <circle cx="90" cy="90" r="75" fill="none" stroke="#C6FF3D" stroke-width="10" stroke-linecap="round" stroke-dasharray="471.2" stroke-dashoffset="103.6" transform="rotate(-90 90 90)"/>
        <text x="90" y="86" text-anchor="middle" font-family="Space Grotesk" font-weight="700" font-size="36" fill="#fff">98.4%</text>
        <text x="90" y="114" text-anchor="middle" font-family="Inter" font-size="13" fill="#D7CCFC">fitment match</text>
      </svg>
    </div>
    <div class="stats">
      <div class="s"><div class="n">3 Branches</div><div class="l">Live Syncing</div></div>
      <div class="s"><div class="n">1,400+</div><div class="l">OEM Fits Tracked</div></div>
      <div class="s"><div class="n">99.9%</div><div class="l">Uptime</div></div>
    </div>
  </div>

  <!-- Form Area (Centered Card on Mobile & Desktop) -->
  <div class="auth-form-wrap">
    <div class="auth-form">
      <!-- Mobile Header -->
      <div class="auth-mobile-header">
        <div class="top-logo">
          <span class="auth-brand" style="display:inline-flex; width:30px; height:30px; border-radius:8px; background:var(--lime); color:#120C25; align-items:center; justify-content:center; font-weight:800; font-size:15px; padding:0; flex:none;">T</span>
          <span>SpareStack OS</span>
        </div>
        <p>Auto Parts & Workshop Management</p>
      </div>

      <div class="auth-tabs">
        <button class="active" id="tabLogin" onclick="setAuthTab('login')">Sign In</button>
        <button id="tabSignup" onclick="setAuthTab('signup')">Create Account</button>
      </div>

      <div id="loginForm">
        <label>Work Email</label>
        <input id="loginEmail" value="efua@asanteautoparts.com" placeholder="you@dealership.com" autocomplete="email">
        
        <label>Password</label>
        <input id="loginPassword" type="password" value="password123" placeholder="••••••••" autocomplete="current-password">
        
        <div class="errline" id="loginErr">Please fill in your credentials.</div>
        
        <button class="authbtn" onclick="App.doLogin()">Sign In to Shop</button>

        <div style="margin-top:16px;">
          <div style="font-size:11.5px; color:var(--ink-soft); font-weight:600; margin-bottom:6px;">⚡ Quick 1-Tap Demo Logins:</div>
          <div class="demo-chips">
            <span class="demo-chip" onclick="fillDemo('owner')">👤 Owner (Efua)</span>
            <span class="demo-chip" onclick="fillDemo('manager')">💼 Manager (Kojo)</span>
            <span class="demo-chip" onclick="fillDemo('cashier')">💳 Cashier (Ama)</span>
          </div>
        </div>
      </div>

      <div id="signupForm" style="display:none;">
        <label>Your Role in the Dealership</label>
        <div class="role-grid">
          <div class="role-opt sel" data-role="Owner" onclick="pickRole(this)">Owner</div>
          <div class="role-opt" data-role="Manager" onclick="pickRole(this)">Manager</div>
          <div class="role-opt" data-role="Cashier" onclick="pickRole(this)">Cashier</div>
        </div>
        <label id="orgLabel">Dealership Name</label>
        <input id="signupOrg" placeholder="e.g. Asante Auto Parts">
        <label>Full Name</label>
        <input id="signupName" placeholder="e.g. Efua Asante">
        <label>Work Email</label>
        <input id="signupEmail" placeholder="you@dealership.com" autocomplete="email">
        <label>Password</label>
        <input id="signupPassword" type="password" placeholder="Create a password" autocomplete="new-password">
        <div class="errline" id="signupErr">All fields are required.</div>
        <button class="authbtn" onclick="App.doSignup()">Create Account</button>
        <div class="authfoot">By signing up, you agree to SpareStack's terms of service.</div>
      </div>
    </div>
  </div>
</div>

<!-- ===================== APP SHELL ===================== -->
<div id="appShell">
  <div class="shell-flex">
    <!-- Mobile Backdrop -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="App.toggleMobileSidebar(false)"></div>

    <!-- Sidebar Drawer -->
    <div class="sidebar" id="appSidebar">
      <div class="brand">
        <span class="mark">S</span>SpareStack OS
      </div>
      <nav id="sideNav"></nav>

      <div class="spacer"></div>

      <div class="branch-select-wrap">
        <label>Active Branch</label>
        <select class="branch-select" id="globalBranchSelect" onchange="App.switchBranch(this.value)"></select>
      </div>

      <div class="userchip">
        <div class="av" id="userAvatar">EA</div>
        <div style="min-width:0;">
          <div class="name" id="userName">Efua Asante</div>
          <div class="role" id="userRole">Owner</div>
        </div>
        <button class="logout" title="Log Out" onclick="App.doLogout()">⏻</button>
      </div>
    </div>

    <!-- Main Workspace -->
    <div class="main">
      <div class="topbar">
        <button class="btn-mobile-menu" onclick="App.toggleMobileSidebar(true)" aria-label="Open Navigation">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
        <h1 id="pageTitle">Dashboard Overview</h1>
        <span class="badge-branch currentBranchLabel">Kumasi Main</span>

        <div class="topbar-actions">
          <div class="themeToggle" onclick="App.toggleTheme()">
            <div class="opt sel" id="lightOpt">☀</div>
            <div class="opt" id="darkOpt">☾</div>
          </div>
        </div>
      </div>

      <!-- ===== DASHBOARD VIEW ===== -->
      <div id="dashboard" class="view active">
        <div class="dash-head">
          <h2>Welcome back, <span id="dashName">Efua</span></h2>
          <p>Real-time telemetry for <strong class="currentBranchLabel">Kumasi Main</strong> and across your branches.</p>
        </div>

        <div class="kpi-grid">
          <div class="kpi-card">
            <div>
              <div class="val" id="dashSalesToday">GHS 0.00</div>
              <div class="lbl">Total Sales Today</div>
            </div>
          </div>
          <div class="kpi-card">
            <div>
              <div class="val" id="dashStaffCount">3 of 6</div>
              <div class="lbl">Staff Online Now</div>
            </div>
          </div>
          <div class="kpi-card">
            <div>
              <div class="val" id="dashLowStock" style="color:var(--coral);">2</div>
              <div class="lbl">Parts Requiring Reorder</div>
            </div>
          </div>
          <div class="kpi-card">
            <div>
              <div class="val" id="dashBranches">3</div>
              <div class="lbl">Active Connected Branches</div>
            </div>
          </div>
        </div>

        <h3 style="font-size:16px; margin-bottom:14px;">Quick Actions</h3>
        <div class="tile-grid" id="tileGrid"></div>
      </div>

      <!-- ===== POINT OF SALE (POS) VIEW ===== -->
      <div id="pos" class="view">
        <div class="scanbar">
          <span class="mono" style="color:var(--ink-faint); font-size:16px;">⌕</span>
          <input id="posSearch" placeholder="Search by Part Name, OEM #, SKU, or Vehicle Model (e.g. Corolla, Civic, Elantra)...">
          <button class="btn-scan" onclick="POS.simulateBarcodeScan()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 5v14M8 5v14M12 5v14M17 5v14M21 5v14"/></svg>
            Barcode Scanner
          </button>
        </div>

        <!-- Vehicle Fitment Quick Filter -->
        <div class="fitment-bar" id="fitmentBar">
          <span class="fitment-tag active" onclick="POS.selectVehicle('all', this)">All Vehicles</span>
          <span class="fitment-tag" onclick="POS.selectVehicle('Toyota', this)">Toyota Corolla / Matrix</span>
          <span class="fitment-tag" onclick="POS.selectVehicle('Honda', this)">Honda Accord / Civic</span>
          <span class="fitment-tag" onclick="POS.selectVehicle('Nissan', this)">Nissan Almera / Tiida</span>
          <span class="fitment-tag" onclick="POS.selectVehicle('Hyundai', this)">Hyundai Elantra</span>
          <span class="fitment-tag" onclick="POS.selectVehicle('Kia', this)">Kia Forte / Cerato</span>
        </div>

        <div class="pos-grid">
          <div>
            <div class="cats" id="cats"></div>
            <div class="product-grid" id="productGrid"></div>
          </div>

          <!-- Live Cart -->
          <div class="cart">
            <h3>
              Current Sale 
              <span class="mono" id="itemCount" style="font-size:12px; color:var(--ink-faint); font-weight:500;">0 items</span>
            </h3>

            <!-- Price Tier Toggle (Retail vs Mechanic Wholesale) -->
            <div style="display:flex; gap:6px; background:var(--panel-raised); border:1px solid var(--line); border-radius:8px; padding:3px; margin:8px 0 10px;">
              <button style="flex:1; border:none; padding:5px 0; border-radius:6px; font-size:11.5px; font-weight:600; cursor:pointer; background:var(--accent); color:#fff;" id="btnRetailTier" onclick="POS.toggleWholesale(false); this.style.background='var(--accent)'; this.style.color='#fff'; document.getElementById('btnWholesaleTier').style.background='transparent'; document.getElementById('btnWholesaleTier').style.color='var(--ink-soft)';">
                Retail Price
              </button>
              <button style="flex:1; border:none; padding:5px 0; border-radius:6px; font-size:11.5px; font-weight:600; cursor:pointer; background:transparent; color:var(--ink-soft);" id="btnWholesaleTier" onclick="POS.toggleWholesale(true); this.style.background='var(--accent)'; this.style.color='#fff'; document.getElementById('btnRetailTier').style.background='transparent'; document.getElementById('btnRetailTier').style.color='var(--ink-soft)';">
                Mechanic Wholesale (-15%)
              </button>
            </div>
            
            <div class="customer-select-bar">
              <select id="posCustomerSelect"></select>
            </div>

            <div class="cart-items" id="cartItems">
              <div class="empty">Scan barcode or tap a part to add to sale.</div>
            </div>

            <div class="cart-perf"></div>

            <div class="totals">
              <div><span>Subtotal</span><span id="tSub" class="mono">GHS 0.00</span></div>
              <div><span>VAT (15%)</span><span id="tVat" class="mono">GHS 0.00</span></div>
              <div class="grand"><span>Total Due</span><span id="tTotal" class="mono">GHS 0.00</span></div>
            </div>

            <div class="paymethods" id="payMethods">
              <button class="sel" data-m="Cash">Cash</button>
              <button data-m="MoMo">MoMo</button>
              <button data-m="Card">Card</button>
              <button data-m="Credit">Credit</button>
            </div>

            <button class="completebtn" id="completeBtn" onclick="POS.executeCheckout()" disabled>Add items to continue</button>
            
            <button class="btn-dark" style="width:100%; margin-top:8px; padding:10px 0; background:var(--panel-raised); border:1px solid var(--line); color:var(--ink); font-weight:600; font-size:13px;" onclick="POS.generateQuotation()">
              📄 Generate Proforma Quotation
            </button>
          </div>
        </div>
      </div>

      <!-- ===== OPERATIONS & INVENTORY VIEW ===== -->
      <div id="ops" class="view">
        <!-- Cashier Shift Card -->
        <div class="ops-section" style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
          <div style="display:flex; align-items:center; gap:12px;">
            <span class="dot" id="shiftDot"></span>
            <div>
              <div style="font-weight:700; font-size:15px;" id="opsWho">Efua Asante · Manager</div>
              <div style="font-size:12px; color:var(--ink-soft);" id="shiftSince">Clocked in at 8:00 AM</div>
            </div>
          </div>
          <div style="margin-left:auto; text-align:right;">
            <div class="mono" style="font-weight:700; font-size:16px;" id="shiftFloatAmount">GHS 300.00</div>
            <div style="font-size:11px; color:var(--ink-faint); text-transform:uppercase;">Opening Float</div>
          </div>
          <button id="clockBtn" class="btn-dark" onclick="Ops.toggleClock()">Reconcile & Clock Out</button>
        </div>

        <!-- Inventory Catalog Table -->
        <div class="ops-section">
          <div class="ops-section-head">
            <div>
              <h3>Branch Inventory — <span class="currentBranchLabel">Kumasi Main</span></h3>
              <p style="font-size:12.5px; color:var(--ink-soft); margin:3px 0 0;">Track quantities, vehicle fitments, and stock adjustments.</p>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
              <button class="btn-dark" style="background:var(--panel-raised); border:1px solid var(--line); color:var(--ink);" onclick="Inventory.openReturnModal()">
                ↺ Customer Return / Refund
              </button>
              <button class="btn-primary" onclick="Inventory.openItemModal()">+ Add New Part</button>
            </div>
          </div>
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Photo</th>
                  <th>Part Description & Fitment</th>
                  <th class="mono">SKU</th>
                  <th>In Stock</th>
                  <th class="mono">Reorder</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="invtBody"></tbody>
            </table>
          </div>
        </div>

        <!-- Purchase Orders -->
        <div class="ops-section">
          <div class="ops-section-head">
            <div>
              <h3>Supplier Purchase Orders</h3>
              <p style="font-size:12.5px; color:var(--ink-soft); margin:3px 0 0;">Manage orders from auto parts distributors and warehouse restocks.</p>
            </div>
            <button class="btn-dark" onclick="Ops.openPOModal()">+ New Purchase Order</button>
          </div>
          <div id="poList"></div>
        </div>
      </div>

      <!-- ===== OWNER & MULTI-BRANCH CONSOLE ===== -->
      <div id="owner" class="view">
        <div class="ops-section-head" style="margin-bottom:18px;">
          <div>
            <h2>Dealership Branches & Workshop Accounts</h2>
            <p style="font-size:13px; color:var(--ink-soft); margin-top:4px;">Multi-branch telemetry, staff allocations, and garage credit management.</p>
          </div>
          <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button class="btn-dark" onclick="Owner.openCustomerModal()">+ Add Garage Account</button>
            <button class="btn-primary" onclick="Owner.openBranchModal()">+ Add New Branch</button>
          </div>
        </div>

        <!-- Branches List -->
        <div id="ownerBranchList"></div>

        <!-- Garage Credit Ledger -->
        <div class="ops-section" style="margin-top:24px;">
          <div class="ops-section-head">
            <div>
              <h3>Mechanic & Workshop Credit Accounts</h3>
              <p style="font-size:12.5px; color:var(--ink-soft); margin:3px 0 0;">Track unpaid parts balances and collect credit payments from affiliated garages.</p>
            </div>
          </div>
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Customer Name</th>
                  <th>Workshop / Garage</th>
                  <th class="mono">Phone</th>
                  <th>Unpaid Credit Balance</th>
                  <th>Credit Limit</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="customerTableBody"></tbody>
            </table>
          </div>
        </div>

        <!-- Accounting CSV Reports -->
        <div class="ops-section" style="margin-top:24px;">
          <div class="ops-section-head">
            <div>
              <h3>Accounting & Financial Exports</h3>
              <p style="font-size:12.5px; color:var(--ink-soft); margin:3px 0 0;">Export CSV journal reports for Excel, accountants, and tax auditing.</p>
            </div>
          </div>
          <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px;">
            <a href="api/export.php?type=sales" target="_blank" class="btn-dark" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px; padding:10px 16px;">
              📊 Download Sales Transactions CSV
            </a>
            <a href="api/export.php?type=inventory" target="_blank" class="btn-dark" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px; padding:10px 16px; background:var(--panel-raised); color:var(--ink); border:1px solid var(--line);">
              📦 Download Inventory Valuation CSV
            </a>
          </div>
        </div>

        <!-- Dealership Settings -->
        <div class="ops-section" style="margin-top:24px;">
          <h3>Dealership & POS Settings</h3>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:14px;">
            <div>
              <label style="font-size:12px; color:var(--ink-soft);">Dealership Name</label>
              <input id="setShopName" style="width:100%; padding:9px 12px; border:1px solid var(--line); border-radius:8px; background:var(--panel); color:var(--ink);">
            </div>
            <div>
              <label style="font-size:12px; color:var(--ink-soft);">Tagline / Moto</label>
              <input id="setTagline" style="width:100%; padding:9px 12px; border:1px solid var(--line); border-radius:8px; background:var(--panel); color:var(--ink);">
            </div>
            <div>
              <label style="font-size:12px; color:var(--ink-soft);">Contact Phone</label>
              <input id="setPhone" style="width:100%; padding:9px 12px; border:1px solid var(--line); border-radius:8px; background:var(--panel); color:var(--ink);">
            </div>
            <div>
              <label style="font-size:12px; color:var(--ink-soft);">Physical Address</label>
              <input id="setAddress" style="width:100%; padding:9px 12px; border:1px solid var(--line); border-radius:8px; background:var(--panel); color:var(--ink);">
            </div>
            <div>
              <label style="font-size:12px; color:var(--ink-soft);">Currency</label>
              <select id="setCurrency" style="width:100%; padding:9px 12px; border:1px solid var(--line); border-radius:8px; background:var(--panel); color:var(--ink);">
                <option value="GHS">GHS (Ghanaian Cedi)</option>
                <option value="USD">USD (US Dollar)</option>
                <option value="EUR">EUR (Euro)</option>
                <option value="NGN">NGN (Nigerian Naira)</option>
                <option value="KES">KES (Kenyan Shilling)</option>
              </select>
            </div>
            <div>
              <label style="font-size:12px; color:var(--ink-soft);">VAT Rate (%)</label>
              <input id="setVatRate" type="number" style="width:100%; padding:9px 12px; border:1px solid var(--line); border-radius:8px; background:var(--panel); color:var(--ink);">
            </div>
          </div>
          <div style="margin-top:16px; text-align:right;">
            <button class="btn-primary" onclick="Owner.saveSettings()">Save Configuration</button>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ===================== MODALS ===================== -->

<!-- 1. Restock Modal -->
<div class="modal-backdrop" id="restockModal">
  <div class="modal">
    <h3>Restock Part Inventory</h3>
    <p style="font-size:13.5px; color:var(--ink-soft); margin-bottom:14px;" id="restockPartName"></p>
    <label>Quantity to Add</label>
    <input type="number" id="restockQty" min="1" value="10">
    <label>Reason / Source</label>
    <select id="restockReason">
      <option value="Supplier Delivery">Supplier Delivery</option>
      <option value="Inter-Branch Transfer">Inter-Branch Transfer</option>
      <option value="Inventory Audit Adjust">Inventory Audit Adjust</option>
    </select>
    <div class="modal-actions">
      <button onclick="Inventory.closeRestockModal()">Cancel</button>
      <button class="btn-primary" onclick="Inventory.confirmRestock()">Add to Stock</button>
    </div>
  </div>
</div>

<!-- 2. Inter-Branch Stock Transfer Modal -->
<div class="modal-backdrop" id="transferModal">
  <div class="modal">
    <h3>Inter-Branch Stock Transfer</h3>
    <p style="font-size:13.5px; color:var(--ink-soft); margin-bottom:14px;" id="transferPartName"></p>
    <label>From Source Branch</label>
    <select id="transferFromBranch"></select>
    <label>To Destination Branch</label>
    <select id="transferToBranch"></select>
    <label>Quantity to Transfer</label>
    <input type="number" id="transferQty" min="1" value="5">
    <div class="modal-actions">
      <button onclick="Inventory.closeTransferModal()">Cancel</button>
      <button class="btn-primary" onclick="Inventory.confirmTransfer()">Execute Transfer</button>
    </div>
  </div>
</div>

<!-- 3. Customer Return / Refund Modal -->
<div class="modal-backdrop" id="returnModal">
  <div class="modal">
    <h3>Process Customer Return & Refund</h3>
    <p style="font-size:12.5px; color:var(--ink-soft); margin-bottom:12px;">Refund sale items and automatically return parts back to inventory stock.</p>
    <label>Original Invoice Number</label>
    <input id="returnInvoiceNo" placeholder="e.g. INV-260826-A1B2">
    <label>Reason for Return</label>
    <select id="returnReason">
      <option value="Defective / Warranty Replacement">Defective / Warranty Replacement</option>
      <option value="Wrong Fitment Ordered">Wrong Fitment Ordered</option>
      <option value="Customer Cancellation">Customer Cancellation</option>
    </select>
    <div class="modal-actions">
      <button onclick="Inventory.closeReturnModal()">Cancel</button>
      <button class="btn-primary" onclick="Inventory.confirmReturn()" style="background:var(--coral); border-color:var(--coral);">Process Refund & Restock</button>
    </div>
  </div>
</div>

<!-- 4. Stock Movement History Modal -->
<div class="modal-backdrop" id="auditModal">
  <div class="modal modal-lg">
    <h3>Stock Movement Audit Ledger</h3>
    <div class="table-responsive">
      <table class="data-table" style="margin-top:12px;">
        <thead>
          <tr>
            <th>Date & Time</th>
            <th>Reason & Note</th>
            <th>Qty Change</th>
            <th>Balance</th>
            <th>Staff User</th>
          </tr>
        </thead>
        <tbody id="auditLogBody"></tbody>
      </table>
    </div>
    <div class="modal-actions">
      <button onclick="Inventory.closeAuditModal()">Close Ledger</button>
    </div>
  </div>
</div>

<!-- 5. Add Part Catalog Modal -->
<div class="modal-backdrop" id="itemModal">
  <div class="modal">
    <h3>Add Part to Master Catalog</h3>
    <label>Part Name</label>
    <input id="newItemName" placeholder="e.g. Ceramic Front Brake Pads">
    <label>SKU Number</label>
    <input id="newItemSku" placeholder="e.g. BP-8842">
    <label>Category</label>
    <select id="newItemCategory">
      <option value="1">Brakes & Friction</option>
      <option value="2">Filters & Intake</option>
      <option value="3">Electrical & Batteries</option>
      <option value="4">Suspension & Steering</option>
      <option value="5">Ignition & Spark</option>
      <option value="6">Engine & Cooling</option>
      <option value="7">Fluids & Lubricants</option>
    </select>
    <label>Vehicle Compatibility / Fitment Notes</label>
    <input id="newItemFits" placeholder="e.g. Toyota Corolla (2012-2018) 1.8L">
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
      <div>
        <label>Cost Price (GHS)</label>
        <input type="number" id="newItemCost" value="50">
      </div>
      <div>
        <label>Selling Price (GHS)</label>
        <input type="number" id="newItemPrice" value="90">
      </div>
    </div>
    <label>Starting Stock</label>
    <input type="number" id="newItemStock" value="15">
    <div class="modal-actions">
      <button onclick="Inventory.closeItemModal()">Cancel</button>
      <button class="btn-primary" onclick="Inventory.createNewItem()">Add Part to Catalog</button>
    </div>
  </div>
</div>

<!-- 6. Thermal Receipt & Invoice Modal -->
<div class="modal-backdrop" id="receiptModal">
  <div class="modal" style="max-width:380px;">
    <div id="receiptContent"></div>
    <div class="modal-actions" style="margin-top:16px; display:flex; flex-direction:column; gap:8px;">
      <div style="display:flex; gap:8px; width:100%;">
        <button onclick="POS.closeReceiptModal()" style="flex:1;">Close</button>
        <button class="btn-primary" onclick="POS.printReceipt()" style="flex:1;">🖨️ Print Receipt</button>
      </div>
      <button class="btn-dark" onclick="POS.shareWhatsAppReceipt()" style="width:100%; background:#25D366; color:#fff; border:none; padding:10px 0; font-weight:600; display:flex; align-items:center; justify-content:center; gap:6px;">
        💬 Share via WhatsApp
      </button>
    </div>
  </div>
</div>

<!-- 7. Shift Clock In Modal -->
<div class="modal-backdrop" id="clockInModal">
  <div class="modal">
    <h3>Open Shift & Drawer Float</h3>
    <p style="font-size:13px; color:var(--ink-soft);">Enter the physical cash float amount inside the cashier drawer.</p>
    <label>Opening Cash Float (GHS)</label>
    <input type="number" id="clockInFloat" value="300.00" step="0.01">
    <div class="modal-actions">
      <button onclick="Ops.closeClockInModal()">Cancel</button>
      <button class="btn-primary" onclick="Ops.confirmClockIn()">Open Cashier Shift</button>
    </div>
  </div>
</div>

<!-- 8. Shift Clock Out & Cash Drawer Reconciliation Modal -->
<div class="modal-backdrop" id="clockOutModal">
  <div class="modal">
    <h3>End Shift & Cash Drawer Balancing</h3>
    <div style="background:var(--panel-raised); border:1px solid var(--line); border-radius:10px; padding:12px; margin:12px 0;">
      <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
        <span>Opening Float:</span><span class="mono" id="reconcileFloat">GHS 300.00</span>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
        <span>Cash Sales Today:</span><span class="mono" id="reconcileCashSales">GHS 0.00</span>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px; font-weight:bold; border-top:1px dashed var(--line); padding-top:6px; margin-top:6px;">
        <span>Expected in Drawer:</span><span class="mono" id="reconcileExpected">GHS 300.00</span>
      </div>
    </div>
    <label>Physical Cash Counted (GHS)</label>
    <input type="number" id="countedCashInput" step="0.01" oninput="Ops.updateVarianceDisplay()">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; font-size:13.5px; font-weight:600;">
      <span>Drawer Variance:</span>
      <span class="mono" id="reconcileVariance">+GHS 0.00</span>
    </div>
    <div class="modal-actions">
      <button onclick="Ops.closeClockOutModal()">Cancel</button>
      <button class="btn-primary" onclick="Ops.confirmClockOut()">Close & Balance Shift</button>
    </div>
  </div>
</div>

<!-- 9. New Purchase Order Modal -->
<div class="modal-backdrop" id="poModal">
  <div class="modal">
    <h3>Issue Supplier Purchase Order</h3>
    <label>Auto Parts Supplier</label>
    <select id="poSupplierSelect"></select>
    <label>Part to Order</label>
    <select id="poProductSelect"></select>
    <label>Quantity</label>
    <input type="number" id="poQtyInput" value="20" min="1">
    <div class="modal-actions">
      <button onclick="Ops.closePOModal()">Cancel</button>
      <button class="btn-primary" onclick="Ops.createPO()">Send Purchase Order</button>
    </div>
  </div>
</div>

<!-- 10. Add Branch Modal -->
<div class="modal-backdrop" id="branchModal">
  <div class="modal">
    <h3>Add New Branch Location</h3>
    <label>Branch Name</label>
    <input id="newBranchName" placeholder="e.g. Cape Coast Branch">
    <label>Physical Location</label>
    <input id="newBranchLoc" placeholder="e.g. Commercial Street, Cape Coast">
    <label>Phone Contact</label>
    <input id="newBranchPhone" placeholder="e.g. +233 33 213 4400">
    <div class="modal-actions">
      <button onclick="Owner.closeBranchModal()">Cancel</button>
      <button class="btn-primary" onclick="Owner.createBranch()">Activate Branch</button>
    </div>
  </div>
</div>

<!-- 11. Add Garage Customer Modal -->
<div class="modal-backdrop" id="customerModal">
  <div class="modal">
    <h3>Register Mechanic / Workshop Account</h3>
    <label>Master Mechanic / Contact Name</label>
    <input id="custName" placeholder="e.g. Master Kojo Mensah">
    <label>Workshop / Garage Name</label>
    <input id="custWorkshop" placeholder="e.g. Precise Wheels Auto Clinic">
    <label>Phone Number</label>
    <input id="custPhone" placeholder="e.g. +233 24 555 1234">
    <label>Credit Limit (GHS)</label>
    <input type="number" id="custLimit" value="2500.00">
    <div class="modal-actions">
      <button onclick="Owner.closeCustomerModal()">Cancel</button>
      <button class="btn-primary" onclick="Owner.createCustomer()">Register Account</button>
    </div>
  </div>
</div>

<!-- Scripts -->
<script>
let signupRole = 'Owner';
function setAuthTab(t) {
  document.getElementById('tabLogin').classList.toggle('active', t === 'login');
  document.getElementById('tabSignup').classList.toggle('active', t === 'signup');
  document.getElementById('loginForm').style.display = t === 'login' ? 'block' : 'none';
  document.getElementById('signupForm').style.display = t === 'signup' ? 'block' : 'none';
}
function pickRole(el) {
  document.querySelectorAll('.role-opt').forEach(o => o.classList.remove('sel'));
  el.classList.add('sel');
  signupRole = el.dataset.role;
  document.getElementById('orgLabel').textContent = signupRole === 'Owner' ? 'Dealership Name' : 'Branch Invite Code';
}
function fillDemo(role) {
  const emailInput = document.getElementById('loginEmail');
  const passInput = document.getElementById('loginPassword');
  passInput.value = 'password123';
  if (role === 'owner') emailInput.value = 'efua@asanteautoparts.com';
  if (role === 'manager') emailInput.value = 'kojo@asanteautoparts.com';
  if (role === 'cashier') emailInput.value = 'ama@asanteautoparts.com';
}
</script>
<script src="assets/js/app.js"></script>
<script src="assets/js/pos.js"></script>
<script src="assets/js/inventory.js"></script>
<script src="assets/js/ops.js"></script>
<script src="assets/js/owner.js"></script>
</body>
</html>
