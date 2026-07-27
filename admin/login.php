<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (auth_check()) {
    redirect('/admin/');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Enter username and password.';
    } elseif (auth_login($username, $password)) {
        redirect('/admin/');
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login — NCST Main Feed</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
  <div class="admin-wrap">
    <div class="admin-card" style="max-width:420px;margin:48px auto;">
      <h1>Admin Login</h1>
      <p style="color:var(--muted-60);margin:0 0 16px;font-size:14px;">NCST Main Feed</p>
      <?php if ($error): ?>
        <div class="admin-flash admin-flash--error"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post" action="/admin/login.php" autocomplete="username">
        <div class="form-row">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" required autofocus value="<?= e((string) ($_POST['username'] ?? '')) ?>">
        </div>
        <div class="form-row">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary" type="submit">Sign in</button>
        <a class="btn" href="/" style="margin-left:8px;">View feed</a>
      </form>
    </div>
  </div>
</body>
</html>
