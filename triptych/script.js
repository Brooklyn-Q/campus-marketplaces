/**
 * ========================================
 * TRIPTYCH DASHBOARD — script.js
 * Simulated Data Layer + Interactivity
 * Connected to PHP/MySQL via fetch() API
 * ========================================
 */

// ── Simulated Data Store ──
const store = {
  // Seller's inventory
  products: [
    { id: 1, name: 'Wireless Earbuds',     price: 85.00,  discount: 0, status: 'live' },
    { id: 2, name: 'Desk Lamp (LED)',       price: 45.00,  discount: 0, status: 'live' },
    { id: 3, name: 'USB-C Hub Adapter',     price: 62.00,  discount: 0, status: 'live' },
    { id: 4, name: 'Campus Hoodie (XL)',    price: 120.00, discount: 0, status: 'live' },
    { id: 5, name: 'Scientific Calculator', price: 55.00,  discount: 0, status: 'live' },
    { id: 6, name: 'Bluetooth Speaker',     price: 98.00,  discount: 0, status: 'live' },
  ],

  // Admin pending queue
  pendingApprovals: [],

  // Buyer cart (products with approved discounts)
  cart: [],

  // Track selected row in seller table
  selectedRow: null,
};

// Color assignments for buyer cards
const cardColors = [
  { bg: '#051F20', text: '#DAF1DE', hex: '#051F20' },
  { bg: '#0B2B26', text: '#DAF1DE', hex: '#0B2B26' },
  { bg: '#163832', text: '#DAF1DE', hex: '#163832' },
  { bg: '#235347', text: '#DAF1DE', hex: '#235347' },
  { bg: '#8EB69B', text: '#051F20', hex: '#8EB69B' },
  { bg: '#DAF1DE', text: '#051F20', hex: '#DAF1DE' },
];

// ── Toast Notification ──
function showToast(message) {
  const toast = document.getElementById('toast');
  toast.textContent = message;
  toast.classList.add('visible');
  setTimeout(() => toast.classList.remove('visible'), 2800);
}

// ═══════════════════════════════════════
// SELLER PANEL — Render & Logic
// ═══════════════════════════════════════

function renderSellerTable() {
  const tbody = document.getElementById('sellerTableBody');
  const submitArea = document.getElementById('submitArea');

  tbody.innerHTML = store.products.map((p, i) => `
    <tr class="${store.selectedRow === i ? 'selected' : ''}" onclick="selectRow(${i})">
      <td>
        <span class="status-dot ${p.status === 'live' ? 'live' : 'pending'}"></span>
        ${p.name}
      </td>
      <td>₵${p.price.toFixed(2)}</td>
      <td>
        <input 
          type="number" 
          class="discount-input" 
          placeholder="0%" 
          min="0" max="90" 
          value="${p.discount || ''}"
          onclick="event.stopPropagation()"
          onchange="updateDiscount(${i}, this.value)"
          id="discount-${i}"
        >
      </td>
      <td>
        <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); addDiscount(${i})">
          Add Discount
        </button>
      </td>
    </tr>
  `).join('');

  // Show submit button only for selected row with discount
  if (store.selectedRow !== null && store.products[store.selectedRow]?.discount > 0) {
    const p = store.products[store.selectedRow];
    submitArea.innerHTML = `
      <button class="btn btn-submit" onclick="submitForApproval(${store.selectedRow})">
        Submit "${p.name}" (${p.discount}% off) for Admin Approval
      </button>
    `;
  } else {
    submitArea.innerHTML = '';
  }

  // Update stats
  updateSellerStats();
}

function selectRow(index) {
  store.selectedRow = store.selectedRow === index ? null : index;
  renderSellerTable();
}

function updateDiscount(index, value) {
  store.products[index].discount = Math.min(90, Math.max(0, parseInt(value) || 0));
  renderSellerTable();
}

function addDiscount(index) {
  const input = document.getElementById(`discount-${index}`);
  const val = parseInt(input.value) || 0;
  if (val <= 0 || val > 90) {
    showToast('⚠️ Enter a valid discount (1-90%)');
    return;
  }
  store.products[index].discount = val;
  store.selectedRow = index;
  showToast(`✅ ${val}% discount set for "${store.products[index].name}"`);
  renderSellerTable();
}

