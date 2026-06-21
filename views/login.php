<?php /* @var string|null $error */ ?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Official Login</title><link rel="stylesheet" href="assets/app.css"></head><body>
<form class="login" method="post" action="index.php?page=login">
  <h2>Digital Inclusion Dashboard</h2>
  <?php if (!empty($error)): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <input name="username" placeholder="Username" required>
  <input name="password" type="password" placeholder="Password" required>
  <button>Sign in</button>
</form></body></html>
