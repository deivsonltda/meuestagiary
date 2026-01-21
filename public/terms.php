<?php
require_once __DIR__ . '/../app/bootstrap.php';
$base = app_config('base_url', '');

$cssPath = __DIR__ . '/assets/css/app.css';
$css_ver = @filemtime($cssPath) ?: time();
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Termos de Uso · <?= h(app_config('app_name')) ?></title>
  <link rel="icon" href="/assets/img/favicon.ico" sizes="any">
  <link rel="icon" href="/assets/img/favicon.png" type="image/png">
  <link rel="apple-touch-icon" href="/assets/img/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= h($base) ?>/assets/css/app.css?v=<?= (int)$css_ver ?>">
</head>

<body class="auth-body">
  <div class="auth-card legal-card">

    <h1 class="auth-title">Termos de Uso</h1>
    <p class="auth-subtitle">Última atualização: <?= date('d/m/Y') ?></p>

    <div class="small muted" style="line-height:1.7;">
      <p>
        Ao acessar ou utilizar o <strong><?= h(app_config('app_name')) ?></strong>,
        você concorda com os termos e condições descritos neste documento.
      </p>

      <h3>1. Uso da plataforma</h3>
      <p>
        O usuário compromete-se a utilizar a plataforma de forma lícita,
        respeitando a legislação vigente e os direitos de terceiros.
      </p>

      <h3>2. Conta do usuário</h3>
      <p>
        O usuário é responsável por manter a confidencialidade de suas credenciais
        de acesso e por todas as atividades realizadas em sua conta.
      </p>

      <h3>3. Integrações externas</h3>
      <p>
        O uso de integrações, como o Google Agenda, é opcional e depende de autorização
        explícita do usuário. O <?= h(app_config('app_name')) ?> não se responsabiliza
        por falhas ou indisponibilidade de serviços de terceiros.
      </p>

      <h3>4. Limitação de responsabilidade</h3>
      <p>
        A plataforma é fornecida “como está”. Não garantimos que o serviço estará
        disponível de forma ininterrupta ou livre de erros.
      </p>

      <h3>5. Suspensão ou encerramento</h3>
      <p>
        Reservamo-nos o direito de suspender ou encerrar contas que violem estes
        Termos de Uso, sem aviso prévio.
      </p>

      <h3>6. Alterações nos termos</h3>
      <p>
        Estes termos podem ser modificados a qualquer momento. O uso contínuo
        da plataforma após alterações implica concordância com os novos termos.
      </p>

      <h3>7. Foro</h3>
      <p>
        Fica eleito o foro da legislação brasileira para dirimir quaisquer
        controvérsias decorrentes deste documento.
      </p>
    </div>

    <div class="auth-links">
      <a href="<?= h($base) ?>/index.php">Voltar</a>
    </div>
  </div>
</body>

</html>