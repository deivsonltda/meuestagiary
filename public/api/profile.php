<?php
// public/api/profile.php

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/supabase.php';

require_auth();
require_csrf();
enforce_trial_active_or_redirect();

header('Content-Type: application/json; charset=utf-8');

function bad($msg, $code = 400) {
  json_out(['ok' => false, 'error' => $msg], $code);
}

function norm_time_hhmmss($t) {
  $t = trim((string)$t);
  if ($t === '') return '';
  // aceita HH:MM ou HH:MM:SS
  if (preg_match('/^\d{2}:\d{2}$/', $t)) return $t . ':00';
  if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) return $t;
  return '';
}

function defaults_preferences(): array {
  return [
    'ai_mood' => 'neutra',
    'response_size' => 'media',
    'daily_reminder_time' => '08:00:00',
    'reminder_default_minutes' => 30,
  ];
}

// -------------------------
// Contexto do usuário
// -------------------------
$user_key = current_user_key();
if (!$user_key) {
  bad('Sessão inválida. Faça login novamente.', 401);
}

$email = strtolower(trim((string)($_SESSION['user']['email'] ?? '')));

// -------------------------
// Lê action do GET ou do JSON body
// -------------------------
$action = '';
$data = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $action = (string)($_GET['action'] ?? '');
} else {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw ?: '{}', true);
  if (!is_array($data)) $data = [];
  $action = (string)($data['action'] ?? '');
}

// ações aceitas
$allowed = ['update_profile', 'update_plan', 'get_preferences', 'update_preferences'];
if (!in_array($action, $allowed, true)) {
  bad('Ação inválida.');
}

// =========================================================
// GET_PREFERENCES  (puxa / cria defaults)
// =========================================================
if ($action === 'get_preferences') {
  $prefs = defaults_preferences();

  // tenta buscar
  $res = supabase_request('GET', '/rest/v1/profiles', [
    'select' => 'user_key,ai_mood,response_size,daily_reminder_time,reminder_default_minutes',
    'user_key' => 'eq.' . $user_key,
    'limit' => 1,
  ]);

  if (!empty($res['ok']) && !empty($res['data'][0])) {
    $row = $res['data'][0];

    $ai_mood = (string)($row['ai_mood'] ?? $prefs['ai_mood']);
    $response_size = (string)($row['response_size'] ?? $prefs['response_size']);
    $daily_time = (string)($row['daily_reminder_time'] ?? $prefs['daily_reminder_time']);
    $minutes = (int)($row['reminder_default_minutes'] ?? $prefs['reminder_default_minutes']);

    // normalizações leves
    if (!in_array($ai_mood, ['neutra','humorada','seria'], true)) $ai_mood = $prefs['ai_mood'];
    if (!in_array($response_size, ['curta','media','longa'], true)) $response_size = $prefs['response_size'];
    $daily_time = norm_time_hhmmss($daily_time) ?: $prefs['daily_reminder_time'];
    if (!in_array($minutes, [30,60,120], true)) $minutes = $prefs['reminder_default_minutes'];

    json_out([
      'ok' => true,
      'data' => [
        'ai_mood' => $ai_mood,
        'response_size' => $response_size,
        'daily_reminder_time' => substr($daily_time, 0, 5), // UI usa HH:MM
        'reminder_default_minutes' => $minutes,
      ],
    ]);
  }

  // se não existe linha, cria com defaults
  $ins = supabase_request('POST', '/rest/v1/profiles', [], [
    'user_key' => $user_key,
    'email' => $email ?: null,
    'ai_mood' => $prefs['ai_mood'],
    'response_size' => $prefs['response_size'],
    'daily_reminder_time' => $prefs['daily_reminder_time'],
    'reminder_default_minutes' => $prefs['reminder_default_minutes'],
  ]);

  if (empty($ins['ok'])) {
    $msg = $ins['error']['message'] ?? $ins['error']['msg'] ?? 'Falha ao criar preferências.';
    bad($msg, 500);
  }

  json_out([
    'ok' => true,
    'data' => [
      'ai_mood' => $prefs['ai_mood'],
      'response_size' => $prefs['response_size'],
      'daily_reminder_time' => '08:00',
      'reminder_default_minutes' => 30,
    ],
  ]);
}

