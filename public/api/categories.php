<?php
// public/api/categories.php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/supabase.php';

require_auth();
require_csrf();
enforce_trial_active_or_redirect();

$userKey = current_user_key();
if (!$userKey) json_out(['ok' => false, 'error' => 'unauthorized'], 401);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// action pode vir por GET (list) ou por querystring (upsert/delete normalmente via fetch POST)
$action = $_GET['action'] ?? '';

if ($action === 'list') {
  // ✅ list deve aceitar GET
  $res = supabase_request('GET', '/rest/v1/categories', [
    'select'   => 'id,name,color,created_at',
    'user_key' => 'eq.' . $userKey,
    'order'    => 'created_at.asc'
  ]);

  if (!$res['ok']) json_out(['ok' => false, 'error' => $res['error']], 500);
  json_out(['ok' => true, 'items' => ($res['data'] ?? [])]);
}

if ($action === 'upsert') {
  if ($method !== 'POST') json_out(['ok' => false, 'error' => 'method_not_allowed'], 405);

  $raw = json_decode(file_get_contents('php://input'), true) ?? [];

  $id    = trim((string)($raw['id'] ?? ''));
  $name  = trim((string)($raw['name'] ?? ''));
  $color = trim((string)($raw['color'] ?? '#3b82f6'));

  if ($name === '') json_out(['ok' => false, 'error' => 'Nome obrigatório.'], 400);
  if (!preg_match('/^#([0-9a-fA-F]{6})$/', $color)) $color = '#3b82f6';

  // UPDATE
  if ($id !== '') {
    $check = supabase_request('GET', '/rest/v1/categories', [
      'select'   => 'id',
      'id'       => 'eq.' . $id,
      'user_key' => 'eq.' . $userKey,
      'limit'    => '1'
    ]);
    if (!$check['ok'] || empty($check['data'])) json_out(['ok' => false, 'error' => 'Categoria não encontrada.'], 404);

    $res = supabase_request('PATCH', '/rest/v1/categories', [
      'id'       => 'eq.' . $id,
      'user_key' => 'eq.' . $userKey,
    ], [
      'name'  => $name,
      'color' => $color,
    ]);

    if (!$res['ok']) json_out(['ok' => false, 'error' => $res['error']], 500);
    json_out(['ok' => true]);
  }

  // INSERT
  $res = supabase_request('POST', '/rest/v1/categories', [], [
    'user_key' => $userKey,
    'name'     => $name,
    'color'    => $color,
  ]);

  if (!$res['ok']) json_out(['ok' => false, 'error' => $res['error']], 500);
  json_out(['ok' => true]);
}

if ($action === 'delete') {
  if ($method !== 'POST') json_out(['ok' => false, 'error' => 'method_not_allowed'], 405);

  $raw = json_decode(file_get_contents('php://input'), true) ?? [];
  $id = trim((string)($raw['id'] ?? ''));

  if ($id === '') json_out(['ok' => false, 'error' => 'ID obrigatório.'], 400);

  $check = supabase_request('GET', '/rest/v1/categories', [
    'select'   => 'id',
    'id'       => 'eq.' . $id,
    'user_key' => 'eq.' . $userKey,
    'limit'    => '1'
  ]);
  if (!$check['ok'] || empty($check['data'])) json_out(['ok' => false, 'error' => 'Categoria não encontrada.'], 404);

  // impede deletar se usada
  $tx = supabase_request('GET', '/rest/v1/transactions', [
    'select'      => 'id',
    'user_key'    => 'eq.' . $userKey,
    'category_id' => 'eq.' . $id,
    'limit'       => '1'
  ]);
  if ($tx['ok'] && !empty($tx['data'])) {
    json_out(['ok' => false, 'error' => 'Essa categoria está sendo usada em transações. Troque a categoria dessas transações antes de excluir.'], 409);
  }

  $res = supabase_request('DELETE', '/rest/v1/categories', [
    'id'       => 'eq.' . $id,
    'user_key' => 'eq.' . $userKey,
  ]);

  if (!$res['ok']) json_out(['ok' => false, 'error' => $res['error']], 500);
  json_out(['ok' => true]);
}

json_out(['ok' => false, 'error' => 'Ação inválida.'], 400);