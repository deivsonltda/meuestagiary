<?php
// public/api/register.php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/supabase.php';

function redirect_with_error(string $msg, array $q = []): void {
  $base = '/register.php';
  $qs = http_build_query(array_merge($q, ['error' => $msg]));
  header("Location: {$base}?{$qs}", true, 302);
  exit;
}

function redirect_with_ok(array $q = []): void {
  $base = '/register.php';
  $qs = http_build_query(array_merge($q, ['success' => '1', 'redir' => '1']));
  header("Location: {$base}?{$qs}", true, 302);
  exit;
}

function now_iso(): string {
  return gmdate('Y-m-d\TH:i:s\Z');
}

function add_days_iso(int $days): string {
  return gmdate('Y-m-d\TH:i:s\Z', time() + ($days * 86400));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo "Method Not Allowed";
  exit;
}

// onboarding params
$uk   = trim((string)($_POST['uk'] ?? ''));
$chat = trim((string)($_POST['chat'] ?? ''));
$tok  = trim((string)($_POST['tok'] ?? ''));
$src  = trim((string)($_POST['src'] ?? 'whatsapp'));

// tracking (opcional)
$q = [
  'uk' => $uk,
  'chat' => $chat,
  'tok' => $tok,
  'src' => $src,
  'lid' => trim((string)($_POST['lid'] ?? '')),
  'ref' => trim((string)($_POST['ref'] ?? '')),
  'utm_source' => trim((string)($_POST['utm_source'] ?? '')),
  'utm_medium' => trim((string)($_POST['utm_medium'] ?? '')),
  'utm_campaign' => trim((string)($_POST['utm_campaign'] ?? '')),
  'utm_content' => trim((string)($_POST['utm_content'] ?? '')),
  'utm_term' => trim((string)($_POST['utm_term'] ?? '')),
];

if (!$uk || !$chat || !$tok) {
  redirect_with_error('Link inválido. Volte ao WhatsApp e solicite um novo link.', $q);
}

