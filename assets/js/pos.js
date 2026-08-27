/**
 * Torque Auto Parts OS - Point of Sale (POS) Engine
 * Extended with: Wholesale/Retail Pricing, Proforma Quotations & WhatsApp Receipt Sharing
 */

const POS = {
  products: [],
  categories: [],
  cart: [],
  selectedPayment: 'Cash',
  currentCategory: 'all',
  currentVehicle: 'all',
  customers: [],
  isWholesale: false,
  lastReceiptData: null,

  init() {
    this.loadCategories();
    this.loadProducts();
    this.loadCustomers();
    this.setupListeners();
  },

  setupListeners() {
    const searchInput = document.getElementById('posSearch');
    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        this.filterProducts(e.target.value);
      });
    }

    document.querySelectorAll('#payMethods button').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('#payMethods button').forEach(b => b.classList.remove('sel'));
        btn.classList.add('sel');
        this.selectedPayment = btn.dataset.m;
        this.updateCartTotals();
      });
    });
  },

  toggleWholesale(enabled) {
    this.isWholesale = enabled;
    // Update cart item pricing based on wholesale mode
    this.cart.forEach(item => {
      const prod = this.products.find(p => p.id == item.id);
      if (prod) {
        item.price = this.isWholesale ? (parseFloat(prod.selling_price) * 0.85) : parseFloat(prod.selling_price);
      }
    });
    this.renderCart();
    App.toast(this.isWholesale ? 'Wholesale/Mechanic Pricing Activated (15% Off)' : 'Standard Retail Pricing Active', 'info');
  },

  async loadCategories() {
    try {
      const res = await App.api('api/products.php?action=categories');
      if (res.success) {
        this.categories = res.categories;
        this.renderCategories();
      }
    } catch (e) {}
  },

  renderCategories() {
    const wrap = document.getElementById('cats');
    if (!wrap) return;
    const catsHtml = ['<span class="active" onclick="POS.selectCategory(\'all\', this)">All Parts</span>']
      .concat(this.categories.map(c => 
        `<span onclick="POS.selectCategory('${c.slug}', this)">${c.name}</span>`
      )).join('');
    wrap.innerHTML = catsHtml;
  },

  selectCategory(slug, el) {
    this.currentCategory = slug;
    document.querySelectorAll('#cats span').forEach(s => s.classList.remove('active'));
    if (el) el.classList.add('active');
    this.loadProducts();
  },

  selectVehicle(make, el) {
    this.currentVehicle = make;
    document.querySelectorAll('#fitmentBar .fitment-tag').forEach(s => s.classList.remove('active'));
    if (el) el.classList.add('active');
    const searchInput = document.getElementById('posSearch');
    if (make === 'all') {
      if (searchInput) searchInput.value = '';
      this.loadProducts();
    } else {
      if (searchInput) searchInput.value = make;
      this.filterProducts(make);
    }
  },

  async loadCustomers() {
    try {
      const res = await App.api('api/customers.php');
      if (res.success) {
        this.customers = res.customers;
        const sel = document.getElementById('posCustomerSelect');
        if (sel) {
          sel.innerHTML = '<option value="">Walk-in Customer (Standard Retail)</option>' + 
            this.customers.map(c => `<option value="${c.id}">${c.name} (${c.workshop_name || 'Individual'}) · Bal: GHS ${c.credit_balance}</option>`).join('');
        }
      }
    } catch (e) {}
  },

  async loadProducts() {
    try {
      const branchId = App.currentBranchId;
      const catQuery = this.currentCategory !== 'all' ? `&category=${this.currentCategory}` : '';
      const res = await App.api(`api/products.php?branch_id=${branchId}${catQuery}`);
      if (res.success) {
        this.products = res.products;
        this.renderProducts(this.products);
      }
    } catch (e) {}
  },

  filterProducts(query) {
    const q = query.toLowerCase().trim();
    if (!q) {
      this.renderProducts(this.products);
      return;
    }
    const filtered = this.products.filter(p => 
      p.name.toLowerCase().includes(q) ||
      p.sku.toLowerCase().includes(q) ||
      (p.oem_number && p.oem_number.toLowerCase().includes(q)) ||
      (p.fits_vehicles && p.fits_vehicles.toLowerCase().includes(q)) ||
      (p.barcode && p.barcode.includes(q))
    );
    this.renderProducts(filtered);
  },

  renderProducts(list) {
    const grid = document.getElementById('productGrid');
    if (!grid) return;
    if (list.length === 0) {
      grid.innerHTML = '<div style="grid-column: 1/-1; padding: 40px; text-align: center; color: var(--ink-faint);">No automotive parts found matching search criteria.</div>';
      return;
    }

    grid.innerHTML = list.map((p) => {
      const stock = parseInt(p.branch_stock ?? p.stock_quantity);
      const isLow = stock <= parseInt(p.branch_reorder ?? p.reorder_level);
      const price = this.isWholesale ? (parseFloat(p.selling_price) * 0.85) : parseFloat(p.selling_price);

      const thumbHtml = p.image_url 
        ? `<img class="thumb" src="${p.image_url}" alt="${p.name}">`
        : `<div class="thumb-placeholder"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M21 16l-4.5-4.5a1.5 1.5 0 0 0-2 0L9 17"/></svg><span>Photo coming soon</span></div>`;

      return `
        <div class="product-card">
          <span class="stockbadge ${isLow ? 'low' : 'ok'}">${stock <= 0 ? 'Out of Stock' : (isLow ? stock + ' left' : stock + ' in stock')}</span>
          ${thumbHtml}
          <div class="p-name">${p.name}</div>
          <div class="p-sku mono">SKU: ${p.sku} ${p.oem_number ? '· OEM: ' + p.oem_number : ''}</div>
          <div class="p-fits">${p.fits_vehicles || 'Universal Fitment'}</div>
          <div class="p-row">
            <div>
              <span class="p-price">GHS ${price.toFixed(2)}</span>
              ${this.isWholesale ? `<span style="font-size:10px; color:var(--lime-ink); display:block;">Wholesale</span>` : ''}
            </div>
            <button class="btn-add-cart" onclick="POS.addToCart(${p.id})" title="Add to sale">+</button>
          </div>
        </div>
      `;
    }).join('');
  },

  addToCart(productId) {
    const prod = this.products.find(p => p.id == productId);
    if (!prod) return;

    const unitPrice = this.isWholesale ? (parseFloat(prod.selling_price) * 0.85) : parseFloat(prod.selling_price);
    const existing = this.cart.find(c => c.id == productId);
    if (existing) {
      existing.qty += 1;
    } else {
      this.cart.push({
        id: prod.id,
        name: prod.name,
        sku: prod.sku,
        price: unitPrice,
        cost: parseFloat(prod.cost_price),
        qty: 1,
        image_url: prod.image_url
      });
    }
    this.renderCart();
  },

  updateQty(productId, delta) {
    const item = this.cart.find(c => c.id == productId);
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) {
      this.cart = this.cart.filter(c => c.id != productId);
    }
    this.renderCart();
  },

  removeFromCart(productId) {
    this.cart = this.cart.filter(c => c.id != productId);
    this.renderCart();
  },

  renderCart() {
    const wrap = document.getElementById('cartItems');
    const totalCount = this.cart.reduce((sum, c) => sum + c.qty, 0);
    document.getElementById('itemCount').textContent = `${totalCount} item${totalCount !== 1 ? 's' : ''}`;

    if (this.cart.length === 0) {
      wrap.innerHTML = '<div class="empty">Scan barcode or tap a part to add to sale.</div>';
    } else {
      wrap.innerHTML = this.cart.map(c => `
        <div class="cart-line">
          <div class="linfo">
            <span class="txt">${c.name}</span>
          </div>
          <div class="qty-controls">
            <button class="qty-btn" onclick="POS.updateQty(${c.id}, -1)">-</button>
            <span class="mono" style="font-size:12px; font-weight:700; min-width:14px; text-align:center;">${c.qty}</span>
            <button class="qty-btn" onclick="POS.updateQty(${c.id}, 1)">+</button>
          </div>
          <span class="mono" style="font-weight:600; margin-left:auto;">GHS ${(c.price * c.qty).toFixed(2)}</span>
          <span class="rm" onclick="POS.removeFromCart(${c.id})" title="Remove item">✕</span>
        </div>
      `).join('');
    }

    this.updateCartTotals();
  },

  updateCartTotals() {
    const sub = this.cart.reduce((sum, c) => sum + (c.price * c.qty), 0);
    const vatRate = 0.15;
    const vat = sub * vatRate;
    const total = sub + vat;

    document.getElementById('tSub').textContent = 'GHS ' + sub.toFixed(2);
    document.getElementById('tVat').textContent = 'GHS ' + vat.toFixed(2);
    document.getElementById('tTotal').textContent = 'GHS ' + total.toFixed(2);

    const btn = document.getElementById('completeBtn');
    if (this.cart.length === 0) {
      btn.disabled = true;
      btn.textContent = 'Add items to continue';
    } else {
      btn.disabled = false;
      btn.textContent = `Complete Sale (${this.selectedPayment}) · GHS ${total.toFixed(2)}`;
    }
  },

  async executeCheckout() {
    if (this.cart.length === 0) return;

    const customerId = document.getElementById('posCustomerSelect')?.value || null;
    const payload = {
      items: this.cart,
      payment_method: this.selectedPayment,
      branch_id: App.currentBranchId,
      customer_id: customerId
    };

    try {
      const res = await App.api('api/sales.php?action=checkout', 'POST', payload);
      if (res.success) {
        this.lastReceiptData = res;
        App.toast(`Sale ${res.invoice_number} recorded successfully!`, 'success');
        this.showThermalReceipt(res);
        this.cart = [];
        this.renderCart();
        this.loadProducts(); // refresh stock numbers
      }
    } catch (e) {}
  },

  generateQuotation() {
    if (this.cart.length === 0) {
      App.toast('Add parts to the sale first to generate a quote', 'error');
      return;
    }
    const sub = this.cart.reduce((sum, c) => sum + (c.price * c.qty), 0);
    const vat = sub * 0.15;
    const quoteNo = 'QT-' + new Date().toISOString().slice(2, 10).replace(/-/g, '') + '-' + Math.floor(1000 + Math.random() * 9000);

    const quoteData = {
      is_quote: true,
      invoice_number: quoteNo,
      date: new Date().toLocaleDateString() + ' ' + new Date().toLocaleTimeString(),
      cashier: App.currentUser.name,
      payment_method: 'Proforma Quotation (Valid 14 Days)',
      subtotal: sub,
      vat_amount: vat,
      grand_total: sub + vat,
      items: this.cart.map(c => ({ product_name: c.name, quantity: c.qty, total_price: c.price * c.qty })),
      dealership: {
        dealership_name: 'Torque Auto Parts OS',
        dealership_tagline: 'OFFICIAL PROFORMA QUOTATION',
        address: 'Plot 14 Harper Road, Adum, Kumasi',
        phone: '+233 32 202 4491',
        receipt_footer: 'Official parts estimate. Valid for 14 calendar days from date of issue.'
      }
    };

    this.lastReceiptData = quoteData;
    this.showThermalReceipt(quoteData);
    App.toast(`Proforma Quote ${quoteNo} generated!`, 'success');
  },

  showThermalReceipt(data) {
    this.lastReceiptData = data;
    const modal = document.getElementById('receiptModal');
    const container = document.getElementById('receiptContent');
    if (!modal || !container) return;

    const isQuote = data.is_quote;
    const itemsHtml = data.items.map(it => `
      <div class="t-row">
        <span>${it.product_name} x${it.quantity}</span>
        <span>GHS ${Number(it.total_price).toFixed(2)}</span>
      </div>
    `).join('');

    container.innerHTML = `
      <div class="thermal-receipt" id="printArea">
        <div class="t-center">
          <strong style="font-size:14px;">${data.dealership.dealership_name}</strong><br>
          <span style="font-weight:600; color:var(--accent);">${data.dealership.dealership_tagline}</span><br>
          <span>${data.dealership.address}</span><br>
          <span>Tel: ${data.dealership.phone}</span>
        </div>
        <div class="t-line"></div>
        <div class="t-row"><span>${isQuote ? 'Quote #:' : 'Invoice #:'}</span><strong>${data.invoice_number}</strong></div>
        <div class="t-row"><span>Date:</span><span>${data.date}</span></div>
        <div class="t-row"><span>Staff:</span><span>${data.cashier}</span></div>
        <div class="t-row"><span>Status:</span><strong>${data.payment_method}</strong></div>
        <div class="t-line"></div>
        ${itemsHtml}
        <div class="t-line"></div>
        <div class="t-row"><span>Subtotal:</span><span>GHS ${Number(data.subtotal).toFixed(2)}</span></div>
        <div class="t-row"><span>VAT (15%):</span><span>GHS ${Number(data.vat_amount).toFixed(2)}</span></div>
        <div class="t-row" style="font-size:13px; font-weight:bold; margin-top:4px;">
          <span>TOTAL:</span><span>GHS ${Number(data.grand_total).toFixed(2)}</span>
        </div>
        <div class="t-line"></div>
        <div class="t-center" style="font-size:10px; color:#333; margin-top:6px;">
          ${data.dealership.receipt_footer}
        </div>
      </div>
    `;

    modal.classList.add('show');
  },

  shareWhatsAppReceipt() {
    if (!this.lastReceiptData) return;
    const d = this.lastReceiptData;
    const itemsText = d.items.map(it => `• ${it.product_name} (x${it.quantity}) - GHS ${Number(it.total_price).toFixed(2)}`).join('%0A');
    
    const message = `*${encodeURIComponent(d.dealership.dealership_name)}*%0A` +
      `*Receipt / Quote #:* ${d.invoice_number}%0A` +
      `*Date:* ${d.date}%0A%0A` +
      `*Items:*%0A${itemsText}%0A%0A` +
      `*Total Amount:* GHS ${Number(d.grand_total).toFixed(2)}%0A` +
      `*Payment:* ${d.payment_method}%0A%0A` +
      `_Thank you for choosing Torque Auto Parts!_`;

    const phone = prompt('Enter customer WhatsApp Phone Number (with country code, e.g. 233244123456) or leave blank to choose contact:', '');
    const url = phone ? `https://wa.me/${phone.replace(/[^0-9]/g, '')}?text=${message}` : `https://wa.me/?text=${message}`;
    window.open(url, '_blank');
  },

  printReceipt() {
    window.print();
  },

  closeReceiptModal() {
    document.getElementById('receiptModal').classList.remove('show');
  },

  simulateBarcodeScan() {
    if (this.products.length > 0) {
      const p = this.products[Math.floor(Math.random() * this.products.length)];
      App.toast(`Barcode Scanned: [${p.barcode || p.sku}] ${p.name}`, 'info');
      this.addToCart(p.id);
    }
  }
};
