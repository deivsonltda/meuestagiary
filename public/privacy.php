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
  <title>Política de Privacidade · <?= h(app_config('app_name')) ?></title>
  <link rel="icon" href="/assets/img/favicon.ico" sizes="any">
  <link rel="icon" href="/assets/img/favicon.png" type="image/png">
  <link rel="apple-touch-icon" href="/assets/img/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= h($base) ?>/assets/css/app.css?v=<?= (int)$css_ver ?>">
</head>

<body class="auth-body legal">
  <div class="auth-card legal-card">

    <h1 class="auth-title">Política de Privacidade</h1>
    <p class="auth-subtitle">Última atualização: <?= date('d/m/Y') ?></p>

    <div class="small muted" style="line-height:1.7;">
      <p>
        A sua privacidade é importante para nós. Esta Política de Privacidade descreve
        como o <strong><?= h(app_config('app_name')) ?></strong> coleta, utiliza, armazena
        e protege os dados pessoais dos usuários.
      </p>

      <h3>1. Dados que coletamos</h3>
      <p>Podemos coletar as seguintes informações:</p>
      <ul>
        <li>Dados de cadastro (nome, e-mail)</li>
        <li>Dados de autenticação e sessão</li>
        <li>Informações de uso da aplicação</li>
        <li>Dados de integrações autorizadas pelo usuário (ex: Google Agenda)</li>
      </ul>

      <h3>2. Integração com o Google Agenda</h3>
      <p>
        Quando você opta por integrar sua conta do Google Agenda, coletamos apenas
        as permissões estritamente necessárias para sincronizar compromissos.
        Nenhuma informação é acessada sem o seu consentimento explícito.
      </p>

      <h3>3. Uso das informações</h3>
      <p>Utilizamos os dados para:</p>
      <ul>
        <li>Fornecer e melhorar nossos serviços</li>
        <li>Gerenciar sua conta e preferências</li>
        <li>Exibir compromissos e dados financeiros organizados</li>
        <li>Cumprir obrigações legais</li>
      </ul>

      <h3>4. Armazenamento e segurança</h3>
      <p>
        Os dados são armazenados em infraestrutura segura e utilizamos medidas
        técnicas e organizacionais para protegê-los contra acesso não autorizado.
      </p>

      <h3>5. Compartilhamento de dados</h3>
      <p>
        Não vendemos, alugamos ou compartilhamos seus dados pessoais com terceiros,
        exceto quando necessário para o funcionamento do serviço ou por obrigação legal.
      </p>

      <h3>6. Seus direitos</h3>
      <p>
        Você pode, a qualquer momento, solicitar acesso, correção ou exclusão
        de seus dados pessoais, conforme previsto na LGPD.
      </p>

      <h3>7. Alterações nesta política</h3>
      <p>
        Esta política pode ser atualizada periodicamente. Recomendamos que você
        a revise regularmente.
      </p>

      <p>
        Em caso de dúvidas, entre em contato conosco através da plataforma.
      </p>
    </div>

    <div class="auth-links">
      <a href="<?= h($base) ?>/index.php">Voltar</a>
    </div>
  </div>
</body>

</html>