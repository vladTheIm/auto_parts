/**
 * Torque Auto Parts OS - Owner Dashboard, Multi-Branch & Settings Module
 */

const Owner = {
  branches: [],
  customers: [],

  async load() {
    await this.loadBranches();
    await this.loadCustomers();
    await this.loadSettings();
  },

  async loadBranches() {
    try {
      const res = await App.api('api/branches.php');
      if (res.success) {
        this.branches = res.branches;
        this.renderBranches();
      }
    } catch (e) {}
  },

  renderBranches() {
    const list = document.getElementById('ownerBranchList');
    if (!list) return;

    list.innerHTML = this.branches.map((b, i) => {
      const activeStaff = b.staff.filter(s => s.is_online == 1).length;
      const totalStaff = b.staff.length;

      return `
        <div class="branch ${i === 0 ? 'open' : ''}" id="ownerBranch-${b.id}" style="background:var(--panel); border:1px solid var(--line); border-radius:14px; margin-bottom:14px; overflow:hidden;">
          <div class="branch-top" style="display:flex; align-items:center; padding:14px 18px; cursor:pointer; gap:12px;" onclick="Owner.toggleBranch(${b.id})">
            <span class="dot ${b.is_active ? '' : 'idle'}"></span>
            <div>
              <div style="font-weight:700; font-size:15.5px;">${b.name}</div>
              <div style="font-size:12px; color:var(--ink-soft);">${b.location}</div>
            </div>
            <div style="display:flex; gap:24px; margin-left:auto; text-align:right;">
              <div>
                <div class="mono" style="font-weight:700; font-size:14.5px;">${activeStaff}/${totalStaff}</div>
                <div style="font-size:10.5px; color:var(--ink-faint); text-transform:uppercase;">On Shift</div>
              </div>
              <div>
                <div class="mono" style="font-weight:700; font-size:14.5px;">GHS ${Number(b.sales_today).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                <div style="font-size:10.5px; color:var(--ink-faint); text-transform:uppercase;">Sales Today</div>
              </div>
            </div>
          </div>
          <div class="branch-body" style="padding:0 18px 18px; border-top:1px solid var(--line);">
            <div style="font-size:12.5px; font-weight:600; color:var(--ink-soft); margin:12px 0 6px;">Staff Team Members:</div>
            ${b.staff.map(s => `
              <div style="display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid var(--line); font-size:13px;">
                <div class="avatar" style="width:26px; height:26px; border-radius:50%; background:var(--accent-tint); color:var(--accent-ink); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700;">
                  ${App.initials(s.name)}
                </div>
                <span>${s.name}</span>
                <span style="font-size:11px; margin-left:auto; color:${s.is_online ? 'var(--lime-ink)' : 'var(--ink-faint)'};">● ${s.is_online ? 'Active Now' : 'Off Shift'}</span>
                <span class="status-pill" style="background:var(--panel-raised); font-size:10.5px; margin-left:8px;">${s.role}</span>
              </div>
            `).join('')}
            <div style="display:flex; gap:8px; margin-top:14px;">
              <input type="text" placeholder="New staff full name" id="staffNameInput-${b.id}" style="flex:1; padding:8px 10px; border:1px solid var(--line); border-radius:8px; font-size:12.5px; background:var(--panel); color:var(--ink);">
              <select id="staffRoleSelect-${b.id}" style="padding:8px 10px; border:1px solid var(--line); border-radius:8px; font-size:12.5px; background:var(--panel); color:var(--ink);">
                <option value="Cashier">Cashier</option>
                <option value="Manager">Manager</option>
              </select>
              <button class="btn-dark" onclick="Owner.addStaffToBranch(${b.id})">+ Add Staff</button>
            </div>
          </div>
        </div>
      `;
    }).join('');
  },

  toggleBranch(id) {
    const el = document.getElementById('ownerBranch-' + id);
    if (el) el.classList.toggle('open');
  },

  openBranchModal() {
    document.getElementById('branchModal').classList.add('show');
  },

  closeBranchModal() {
    document.getElementById('branchModal').classList.remove('show');
  },

  async createBranch() {
    const name = document.getElementById('newBranchName').value.trim();
    const location = document.getElementById('newBranchLoc').value.trim();
    const phone = document.getElementById('newBranchPhone').value.trim();

    if (!name || !location) {
      App.toast('Branch name and location required', 'error');
      return;
    }

    try {
      const res = await App.api('api/branches.php?action=create_branch', 'POST', { name, location, phone });
      if (res.success) {
        App.toast('New branch location activated!', 'success');
        this.closeBranchModal();
        this.loadBranches();
        App.loadBranches();
      }
    } catch (e) {}
  },

  async addStaffToBranch(branchId) {
    const input = document.getElementById('staffNameInput-' + branchId);
    const role = document.getElementById('staffRoleSelect-' + branchId).value;
    const name = input.value.trim();

    if (!name) {
      App.toast('Enter staff name', 'error');
      return;
    }

    try {
      const res = await App.api('api/branches.php?action=add_staff', 'POST', {
        branch_id: branchId, name, role
      });
      if (res.success) {
        App.toast('Staff member registered!', 'success');
        input.value = '';
        this.loadBranches();
      }
    } catch (e) {}
  },

  // ===== CUSTOMERS / GARAGE LEDGER =====
  async loadCustomers() {
    try {
      const res = await App.api('api/customers.php');
      if (res.success) {
        this.customers = res.customers;
        this.renderCustomers();
      }
    } catch (e) {}
  },

  renderCustomers() {
    const tbody = document.getElementById('customerTableBody');
    if (!tbody) return;

    tbody.innerHTML = this.customers.map(c => `
      <tr>
        <td><strong>${c.name}</strong></td>
        <td>${c.workshop_name || 'Individual Garage'}</td>
        <td class="mono">${c.phone}</td>
        <td><strong class="mono" style="color:${c.credit_balance > 0 ? 'var(--coral-ink)' : 'var(--lime-ink)'};">GHS ${Number(c.credit_balance).toFixed(2)}</strong></td>
        <td class="mono">GHS ${Number(c.credit_limit).toFixed(2)}</td>
        <td>
          ${c.credit_balance > 0 ? `<button class="btn-primary" style="font-size:11px; padding:4px 10px;" onclick="Owner.recordCreditPayment(${c.id}, '${c.name}', ${c.credit_balance})">Collect Payment</button>` : '<span style="color:var(--lime-ink); font-size:12px; font-weight:600;">Settled</span>'}
        </td>
      </tr>
    `).join('');
  },

  openCustomerModal() {
    document.getElementById('customerModal').classList.add('show');
  },

  closeCustomerModal() {
    document.getElementById('customerModal').classList.remove('show');
  },

  async createCustomer() {
    const name = document.getElementById('custName').value.trim();
    const phone = document.getElementById('custPhone').value.trim();
    const workshop_name = document.getElementById('custWorkshop').value.trim();
    const credit_limit = parseFloat(document.getElementById('custLimit').value) || 2000;

    if (!name || !phone) {
      App.toast('Customer name and phone required', 'error');
      return;
    }

    try {
      const res = await App.api('api/customers.php?action=create', 'POST', {
        name, phone, workshop_name, credit_limit
      });
      if (res.success) {
        App.toast('Workshop customer registered!', 'success');
        this.closeCustomerModal();
        this.loadCustomers();
      }
    } catch (e) {}
  },

  async recordCreditPayment(customerId, customerName, currentBalance) {
    const amountStr = prompt(`Enter amount paid by ${customerName} (Current Balance: GHS ${currentBalance}):`, currentBalance);
    if (!amountStr) return;
    const amount = parseFloat(amountStr);
    if (isNaN(amount) || amount <= 0) return;

    try {
      const res = await App.api('api/customers.php?action=pay_credit', 'POST', { customer_id: customerId, amount });
      if (res.success) {
        App.toast('Credit payment recorded!', 'success');
        this.loadCustomers();
      }
    } catch (e) {}
  },

  // ===== SETTINGS =====
  async loadSettings() {
    try {
      const res = await App.api('api/settings.php');
      if (res.success) {
        const s = res.settings;
        if (document.getElementById('setShopName')) document.getElementById('setShopName').value = s.dealership_name || '';
        if (document.getElementById('setTagline')) document.getElementById('setTagline').value = s.dealership_tagline || '';
        if (document.getElementById('setPhone')) document.getElementById('setPhone').value = s.phone || '';
        if (document.getElementById('setAddress')) document.getElementById('setAddress').value = s.address || '';
        if (document.getElementById('setCurrency')) document.getElementById('setCurrency').value = s.currency_symbol || 'GHS';
        if (document.getElementById('setVatRate')) document.getElementById('setVatRate').value = s.vat_rate || '15.00';
      }
    } catch (e) {}
  },

  async saveSettings() {
    const payload = {
      dealership_name: document.getElementById('setShopName').value.trim(),
      dealership_tagline: document.getElementById('setTagline').value.trim(),
      phone: document.getElementById('setPhone').value.trim(),
      address: document.getElementById('setAddress').value.trim(),
      currency_symbol: document.getElementById('setCurrency').value,
      vat_rate: document.getElementById('setVatRate').value
    };

    try {
      const res = await App.api('api/settings.php', 'POST', payload);
      if (res.success) {
        App.toast('Business settings updated!', 'success');
      }
    } catch (e) {}
  }
};
