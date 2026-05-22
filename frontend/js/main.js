// CobbleCart — Main JavaScript

// ============================================
// ORDS API BASE URL
// ============================================
const ORDS_BASE = 'http://localhost:8080/ords/cobbleuser/';

function money(value) {
  return '$' + (Number(value) || 0).toFixed(2);
}

function getOfferPercent(product) {
  return Number(product.OFFER_PERCENT ?? product.offer_percent ?? product.offerPercent ?? 0) || 0;
}

function getOriginalPrice(product) {
  return Number(product.PRICE ?? product.originalPrice ?? product.original_price ?? product.price ?? 0) || 0;
}

function getEffectivePrice(product) {
  const explicit = Number(product.OFFER_PRICE ?? product.offer_price);
  if (Number.isFinite(explicit) && explicit > 0) return explicit;
  const original = getOriginalPrice(product);
  const offer = getOfferPercent(product);
  return offer > 0 ? Number((original * (100 - offer) / 100).toFixed(2)) : original;
}

function renderPrice(product) {
  const original = getOriginalPrice(product);
  const effective = getEffectivePrice(product);
  const offer = getOfferPercent(product);
  if (offer > 0 && original > effective) {
    return `<span style="color:#BA7517;font-weight:700;">${money(effective)}</span>
      <span style="color:#888;text-decoration:line-through;font-size:12px;margin-left:6px;">${money(original)}</span>
      <span style="background:#fff3dc;color:#8a6512;border:1px solid #f0d99a;border-radius:4px;padding:2px 5px;font-size:11px;margin-left:6px;">${offer}% off</span>`;
  }
  return money(effective);
}

// ============================================
// IMAGE PATH — resolves correctly from any folder
// ============================================
function getImgPath(filename) {
  // Works from customer/, trader/, admin/ and root
  const path = window.location.pathname;
  if (path.includes('/customer/') || path.includes('/trader/') || path.includes('/admin/')) {
    return '../images/products/' + filename;
  }
  return 'images/products/' + filename;
}

// ============================================
// CART
// ============================================
function getCart() {
  try {
    const raw = JSON.parse(localStorage.getItem('cobblecart_cart')) || [];
    // Drop legacy mock items that used string IDs like "p1" — they can't be
    // ordered against the real DB (place-order.php casts id to int and rejects 0).
    const cleaned = raw.filter(item => {
      const n = Number(item.id);
      return Number.isInteger(n) && n > 0;
    });
    if (cleaned.length !== raw.length) {
      localStorage.setItem('cobblecart_cart', JSON.stringify(cleaned));
    }
    return cleaned;
  } catch(e) {
    return [];
  }
}

function saveCart(cart) {
  localStorage.setItem('cobblecart_cart', JSON.stringify(cart));
}

function addToCart(id, name, price, shop, meta = {}) {
  const numericId = Number(id);
  if (!Number.isInteger(numericId) || numericId <= 0) {
    showAlert('Cannot add: product has no valid ID', 'error');
    return;
  }
  const unitPrice = Number(price) || 0;
  const originalPrice = Number(meta.originalPrice ?? meta.original_price ?? unitPrice) || unitPrice;
  const offerPercent = Number(meta.offerPercent ?? meta.offer_percent ?? 0) || 0;
  let cart = getCart();
  const existing = cart.find(item => Number(item.id) === numericId);
  if (existing) {
    existing.price = unitPrice;
    existing.originalPrice = originalPrice;
    existing.offerPercent = offerPercent;
    existing.qty = Math.min(MAX_PER_PRODUCT, existing.qty + 1);
    if (existing.qty >= MAX_PER_PRODUCT) showAlert('Maximum of ' + MAX_PER_PRODUCT + ' units allowed per product', 'error');
  } else {
    cart.push({ id: numericId, name, price: unitPrice, originalPrice, offerPercent, shop, qty: 1 });
  }
  saveCart(cart);
  updateCartCount();
  showAlert(name + ' added to basket', 'success');
}

function getApiUrl(path) {
  const trimmed = path.replace(/^\/+|\/+$/g, '');
  return new URL('../../api/' + trimmed, window.location.href).href;
}

