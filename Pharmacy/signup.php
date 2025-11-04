<?php
if($_SERVER['REQUEST_METHOD']=='POST'){
  $users = json_decode(file_get_contents(__DIR__ . '/users.json'), true);
  $email = $_POST['email'];
  $pass = $_POST['password'];
  $name = $_POST['name'];
  foreach($users as $u) if($u['email']==$email){ $err='Email already registered'; break; }
  if(empty($err)){
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $users[] = ['id'=>time(),'name'=>$name,'email'=>$email,'password'=>$hash];
    file_put_contents(__DIR__ . '/users.json', json_encode($users, JSON_PRETTY_PRINT));
    header('Location: login.php?success=1');
    exit;
  }
}
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Sign up</title><link rel="stylesheet" href="css/styles.css"></head>
<body>
<header class="header"><div class="brand"><img class="logo" src="assets/images/abc medicos outside.jpg"><div><div style="font-size:18px;font-weight:800">ABC Medicos</div></div></div></header>
<main class="container">
<h2>Sign up</h2>
<?php if(isset($err)) echo '<div style="color:#ff4d4f">'.$err.'</div>'; ?>
<form method="post" class="form">
<label>Name</label><input name="name" class="input" required>
<label>Email</label><input name="email" class="input" type="email" required>
<label>Password</label><input name="password" class="input" type="password" required>
<div style="margin-top:12px"><button class="btn btn-primary" type="submit">Create account</button></div>
</form>
</main>
<footer class="footer">© 2025 ABC Medicos</footer></body></html>
