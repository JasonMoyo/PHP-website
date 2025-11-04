<?php session_start(); if($_SERVER['REQUEST_METHOD']=='POST'){ $_SESSION['last_order'] = $_POST; $_SESSION['cart']=[]; header('Location: thankyou.php'); exit;} ?>
<!doctype html><html><head><meta charset="utf-8"><title>Checkout - ABC Medicos</title><link rel="stylesheet" href="css/styles.css"></head><body>
<header class="header"><div class="brand"><img class="logo" src="assets/images/abc medicos outside.jpg" alt="logo"><div><div style="font-size:18px;font-weight:800">ABC Medicos</div></div></div></header>
<main class="container">
  <h2>Checkout</h2>
  <div id="cartSummary" class="form"></div>
  <form method="post" class="form" id="checkoutForm">
    <label>Full name</label><input name="name" class="input" required>
    <label>Phone</label><input name="phone" class="input" required>
    <label>Address</label><input name="address" class="input" required>
    <input type="hidden" name="latitude" id="lat"><input type="hidden" name="longitude" id="lng">
    <div style="margin-top:12px"><button class="btn btn-primary" type="submit">Place Order</button></div>
  </form>
  <div style="margin-top:12px"><button class="btn" onclick="navigator.geolocation.getCurrentPosition(success,err)">Get my current location</button> <span id="locMsg" style="color:var(--muted)"></span></div>
</main>
<footer class="footer">© 2025 ABC Medicos</footer>
<script>
function loadCart(){ fetch('cart.php').then(r=>r.json()).then(j=>{ document.getElementById('cartSummary').innerHTML = `<h3>Cart total: ₹${j.total}</h3>`; }); }
function success(pos){ document.getElementById('lat').value = pos.coords.latitude; document.getElementById('lng').value = pos.coords.longitude; document.getElementById('locMsg').innerText = 'Location captured ✓'; }
function err(){ document.getElementById('locMsg').innerText = 'Unable to get location'; }
loadCart();
</script>
</body></html>