async function fetchJson(url, options = {}) {
  const response = await fetch(url, { credentials: 'include', ...options });
  const text = await response.text();
  let data = null;
  try {
    data = JSON.parse(text);
  } catch (err) {
    data = null;
  }
  if (!response.ok) {
    const message = data && data.message ? data.message : (text ? text : response.statusText || 'Request failed');
    throw new Error(message);
  }
  if (data === null) {
    throw new Error('Invalid JSON response: ' + text);
  }
  return data;
}

async function addToWishlist(productId, name) {
  if (!isLoggedIn()) {
    window.location.href = getLoginPageUrl();
    return;
  }
  try {
    const data = await fetchJson(getApiUrl('add-favorite.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ product_id: productId })
    });
    if (!data.success) throw new Error(data.message || 'Could not add to favorites');
    showAlert((name || 'Product') + ' added to favorites', 'success');
    updateWishlistCount();
    return data.wishlist || [];
  } catch (err) {
    showAlert(err.message || 'Unable to add to favorites', 'error');
  }
}

async function removeFromWishlist(productId, name) {
  if (!isLoggedIn()) {
    window.location.href = getLoginPageUrl();
    return;
  }
  try {
    const data = await fetchJson(getApiUrl(`remove-favorite.php?id=${productId}`), {
      method: 'DELETE'
    });
    if (!data.success) throw new Error(data.message || 'Could not remove from favorites');
    showAlert((name || 'Product') + ' removed from favorites', 'info');
    updateWishlistCount();
    const wishlist = data.wishlist || [];
    if (document.getElementById('wishlist-items')) {
      renderWishlist(wishlist);
    }
    return wishlist;
  } catch (err) {
    showAlert(err.message || 'Unable to remove from favorites', 'error');
  }
}

async function getWishlistItems() {
  if (!isLoggedIn()) {
    window.location.href = getLoginPageUrl();
    return [];
  }
  try {
    const data = await fetchJson(getApiUrl('get-favorites.php'), { method: 'GET' });
    if (!data.success) throw new Error(data.message || 'Failed to load favorites');
    return data.wishlist || [];
  } catch (err) {
    showAlert(err.message || 'Unable to load favorites', 'error');
    return [];
  }
}

function renderWishlist(items, containerId = 'wishlist-items') {
  const container = document.getElementById(containerId);
  if (!container) return;
  if (!items || !items.length) {
    container.innerHTML = '<p style="color:#888;font-size:14px;">Your favorites list is empty. Browse products to add items you love.</p>';
    return;
  }
  container.innerHTML = items.map(item => `
    <div class="wishlist-card">
      <div class="wishlist-info">
        <div class="wishlist-name">${item.NAME}</div>
        <div class="wishlist-shop">${item.SHOP_NAME}</div>
        <div class="wishlist-price">${renderPrice(item)}</div>
      </div>
      <div class="wishlist-actions">
        <button class="btn btn-outline" onclick="window.location='product-details.html?id=${item.PRODUCT_ID}'">View</button>
        <button class="btn btn-danger" onclick="removeFromWishlist(${item.PRODUCT_ID}, ${JSON.stringify(item.NAME)})">Remove</button>
      </div>
    </div>
  `).join('');
}

function renderWishlistCount(count) {
  const badge = document.getElementById('wishlist-count');
  if (badge) badge.textContent = count;
}

function updateWishlistCount() {
  getWishlistItems().then(items => {
    if (items) renderWishlistCount(items.length);
  });
}

function removeFromCart(id) {
  let cart = getCart().filter(item => item.id !== id);
  saveCart(cart);
  updateCartCount();
  renderCart();
}

function updateQty(id, qty) {
  let cart = getCart();
  const item = cart.find(i => i.id === id);
  if (item) {
    let q = parseInt(qty) || 0;
    if (q > MAX_PER_PRODUCT) {
      showAlert('Maximum of ' + MAX_PER_PRODUCT + ' units allowed per product', 'error');
      q = MAX_PER_PRODUCT;
    }
    item.qty = q;
    if (item.qty <= 0) {
      cart = cart.filter(i => i.id !== id);
    }
  }
  saveCart(cart);
  updateCartCount();
  renderCart();
}

