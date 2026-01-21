<?php
require_once __DIR__ . '/../app/bootstrap.php';

$base = app_config('base_url', '');
$success = isset($_GET['success']) && $_GET['success'] === '1';
$error = trim((string)($_GET['error'] ?? ''));
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h(app_config('app_name')) ?> — Recuperar senha</title>
  <link rel="icon" href="/assets/img/favicon.ico" sizes="any">
  <link rel="icon" href="/assets/img/favicon.png" type="image/png">
  <link rel="apple-touch-icon" href="/assets/img/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h($base) ?>/assets/css/app.css">
</head>

<body class="auth-body">
  <div class="auth-card">
    <div class="auth-brand">
      <img src="/assets/img/icone.png" alt="MeuEstagiário" class="brand-icon">
      <span class="brand-text">MeuEstagiário</span>
    </div>

    <h1 class="auth-title">Recuperar senha</h1>
    <p class="auth-subtitle">Vamos enviar um link para redefinir sua senha</p>

    <?php if ($success): ?>
      <div class="alert" style="background:#ecfdf5;border-color:rgba(22,163,74,.25);color:#14532d;">
        Se o e-mail existir, enviamos um link de recuperação. Verifique sua caixa de entrada e spam.
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= h($base) ?>/api/forgot_password.php" class="auth-form">
      <label class="field">
        <span>Email</span>
        <input type="email" name="email" placeholder="Digite seu e-mail" autocomplete="email" required />
      </label>

      <button class="btn btn-black" type="submit">Enviar link</button>

      <div class="auth-links">
        <a href="<?= h($base) ?>/index.php">Voltar ao login</a>
      </div>
    </form>
  </div>
</body>

</html>