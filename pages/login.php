<?php require_once __DIR__ . '/_guard.php'; ?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administrator Login | SSU Library</title>
  <link rel="stylesheet" href="<?= escapeHtml($basePath) ?>/assets/php-app.css">
</head>

<body class="login-page">
  <main class="login-card">
    <section class="login-brand">
      <p class="eyebrow">Samar State University</p>
      <h1>Library Access Portal</h1>
      <p>Secure student entry, QR verification, and visitor logging for the library.</p>
      <div class="login-note"><strong>Quick verification</strong><br>Scan a student QR code to validate the account, log
        the visit, and prevent duplicate entry attempts.</div>
    </section>
    <section class="login-form">
      <p class="eyebrow">Administrator Login</p>
      <h2>Welcome back</h2>
      <?php if ($loginError): ?>
        <div class="alert error" role="alert"><?= escapeHtml($loginError) ?></div><?php endif; ?>
      <form method="post" action="index.php" class="stack">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
        <label>Email address<input type="email" name="email" autocomplete="username" required></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="button primary full" type="submit">Sign in</button>
      </form>
    </section>
  </main>
</body>

</html>
<!-- End: login page -->