function getCartTotal() {
  return getCart().reduce((sum, item) => sum + (parseFloat(item.price) * item.qty), 0).toFixed(2);
}

async function refreshCartPricing() {
  const cart = getCart();
  if (!cart.length) return;
  try {
    const refreshed = await Promise.all(cart.map(async item => {
      const data = await fetchJson(getApiUrl('get-products.php?product_id=' + item.id));
      const product = (data.products || [])[0];
      if (!product) return item;
      return {
        ...item,
        name: product.NAME || item.name,
        shop: product.SHOP_NAME || item.shop,
        price: getEffectivePrice(product),
        originalPrice: Number(product.PRICE) || item.originalPrice || item.price,
        offerPercent: Number(product.OFFER_PERCENT) || 0
      };
    }));
    saveCart(refreshed);
    updateCartCount();
    renderCart();
    renderCheckoutSummary();
  } catch (e) {
    // Keep the locally stored cart usable if the API is offline.
  }
}

function getCartCount() {
  return getCart().reduce((sum, item) => sum + item.qty, 0);
}

function updateCartCount() {
  const badge = document.getElementById('cart-count');
  const floatBadge = document.getElementById('cart-count-float');
  const count = getCartCount();
  if (badge) badge.textContent = count;
  if (floatBadge) floatBadge.textContent = count;
}

function renderCart() {
  const container = document.getElementById('cart-items');
  const totalEl   = document.getElementById('cart-total');
  const grandEl   = document.getElementById('cart-grand-total');
  const emptyMsg  = document.getElementById('cart-empty-msg');
  const cart      = getCart();

  if (!container) return;

  if (cart.length === 0) {
    container.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#888;">Your basket is empty. <a href="home.html">Continue shopping</a></td></tr>';
    if (emptyMsg) emptyMsg.style.display = 'block';
  } else {
    if (emptyMsg) emptyMsg.style.display = 'none';
    container.innerHTML = cart.map(item => `
      <tr data-item-id="${item.id}">
        <td style="font-weight:500;">${item.name}</td>
        <td>${item.shop}</td>
        <td>${renderPrice(item)}</td>
        <td>
          <input type="number" min="1" max="20" value="${item.qty}"
            onchange="updateQty('${item.id}', this.value)"
            style="width:60px;padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:13px;">
        </td>
        <td>${money(parseFloat(item.price) * item.qty)}</td>
        <td>
          <button onclick="removeFromCart('${item.id}')"
            style="padding:4px 10px;background:#c62828;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px;">
            Remove
          </button>
        </td>
      </tr>
    `).join('');
  }

  const total = getCartTotal();
  if (totalEl) totalEl.textContent = '$' + total;
  if (grandEl) grandEl.textContent = '$' + total;
}

// Highlight cart items with quantity greater than `limit`.
function highlightCartItemsExceed(limit) {
  try {
    clearCartHighlights();
    const cart = getCart();
    const ids = cart.filter(i => Number(i.qty) > limit).map(i => String(i.id));
    if (!ids.length) return;
    ids.forEach(id => {
      const tr = document.querySelector(`#cart-items tr[data-item-id='${id}']`);
      if (tr) tr.classList.add('cart-item-warning');
      // Also highlight order summary items (checkout page)
      document.querySelectorAll('.order-summary-item').forEach(el => {
        if (el.textContent.includes('x' + (cart.find(i => String(i.id) === id)?.qty || ''))) {
          el.classList.add('highlight');
        }
      });
    });
    // Scroll to first offending item if visible
    const first = document.querySelector('#cart-items tr.cart-item-warning');
    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
  } catch (e) {/* ignore */}
}

function clearCartHighlights() {
  document.querySelectorAll('#cart-items tr.cart-item-warning').forEach(el => el.classList.remove('cart-item-warning'));
  document.querySelectorAll('.order-summary-item.highlight').forEach(el => el.classList.remove('highlight'));
}

// Prevent adding more than 20 units via client-side addToCart
const MAX_PER_PRODUCT = 20;

function clearCart() {
  saveCart([]);
  updateCartCount();
  renderCart();
}

