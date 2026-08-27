/**
 * SpareStack Auto Parts OS - Master Application Controller
 */

const App = {
  currentUser: null,
  currentBranchId: 1,
  branches: [],
  settings: {},

  init() {
    this.initTheme();
    this.checkSession();
  },

  toggleMobileSidebar(open) {
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar) sidebar.classList.toggle('mobile-open', open);
    if (backdrop) backdrop.classList.toggle('mobile-open', open);
  },

  async api(endpoint, method = 'GET', data = null) {
    const options = { method, headers: {} };
    if (data) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(data);
    }
    try {
      const res = await fetch(endpoint, options);
      const json = await res.json();
      if (!res.ok && json.error) {
        throw new Error(json.error);
      }
      return json;
    } catch (err) {
      console.error(`API Error on ${endpoint}:`, err);
      App.toast(err.message, 'error');
      throw err;
    }
  },

  toast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const el = document.createElement('div');
    el.className = 'toast';
    if (type === 'error') el.style.borderLeft = '4px solid var(--coral)';
    if (type === 'success') el.style.borderLeft = '4px solid var(--lime)';
    el.textContent = message;
    container.appendChild(el);
    setTimeout(() => { el.remove(); }, 3500);
  },

  async checkSession() {
    try {
      const res = await this.api('api/auth.php?action=current');
      if (res.success && res.user) {
        this.currentUser = res.user;
        this.currentBranchId = res.user.branch_id || 1;
        this.enterApp();
      } else {
        this.showAuth();
      }
    } catch (e) {
      this.showAuth();
    }
  },

  async loadBranches() {
    try {
      const res = await this.api('api/branches.php');
      if (res.success) {
        this.branches = res.branches;
        this.renderBranchSelect();
      }
    } catch (e) {}
  },

  renderBranchSelect() {
    const sel = document.getElementById('globalBranchSelect');
    if (!sel) return;
    sel.innerHTML = this.branches.map(b => 
      `<option value="${b.id}" ${b.id == this.currentBranchId ? 'selected' : ''}>${b.name}</option>`
    ).join('');
  },

  switchBranch(branchId) {
    this.currentBranchId = parseInt(branchId);
    const branch = this.branches.find(b => b.id == this.currentBranchId);
    if (branch) {
      document.querySelectorAll('.currentBranchLabel').forEach(el => el.textContent = branch.name);
    }
    // Refresh active view
    if (typeof POS !== 'undefined' && POS.loadProducts) POS.loadProducts();
    if (typeof Inventory !== 'undefined' && Inventory.load) Inventory.load();
    if (typeof Ops !== 'undefined' && Ops.load) Ops.load();
    if (typeof Owner !== 'undefined' && Owner.load) Owner.load();
  },

  enterApp() {
    document.getElementById('authScreen').style.display = 'none';
    document.getElementById('appShell').style.display = 'block';

    this.loadBranches();

    document.getElementById('userName').textContent = this.currentUser.name;
    document.getElementById('userRole').textContent = this.currentUser.role;
    document.getElementById('userAvatar').textContent = this.initials(this.currentUser.name);
    document.getElementById('dashName').textContent = this.currentUser.name.split(' ')[0];

    this.buildNav();
  },

  showAuth() {
    document.getElementById('appShell').style.display = 'none';
    document.getElementById('authScreen').style.display = 'flex';
  },

  initials(name) {
    if (!name) return 'TA';
    return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
  },

  navDefs: [
    { id: 'dashboard', label: 'Dashboard', roles: ['Owner', 'Manager', 'Cashier'], blurb: 'Jump to any part of the shop or view quick stats.' },
    { id: 'pos', label: 'Checkout & POS', roles: ['Owner', 'Manager', 'Cashier'], blurb: 'Ring up sales, scan vehicle parts, and print thermal receipts.' },
    { id: 'ops', label: 'Shop Operations', roles: ['Owner', 'Manager'], blurb: 'Manage branch inventory, stock audits, restocks, and purchase orders.' },
    { id: 'owner', label: 'Owner & Multi-Branch', roles: ['Owner'], blurb: 'See sales across all branches, staff shifts, and business settings.' },
  ],

  navIcons: {
    dashboard: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>',
    pos: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h2l2.6 12.6a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 2-1.6L21 7H6"/></svg>',
    ops: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
    owner: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V9l7-5 7 5v12"/><path d="M10 21v-6h4v6"/></svg>'
  },

  buildNav() {
    const nav = document.getElementById('sideNav');
    const items = this.navDefs.filter(n => n.roles.includes(this.currentUser.role));
    nav.innerHTML = items.map((n, i) => `
      <button class="${i === 0 ? 'active' : ''}" onclick="App.showView('${n.id}', this)">
        ${this.navIcons[n.id]}${n.label}
      </button>
    `).join('');

    this.renderDashboardTiles(items);
    this.showView(items[0].id, nav.children[0]);
  },

  renderDashboardTiles(items) {
    const tiles = items.filter(n => n.id !== 'dashboard');
    document.getElementById('tileGrid').innerHTML = tiles.map(n => `
      <button class="tile" onclick="App.goTo('${n.id}')">
        <div class="tile-icon">${this.navIcons[n.id]}</div>
        <h3>${n.label}</h3>
        <p>${n.blurb}</p>
        <div class="tile-go">Open →</div>
      </button>
    `).join('');
  },

  goTo(id) {
    const btn = Array.from(document.querySelectorAll('.sidebar nav button'))
      .find(b => b.textContent.trim().includes(this.navDefs.find(n => n.id === id).label));
    this.showView(id, btn);
  },

  showView(id, btn) {
    document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
    const target = document.getElementById(id);
    if (target) target.classList.add('active');

    document.querySelectorAll('.sidebar nav button').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    // Close mobile drawer after navigation
    this.toggleMobileSidebar(false);

    const titles = { dashboard: 'Dashboard Overview', pos: 'Point of Sale / Checkout', ops: 'Shop Operations & Stock', owner: 'Owner Multi-Branch Console' };
    document.getElementById('pageTitle').textContent = titles[id] || 'SpareStack';

    // Trigger module loads
    if (id === 'pos' && typeof POS !== 'undefined') POS.init();
    if (id === 'ops' && typeof Inventory !== 'undefined') { Inventory.load(); Ops.load(); }
    if (id === 'owner' && typeof Owner !== 'undefined') Owner.load();
    if (id === 'dashboard') this.loadDashboardKPIs();
  },

  async loadDashboardKPIs() {
    try {
      const res = await this.api('api/analytics.php');
      if (res.success) {
        document.getElementById('dashSalesToday').textContent = 'GHS ' + Number(res.kpis.sales_today).toLocaleString('en-US', { minimumFractionDigits: 2 });
        document.getElementById('dashStaffCount').textContent = `${res.kpis.online_staff} of ${res.kpis.total_staff}`;
        document.getElementById('dashLowStock').textContent = res.kpis.low_stock_count;
        document.getElementById('dashBranches').textContent = res.kpis.total_branches;
      }
    } catch (e) {}
  },

  initTheme() {
    const saved = localStorage.getItem('torque_theme');
    let theme = saved;

    // If no manual preference saved, automatically use system OS dark/light mode
    if (!theme) {
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      theme = prefersDark ? 'dark' : 'light';
    }

    document.documentElement.setAttribute('data-theme', theme);
    this.updateThemeIcons(theme);

    // Listen for OS system theme changes in real time
    if (window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        // Only auto-switch if user hasn't explicitly locked preference
        if (!localStorage.getItem('torque_theme')) {
          const sysTheme = e.matches ? 'dark' : 'light';
          document.documentElement.setAttribute('data-theme', sysTheme);
          App.updateThemeIcons(sysTheme);
        }
      });
    }
  },

  toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('torque_theme', next);
    this.updateThemeIcons(next);
  },

  updateThemeIcons(theme) {
    const light = document.getElementById('lightOpt');
    const dark = document.getElementById('darkOpt');
    if (light && dark) {
      light.classList.toggle('sel', theme === 'light');
      dark.classList.toggle('sel', theme === 'dark');
    }
  },

  async doLogin() {
    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value.trim();
    if (!email || !password) {
      document.getElementById('loginErr').style.display = 'block';
      return;
    }
    document.getElementById('loginErr').style.display = 'none';

    try {
      const res = await this.api('api/auth.php?action=login', 'POST', { email, password });
      if (res.success) {
        this.currentUser = res.user;
        this.enterApp();
      }
    } catch (e) {}
  },

  async doSignup() {
    const orgName = document.getElementById('signupOrg').value.trim();
    const name = document.getElementById('signupName').value.trim();
    const email = document.getElementById('signupEmail').value.trim();
    const password = document.getElementById('signupPassword').value.trim();
    const role = window.signupRole || 'Owner';

    if (!orgName || !name || !email || !password) {
      document.getElementById('signupErr').style.display = 'block';
      return;
    }
    document.getElementById('signupErr').style.display = 'none';

    try {
      const res = await this.api('api/auth.php?action=register', 'POST', { orgName, name, email, password, role });
      if (res.success) {
        this.currentUser = res.user;
        this.enterApp();
      }
    } catch (e) {}
  },

  async doLogout() {
    try {
      await this.api('api/auth.php?action=logout', 'GET');
    } catch (e) {}
    this.currentUser = null;
    this.showAuth();
  }
};

document.addEventListener('DOMContentLoaded', () => { App.init(); });
