<?php
// app/pages/account.php

require_once __DIR__ . '/../supabase.php';
require_once __DIR__ . '/../auth.php';

$tab = strtolower($_GET['tab'] ?? 'perfil');
$tabs = [
  'perfil' => 'Perfil',
  'plano' => 'Plano',
  'integracoes' => 'Integrações',
];
if (!isset($tabs[$tab])) $tab = 'perfil';

function tab_url($key)
{
  return rtrim(app_config('base_url', ''), '/') . '/app.php?page=account&tab=' . urlencode($key);
}

function phone_display($phone): string
{
  $p = preg_replace('/\D+/', '', (string)$phone);
  if ($p === '') return '';
  if (strlen($p) >= 12 && str_starts_with($p, '55')) {
    $p = substr($p, 2);
  }
  return $p;
}

// ---------------------------------------------------------
// Busca profile no Supabase: full_name, phone, flag Google Calendar e billing_cycle
// ---------------------------------------------------------
$full_name      = (string)($_SESSION['user']['name'] ?? '');
$email          = strtolower(trim((string)($_SESSION['user']['email'] ?? '')));
$phone          = '';
$gc_enabled     = false;
$billing_cycle  = 'monthly';

if ($email !== '') {
  try {
    $res = supabase_request('GET', '/rest/v1/profiles', [
      'select' => 'full_name,phone,google_calendar_enabled,billing_cycle',
      'email'  => 'eq.' . $email,
      'limit'  => 1,
    ]);

    if (!empty($res['ok']) && !empty($res['data'][0])) {
      $row = $res['data'][0];

      $full_name  = (string)($row['full_name'] ?? $full_name);
      $phone      = (string)($row['phone'] ?? '');
      $gc_enabled = !empty($row['google_calendar_enabled']);

      $billing_cycle = (string)($row['billing_cycle'] ?? 'monthly');
      if ($billing_cycle !== 'annual') $billing_cycle = 'monthly';
    }
  } catch (\Throwable $e) {
    // silêncio proposital
  }
}

$phone_ui = phone_display($phone);

$_SESSION['user']['name']  = $full_name;
$_SESSION['user']['phone'] = $phone_ui;

// urls úteis
$base = rtrim(app_config('base_url', ''), '/');
$returnUrl = $base . '/app.php?page=account&tab=integracoes';
$oauthStartUrl = $base . '/api/google/oauth_start.php?return=' . urlencode($returnUrl);

$user_key = current_user_key();
?>
<script>
  window.__PAGE__ = 'account';
  window.__USER_KEY__ = <?= json_encode($user_key ?: '') ?>;
</script>

<div class="page">
  <div class="account-tabs">
    <?php foreach ($tabs as $key => $label): ?>
      <a class="account-tab <?= $tab === $key ? 'active' : '' ?>" href="<?= tab_url($key) ?>">
        <?= h($label) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($tab === 'perfil'): ?>
    <div class="card">
      <div class="card-pad">
        <div class="account-head">
          <div>
            <h2 class="account-title">Minhas Informações</h2>
          </div>
          <div class="account-actions">
            <button class="btn btn-ghost" id="btnSaveProfile" type="button">Salvar</button>
            <button class="btn btn-ghost" id="btnChangePassword" type="button">Alterar senha</button>
          </div>
        </div>

        <div class="account-grid-3">
          <div class="form-row">
            <label>NOME</label>
            <input id="accFullName" type="text" value="<?= h($full_name); ?>" />
          </div>

          <div class="form-row">
            <label>E-MAIL</label>
            <input type="text" value="<?= h($email); ?>" disabled />
          </div>

          <div class="form-row">
            <label>TELEFONE</label>
            <input id="accPhone" type="text" value="<?= h($phone_ui); ?>" placeholder="Ex: 81998517063" />
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-pad">
        <h3 class="account-subtitle">Convidar Novo Usuário</h3>
        <p class="muted small" style="margin-top:6px;">
          Gere um código e envie para a pessoa que deseja se conectar à sua conta.
          Ela poderá enviar esse código no WhatsApp para iniciar o cadastro automaticamente.
        </p>

        <div class="account-center">
          <button class="btn btn-black" style="width:auto; padding:12px 18px;" type="button">
            + Gerar Código de Convite
          </button>
        </div>
      </div>
    </div>

  <?php elseif ($tab === 'plano'): ?>

    <?php
    $is_annual = ($billing_cycle === 'annual');
    $price     = $is_annual ? '19,90' : '29,90';
    $period    = $is_annual ? '/ano' : '/mês';
    ?>

    <div class="card">
      <div class="card-pad">
        <div class="plan-head">
          <h2 class="account-title">Gerenciar Assinatura</h2>

          <div class="plan-toggle">
            <span class="muted small">Mensal</span>
            <label class="switch" title="Alternar ciclo de cobrança">
              <input
                id="planBillingToggle"
                type="checkbox"
                <?= $is_annual ? 'checked' : '' ?> />
              <span class="slider"></span>
            </label>
            <span class="muted small">Anual</span>
          </div>
        </div>

        <div class="plan-card">
          <div class="plan-left">
            <div class="plan-name">MeuEstagiário - Completo</div>
            <div class="muted small">Acesso total às funcionalidades</div>

            <ul class="plan-list">
              <li>✓ Perguntas para seu Assessor IA</li>
              <li>✓ Dashboard para acompanhamento</li>
              <li>✓ Controle de transações</li>
              <li>✓ Saldo e fluxo de caixa</li>
              <li>✓ Lembretes diários</li>
              <li>✓ Agenda integrada</li>
              <li>✓ Ajuda com mensagens</li>
            </ul>

            <div class="plan-price">
              <span class="plan-value">R$ <span id="planPrice"><?= h($price) ?></span></span>
              <span class="muted small" id="planPeriod"><?= h($period) ?></span>
            </div>
          </div>

          <div class="plan-right">
            <button class="btn btn-black" style="width:auto; padding:12px 18px;" type="button">
              Assinar plano
            </button>
          </div>
        </div>
      </div>
    </div>

  <?php else: /* integracoes */ ?>
    <div class="card">
      <div class="card-pad">
        <div class="integr-head">
          <div>
            <h3 class="integr-title">Integração Google Agenda (Beta)</h3>
            <p class="integr-desc">
              Conecte a sua agenda do Google para que os compromissos da sua agenda
              sejam recebidos no MeuAssessor.com. Você pode conectar quantos e-mails quiser.
            </p>
          </div>

          <label class="integr-toggle">
            <span>Ativar integração com Google Agenda</span>
            <div class="switch">
              <input
                type="checkbox"
                id="gcToggle"
                <?= $gc_enabled ? 'checked' : '' ?>>
              <span class="slider"></span>
            </div>
          </label>
        </div>

        <div id="gcArea" class="<?= $gc_enabled ? '' : 'is-hidden' ?>">

          <div id="gcConnected" class="gc-box is-hidden">
            <div class="gc-box-row">
              <div class="gc-box-title">Conta conectada</div>
              <div class="gc-box-email" data-gcal-email>—</div>
            </div>

            <button id="gcRemoveBtn" class="btn btn-ghost gc-remove" type="button">
              Remover conta
            </button>
          </div>

          <div id="gcEmpty" class="gc-box">
            <div class="gc-empty-msg">
              Você ainda não conectou nenhuma conta do Google Agenda.
            </div>
          </div>

          <div class="integr-action">
            <a href="<?= h($oauthStartUrl) ?>" class="gc-btn" role="button">
              Conectar ao Google Agenda
            </a>
          </div>

        </div>
      </div>
    </div>
  <?php endif; ?>
</div>