// ============================================
// ALERTS
// ============================================
function showAlert(message, type) {
  const existing = document.querySelector('.alert-toast');
  if (existing) existing.remove();
  const alert = document.createElement('div');
  alert.className = 'alert-toast';
  const colors = {
    success: { bg: '#e8f5e9', color: '#2e7d32', border: '#a5d6a7' },
    error:   { bg: '#ffebee', color: '#c62828', border: '#ef9a9a' },
    info:    { bg: '#FFF3DC', color: '#BA7517', border: '#FAC775' }
  };
  const c = colors[type] || colors.info;
  alert.style.cssText = `position:fixed;top:76px;right:20px;z-index:9999;min-width:280px;padding:14px 18px;border-radius:8px;font-size:14px;font-family:'Segoe UI',Arial,sans-serif;box-shadow:0 4px 16px rgba(0,0,0,0.15);background:${c.bg};color:${c.color};border:1px solid ${c.border};`;
  alert.textContent = message;
  document.body.appendChild(alert);
  setTimeout(() => { if (alert.parentNode) alert.remove(); }, 3000);
}

function getCurrentUser() {
  try {
    return JSON.parse(localStorage.getItem('cc_user')) || null;
  } catch (e) {
    return null;
  }
}

function setCurrentUser(user) {
  if (user && typeof user === 'object') {
    if (user.role) {
      user.role = String(user.role).toLowerCase();
    }
    localStorage.setItem('cc_user', JSON.stringify(user));
  } else {
    localStorage.removeItem('cc_user');
  }
}

function clearCurrentUser() {
  localStorage.removeItem('cc_user');
}

function isLoggedIn() {
  const user = getCurrentUser();
  return user && user.user_id;
}

function getLoginPageUrl() {
  const path = window.location.pathname.toLowerCase();
  if (path.includes('/admin/') || path.includes('/trader/') || path.includes('/customer/')) {
    return new URL('../customer/login.html', window.location.href).href;
  }
  return new URL('customer/login.html', window.location.href).href;
}

const DEV_SHOPS = [
  { shop_id: 2, shop_name: 'Butcher Shop', shop_type: 'Butcher', status: 'ACTIVE' },
  { shop_id: 3, shop_name: 'Greengrocer Shop', shop_type: 'Greengrocer', status: 'ACTIVE' },
  { shop_id: 4, shop_name: 'Fishmonger Shop', shop_type: 'Fishmonger', status: 'ACTIVE' },
  { shop_id: 5, shop_name: 'Bakery Shop', shop_type: 'Bakery', status: 'ACTIVE' },
  { shop_id: 6, shop_name: 'Delicatessen Shop', shop_type: 'Delicatessen', status: 'ACTIVE' }
];

