<?php
session_start();
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>ABC Medicos Pharmacy</title>
<link rel="stylesheet" href="css/styles.css">
</head>
<body>
<header class="header">
  <div class="brand">
    <img class="logo" src="assets/images/abc medicos outside.jpg" alt="logo">
    <div>
      <div style="font-size:18px;font-weight:800">ABC Medicos</div>
      <div style="opacity:0.85;font-size:13px">Trusted healthcare • Lawgate Market</div>
    </div>
  </div>
  <nav class="actions">
    <a class="btn btn-ghost" href="index.php">Home</a>
    <a class="btn btn-ghost" href="products.php">Products</a>
    <a class="btn btn-ghost" href="contact.html">Contact</a>
    <?php if($user): ?>
      <span style="color:white;font-weight:600">Welcome, <?php echo htmlspecialchars($user['name']); ?> 👋</span>
      <a class="btn btn-ghost" href="logout.php">Logout</a>
    <?php else: ?>
      <a class="btn btn-ghost" href="login.php">Login</a>
      <a class="btn btn-primary" href="signup.php">Sign up</a>
    <?php endif; ?>
    <a class="btn" href="cart_view.php"><span class="cart-badge" id="cartCount">0</span> Cart</a>
  </nav>
</header>
<main class="container">
  <section class="hero">
    <img src="assets/images/abc medicos inside.jpg" alt="inside">
    <div>
      <h2 style="margin:0 0 6px">Your neighborhood pharmacy — ABC Medicos</h2>
      <p style="margin:0 0 12px;color:#334155">We stock prescription & OTC medicines, first aid, vitamins, and health devices. Fast local delivery.</p>
      <div><a class="btn btn-primary" href="products.php">Browse Products</a></div>
    </div>
  </section>
  <section style="margin-top:18px">
    <h3>Popular items</h3>
    <div class="grid" id="popularGrid"></div>
  </section>
</main>
<footer class="footer">© 2025 ABC Medicos • Lawgate Market</footer>
<script>
fetch('products/products.json').then(r=>r.json()).then(j=>{
  const grid = document.getElementById('popularGrid');
  j.slice(0,6).forEach(p=>{
    const el = document.createElement('div'); el.className='card';
    el.innerHTML = `<img src="${p.thumb}" /><h3>${p.name}</h3><div class="meta">${p.category} • ${p.brand}</div><div class="price">₹${p.price}</div><div style="margin-top:10px"><button class="btn btn-primary" onclick="addCart(${p.product_id})">Add to cart</button> <a class="btn btn-ghost" href="product-details.php?id=${p.product_id}">View</a></div>`;
    grid.appendChild(el);
  });
});
function updateCartCount(){ fetch('cart.php?action=count').then(r=>r.json()).then(j=>document.getElementById('cartCount').innerText=j.count); }
function addCart(id){ fetch('cart.php?action=add&id='+id,{method:'POST'}).then(()=>{ updateCartCount(); alert('Added to cart'); }); }
updateCartCount();
</script>
</body></html>
