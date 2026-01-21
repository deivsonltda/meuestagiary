<?php
// app/auth.php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function ensure_session(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }
}

function is_logged_in(): bool {
  ensure_session();
  return !empty($_SESSION['user']) && is_array($_SESSION['user']);
}

function current_user(): ?array {
  ensure_session();
  return (isset($_SESSION['user']) && is_array($_SESSION['user'])) ? $_SESSION['user'] : null;
}

/**
 * Retorna o user_key (uuid) do profile.
 * - Prioridade: $_SESSION['user_key']
 * - Fallback: consulta o profiles por id e/ou email e salva em sessão
 */
function current_user_key(): string {
  ensure_session();

  if (!empty($_SESSION['user_key'])) {
    return (string)$_SESSION['user_key'];
  }

  $u = current_user();
  if (!$u) return '';

  $userId = (string)($u['id'] ?? '');
  $email  = (string)($u['email'] ?? '');

  // Busca no profiles apenas quando necessário
  require_once __DIR__ . '/supabase.php';

  $uk = '';

  // tenta por id primeiro (se seu profiles.id estiver pareado com auth.users.id)
  if ($userId) {
    $r = supabase_request('GET', '/rest/v1/profiles', [
      'select' => 'user_key',
      'id'     => "eq.{$userId}",
      'limit'  => '1',
    ]);
    if (($r['ok'] ?? false) && !empty($r['data'][0]['user_key'])) {
      $uk = (string)$r['data'][0]['user_key'];
    }
  }

  // fallback por email (no seu fluxo atual é o mais confiável)
  if (!$uk && $email) {
    $r = supabase_request('GET', '/rest/v1/profiles', [
      'select' => 'user_key',
      'email'  => "eq.{$email}",
      'limit'  => '1',
    ]);
    if (($r['ok'] ?? false) && !empty($r['data'][0]['user_key'])) {
      $uk = (string)$r['data'][0]['user_key'];
    }
  }

  if ($uk) {
    $_SESSION['user_key'] = $uk;
    return $uk;
  }

  // Último fallback (não ideal): evita quebrar geral, mas pode dar lista vazia
  return '';
}

function login_user(array $user, array $session, ?string $userKey = null): void {
  ensure_session();

  $_SESSION['user'] = [
    'id'    => (string)($user['id'] ?? ''),
    'email' => (string)($user['email'] ?? ''),
    'name'  => (string)($user['user_metadata']['full_name'] ?? ''),
  ];

  $_SESSION['sb_session'] = [
    'access_token'  => (string)($session['access_token'] ?? ''),
    'refresh_token' => (string)($session['refresh_token'] ?? ''),
    'expires_at'    => (int)($session['expires_at'] ?? 0),
  ];

  if ($userKey) {
    $_SESSION['user_key'] = $userKey;
  } else {
    unset($_SESSION['user_key']); // força recálculo quando precisar
  }
}

function logout_user(): void {
  ensure_session();
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params['path'], $params['domain'],
      $params['secure'], $params['httponly']
    );
  }
  session_destroy();
}

function require_auth(): void {
  if (!is_logged_in()) {
    redirect('/?error=' . rawurlencode('Faça login para continuar.'));
    exit;
  }
}

function enforce_trial_active_or_redirect(): void
{
  // se não está logado, não há o que validar aqui
  if (!is_logged_in()) return;

  // pega user_key da sessão (ajuste o nome da chave se for diferente no seu auth.php)
  $userKey = (string)($_SESSION['user_key'] ?? '');
  if (!$userKey) return; // sem user_key: não bloqueia, não quebra

  try {
    // precisa do supabase_request (se auth.php já inclui supabase.php, ok; se não, inclua onde você chamar)
    $p = supabase_request('GET', '/rest/v1/profiles', [
      'select'   => 'trial_ends_at',
      'user_key' => "eq.{$userKey}",
      'limit'    => '1',
    ], null, [
      'Accept' => 'application/json'
    ]);

    if (!($p['ok'] ?? false) || empty($p['data'][0]['trial_ends_at'])) {
      return; // sem trial_ends_at: não bloqueia
    }

    $raw = (string)$p['data'][0]['trial_ends_at'];

    // normaliza: "2026-01-19 20:42:27+00" → "2026-01-19T20:42:27+00:00"
    $normalized = preg_replace(
      '/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})\+(\d{2})$/',
      '$1T$2+$3:00',
      $raw
    );

    $dt = new DateTimeImmutable($normalized ?: $raw);

    if ($dt->getTimestamp() < time()) {
      // derruba sessão e manda pro fluxo de expiração
      logout_user();               // usa teu logout existente
      redirect('/?trial_expired=1');
      exit;
    }
  } catch (Throwable $e) {
    // fail-open: não bloqueia para não quebrar o app se houver instabilidade
    return;
  }
}