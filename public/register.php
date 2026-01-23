<?php
require_once __DIR__ . '/../app/bootstrap.php';

$base = app_config('base_url', '');

// 1) captura parâmetros do link (onboarding)
$tok  = trim((string)($_GET['tok'] ?? ''));
$uk   = trim((string)($_GET['uk']  ?? ''));
$chat = trim((string)($_GET['chat'] ?? ''));
$src  = trim((string)($_GET['src'] ?? 'whatsapp'));

// 2) captura tracking do link
$track = [
  'lid' => trim((string)($_GET['lid'] ?? '')),
  'ref' => trim((string)($_GET['ref'] ?? '')),
  'utm_source'   => trim((string)($_GET['utm_source'] ?? '')),
  'utm_medium'   => trim((string)($_GET['utm_medium'] ?? '')),
  'utm_campaign' => trim((string)($_GET['utm_campaign'] ?? '')),
  'utm_content'  => trim((string)($_GET['utm_content'] ?? '')),
  'utm_term'     => trim((string)($_GET['utm_term'] ?? '')),
];

// salva cookie para o bot/N8N conferir depois
$cookiePayload = json_encode($track, JSON_UNESCAPED_UNICODE);
setcookie('sb_reg_track', $cookiePayload, [
  'expires' => time() + (60 * 60 * 24 * 30), // 30 dias
  'path' => '/',
  'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
  'httponly' => false,
  'samesite' => 'Lax',
]);

$success = isset($_GET['success']) && $_GET['success'] === '1';
$error = trim((string)($_GET['error'] ?? ''));
$redir = isset($_GET['redir']) && $_GET['redir'] === '1';

// WhatsApp destino (mantive igual ao que você já tinha)
$waUrl = "https://wa.me/5581936181079";

// Se concluiu e é pra redirecionar, mostra tela full-screen (sem mexer no fluxo)
if ($success && $redir) {
?>
  <!doctype html>
  <html lang="pt-br">

  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= h(app_config('app_name')) ?> — Cadastro concluído</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h($base) ?>/assets/css/app.css">
    <style>
      .finish-wrap {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #ffffff;
        padding: 24px;
      }

      .finish-box {
        width: min(520px, 100%);
        text-align: center;
      }

      .finish-spinner {
        width: 64px;
        height: 64px;
        border-radius: 999px;
        border: 5px solid rgba(22, 163, 74, .18);
        border-top-color: #16a34a;
        margin: 0 auto 16px auto;
        animation: spin 900ms linear infinite;
      }

      @keyframes spin {
        to {
          transform: rotate(360deg);
        }
      }

      .finish-title {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 6px 0;
      }

      .finish-sub {
        color: rgba(15, 23, 42, .65);
        font-size: 14px;
        margin: 0;
      }

      .finish-sub b {
        color: #0f172a;
      }

      .finish-hint {
        margin-top: 14px;
        font-size: 13px;
        color: rgba(15, 23, 42, .55);
      }

      .finish-hint a {
        color: #16a34a;
        font-weight: 700;
        text-decoration: none;
      }
    </style>
  </head>

  <body>
    <div class="finish-wrap">
      <div class="finish-box">
        <div class="finish-spinner" aria-hidden="true"></div>
        <h1 class="finish-title">Cadastro concluído.</h1>
        <p class="finish-sub">
          Você será redirecionado em <b id="sec">3</b>...
        </p>
        <div class="finish-hint">
          Se não redirecionar, <a href="<?= h($waUrl) ?>">clique aqui</a>.
        </div>
      </div>
    </div>

    <script>
      (function() {
        var s = 3;
        var el = document.getElementById('sec');
        var t = setInterval(function() {
          s--;
          if (el) el.textContent = String(s);
          if (s <= 0) {
            clearInterval(t);
            window.location.href = "<?= h($waUrl) ?>";
          }
        }, 1000);
      })();
    </script>
  </body>

  </html>
<?php
  exit;
}
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h(app_config('app_name')) ?> — Cadastro</title>
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

    <h1 class="auth-title">Crie sua conta</h1>
    <p class="auth-subtitle">Preencha seus dados para acessar a plataforma</p>

    <?php if ($success): ?>
      <div class="alert" style="background:#ecfdf5;border-color:rgba(22,163,74,.25);color:#14532d;">
        Cadastro concluído. <a href="<?= h($base) ?>/index.php" style="font-weight:700;">Entre e faça login</a>.
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= h($base) ?>/api/register.php" class="auth-form">
      <!-- onboarding params -->
      <input type="hidden" name="tok" value="<?= h($tok) ?>">
      <input type="hidden" name="uk" value="<?= h($uk) ?>">
      <input type="hidden" name="chat" value="<?= h($chat) ?>">
      <input type="hidden" name="src" value="<?= h($src) ?>">

      <!-- tracking params -->
      <input type="hidden" name="lid" value="<?= h($track['lid']) ?>">
      <input type="hidden" name="ref" value="<?= h($track['ref']) ?>">
      <input type="hidden" name="utm_source" value="<?= h($track['utm_source']) ?>">
      <input type="hidden" name="utm_medium" value="<?= h($track['utm_medium']) ?>">
      <input type="hidden" name="utm_campaign" value="<?= h($track['utm_campaign']) ?>">
      <input type="hidden" name="utm_content" value="<?= h($track['utm_content']) ?>">
      <input type="hidden" name="utm_term" value="<?= h($track['utm_term']) ?>">

      <label class="field">
        <span>Nome completo</span>
        <input type="text" name="full_name" placeholder="Digite seu nome completo" required />
      </label>

      <label class="field">
        <span>E-mail</span>
        <input type="email" name="email" placeholder="Digite seu e-mail" autocomplete="email" required />
      </label>

      <label class="field">
        <span>Telefone</span>
        <input type="tel" name="phone" placeholder="(DDD) 9xxxx-xxxx" required />
      </label>

      <label class="field">
        <span>Senha</span>
        <input type="password" name="password" placeholder="Crie uma senha" minlength="8" required />
      </label>

      <button class="btn btn-black" type="submit">Criar conta</button>

      <div class="auth-links">
        <a href="<?= h($base) ?>/index.php">Já tenho conta</a>
      </div>
    </form>

    <div class="muted small" style="margin-top:10px;text-align:center;">
      Ao criar sua conta, seu dispositivo pode receber um cookie para concluir o rastreio do cadastro.
    </div>
  </div>
</body>

</html>