// =========================================================
// UPDATE_PREFERENCES
// =========================================================
if ($action === 'update_preferences') {
  $prefs = defaults_preferences();

  $ai_mood = strtolower(trim((string)($data['ai_mood'] ?? $prefs['ai_mood'])));
  $response_size = strtolower(trim((string)($data['response_size'] ?? $prefs['response_size'])));
  $daily_time = norm_time_hhmmss((string)($data['daily_reminder_time'] ?? $prefs['daily_reminder_time']));
  $minutes = (int)($data['reminder_default_minutes'] ?? $prefs['reminder_default_minutes']);

  if (!in_array($ai_mood, ['neutra','humorada','seria'], true)) bad('ai_mood inválido.');
  if (!in_array($response_size, ['curta','media','longa'], true)) bad('response_size inválido.');
  if ($daily_time === '') bad('daily_reminder_time inválido. Use HH:MM.');
  if (!in_array($minutes, [30,60,120], true)) bad('reminder_default_minutes inválido.');

  // tenta atualizar
  $upd = supabase_request('PATCH', '/rest/v1/profiles', [
    'user_key' => 'eq.' . $user_key,
  ], [
    'ai_mood' => $ai_mood,
    'response_size' => $response_size,
    'daily_reminder_time' => $daily_time,
    'reminder_default_minutes' => $minutes,
  ]);

  // garante existência (se não existir, cria)
  $chk = supabase_request('GET', '/rest/v1/profiles', [
    'select' => 'id,user_key',
    'user_key' => 'eq.' . $user_key,
    'limit' => 1,
  ]);

  if (!empty($chk['ok']) && empty($chk['data'][0])) {
    $ins = supabase_request('POST', '/rest/v1/profiles', [], [
      'user_key' => $user_key,
      'email' => $email ?: null,
      'ai_mood' => $ai_mood,
      'response_size' => $response_size,
      'daily_reminder_time' => $daily_time,
      'reminder_default_minutes' => $minutes,
    ]);

    if (empty($ins['ok'])) {
      $msg = $ins['error']['message'] ?? $ins['error']['msg'] ?? 'Falha ao criar profile.';
      bad($msg, 500);
    }
  }

  json_out([
    'ok' => true,
    'data' => [
      'ai_mood' => $ai_mood,
      'response_size' => $response_size,
      'daily_reminder_time' => substr($daily_time, 0, 5),
      'reminder_default_minutes' => $minutes,
    ],
  ]);
}

// =========================================================
// UPDATE_PLAN (mantive, mas agora pelo user_key)
// =========================================================
if ($action === 'update_plan') {
  $cycle = (string)($data['billing_cycle'] ?? '');
  if (!in_array($cycle, ['monthly', 'annual'], true)) {
    bad('Ciclo inválido.');
  }

  $upd = supabase_request('PATCH', '/rest/v1/profiles', [
    'user_key' => 'eq.' . $user_key,
  ], [
    'billing_cycle' => $cycle,
  ]);

  $chk = supabase_request('GET', '/rest/v1/profiles', [
    'select' => 'id,user_key',
    'user_key' => 'eq.' . $user_key,
    'limit'  => 1,
  ]);

  if (!empty($chk['ok']) && empty($chk['data'][0])) {
    $ins = supabase_request('POST', '/rest/v1/profiles', [], [
      'user_key' => $user_key,
      'email' => $email ?: null,
      'billing_cycle' => $cycle,
    ]);

    if (empty($ins['ok'])) {
      $msg = $ins['error']['message'] ?? $ins['error']['msg'] ?? 'Falha ao criar profile.';
      bad($msg, 500);
    }
  }

  json_out(['ok' => true, 'data' => ['billing_cycle' => $cycle]]);
}

// =========================================================
// UPDATE_PROFILE (mantive, mas agora pelo user_key)
// =========================================================
$full_name = trim((string)($data['full_name'] ?? ''));
$phone     = preg_replace('/\D+/', '', (string)($data['phone'] ?? ''));

// remove +55 se vier
if (strlen($phone) >= 12 && str_starts_with($phone, '55')) $phone = substr($phone, 2);

if ($full_name === '') bad('Nome é obrigatório.');
if ($phone !== '' && !(strlen($phone) === 10 || strlen($phone) === 11)) {
  bad('Telefone inválido. Use DDD + número (10 ou 11 dígitos), sem +55.');
}

$upd = supabase_request('PATCH', '/rest/v1/profiles', [
  'user_key' => 'eq.' . $user_key,
], [
  'email'     => $email ?: null,
  'full_name' => $full_name,
  'phone'     => $phone,
]);

$chk = supabase_request('GET', '/rest/v1/profiles', [
  'select' => 'id,user_key',
  'user_key' => 'eq.' . $user_key,
  'limit'  => 1,
]);

if (!empty($chk['ok']) && empty($chk['data'][0])) {
  $ins = supabase_request('POST', '/rest/v1/profiles', [], [
    'user_key'  => $user_key,
    'email'     => $email ?: null,
    'full_name' => $full_name,
    'phone'     => $phone,
  ]);

  if (empty($ins['ok'])) {
    $msg = $ins['error']['message'] ?? $ins['error']['msg'] ?? 'Falha ao criar profile.';
    bad($msg, 500);
  }
}

// Atualiza sessão (pra refletir imediatamente sem recarregar)
$_SESSION['user']['name']  = $full_name;
$_SESSION['user']['phone'] = $phone;

json_out(['ok' => true, 'data' => ['full_name' => $full_name, 'phone' => $phone]]);