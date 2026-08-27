/**
 * SpareStack Auto Parts OS - Operations (Shifts, Cash Reconciliation & Purchase Orders)
 */

const Ops = {
  activeShift: null,
  purchaseOrders: [],
  suppliers: [],

  async load() {
    await this.loadShiftStatus();
    await this.loadSuppliers();
    await this.loadPurchaseOrders();
  },

  async loadShiftStatus() {
    try {
      const res = await App.api('api/shifts.php?action=current');
      if (res.success) {
        this.activeShift = res.shift;
        this.renderShiftCard();
      }
    } catch (e) {}
  },

  renderShiftCard() {
    const dot = document.getElementById('shiftDot');
    const who = document.getElementById('opsWho');
    const since = document.getElementById('shiftSince');
    const floatEl = document.getElementById('shiftFloatAmount');
    const btn = document.getElementById('clockBtn');

    if (this.activeShift) {
      if (dot) dot.className = 'dot';
      if (who) who.textContent = `${App.currentUser.name} · ${App.currentUser.role}`;
      if (since) since.textContent = `Clocked in at ${this.activeShift.opened_at}`;
      if (floatEl) floatEl.textContent = `GHS ${Number(this.activeShift.opening_float).toFixed(2)}`;
      if (btn) {
        btn.textContent = 'Reconcile & Clock Out';
        btn.className = 'btn-dark';
      }
    } else {
      if (dot) dot.className = 'dot idle';
      if (who) who.textContent = `${App.currentUser.name} (Off Duty)`;
      if (since) since.textContent = 'No active shift open';
      if (floatEl) floatEl.textContent = 'GHS 0.00';
      if (btn) {
        btn.textContent = 'Clock In / Open Float';
        btn.className = 'btn-primary';
      }
    }
  },

  toggleClock() {
    if (this.activeShift) {
      this.openClockOutModal();
    } else {
      this.openClockInModal();
    }
  },

  openClockInModal() {
    document.getElementById('clockInFloat').value = '300.00';
    document.getElementById('clockInModal').classList.add('show');
  },

  closeClockInModal() {
    document.getElementById('clockInModal').classList.remove('show');
  },

  async confirmClockIn() {
    const opening_float = parseFloat(document.getElementById('clockInFloat').value) || 0;
    try {
      const res = await App.api('api/shifts.php?action=clock_in', 'POST', { opening_float });
      if (res.success) {
        App.toast('Shift opened successfully with cash float!', 'success');
        this.closeClockInModal();
        this.loadShiftStatus();
      }
    } catch (e) {}
  },

  openClockOutModal() {
    if (!this.activeShift) return;
    const exp = this.activeShift.expected_cash_drawer || 0;
    document.getElementById('reconcileExpected').textContent = 'GHS ' + Number(exp).toFixed(2);
    document.getElementById('reconcileFloat').textContent = 'GHS ' + Number(this.activeShift.opening_float).toFixed(2);
    document.getElementById('reconcileCashSales').textContent = 'GHS ' + Number(this.activeShift.sales_summary?.cash_sales || 0).toFixed(2);
    document.getElementById('countedCashInput').value = exp.toFixed(2);
    this.updateVarianceDisplay();
    document.getElementById('clockOutModal').classList.add('show');
  },

  closeClockOutModal() {
    document.getElementById('clockOutModal').classList.remove('show');
  },

  updateVarianceDisplay() {
    const counted = parseFloat(document.getElementById('countedCashInput').value) || 0;
    const exp = this.activeShift ? this.activeShift.expected_cash_drawer : 0;
    const diff = counted - exp;
    const varEl = document.getElementById('reconcileVariance');
    if (varEl) {
      varEl.textContent = (diff >= 0 ? '+GHS ' : '-GHS ') + Math.abs(diff).toFixed(2);
      varEl.style.color = diff === 0 ? 'var(--lime-ink)' : (diff > 0 ? 'var(--accent)' : 'var(--coral)');
    }
  },

  async confirmClockOut() {
    const cash_counted = parseFloat(document.getElementById('countedCashInput').value) || 0;
    try {
      const res = await App.api('api/shifts.php?action=clock_out', 'POST', { cash_counted });
      if (res.success) {
        App.toast('Shift closed and cash drawer settled!', 'success');
        this.closeClockOutModal();
        this.loadShiftStatus();
      }
    } catch (e) {}
  },

  // ===== PURCHASE ORDERS =====
  async loadSuppliers() {
    try {
      const res = await App.api('api/purchase_orders.php?action=suppliers');
      if (res.success) {
        this.suppliers = res.suppliers;
        const sel = document.getElementById('poSupplierSelect');
        if (sel) {
          sel.innerHTML = this.suppliers.map(s => `<option value="${s.id}">${s.name} (${s.address})</option>`).join('');
        }
      }
    } catch (e) {}
  },

  async loadPurchaseOrders() {
    try {
      const res = await App.api(`api/purchase_orders.php?branch_id=${App.currentBranchId}`);
      if (res.success) {
        this.purchaseOrders = res.purchase_orders;
        this.renderPurchaseOrders();
      }
    } catch (e) {}
  },

  renderPurchaseOrders() {
    const wrap = document.getElementById('poList');
    if (!wrap) return;

    if (this.purchaseOrders.length === 0) {
      wrap.innerHTML = '<div style="text-align:center; padding:20px; color:var(--ink-faint);">No purchase orders created yet.</div>';
      return;
    }

    wrap.innerHTML = this.purchaseOrders.map(po => `
      <div style="display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--line);">
        <div>
          <strong>${po.product_name}</strong> &times; <span class="mono">${po.quantity} units</span>
          <div style="font-size:11.5px; color:var(--ink-soft);">${po.supplier_name} · ${po.po_number}</div>
        </div>
        <div style="margin-left:auto; display:flex; align-items:center; gap:12px;">
          <span class="status-pill ${po.status === 'Received' ? 'received' : 'ordered'}">${po.status}</span>
          ${po.status === 'Ordered' ? `<button class="btn-dark" style="font-size:11px; padding:4px 10px;" onclick="Ops.receivePO(${po.id})">Receive & Restock</button>` : ''}
        </div>
      </div>
    `).join('');
  },

  openPOModal() {
    // Populate products
    const prodSel = document.getElementById('poProductSelect');
    if (prodSel && Inventory.products) {
      prodSel.innerHTML = Inventory.products.map(p => `<option value="${p.id}">${p.name} (SKU: ${p.sku})</option>`).join('');
    }
    document.getElementById('poModal').classList.add('show');
  },

  closePOModal() {
    document.getElementById('poModal').classList.remove('show');
  },

  async createPO() {
    const supplier_id = document.getElementById('poSupplierSelect').value;
    const product_id = document.getElementById('poProductSelect').value;
    const quantity = parseInt(document.getElementById('poQtyInput').value) || 10;

    try {
      const res = await App.api('api/purchase_orders.php?action=create', 'POST', {
        supplier_id, product_id, quantity, branch_id: App.currentBranchId
      });
      if (res.success) {
        App.toast(`PO ${res.po_number} issued to supplier!`, 'success');
        this.closePOModal();
        this.loadPurchaseOrders();
      }
    } catch (e) {}
  },

  async receivePO(poId) {
    try {
      const res = await App.api('api/purchase_orders.php?action=receive', 'POST', { po_id: poId });
      if (res.success) {
        App.toast('PO items received and added to inventory!', 'success');
        this.loadPurchaseOrders();
        Inventory.loadInventoryTable();
      }
    } catch (e) {}
  }
};
