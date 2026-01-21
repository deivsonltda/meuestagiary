<?php
$base = app_config('base_url', '');
$current = $_GET['page'] ?? 'dashboard';
?>
<aside class="sidebar" id="sidebar" aria-hidden="true">

  <!-- topo do menu: só o X na ESQUERDA (igual Assessor) -->
  <div class="sidebar-head">
    <button class="sidebar-close" id="btnCloseSidebar" aria-label="Fechar menu" type="button">✕</button>
  </div>

  <nav class="sidebar-links">
    <a class="<?= $current === 'dashboard' ? 'active' : '' ?>" href="<?= h($base) ?>/app.php?page=dashboard">
      <i class="fa-solid fa-house"></i>
      <span>Visão Geral</span>
    </a>

    <a class="<?= $current === 'transactions' ? 'active' : '' ?>" href="<?= h($base) ?>/app.php?page=transactions">
      <i class="fa-regular fa-rectangle-list"></i>
      <span>Transações</span>
    </a>

    <a class="<?= $current === 'cards' ? 'active' : '' ?>" href="<?= h($base) ?>/app.php?page=cards">
      <i class="fa-regular fa-credit-card"></i>
      <span>Cartões de crédito</span>
    </a>

    <a class="<?= $current === 'categories' ? 'active' : '' ?>" href="<?= h($base) ?>/app.php?page=categories">
      <i class="fa-regular fa-bookmark"></i>
      <span>Minhas Categorias</span>
    </a>

    <a class="<?= $current === 'agenda' ? 'active' : '' ?>" href="<?= h($base) ?>/app.php?page=agenda">
      <i class="fa-regular fa-calendar"></i>
      <span>Agenda</span>
    </a>

    <a class="<?= $current === 'account' ? 'active' : '' ?>" href="<?= h($base) ?>/app.php?page=account&tab=perfil">
      <i class="fa-regular fa-user"></i>
      <span>Minha Conta</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="<?= h($base) ?>/logout.php">
      <i class="fa-solid fa-right-from-bracket"></i>
      <span>Sair</span>
    </a>
  </div>
</aside>

<div class="backdrop" id="backdrop"></div>