// form
$name  = trim((string)($_POST['full_name'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$pass  = (string)($_POST['password'] ?? '');
$phone = preg_replace('/\D+/', '', (string)($_POST['phone'] ?? ''));

if (!$name || !$email || !$pass) {
  redirect_with_error('Preencha nome, email e senha.', $q);
}
if (strlen($pass) < 8) {
  redirect_with_error('Senha muito curta. Use no mínimo 8 caracteres.', $q);
}

// 1) valida onboarding_requests (status pending)
$check = supabase_request(
  'GET',
  '/rest/v1/onboarding_requests',
  [
    'select' => 'id,user_key,wa_chat_id,token,status',
    'user_key' => "eq.{$uk}",
    'wa_chat_id' => "eq.{$chat}",
    'token' => "eq.{$tok}",
    'status' => 'eq.pending',
    'limit' => '1'
  ]
);

if (!$check['ok'] || empty($check['data']) || !is_array($check['data']) || count($check['data']) === 0) {
  redirect_with_error('Esse link expirou ou já foi usado. Volte ao WhatsApp e solicite um novo.', $q);
}

$onb = $check['data'][0];
$onbId = $onb['id'] ?? null;
if (!$onbId) {
  redirect_with_error('Falha ao validar o link. Tente novamente.', $q);
}

// 2) cria usuário no Auth (admin)
$createUser = supabase_request(
  'POST',
  '/auth/v1/admin/users',
  [],
  [
    'email' => $email,
    'password' => $pass,
    'email_confirm' => true,
    'user_metadata' => [
      'full_name' => $name,
      'phone' => $phone ?: null,
      'source' => $src,
      'wa_chat_id' => $chat,
      'user_key' => $uk,
    ]
  ]
);

if (!$createUser['ok']) {
  redirect_with_error('Não foi possível criar sua conta (email pode já estar em uso). Tente outro email.', $q);
}

$userId = $createUser['data']['id'] ?? null;
if (!$userId) {
  redirect_with_error('Falha ao criar conta. Tente novamente.', $q);
}

// 3) ATUALIZA profile EXISTENTE PELO user_key (somente campos do cadastro)
$trialStart = now_iso();
$trialEnd   = add_days_iso(7); // ajuste a duração do trial aqui

// (Opcional, mas recomendado) garantir que existe profile com esse user_key
$profileCheck = supabase_request(
  'GET',
  '/rest/v1/profiles',
  [
    'select' => 'user_key',
    'user_key' => "eq.{$uk}",
    'limit' => '1'
  ]
);

if (!$profileCheck['ok'] || empty($profileCheck['data']) || !is_array($profileCheck['data']) || count($profileCheck['data']) === 0) {
  redirect_with_error('Não encontramos seu pré-cadastro (user_key inválida). Volte ao WhatsApp e solicite um novo link.', $q);
}

$patchProfile = supabase_request(
  'PATCH',
  '/rest/v1/profiles',
  ['user_key' => "eq.{$uk}", 'return' => 'minimal'],
  [
    'email' => $email,
    'full_name' => $name,
    'trial_started_at' => $trialStart,
    'trial_ends_at' => $trialEnd,
    'step' => 'TRIAL',
  ]
);

if (!$patchProfile['ok']) {
  redirect_with_error('Falha ao atualizar seu perfil. Tente novamente.', $q);
}

// 4) cria categorias padrão (inclui Alimentação)
$defaults = [
  ['user_key' => $uk, 'name' => 'Mercado',           'color' => '#3b82f6'],
  ['user_key' => $uk, 'name' => 'Vestuário',         'color' => '#a855f7'],
  ['user_key' => $uk, 'name' => 'Pets',              'color' => '#a16207'],
  ['user_key' => $uk, 'name' => 'Impostos',          'color' => '#f59e0b'],
  ['user_key' => $uk, 'name' => 'Lazer',             'color' => '#22c55e'],
  ['user_key' => $uk, 'name' => 'Cuidados Pessoais', 'color' => '#06b6d4'],
  ['user_key' => $uk, 'name' => 'Casa',              'color' => '#64748b'],
  ['user_key' => $uk, 'name' => 'Educação',          'color' => '#ef4444'],
  ['user_key' => $uk, 'name' => 'Alimentação',       'color' => '#f97316'],
];

$existing = supabase_request(
  'GET',
  '/rest/v1/categories',
  [
    'select' => 'name',
    'user_key' => "eq.{$uk}",
    'limit' => '200'
  ]
);

$existingNames = [];
if ($existing['ok'] && is_array($existing['data'])) {
  foreach ($existing['data'] as $row) {
    if (!empty($row['name'])) $existingNames[strtolower((string)$row['name'])] = true;
  }
}

$toInsert = [];
foreach ($defaults as $cat) {
  $k = strtolower($cat['name']);
  if (!isset($existingNames[$k])) $toInsert[] = $cat;
}

if ($toInsert) {
  supabase_request(
    'POST',
    '/rest/v1/categories',
    ['return' => 'minimal'],
    $toInsert
  );
}

// 5) marca onboarding como completed
supabase_request(
  'PATCH',
  '/rest/v1/onboarding_requests',
  ['id' => "eq.{$onbId}", 'return' => 'minimal'],
  [
    'status' => 'completed',
    'completed_at' => now_iso(),
  ]
);

// 6) webhook (n8n) — dispara mensagem de boas-vindas (no n8n você coloca Delay 3s)
$hook = (string)app_config('onboarding_complete_webhook', '');
if ($hook) {
  $payload = [
    'event' => 'onboarding.completed',
    'user_id' => $userId,
    'user_key' => $uk,
    'wa_chat_id' => $chat,
    'email' => $email,
    'name' => $name,
    'source' => $src,
    'timestamp' => now_iso(),
  ];

  $ch = curl_init($hook);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  curl_exec($ch);
  curl_close($ch);
}

redirect_with_ok($q);