function submitForApproval(index) {
  const product = store.products[index];
  if (product.discount <= 0) return;

  const discountedPrice = product.price * (1 - product.discount / 100);

  // Push to admin queue
  store.pendingApprovals.push({
    id: Date.now(),
    productId: product.id,
    productName: product.name,
    sellerName: 'You (Seller)',
    originalPrice: product.price,
    discountPercent: product.discount,
    discountedPrice: discountedPrice,
  });

  // Mark as pending
  product.status = 'pending';
  store.selectedRow = null;

  showToast(`📨 "${product.name}" submitted for admin approval`);

  // Try to also save to DB via API
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  fetch(`${window.MARKETPLACE_BASE_URL || '/'}api/discount.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'submit_discount',
      product_id: product.id,
      discount: product.discount,
      csrf_token: csrfToken
    })
  }).catch(() => { /* API optional, simulation works standalone */ });

  renderAll();
}

function updateSellerStats() {
  const total = store.products.length;
  const live = store.products.filter(p => p.status === 'live').length;
  const pending = store.pendingApprovals.length;
  document.getElementById('statTotal').textContent = total;
  document.getElementById('statLive').textContent = live;
  document.getElementById('statPending').textContent = pending;
}

// ═══════════════════════════════════════
// ADMIN PANEL — Render & Logic
// ═══════════════════════════════════════

function renderAdminPanel() {
  const container = document.getElementById('approvalList');

  if (store.pendingApprovals.length === 0) {
    container.innerHTML = `
      <div class="empty-state">
        <div class="icon">✅</div>
        <p>No pending approvals.<br>All clear!</p>
      </div>
    `;
    return;
  }

  container.innerHTML = store.pendingApprovals.map(item => `
    <div class="approval-card" id="approval-${item.id}">
      <div class="meta">
        <div>
          <div class="meta-label">Product</div>
          <div class="meta-value">${item.productName}</div>
        </div>
        <div>
          <div class="meta-label">Seller</div>
          <div class="meta-value">${item.sellerName}</div>
        </div>
      </div>
      <div class="price-compare">
        <span class="price-original">₵${item.originalPrice.toFixed(2)}</span>
        <span class="price-discounted">₵${item.discountedPrice.toFixed(2)}</span>
        <span style="font-size:0.75rem; opacity:0.5; margin-left:auto;">(−${item.discountPercent}%)</span>
      </div>
      <div class="actions">
        <button class="btn btn-approve" onclick="approveDiscount(${item.id})">✓ Approve</button>
        <button class="btn btn-reject" onclick="rejectDiscount(${item.id})">✕ Reject</button>
      </div>
    </div>
  `).join('');
}

function approveDiscount(id) {
  const index = store.pendingApprovals.findIndex(a => a.id === id);
  if (index === -1) return;

  const item = store.pendingApprovals[index];

  // Animate card out
  const card = document.getElementById(`approval-${id}`);
  if (card) {
    card.style.transform = 'translateX(30px)';
    card.style.opacity = '0';
  }

  setTimeout(() => {
    // Add to buyer cart
    store.cart.push({
      id: item.productId,
      name: item.productName,
      originalPrice: item.originalPrice,
      finalPrice: item.discountedPrice,
      discount: item.discountPercent,
      colorIndex: store.cart.length % cardColors.length,
    });

    // Mark product as live again
    const prod = store.products.find(p => p.id === item.productId);
    if (prod) { prod.status = 'live'; prod.discount = 0; }

    // Remove from pending
    store.pendingApprovals.splice(index, 1);

    showToast(`✅ "${item.productName}" approved — visible to buyers!`);
    renderAll();
  }, 350);
}

function rejectDiscount(id) {
  const index = store.pendingApprovals.findIndex(a => a.id === id);
  if (index === -1) return;

  const item = store.pendingApprovals[index];

  const card = document.getElementById(`approval-${id}`);
  if (card) {
    card.style.transform = 'translateX(-30px)';
    card.style.opacity = '0';
  }

  setTimeout(() => {
    // Reset product
    const prod = store.products.find(p => p.id === item.productId);
    if (prod) { prod.status = 'live'; prod.discount = 0; }

    store.pendingApprovals.splice(index, 1);
    showToast(`❌ "${item.productName}" discount rejected`);
    renderAll();
  }, 350);
}

// ═══════════════════════════════════════
// BUYER PANEL — Render & Logic
// ═══════════════════════════════════════

function renderBuyerPanel() {
  const grid = document.getElementById('productGrid');
  const totalEl = document.getElementById('cartTotal');
  const countEl = document.getElementById('cartCount');

  if (store.cart.length === 0) {
    grid.innerHTML = `
      <div class="empty-state" style="grid-column: 1/-1;">
        <div class="icon">🛒</div>
        <p>No discounted products yet.<br>Waiting for admin approvals.</p>
      </div>
    `;
    totalEl.textContent = '₵0.00';
    countEl.textContent = '0 items';
    return;
  }

  grid.innerHTML = store.cart.map((item, i) => {
    const color = cardColors[item.colorIndex];
    return `
      <div 
        class="product-card" 
        style="background:${color.bg}; color:${color.text};"
        onclick="showToast('📦 ${item.name} — ₵${item.finalPrice.toFixed(2)}')"
      >
        <div class="card-name">${item.name}</div>
        <div class="card-hex">${color.hex}</div>
        <div class="card-price">₵${item.finalPrice.toFixed(2)}</div>
        <div class="card-qty">was ₵${item.originalPrice.toFixed(2)} · ${item.discount}% off</div>
      </div>
    `;
  }).join('');

  const total = store.cart.reduce((sum, item) => sum + item.finalPrice, 0);
  totalEl.textContent = `₵${total.toFixed(2)}`;
  countEl.textContent = `${store.cart.length} item${store.cart.length !== 1 ? 's' : ''}`;
}

function placeOrder() {
  if (store.cart.length === 0) {
    showToast('🛒 Your cart is empty!');
    return;
  }
  const total = store.cart.reduce((sum, item) => sum + item.finalPrice, 0);
  showToast(`🎉 Order placed! Total: ₵${total.toFixed(2)} — Pay in-person.`);

  // Clear cart after animation
  setTimeout(() => {
    store.cart = [];
    renderBuyerPanel();
  }, 1500);
}

// ═══════════════════════════════════════
// RENDER ALL PANELS
// ═══════════════════════════════════════

function renderAll() {
  renderSellerTable();
  renderAdminPanel();
  renderBuyerPanel();
}

// ── Initialize on DOM ready ──
document.addEventListener('DOMContentLoaded', renderAll);
