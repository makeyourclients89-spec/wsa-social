<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="Login to WSA Multi-Channel Publisher Portal.">
  <title>Login - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="assets/app.css">
</head>
<body class="login">
  <form class="loginbox" method="post" action="index.php?page=login">
    <div class="loginlogo"><?= Icons::svg('zap', 24, 'brand-icon') ?></div>
    <h1>WSA Publisher</h1>
    <p>Multi-Channel Management Portal</p>
    <?= csrf_field() ?>
    <?php if ($e = flash('error')): ?>
      <div class="alert err" role="alert"><?= h($e) ?></div>
    <?php endif; ?>
    <label for="login_email">Email
      <input id="login_email" name="email" type="email" placeholder="admin@example.com" required>
    </label>
    <label for="login_password">Password
      <input id="login_password" name="password" type="password" placeholder="Enter password" required>
    </label>
    <button type="submit">
      <?= Icons::svg('shield', 16) ?>
      <span>Sign in</span>
    </button>
    <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0;display:flex;justify-content:center;gap:10px;font-size:11px;color:#64748b;flex-wrap:wrap;">
      <a href="index.php?page=privacy" style="color:#2563eb;text-decoration:none;font-weight:600;">Privacy Policy</a>
      <span>•</span>
      <a href="index.php?page=terms" style="color:#2563eb;text-decoration:none;font-weight:600;">Terms of Service</a>
      <span>•</span>
      <a href="index.php?page=data-deletion" style="color:#2563eb;text-decoration:none;font-weight:600;">Data Deletion</a>
    </div>
  </form>
</body>
</html>