const DEV_PRODUCTS_BY_SHOP = {
  2: [
    { PRODUCT_ID: 2001, NAME: 'Ribeye Steak 400g', PRICE: '12.50', STOCK_QUANTITY: 8, APPROVAL_STATUS: 'APPROVED', IMAGE: 'ribeye-steak.jpg', DESCRIPTION: 'Premium hand-cut steak from local herds.' },
    { PRODUCT_ID: 2002, NAME: 'Pork Sausages (6pk)', PRICE: '4.80', STOCK_QUANTITY: 12, APPROVAL_STATUS: 'APPROVED', IMAGE: 'pork-sausages.jpg', DESCRIPTION: 'Savory pork sausages made with house seasoning.' }
  ],
  3: [
    { PRODUCT_ID: 3001, NAME: 'Organic Carrots 1kg', PRICE: '1.20', STOCK_QUANTITY: 25, APPROVAL_STATUS: 'APPROVED', IMAGE: 'carrots.jpg', DESCRIPTION: 'Fresh organic carrots from the local farm.' },
    { PRODUCT_ID: 3002, NAME: 'Mixed Salad Bag', PRICE: '2.50', STOCK_QUANTITY: 18, APPROVAL_STATUS: 'APPROVED', IMAGE: 'salad.jpg', DESCRIPTION: 'Crisp salad leaves ready to eat.' }
  ],
  4: [
    { PRODUCT_ID: 4001, NAME: 'Fresh Salmon Fillet', PRICE: '8.00', STOCK_QUANTITY: 10, APPROVAL_STATUS: 'APPROVED', IMAGE: 'salmon.jpg', DESCRIPTION: 'Fresh Atlantic salmon fillet, delivered daily.' },
    { PRODUCT_ID: 4002, NAME: 'Cod Loins 300g', PRICE: '6.50', STOCK_QUANTITY: 14, APPROVAL_STATUS: 'APPROVED', IMAGE: 'cod.jpg', DESCRIPTION: 'Tender cod loins perfect for baking or frying.' }
  ],
  5: [
    { PRODUCT_ID: 5001, NAME: 'Sourdough Loaf', PRICE: '3.80', STOCK_QUANTITY: 16, APPROVAL_STATUS: 'APPROVED', IMAGE: 'sourdough.jpg', DESCRIPTION: 'Slow-fermented sourdough baked fresh today.' },
    { PRODUCT_ID: 5002, NAME: 'Croissants (4pk)', PRICE: '3.20', STOCK_QUANTITY: 20, APPROVAL_STATUS: 'APPROVED', IMAGE: 'croissants.jpg', DESCRIPTION: 'Flaky buttery croissants baked each morning.' }
  ],
  6: [
    { PRODUCT_ID: 6001, NAME: 'Aged Cheddar 250g', PRICE: '5.50', STOCK_QUANTITY: 12, APPROVAL_STATUS: 'APPROVED', IMAGE: 'cheddar.jpg', DESCRIPTION: 'Mature cheddar with rich, sharp flavour.' },
    { PRODUCT_ID: 6002, NAME: 'Prosciutto 100g', PRICE: '4.80', STOCK_QUANTITY: 15, APPROVAL_STATUS: 'APPROVED', IMAGE: 'prosciutto.jpg', DESCRIPTION: 'Thin slices of premium Italian prosciutto.' }
  ]
};

function getDevProductsForShop(shopId) {
  return DEV_PRODUCTS_BY_SHOP[shopId] || [];
}

function getDevShopById(shopId) {
  return DEV_SHOPS.find(shop => shop.shop_id === shopId) || null;
}

function requirePageRole(roles) {
  const user = getCurrentUser();
  const role = user?.role ? String(user.role).toLowerCase() : null;
  const accepted = roles.map(r => String(r).toLowerCase());
  const shopId = Number(new URLSearchParams(window.location.search).get('shop_id'));

  // Admin may act as a trader when a valid shop_id is provided in the URL.
  if (role === 'admin' && accepted.includes('trader') && Number.isInteger(shopId) && shopId > 0) {
    return true;
  }

  if (!user || !role || !accepted.includes(role)) {
    clearCurrentUser();
    window.location.href = getLoginPageUrl();
    return false;
  }
  return true;
}

function logout() {
  fetch('../../api/logout.php', { method: 'POST', credentials: 'include' })
    .catch(() => {})
    .finally(() => {
      clearCurrentUser();
      window.location.href = getLoginPageUrl();
    });
}

function autoProtectPage() {
  const path = window.location.pathname.toLowerCase();
  if (path.includes('/admin/')) return requirePageRole(['admin']);
  if (path.includes('/trader/')) return requirePageRole(['trader']);
  if (path.endsWith('/checkout.html') || path.endsWith('/profile.html') || path.endsWith('/invoice.html')) {
    return requirePageRole(['customer']);
  }
  return true;
}

// ============================================
// DATES — real current dates for slots
// ============================================
function getNextSlotDays(count) {
  const dayNames   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  const slotDays   = [3, 4, 5]; // Wed, Thu, Fri
  const result     = [];
  const now        = new Date();
  // Must be at least 24 hours ahead
  const earliest   = new Date(now.getTime() + 24 * 60 * 60 * 1000);
  let d = new Date(earliest);
  // Start from next day
  d.setDate(d.getDate());
  let safety = 0;
  while (result.length < count && safety < 30) {
    if (slotDays.includes(d.getDay())) {
      result.push({
        label: `${dayNames[d.getDay()]} ${d.getDate()} ${monthNames[d.getMonth()]} ${d.getFullYear()}`,
        short: `${dayNames[d.getDay()].substring(0,3)} ${d.getDate()} ${monthNames[d.getMonth()].substring(0,3)}`
      });
    }
    d.setDate(d.getDate() + 1);
    safety++;
  }
  return result;
}

