/**
 * SpareStack Auto Parts OS - Inventory & Stock Management
 * Extended with: Stock Moves Between Branches & Refunds
 */

const Inventory = {
  products: [],
  selectedProductId: null,

  async load() {
    await this.loadInventoryTable();
  },

  async loadInventoryTable() {
    try {
      const branchId = App.currentBranchId;
      const res = await App.api(`api/products.php?branch_id=${branchId}`);
      if (res.success) {
        this.products = res.products;
        this.renderTable(this.products);
      }
    } catch (e) {}
  },

  renderTable(list) {
    const tbody = document.getElementById('invtBody');
    if (!tbody) return;

    if (list.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--ink-faint);">No parts in stock yet.</td></tr>';
      return;
    }

    tbody.innerHTML = list.map(p => {
      const stock = parseInt(p.branch_stock ?? p.stock_quantity);
      const reorder = parseInt(p.branch_reorder ?? p.reorder_level);
      const isLow = stock <= reorder;

      const photoCell = p.image_url
        ? `<img src="${p.image_url}" style="width:36px; height:36px; border-radius:7px; object-fit:cover;">`
        : `<label class="invt-thumb-add" title="Upload Photo" style="width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border:1px dashed var(--line); border-radius:7px; cursor:pointer; background:var(--panel-raised);">
             +<input type="file" accept="image/*" style="display:none;" onchange="Inventory.uploadProductPhoto(${p.id}, this)">
           </label>`;

      return `
        <tr>
          <td>${photoCell}</td>
          <td>
            <strong>${p.name}</strong>
            <div style="font-size:12px; color:var(--ink-soft);">${p.fits_vehicles || 'Fits all cars'}</div>
          </td>
          <td class="mono">${p.sku}</td>
          <td><strong style="font-size:14.5px;">${stock}</strong> units</td>
          <td class="mono">${reorder}</td>
          <td><span class="status-pill ${isLow ? 'low' : 'ok'}">${isLow ? 'Low on Stock' : 'In Stock'}</span></td>
          <td>
            <button class="btn-dark" style="padding:6px 10px; font-size:13px;" onclick="Inventory.openRestockModal(${p.id})">+ Add Stock</button>
            <button class="btn-dark" style="padding:6px 10px; font-size:13px; background:var(--panel-raised); color:var(--ink); border:1px solid var(--line);" onclick="Inventory.openTransferModal(${p.id})">Move</button>
            <button class="btn-dark" style="padding:6px 10px; font-size:13px; background:var(--panel-raised); color:var(--ink); border:1px solid var(--line);" onclick="Inventory.openAuditLog(${p.id})">History</button>
          </td>
        </tr>
      `;
    }).join('');
  },

  openRestockModal(productId) {
    const prod = this.products.find(p => p.id == productId);
    if (!prod) return;
    this.selectedProductId = productId;
    document.getElementById('restockPartName').textContent = `${prod.name} (SKU: ${prod.sku})`;
    document.getElementById('restockQty').value = '10';
    document.getElementById('restockModal').classList.add('show');
  },

  closeRestockModal() {
    document.getElementById('restockModal').classList.remove('show');
    this.selectedProductId = null;
  },

  async confirmRestock() {
    if (!this.selectedProductId) return;
    const qty = parseInt(document.getElementById('restockQty').value);
    const reason = document.getElementById('restockReason')?.value || 'Shipment Restock';

    if (isNaN(qty) || qty <= 0) {
      App.toast('Enter a number above 0 for the pieces added', 'error');
      return;
    }

    try {
      const res = await App.api('api/inventory.php?action=restock', 'POST', {
        product_id: this.selectedProductId,
        quantity: qty,
        reason: reason,
        branch_id: App.currentBranchId
      });
      if (res.success) {
        App.toast(res.message, 'success');
        this.closeRestockModal();
        this.loadInventoryTable();
      }
    } catch (e) {}
  },

  // ===== INTER-BRANCH STOCK TRANSFER =====
  openTransferModal(productId) {
    const prod = this.products.find(p => p.id == productId);
    if (!prod) return;
    this.selectedProductId = productId;
    document.getElementById('transferPartName').textContent = `${prod.name} (SKU: ${prod.sku})`;
    
    // Fill from and to branch selects
    const fromSel = document.getElementById('transferFromBranch');
    const toSel = document.getElementById('transferToBranch');
    if (fromSel && toSel && App.branches) {
      fromSel.innerHTML = App.branches.map(b => `<option value="${b.id}" ${b.id == App.currentBranchId ? 'selected' : ''}>${b.name}</option>`).join('');
      toSel.innerHTML = App.branches.map(b => `<option value="${b.id}" ${b.id != App.currentBranchId ? 'selected' : ''}>${b.name}</option>`).join('');
    }
    document.getElementById('transferQty').value = '5';
    document.getElementById('transferModal').classList.add('show');
  },

  closeTransferModal() {
    document.getElementById('transferModal').classList.remove('show');
    this.selectedProductId = null;
  },

  async confirmTransfer() {
    if (!this.selectedProductId) return;
    const from_branch_id = parseInt(document.getElementById('transferFromBranch').value);
    const to_branch_id = parseInt(document.getElementById('transferToBranch').value);
    const quantity = parseInt(document.getElementById('transferQty').value);

    if (isNaN(quantity) || quantity <= 0) {
      App.toast('Enter how many pieces to move', 'error');
      return;
    }
    if (from_branch_id === to_branch_id) {
      App.toast('Pick two different branches to move between', 'error');
      return;
    }

    try {
      const res = await App.api('api/inventory.php?action=transfer', 'POST', {
        product_id: this.selectedProductId,
        from_branch_id,
        to_branch_id,
        quantity
      });
      if (res.success) {
        App.toast(res.message, 'success');
        this.closeTransferModal();
        this.loadInventoryTable();
      }
    } catch (e) {}
  },

  // ===== SALES RETURNS & REFUNDS =====
  openReturnModal() {
    document.getElementById('returnInvoiceNo').value = '';
    document.getElementById('returnReason').value = 'Defective / Warranty Replacement';
    document.getElementById('returnModal').classList.add('show');
  },

  closeReturnModal() {
    document.getElementById('returnModal').classList.remove('show');
  },

  async confirmReturn() {
    const invoice_number = document.getElementById('returnInvoiceNo').value.trim();
    const reason = document.getElementById('returnReason').value.trim();

    if (!invoice_number) {
      App.toast('Enter the receipt number for the return', 'error');
      return;
    }

    try {
      const res = await App.api('api/sales.php?action=return_sale', 'POST', { invoice_number, reason });
      if (res.success) {
        App.toast(res.message, 'success');
        this.closeReturnModal();
        this.loadInventoryTable();
      }
    } catch (e) {}
  },

  async openAuditLog(productId) {
    try {
      const res = await App.api(`api/inventory.php?action=movements&product_id=${productId}&branch_id=${App.currentBranchId}`);
      if (res.success) {
        const tbody = document.getElementById('auditLogBody');
        if (tbody) {
          tbody.innerHTML = res.movements.length === 0
            ? '<tr><td colspan="5" style="text-align:center; padding:20px; color:var(--ink-faint);">No stock changes recorded for this part yet.</td></tr>'
            : res.movements.map(m => `
              <tr>
                <td>${m.created_at}</td>
                <td><strong>${m.reason}</strong><div style="font-size:11px; color:var(--ink-soft);">${m.notes || ''}</div></td>
                <td class="mono" style="color: ${m.change_qty > 0 ? 'var(--lime-ink)' : 'var(--coral-ink)'}; font-weight:bold;">
                  ${m.change_qty > 0 ? '+' + m.change_qty : m.change_qty}
                </td>
                <td class="mono">${m.previous_qty} → <strong>${m.new_qty}</strong></td>
                <td>${m.user_name || 'System'}</td>
              </tr>
            `).join('');
        }
        document.getElementById('auditModal').classList.add('show');
      }
    } catch (e) {}
  },

  closeAuditModal() {
    document.getElementById('auditModal').classList.remove('show');
  },

  openItemModal() {
    document.getElementById('newItemName').value = '';
    document.getElementById('newItemSku').value = '';
    document.getElementById('newItemCategory').value = '1';
    document.getElementById('newItemFits').value = '';
    document.getElementById('newItemCost').value = '50';
    document.getElementById('newItemPrice').value = '90';
    document.getElementById('newItemStock').value = '15';
    document.getElementById('itemModal').classList.add('show');
  },

  closeItemModal() {
    document.getElementById('itemModal').classList.remove('show');
  },

  async createNewItem() {
    const name = document.getElementById('newItemName').value.trim();
    const sku = document.getElementById('newItemSku').value.trim();
    const category_id = document.getElementById('newItemCategory').value;
    const fits_vehicles = document.getElementById('newItemFits').value.trim();
    const cost_price = parseFloat(document.getElementById('newItemCost').value) || 0;
    const selling_price = parseFloat(document.getElementById('newItemPrice').value) || 0;
    const stock_quantity = parseInt(document.getElementById('newItemStock').value) || 0;

    if (!name || !sku) {
      App.toast('Enter the part name and SKU', 'error');
      return;
    }

    try {
      const res = await App.api('api/products.php?action=create', 'POST', {
        name, sku, category_id, fits_vehicles, cost_price, selling_price, stock_quantity
      });
      if (res.success) {
        App.toast('Part added to the catalog!', 'success');
        this.closeItemModal();
        this.loadInventoryTable();
      }
    } catch (e) {}
  },

  uploadProductPhoto(productId, input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = async (e) => {
      const base64 = e.target.result;
      try {
        const res = await App.api('api/products.php?action=set_image', 'POST', {
          product_id: productId,
          image_url: base64
        });
        if (res.success) {
          App.toast('Part photo updated', 'success');
          Inventory.loadInventoryTable();
        }
      } catch (err) {}
    };
    reader.readAsDataURL(file);
  }
};
