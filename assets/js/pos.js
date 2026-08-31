/**
 * SpareStack Auto Parts OS - Point of Sale (POS) Engine
 * Extended with: Wholesale/Retail Pricing, Price Quotes & WhatsApp Receipt Sharing
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
  manualItemCounter: 0,
  changeDue: 0,
  posPage: 0,
  pickerProduct: null,
  pickerSearch: '',
  visibleList: [],

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

    const cashGivenInput = document.getElementById('cashGiven');
    if (cashGivenInput) {
      cashGivenInput.addEventListener('input', () => {
        this.updateChangeDisplay(this.cartTotal());
      });
    }
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
    App.toast(this.isWholesale ? 'Mechanic Price On (15% off)' : 'Full Price On', 'info');
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
    this.visibleList = list;
    const pager = document.getElementById('productPager');
    const pageSize = this.getPageSize();
    const showPager = list.length > pageSize;
    if (pager) pager.classList.toggle('show', showPager);
    const totalPages = Math.max(1, Math.ceil(list.length / pageSize));
    if (this.posPage > totalPages - 1) this.posPage = totalPages - 1;
    if (this.posPage < 0) this.posPage = 0;
    const pageList = showPager ? list.slice(this.posPage * pageSize, (this.posPage + 1) * pageSize) : list;

    if (pager) {
      const prevBtn = document.getElementById('pagerPrev');
      const nextBtn = document.getElementById('pagerNext');
      const status = document.getElementById('pagerStatus');
      if (prevBtn) prevBtn.disabled = this.posPage === 0;
      if (nextBtn) nextBtn.disabled = this.posPage >= totalPages - 1;
      if (status) status.textContent = `${this.posPage + 1} / ${totalPages}`;
    }

    if (list.length === 0) {
      grid.innerHTML = '<div style="grid-column: 1/-1; padding: 40px; text-align: center; color: var(--ink-faint);">No parts found. Try a different name or car model.</div>';
      return;
    }

    grid.innerHTML = pageList.map((p) => {
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
          <div class="p-sku mono">SKU: ${p.sku}</div>
          <div class="p-fits">${p.fits_vehicles || 'Fits All Cars'}</div>
          <div class="p-row">
            <div>
              <span class="p-price">GHS ${price.toFixed(2)}</span>
              ${this.isWholesale ? `<span style="font-size:10px; color:var(--lime-ink); display:block;">Mechanic Price</span>` : ''}
            </div>
            <button class="btn-add-cart" onclick="POS.addToCart(${p.id})" title="Add to sale" ${stock <= 0 ? 'disabled' : ''}>${stock <= 0 ? 'Out' : '+ Add'}</button>
          </div>
        </div>
      `;
    }).join('');
  },

  addToCart(productId, qty = 1) {
    const prod = this.products.find(p => p.id == productId);
    if (!prod) return;
    const stock = parseInt(prod.branch_stock ?? prod.stock_quantity) || 0;
    qty = Math.max(1, parseInt(qty) || 1);
    if (stock <= 0) {
      App.toast('This part is out of stock', 'error');
      return;
    }

    const unitPrice = this.isWholesale ? (parseFloat(prod.selling_price) * 0.85) : parseFloat(prod.selling_price);
    const existing = this.cart.find(c => c.id == productId);
    const currentQty = existing ? existing.qty : 0;
    const maxAdd = stock - currentQty;
    if (maxAdd <= 0) {
      App.toast(`Only ${stock} available — already all in your sale`, 'error');
      return;
    }
    qty = Math.min(qty, maxAdd);

    if (existing) {
      existing.qty += qty;
    } else {
      this.cart.push({
        id: prod.id,
        name: prod.name,
        sku: prod.sku,
        price: unitPrice,
        cost: parseFloat(prod.cost_price),
        qty: qty,
        image_url: prod.image_url
      });
    }
    this.renderCart();
  },

  updateQty(productId, delta) {
    const item = this.cart.find(c => c.id == productId);
    if (!item) return;
    const prod = this.products.find(p => p.id == item.id);
    const stock = prod ? (parseInt(prod.branch_stock ?? prod.stock_quantity) || 0) : Infinity;
    const next = item.qty + delta;
    if (next <= 0) {
      this.cart = this.cart.filter(c => c.id != productId);
    } else if (next <= stock) {
      item.qty = next;
    } else {
      App.toast(`Only ${stock} available in stock`, 'error');
    }
    this.renderCart();
  },

  removeFromCart(productId) {
    this.cart = this.cart.filter(c => c.id != productId);
    this.renderCart();
  },

  openProductPicker() {
    const modal = document.getElementById('productPickerModal');
    if (!modal) return;
    this.pickerProduct = null;
    this.pickerSearch = '';
    this.posPage = 0;
    const search = document.getElementById('pickerSearch');
    if (search) search.value = '';
    document.getElementById('pickerDetail').style.display = 'none';
    this.renderPickerList('');
    modal.classList.add('show');
    if (search) setTimeout(() => search.focus(), 60);
  },

  closeProductPicker() {
    const modal = document.getElementById('productPickerModal');
    if (modal) modal.classList.remove('show');
  },

  renderPickerList(query) {
    const list = document.getElementById('pickerList');
    if (!list) return;
    const q = (query || '').trim().toLowerCase();

    let pool = this.products;
    if (q) {
      pool = this.products.filter(p =>
        (p.name || '').toLowerCase().includes(q) ||
        (p.sku || '').toLowerCase().includes(q) ||
        (p.oem_number && p.oem_number.toLowerCase().includes(q)) ||
        (p.barcode && p.barcode.includes(q))
      );
    }

    if (pool.length === 0) {
      list.innerHTML = '<div class="empty" style="padding:18px;">No parts match — try a different name or SKU.</div>';
      return;
    }

    list.innerHTML = pool.map(p => {
      const stock = parseInt(p.branch_stock ?? p.stock_quantity) || 0;
      return `
        <div class="picker-row" data-id="${p.id}" onclick="POS.pickProduct(${p.id})">
          <div style="min-width:0; flex:1;">
            <div style="font-weight:600; font-size:13.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(p.name)}</div>
            <div style="font-size:11.5px; color:var(--ink-faint); font-family:'IBM Plex Mono',monospace;">SKU ${escapeHtml(p.sku)}</div>
          </div>
          <div class="mono" style="text-align:right; font-size:12px; color:${stock <= 0 ? 'var(--coral)' : 'var(--ink-soft)'};">
            <div style="font-weight:700;">${stock <= 0 ? 'Out of stock' : stock + ' available'}</div>
          </div>
        </div>
      `;
    }).join('');

    function escapeHtml(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
  },

  pickProduct(productId) {
    const prod = this.products.find(p => p.id == productId);
    if (!prod) return;
    this.pickerProduct = prod;
    const stock = parseInt(prod.branch_stock ?? prod.stock_quantity) || 0;
    const already = (this.cart.find(c => c.id == prod.id) || {}).qty || 0;
    const maxQty = Math.max(0, stock - already);

    document.getElementById('pickerItemName').textContent = prod.name;
    document.getElementById('pickerItemSku').textContent = 'SKU: ' + (prod.sku || '');
    document.getElementById('pickerItemAvail').textContent = maxQty;
    const priceInput = document.getElementById('pickerItemPrice');
    priceInput.value = this.isWholesale ? (parseFloat(prod.selling_price) * 0.85).toFixed(2) : parseFloat(prod.selling_price).toFixed(2);
    priceInput.max = ''; // price is not stock-capped
    const qtyInput = document.getElementById('pickerItemQty');
    qtyInput.value = '1';
    qtyInput.max = maxQty;
    qtyInput.oninput = () => {
      let v = parseInt(qtyInput.value) || 1;
      if (v > maxQty) v = maxQty;
      if (v < 1) v = 1;
      qtyInput.value = v;
    };
    document.getElementById('pickerQtyWarning').textContent = maxQty <= 0 ? 'No stock left to add' : '';
    document.getElementById('pickerPriceHint').textContent = stock <= 0 ? '' : `(${maxQty} units max)`;
    document.getElementById('pickerDetail').style.display = 'block';
  },

  pickerAdd() {
    const prod = this.pickerProduct;
    if (!prod) { App.toast('Pick a part first', 'error'); return; }

    const stock = parseInt(prod.branch_stock ?? prod.stock_quantity) || 0;
    const already = (this.cart.find(c => c.id == prod.id) || {}).qty || 0;
    const maxQty = Math.max(0, stock - already);

    let qty = parseInt(document.getElementById('pickerItemQty').value) || 1;
    if (qty > maxQty) qty = maxQty;
    if (maxQty <= 0) { App.toast('No stock available for this part', 'error'); return; }
    if (qty < 1) qty = 1;

    const price = parseFloat(document.getElementById('pickerItemPrice').value);
    if (!price || isNaN(price) || price <= 0) {
      App.toast('Enter a valid price', 'error');
      return;
    }

    // add/merge selected product as a normal catalog line respecting the (possibly edited) price
    const existing = this.cart.find(c => c.id == prod.id);
    if (existing) {
      existing.qty += qty;
    } else {
      this.cart.push({
        id: prod.id,
        name: prod.name,
        sku: prod.sku,
        price: price,
        cost: parseFloat(prod.cost_price) || 0,
        qty: qty,
        image_url: prod.image_url
      });
    }

    this.closeProductPicker();
    this.renderCart();
    App.toast('Part added to the sale (stock deducted on payment)', 'success');
  },

  getPageSize() {
    return window.innerWidth <= 768 ? 4 : 12;
  },

  pagerNext() {
    const pageSize = this.getPageSize();
    const list = this.visibleList || this.products;
    if ((this.posPage + 1) * pageSize < list.length) {
      this.posPage += 1;
      this.renderProducts(list);
    }
  },

  pagerPrev() {
    if (this.posPage > 0) {
      this.posPage -= 1;
      this.renderProducts(this.visibleList || this.products);
    }
  },

  renderCart() {
    const wrap = document.getElementById('cartItems');
    const totalCount = this.cart.reduce((sum, c) => sum + c.qty, 0);
    document.getElementById('itemCount').textContent = `${totalCount} item${totalCount !== 1 ? 's' : ''}`;

    if (this.cart.length === 0) {
      wrap.innerHTML = '<div class="empty">Tap a part, scan a barcode, or add a custom item.</div>';
    } else {
      wrap.innerHTML = this.cart.map(c => `
        <div class="cart-line">
          <div class="linfo">
            <span class="txt">${c.name}${c.manual ? ' <span style="color:var(--ink-faint); font-weight:400;">(custom)</span>' : ''}</span>
            <span class="rm" onclick="POS.removeFromCart('${c.id}')" title="Remove item">✕</span>
          </div>
          <div class="qty-controls">
            <button class="qty-btn" onclick="POS.updateQty('${c.id}', -1)">-</button>
            <span class="mono" style="font-size:12px; font-weight:700; min-width:14px; text-align:center;">${c.qty}</span>
            <button class="qty-btn" onclick="POS.updateQty('${c.id}', 1)">+</button>
            <span class="mono line-total" style="font-weight:700;">GHS ${(c.price * c.qty).toFixed(2)}</span>
          </div>
        </div>
      `).join('');
    }

    this.updateCartTotals();
  },

  updateCartTotals() {
    const total = this.cartTotal();
    const sub = total / 1.15;

    document.getElementById('tSub').textContent = 'GHS ' + sub.toFixed(2);
    document.getElementById('tVat').textContent = 'GHS ' + (total - sub).toFixed(2);
    document.getElementById('tTotal').textContent = 'GHS ' + total.toFixed(2);

    this.updateChangeDisplay(total);

    const btn = document.getElementById('completeBtn');
    if (this.cart.length === 0) {
      btn.disabled = true;
      btn.textContent = 'Add items to continue';
    } else {
      btn.disabled = false;
      btn.textContent = `Complete Sale — Collect GHS ${total.toFixed(2)}`;
    }
  },

  cartTotal() {
    const sub = this.cart.reduce((sum, c) => sum + (c.price * c.qty), 0);
    return sub + (sub * 0.15);
  },

  updateChangeDisplay(total) {
    const cashWrap = document.getElementById('cashChangeSection');
    if (!cashWrap) return;
    const isCash = this.selectedPayment === 'Cash';
    cashWrap.style.display = isCash ? 'block' : 'none';
    if (!isCash) return;

    const given = parseFloat(document.getElementById('cashGiven').value);
    const changeEl = document.getElementById('changeAmount');
    const shortWrap = document.getElementById('cashShortRow');

    if (isNaN(given) || given <= 0) {
      if (changeEl) { changeEl.textContent = 'GHS 0.00'; changeEl.style.color = 'var(--lime-ink)'; }
      if (shortWrap) shortWrap.style.display = 'none';
      this.changeDue = 0;
      return;
    }

    const change = given - total;
    if (change >= 0) {
      if (shortWrap) shortWrap.style.display = 'none';
      if (changeEl) {
        changeEl.textContent = 'GHS ' + change.toFixed(2);
        changeEl.style.color = 'var(--lime-ink)';
      }
      this.changeDue = change;
    } else {
      if (shortWrap) {
        shortWrap.style.display = 'flex';
        const shortEl = document.getElementById('cashShortAmount');
        if (shortEl) shortEl.textContent = 'GHS ' + Math.abs(change).toFixed(2);
      }
      if (changeEl) {
        changeEl.textContent = 'GHS ' + Math.abs(change).toFixed(2);
        changeEl.style.color = 'var(--coral-ink)';
      }
      this.changeDue = 0;
    }
  },

  async executeCheckout() {
    if (this.cart.length === 0) return;

    const customerId = document.getElementById('posCustomerSelect')?.value || null;
    const items = this.cart.map(c => c.manual
      ? { manual: true, name: c.name, unit_price: c.price, qty: c.qty }
      : { id: c.id, qty: c.qty }
    );
    const payload = {
      items: items,
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
        this.changeDue = 0;
        const cashGiven = document.getElementById('cashGiven');
        if (cashGiven) cashGiven.value = '';
        this.renderCart();
        this.loadProducts(); // refresh stock numbers
      }
    } catch (e) {}
  },

  generateQuotation() {
    if (this.cart.length === 0) {
      App.toast('Add parts to the sale first, then give the quote.', 'error');
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
      payment_method: 'Price Quote (Valid 14 Days)',
      subtotal: sub,
      vat_amount: vat,
      grand_total: sub + vat,
      items: this.cart.map(c => ({ product_name: c.name, quantity: c.qty, total_price: c.price * c.qty })),
      dealership: {
        dealership_name: 'SpareStack Auto Parts OS',
        dealership_tagline: 'PRICE QUOTE',
        address: 'Plot 14 Harper Road, Adum, Kumasi',
        phone: '+233 32 202 4491',
        receipt_footer: 'Official parts estimate. Valid for 14 calendar days from date of issue.'
      }
    };

    this.lastReceiptData = quoteData;
    this.showThermalReceipt(quoteData);
    App.toast(`Quote ${quoteNo} created!`, 'success');
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
      `_Thank you for choosing SpareStack Auto Parts!_`;

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
