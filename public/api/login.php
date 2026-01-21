<?php
// public/api/login.php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/supabase.php';
require_once __DIR__ . '/../../app/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo "Method Not Allowed";
  exit;
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
$pass  = (string)($_POST['password'] ?? '');

if (!$email || !$pass) {
  redirect('/?error=' . rawurlencode('Informe email e senha.'));
  exit;
}

// 1) login no Supabase Auth
$res = supabase_request(
  'POST',
  '/auth/v1/token?grant_type=password',
  [],
  ['email' => $email, 'password' => $pass]
);

if (!$res['ok']) {
  redirect('/?error=' . rawurlencode('Credenciais inválidas.'));
  exit;
}

$user    = $res['data']['user'] ?? null;
$session = $res['data'] ?? null;

$userId    = (string)($user['id'] ?? '');
$userEmail = (string)($user['email'] ?? $email);

// 2) busca user_key + trial_ends_at no profiles (prioridade: email, depois id)
$userKey        = null;
$trialEndsAtRaw = null;

$p = supabase_request('GET', '/rest/v1/profiles', [
  'select' => 'user_key,full_name,trial_ends_at',
  'email'  => "eq.{$userEmail}",
  'limit'  => '1',
]);

if (($p['ok'] ?? false) && !empty($p['data'][0])) {
  if (!empty($p['data'][0]['user_key'])) {
    $userKey = (string)$p['data'][0]['user_key'];
  }
  if (!empty($p['data'][0]['trial_ends_at'])) {
    $trialEndsAtRaw = (string)$p['data'][0]['trial_ends_at'];
  }
}

if ((!$userKey || !$trialEndsAtRaw) && $userId) {
  $p2 = supabase_request('GET', '/rest/v1/profiles', [
    'select' => 'user_key,full_name,trial_ends_at',
    'id'     => "eq.{$userId}",
    'limit'  => '1',
  ]);

  if (($p2['ok'] ?? false) && !empty($p2['data'][0])) {
    if (!$userKey && !empty($p2['data'][0]['user_key'])) {
      $userKey = (string)$p2['data'][0]['user_key'];
    }
    if (!$trialEndsAtRaw && !empty($p2['data'][0]['trial_ends_at'])) {
      $trialEndsAtRaw = (string)$p2['data'][0]['trial_ends_at'];
    }
  }
}

// 3) check trial (sem quebrar login se der erro)
$trialExpired = false;

if ($trialEndsAtRaw) {
  // "2026-01-19 20:42:27+00" -> "2026-01-19T20:42:27+00:00"
  $normalized = preg_replace(
    '/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})\+(\d{2})$/',
    '$1T$2+$3:00',
    $trialEndsAtRaw
  );

  try {
    $dt = new DateTimeImmutable($normalized ?: $trialEndsAtRaw);
    if ($dt->getTimestamp() < time()) {
      $trialExpired = true;
    }
  } catch (\Throwable $e) {
    // se der erro de parse, não bloqueia login
    $trialExpired = false;
  }
}

// ✅ IMPORTANTE: se expirou, NÃO cria sessão
if ($trialExpired) {
  redirect('/?trial_expired=1');
  exit;
}

// 4) só aqui loga de verdade
login_user($user, $session, $userKey);

redirect('/app.php');
exit;