<?php session_start(); ?>
<!doctype html><html><head><meta charset="utf-8"><title>Cart - ABC Medicos</title><link rel="stylesheet" href="css/styles.css"></head><body>
<header class="header"><div class="brand"><img class="logo" src="assets/images/abc medicos outside.jpg" alt="logo"><div><div style="font-size:18px;font-weight:800">ABC Medicos</div></div></div><nav class="actions"><a class="btn btn-ghost" href="products.php">Continue shopping</a><a class="btn btn-primary" href="checkout.php">Checkout</a></nav></header>
<main class="container">
  <h2>Your Cart</h2>
  <div id="cartArea" class="grid"></div>
  <div style="margin-top:18px" id="cartSummary"></div>
</main>
<footer class="footer">© 2025 ABC Medicos</footer>
<script>
function loadCart(){
  fetch('cart.php').then(r=>r.json()).then(j=>{
    const area = document.getElementById('cartArea'); area.innerHTML='';
    if(j.items.length==0){ area.innerHTML='<div class="form">Your cart is empty</div>'; document.getElementById('cartSummary').innerHTML=''; return; }
    j.items.forEach(p=>{
      const card = document.createElement('div'); card.className='card';
      card.innerHTML = `<img src="${p.thumb}"><h3>${p.name}</h3><div class="meta">₹${p.price} × ${p.qty} = <b>₹${p.subtotal}</b></div><div style="margin-top:8px"><button class="btn" onclick="remove(${p.product_id})">Remove</button></div>`;
      area.appendChild(card);
    });
    document.getElementById('cartSummary').innerHTML = `<div class="form"><h3>Order Total: ₹${j.total}</h3><div style="margin-top:10px"><a class="btn btn-primary" href="checkout.php">Proceed to Checkout</a></div></div>`;
  });
}
function remove(id){ fetch('cart.php?action=remove&id='+id,{method:'POST'}).then(()=>loadCart()); }
loadCart();
</script>
</body></html>