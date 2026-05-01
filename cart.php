<?php
require_once 'includes/header.php';
?>

<h2 style="font-size:1.4rem; margin-bottom:1rem;">Your Cart</h2>

<div id="cart-page-container" class="glass" style="padding:2rem;">
    <div id="cart-items" style="display:flex; flex-direction:column; gap:1rem;"></div>
    <div id="cart-empty" style="text-align:center; padding:3rem; display:none;">
        <p style="font-size:1.2rem; color:var(--text-muted); margin-bottom:1rem;">Your cart is empty</p>
        <a href="index.php" class="btn btn-primary">Browse Products</a>
    </div>
    <div id="cart-footer" style="display:none; margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--border);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
                <p style="font-size:0.9rem; color:var(--text-muted);">Total</p>
                <p id="cart-total" style="font-size:1.8rem; font-weight:800; color:var(--primary);">₵0.00</p>
            </div>
            <div style="display:flex; gap:0.75rem;">
                <button class="btn btn-outline btn-sm" onclick="cmCart.clear(); renderCart();">Clear Cart</button>
            </div>
        </div>
    </div>
</div>

<div id="ai-cart-suggestions" style="margin-top:2.5rem;"></div>

<style>
    .cart-item { display:grid; grid-template-columns:70px 1fr auto; gap:1rem; align-items:center; padding:1rem; background:rgba(0,0,0,0.2); border-radius:12px; border:1px solid var(--border); transition:all 0.3s; }
    .cart-item:hover { border-color:rgba(255,255,255,0.15); }
    .cart-item img { width:70px; height:70px; object-fit:cover; border-radius:8px; }
    .cart-item-actions { display:flex; align-items:center; gap:0.5rem; }
    .qty-btn { width:44px; height:44px; border-radius:8px; border:1px solid var(--border); background:rgba(255,255,255,0.05); color:var(--light); cursor:pointer; font-size:1rem; display:flex; align-items:center; justify-content:center; transition:all 0.2s; }
    .qty-btn:hover { background:rgba(255,255,255,0.15); }
    .remove-btn { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); color:#ef4444; padding:6px 12px; border-radius:8px; cursor:pointer; font-size:0.8rem; transition:all 0.2s; }
    .remove-btn:hover { background:rgba(239,68,68,0.25); }
    @media(max-width:500px) {
        .cart-item { grid-template-columns:50px 1fr; }
        .cart-item-actions { grid-column: 1 / -1; justify-content:space-between; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    window.renderCart = function() {
        const cart = cmCart.get();
        const container = document.getElementById('cart-items');
        const empty = document.getElementById('cart-empty');
        const footer = document.getElementById('cart-footer');
        
        if (cart.length === 0) {
            container.innerHTML = '';
            empty.style.display = 'block';
            footer.style.display = 'none';
            return;
        }
        
        empty.style.display = 'none';
        footer.style.display = 'block';
        
        container.innerHTML = cart.map(item => `
            <div class="cart-item">
                <img src="${item.image || ''}" alt="${item.name}" onerror="this.style.background='rgba(0,0,0,0.3)'; this.alt='No Image';">
                <div>
                    <p style="font-weight:600; margin-bottom:4px;">${item.name}</p>
                    <p style="color:var(--primary); font-weight:700;">₵${(item.price * item.qty).toFixed(2)}</p>
                </div>
                <div class="cart-item-actions">
                    <button class="qty-btn" onclick="cmCart.updateQty(${item.id}, ${item.qty - 1}); renderCart();">−</button>
                    <span style="min-width:24px; text-align:center; font-weight:600;">${item.qty}</span>
                    <button class="qty-btn" onclick="cmCart.updateQty(${item.id}, ${item.qty + 1}); renderCart();">+</button>
                    <button class="remove-btn" onclick="cmCart.remove(${item.id}); renderCart();">✕</button>
                </div>
            </div>
        `).join('');
        
        document.getElementById('cart-total').textContent = '₵' + cmCart.total().toFixed(2);
        
        // Fetch AI suggestions based on cart payload
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch('cart_suggestions.php', {
            method: 'POST',
            body: JSON.stringify({...cart, csrf_token: csrfToken}),
            headers: {'Content-Type': 'application/json'}
        })
        .then(res => res.text())
        .then(html => document.getElementById('ai-cart-suggestions').innerHTML = html)
        .catch(() => {});
    };
    
    renderCart();
});
</script>

<?php require_once 'includes/footer.php'; ?>
