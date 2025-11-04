<?php
session_start();
header('Content-Type: application/json');
$action = isset($_GET['action'])?$_GET['action']:'view';
$products = json_decode(file_get_contents(__DIR__ . '/products/products.json'), true);
if(!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
function findProduct($id,$products){ foreach($products as $p) if($p['product_id']==$id) return $p; return null; }
if($action=='add'){
  $id = intval($_GET['id']);
  if(!$id){ echo json_encode(['ok'=>false]); exit; }
  if(isset($_SESSION['cart'][$id])) $_SESSION['cart'][$id] += 1; else $_SESSION['cart'][$id]=1;
  echo json_encode(['ok'=>true]); exit;
}
if($action=='remove'){
  $id = intval($_GET['id']);
  unset($_SESSION['cart'][$id]);
  echo json_encode(['ok'=>true]); exit;
}
if($action=='count'){
  $c=0; foreach($_SESSION['cart'] as $q) $c += $q; echo json_encode(['count'=>$c]); exit;
}
if($action=='clear'){ $_SESSION['cart'] = []; echo json_encode(['ok'=>true]); exit; }
$items = []; $total = 0;
foreach($_SESSION['cart'] as $id=>$qty){
  $p = findProduct(intval($id), $products);
  if($p){
    $p['qty'] = $qty; $p['subtotal'] = $p['price'] * $qty; $items[] = $p; $total += $p['subtotal'];
  }
}
echo json_encode(['items'=>$items,'total'=>$total]);
