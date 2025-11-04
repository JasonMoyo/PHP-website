<?php
session_start();
if($_SERVER['REQUEST_METHOD']=='POST'){
  $users = json_decode(file_get_contents(__DIR__ . '/users.json'), true);
  $email = $_POST['email'];
  $pass = $_POST['password'];
  foreach($users as $u){
    if($u['email']==$email && password_verify($pass,$u['password'])){
      $_SESSION['user']=$u;
      header('Location: index.php');
      exit;
    }
  }
  $err='Invalid credentials';
}
$success = isset($_GET['success']);
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Login</title><link rel="stylesheet" href="css/styles.css"></head>
<body>
<header class="header"><div class="brand"><img class="logo" src="assets/images/abc medicos outside.jpg"><div><div style="font-size:18px;font-weight:800">ABC Medicos</div></div></div></header>
<main class="container">
<h2>Login</h2>
<?php if($success) echo '<div style="color:green;font-weight:600;margin-bottom:10px">✅ You have successfully signed up. Please log in.</div>'; ?>
<?php if(isset($err)) echo '<div style="color:#ff4d4f">'.$err.'</div>'; ?>
<form method="post" class="form">
<label>Email</label><input name="email" class="input" type="email" required>
<label>Password</label><input name="password" class="input" type="password" required>
<div style="margin-top:12px"><button class="btn btn-primary" type="submit">Login</button></div>
</form>
</main>
<footer class="footer">© 2025 ABC Medicos</footer></body></html>
