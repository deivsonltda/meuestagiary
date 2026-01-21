<?php
require_once __DIR__ . '/../app/auth.php';

if (is_logged_in()) redirect('/app.php');

$error = trim((string)($_GET['error'] ?? ''));
$resetOk = (($_GET['reset'] ?? '') === 'success');
$trialExpired = (($_GET['trial_expired'] ?? '') === '1');

$base  = app_config('base_url', '');
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h(app_config('app_name')) ?> — Login</title>
  <link rel="icon" href="/assets/img/favicon.ico" sizes="any">
  <link rel="icon" href="/assets/img/favicon.png" type="image/png">
  <link rel="apple-touch-icon" href="/assets/img/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h($base) ?>/assets/css/app.css">

  <!-- Se o Supabase cair na raiz com #access_token...&type=recovery, manda pro reset.php -->
  <script>
    (function() {
      var h = window.location.hash || "";
      if (!h) return;

      var lower = h.toLowerCase();
      var isRecovery = lower.indexOf("type=recovery") !== -1;
      var hasToken = (lower.indexOf("access_token=") !== -1) || (lower.indexOf("code=") !== -1);
      if (!(isRecovery && hasToken)) return;

      var base = "<?= rtrim((string)$base, '/') ?>";
      var path = window.location.pathname || "";
      if (path.endsWith("/reset.php")) return;

      window.location.replace(base + "/reset.php" + h);
    })();
  </script>

  <?php if ($trialExpired): ?>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        let seconds = 3;
        const redirectUrl = "<?= h($base) ?>/checkout.php";

        const card = document.querySelector(".auth-card");
        if (!card) return;

        card.innerHTML = `
          <div class="auth-brand">
            <img src="/assets/img/icone.png" alt="MeuEstagiário" class="brand-icon">
            <span class="brand-text">MeuEstagiário</span>
          </div>

          <h1 class="auth-title">Período de teste expirado</h1>
          <p class="auth-subtitle">
            Seu período de teste terminou.<br>
            Você será direcionado em <strong id="cd">${seconds}</strong>...
          </p>
        `;

        const cd = document.getElementById("cd");

        const timer = setInterval(() => {
          seconds--;
          if (seconds <= 0) {
            clearInterval(timer);
            window.location.href = redirectUrl;
          } else if (cd) {
            cd.textContent = seconds;
          }
        }, 1000);
      });
    </script>
  <?php endif; ?>
</head>

<body class="auth-body">
  <div class="auth-card">
    <div class="auth-brand">
      <img src="/assets/img/icone.png" alt="MeuEstagiário" class="brand-icon">
      <span class="brand-text">MeuEstagiário</span>
    </div>

    <h1 class="auth-title">Bem-vindo de volta</h1>
    <p class="auth-subtitle">Entre com seu email e senha</p>

    <?php if ($resetOk): ?>
      <div class="alert" style="background:#ecfdf5;border-color:rgba(22,163,74,.25);color:#14532d;">
        Senha alterada com sucesso. Faça login com a nova senha.
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= h($base) ?>/api/login.php" class="auth-form">
      <label class="field">
        <span>Email</span>
        <input type="email" name="email" placeholder="Digite seu e-mail" autocomplete="email" required />
      </label>

      <label class="field">
        <span>Senha</span>
        <input type="password" name="password" placeholder="Digite sua senha" autocomplete="current-password" required />
      </label>

      <button class="btn btn-black" type="submit">Entrar</button>

      <div class="auth-links">
        <a href="<?= h($base) ?>/forgot.php">Esqueci minha senha</a>
      </div>
    </form>
  </div>
</body>

</html>