function applySlotDates() {
  const labels = document.querySelectorAll('.slot-day-label');
  const days   = getNextSlotDays(labels.length || 3);
  labels.forEach((el, i) => {
    if (days[i]) el.textContent = days[i].label;
  });
}

// ============================================
// FORM HELPERS
// ============================================
function validateEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function checkPasswordMatch() {
  const pass    = document.getElementById('password');
  const confirm = document.getElementById('confirm-password');
  const err     = document.getElementById('pass-error') || document.getElementById('cpw-error');
  if (!pass || !confirm) return true;
  if (pass.value !== confirm.value) {
    if (err) err.textContent = 'Passwords do not match';
    return false;
  }
  if (err) err.textContent = '';
  return true;
}

// ============================================
// SLOT SELECTION
// ============================================
function selectSlot(el, slotId, slotLabel) {
  if (el.classList.contains('full')) return;
  document.querySelectorAll('.slot-option').forEach(s => s.classList.remove('selected'));
  el.classList.add('selected');
  slotId = slotId || parseInt(el.dataset.slot, 10);
  slotLabel = slotLabel || el.querySelector('.slot-time')?.textContent || '';
  const input = document.getElementById('selected-slot');
  const display = document.getElementById('slot-display');
  if (input) input.value = slotId;
  if (display) display.textContent = slotLabel || 'Slot selected';
  if (slotId) localStorage.setItem('cobblecart_slot', slotId);
}

function restoreSelectedSlot() {
  const slotId = parseInt(localStorage.getItem('cobblecart_slot') || '0', 10);
  if (!slotId) return;
  const slot = document.querySelector(`.slot-option[data-slot="${slotId}"]`);
  if (slot) selectSlot(slot);
}

// ============================================
// CHECKOUT CART SUMMARY
// ============================================
function renderCheckoutSummary() {
  const container = document.getElementById('checkout-items');
  const totalEl   = document.getElementById('checkout-total');
  const cart      = getCart();
  if (!container) return;
  if (cart.length === 0) {
    container.innerHTML = '<p style="font-size:13px;color:#888;">Your basket is empty. <a href="home.html">Go back to shop</a></p>';
  } else {
    container.innerHTML = cart.map(i => `
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px;">
        <span style="color:#555;">${i.name} x${i.qty}</span>
        <span style="font-weight:500;">${money(parseFloat(i.price) * i.qty)}</span>
      </div>
    `).join('');
  }
  if (totalEl) totalEl.textContent = '$' + getCartTotal();
}

// ============================================
// INIT ON PAGE LOAD
// ============================================
// Global image fallback: single inline SVG used when images fail to load.
// This avoids having to touch every page's inline onerror handlers.
try {
  const _svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">'
    + '<rect width="100%" height="100%" fill="#f0f0f0"/>'
    + '<text x="50%" y="50%" fill="#888" font-size="18" font-family="Segoe UI,Arial,sans-serif" text-anchor="middle" dy=".35em">No image</text>'
    + '</svg>';
  const CC_FALLBACK_URI = 'data:image/svg+xml;utf8,' + encodeURIComponent(_svg);

  window.addEventListener('error', function (e) {
    const tgt = e.target;
    if (tgt && tgt.tagName === 'IMG') {
      const img = tgt;
      if (img.dataset.ccFallback) return; // don't loop
      img.dataset.ccFallback = '1';
      img.src = CC_FALLBACK_URI;
      img.style.objectFit = img.style.objectFit || 'cover';
    }
  }, true);
} catch (e) { /* fail silently */ }

document.addEventListener('DOMContentLoaded', function() {
  if (!autoProtectPage()) return;
  const path = window.location.pathname.toLowerCase();
  updateCartCount();
  renderCart();
  restoreSelectedSlot();
  renderCheckoutSummary();
  if (path.endsWith('/cart.html') || path.endsWith('/checkout.html')) {
    refreshCartPricing();
  }
  applySlotDates();
});
