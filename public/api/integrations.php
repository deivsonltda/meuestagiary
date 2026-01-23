<?php
// public/api/integrations.php

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/supabase.php';

require_auth();
header('Content-Type: application/json; charset=utf-8');

function bad($msg, $code = 400) {
  json_out(['ok' => false, 'error' => $msg], $code);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) $data = [];

$action = (string)($data['action'] ?? '');
if ($action !== 'set_google_calendar_enabled') {
  bad('Ação inválida.');
}

$email = strtolower(trim((string)($_SESSION['user']['email'] ?? '')));
if ($email === '') bad('Sessão inválida.', 401);

$enabled = !empty($data['enabled']) ? true : false;

// atualiza por email
$upd = supabase_request('PATCH', '/rest/v1/profiles', [
  'email' => 'eq.' . $email,
], [
  'google_calendar_enabled' => $enabled,
]);

// se não existir profile ainda, cria
$chk = supabase_request('GET', '/rest/v1/profiles', [
  'select' => 'id,email',
  'email'  => 'eq.' . $email,
  'limit'  => 1,
]);

if (!empty($chk['ok']) && empty($chk['data'][0])) {
  $ins = supabase_request('POST', '/rest/v1/profiles', [], [
    'email' => $email,
    'google_calendar_enabled' => $enabled,
  ]);

  if (empty($ins['ok'])) {
    $msg = $ins['error']['message'] ?? $ins['error']['msg'] ?? 'Falha ao salvar.';
    bad($msg, 500);
  }
}

json_out(['ok' => true, 'enabled' => $enabled]);