<?php
require_once __DIR__ . '/../app/bootstrap.php';

$base = app_config('base_url', '');

// endpoint configurável
$apiUrl = (string)env('PASSWORD_RESET_API_URL', '');
if (!$apiUrl) {
  $apiUrl = rtrim((string)$base, '/') . '/api/reset_password.php';
}
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h(app_config('app_name')) ?> — Redefinir senha</title>
  <link rel="icon" href="/assets/img/favicon.ico" sizes="any">
  <link rel="icon" href="/assets/img/favicon.png" type="image/png">
  <link rel="apple-touch-icon" href="/assets/img/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h($base) ?>/assets/css/app.css">
  <style>
    .btn-loading {
      opacity: .85;
      pointer-events: none;
    }

    .btn .spinner {
      width: 16px;
      height: 16px;
      border-radius: 999px;
      border: 2px solid rgba(255, 255, 255, .35);
      border-top-color: #fff;
      display: inline-block;
      vertical-align: middle;
      margin-right: 8px;
      animation: spin 900ms linear infinite;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    .mono {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      font-size: 12px;
      white-space: pre-wrap;
      word-break: break-word;
      margin-top: 10px;
      padding: 10px;
      border: 1px solid rgba(0, 0, 0, .08);
      border-radius: 10px;
      background: rgba(0, 0, 0, .03);
      display: none;
    }
  </style>
</head>

<body class="auth-body">
  <div class="auth-card">
    <div class="auth-brand">
      <img src="/assets/img/icone.png" alt="MeuEstagiário" class="brand-icon">
      <span class="brand-text">MeuEstagiário</span>
    </div>

    <h1 class="auth-title">Redefinir senha</h1>
    <p class="auth-subtitle">Crie uma nova senha para sua conta</p>

    <div id="msg" class="alert" style="display:none;"></div>
    <div id="debugBox" class="mono"></div>

    <form id="formReset" class="auth-form" novalidate>
      <input type="hidden" id="access_token" value="">

      <label class="field">
        <span>Nova senha</span>
        <input type="password" id="p1" placeholder="Digite a nova senha" minlength="8" required />
      </label>

      <label class="field">
        <span>Confirmar senha</span>
        <input type="password" id="p2" placeholder="Confirme a nova senha" minlength="8" required />
      </label>

      <button id="btnSave" class="btn btn-black" type="submit">
        Salvar nova senha
      </button>

      <div class="auth-links">
        <a href="<?= h($base) ?>/">Voltar para o login</a>
      </div>
    </form>
  </div>

  <script>
    (function() {
      var base = "<?= rtrim((string)$base, '/') ?>";
      var apiUrl = "<?= h($apiUrl) ?>";

      var msgEl = document.getElementById('msg');
      var dbgEl = document.getElementById('debugBox');

      function showMsg(text, type) {
        msgEl.style.display = 'block';
        msgEl.textContent = text;

        if (type === 'success') {
          msgEl.style.background = '#ecfdf5';
          msgEl.style.borderColor = 'rgba(22,163,74,.25)';
          msgEl.style.color = '#14532d';
        } else {
          msgEl.style.background = '';
          msgEl.style.borderColor = '';
          msgEl.style.color = '';
        }
      }

      function showDebug(text) {
        if (!text) {
          dbgEl.style.display = 'none';
          dbgEl.textContent = '';
          return;
        }
        dbgEl.style.display = 'block';
        dbgEl.textContent = text;
      }

      function parseHash(hash) {
        hash = (hash || '').replace(/^#/, '');
        var out = {};
        hash.split('&').forEach(function(kv) {
          if (!kv) return;
          var p = kv.split('=');
          var k = decodeURIComponent(p[0] || '');
          var v = decodeURIComponent((p[1] || '').replace(/\+/g, ' '));
          out[k] = v;
        });
        return out;
      }

      var params = parseHash(window.location.hash);
      var token = params.access_token || '';
      var type = (params.type || '').toLowerCase();

      if (!token || type !== 'recovery') {
        showMsg('Link inválido. Solicite um novo link de recuperação.', 'error');
        return;
      }

      // Anti-reuso client-side
      try {
        var kUsed = 'pwreset_used_' + token.slice(0, 24);
        if (sessionStorage.getItem(kUsed) === '1') {
          showMsg('Este link já foi usado. Solicite um novo em "Esqueci minha senha".', 'error');
          return;
        }
      } catch (e) {}

      document.getElementById('access_token').value = token;

      var form = document.getElementById('formReset');
      var btn = document.getElementById('btnSave');

      function setLoading(on) {
        if (on) {
          btn.classList.add('btn-loading');
          btn.dataset.old = btn.innerHTML;
          btn.innerHTML = '<span class="spinner" aria-hidden="true"></span>Salvando...';
          btn.disabled = true;
        } else {
          btn.classList.remove('btn-loading');
          btn.disabled = false;
          if (btn.dataset.old) btn.innerHTML = btn.dataset.old;
        }
      }

      function safeSnippet(txt) {
        var s = String(txt || '').trim();
        if (s.length > 900) s = s.slice(0, 900) + '\n...';
        return s;
      }

      form.addEventListener('submit', async function(e) {
        e.preventDefault();
        showDebug('');

        var p1 = document.getElementById('p1').value || '';
        var p2 = document.getElementById('p2').value || '';

        if (p1.length < 8) {
          showMsg('A senha deve ter no mínimo 8 caracteres.', 'error');
          return;
        }
        if (p1 !== p2) {
          showMsg('As senhas não conferem.', 'error');
          return;
        }

        setLoading(true);

        try {
          var r = await fetch(apiUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            cache: 'no-store',
            body: JSON.stringify({
              access_token: token,
              password: p1
            })
          });

          var txt = await r.text();
          var data = null;
          try {
            data = JSON.parse(txt);
          } catch (_) {
            data = null;
          }

          if (!data) {
            showMsg('Resposta inesperada do servidor (não veio JSON).', 'error');
            showDebug(safeSnippet(txt));
            setLoading(false);
            return;
          }

          if (!r.ok || !data.ok) {
            var code = (data.code || '').toString();
            if (code === 'token_used') {
              showMsg('Este link já foi usado. Solicite um novo em "Esqueci minha senha".', 'error');
            } else if (code === 'otp_expired') {
              showMsg('Link inválido ou expirado. Solicite um novo em "Esqueci minha senha".', 'error');
            } else {
              showMsg(data.error || 'Falha ao redefinir senha. Tente novamente.', 'error');
            }
            if (txt && txt.trim()) showDebug(safeSnippet(txt));
            setLoading(false);
            return;
          }

          try {
            var kUsed = 'pwreset_used_' + token.slice(0, 24);
            sessionStorage.setItem(kUsed, '1');
          } catch (e) {}

          try {
            history.replaceState(null, '', window.location.pathname);
          } catch (e) {}

          window.location.href = base + '/?reset=success';
        } catch (err) {
          showMsg('Erro de conexão. Tente novamente.', 'error');
          setLoading(false);
        }
      });
    })();
  </script>
</body>

</html>