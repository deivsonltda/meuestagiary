<?php
$base = app_config('base_url', '');
$current = $_GET['page'] ?? 'dashboard';
?>
<header class="topbar">

  <button class="burger-btn" id="btnBurger" aria-label="Abrir menu">
    <i class="fa-solid fa-bars"></i>
  </button>

  <!-- Logo central (mobile) -->
  <div class="topbar-brand">
    <img src="<?= h($base) ?>/assets/img/icone.png" alt="MeuEstagiário" class="brand-icon">
    <div class="brand-text"><?= h(app_config('app_name')) ?></div>
  </div>

  <nav class="topbar-nav">
    <a class="<?= $current === 'dashboard' ? 'active' : '' ?>" href="<?= h($base) ?>/app.php?page=dashboard">Visão Geral</a>
    <a class="<?= $current === 'transactions' ? 'active' : '' ?>" href="<?= h($base) ?>/app.php?page=transactions">Transações</a>
    <a class="<?= $current === 'cards' ? 'active' : '' ?>" href="<?= h($base) ?>/app.php?page=cards">Cartões de crédito</a>
    <a class="<?= $current === 'categories' ? 'active' : '' ?>" href="<?= h($base) ?>/app.php?page=categories">Minhas Categorias</a>
    <a class="<?= $current === 'agenda' ? 'active' : '' ?>" href="<?= h($base) ?>/app.php?page=agenda">Agenda</a>
  </nav>

  <!-- espaço “fantasma” p/ manter o centro perfeito no mobile (igual assessor) -->
  <div class="topbar-spacer" aria-hidden="true"></div>

  <div class="topbar-right">
    <div class="user-actions">
      <a class="ua-btn ua-account <?= $current === 'account' ? 'active' : '' ?>"
        href="<?= h($base) ?>/app.php?page=account&tab=perfil" title="Minha Conta">
        <i class="fa-regular fa-user"></i>
        <span>Minha Conta</span>
      </a>

      <span class="ua-divider" aria-hidden="true"></span>

      <a class="ua-btn ua-logout"
        href="<?= h($base) ?>/logout.php" title="Sair">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Sair</span>
      </a>
    </div>
  </div>
</header>