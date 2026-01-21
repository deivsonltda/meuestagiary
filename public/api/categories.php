<?php
// public/api/categories.php

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

$action = $_GET['action'] ?? '';

if ($action === 'list') {

  $res = supabase_request('GET', '/rest/v1/categories', [
    'select'   => 'id,name,color,created_at',
    'user_key' => 'eq.' . $userKey,
    'order'    => 'created_at.asc'
  ]);

  if (!$res['ok']) json_out(['ok' => false, 'error' => $res['error']], 500);

  $items = $res['data'] ?? [];

  if (count($items) === 0) {
    $defaults = [
      ['user_key' => $userKey, 'name' => 'Alimentação', 'color' => '#3b82f6'],
      ['user_key' => $userKey, 'name' => 'Mercado', 'color' => '#60a5fa'],
      ['user_key' => $userKey, 'name' => 'Vestuário', 'color' => '#a855f7'],
      ['user_key' => $userKey, 'name' => 'Pets', 'color' => '#a16207'],
      ['user_key' => $userKey, 'name' => 'Impostos', 'color' => '#f59e0b'],
      ['user_key' => $userKey, 'name' => 'Lazer e Entretenimento', 'color' => '#22c55e'],
      ['user_key' => $userKey, 'name' => 'Cuidados Pessoais', 'color' => '#06b6d4'],
      ['user_key' => $userKey, 'name' => 'Casa', 'color' => '#64748b'],
      ['user_key' => $userKey, 'name' => 'Educação', 'color' => '#ef4444'],
      ['user_key' => $userKey, 'name' => 'Cartão de Crédito', 'color' => '#1e40af'], // azul escuro
      ['user_key' => $userKey, 'name' => 'Doações', 'color' => '#f97316'],          // laranja
      ['user_key' => $userKey, 'name' => 'Saúde', 'color' => '#10b981'],            // verde/teal
      ['user_key' => $userKey, 'name' => 'Recebimentos', 'color' => '#16a34a'],     // verde
      ['user_key' => $userKey, 'name' => 'Transporte', 'color' => '#0ea5e9'],       // sky
      ['user_key' => $userKey, 'name' => 'Utilidades', 'color' => '#94a3b8'],       // cinza claro
      ['user_key' => $userKey, 'name' => 'Viagem', 'color' => '#8b5cf6'],           // violeta
      ['user_key' => $userKey, 'name' => 'Outros', 'color' => '#475569'],           // slate
    ];


    // lote
    $seed = supabase_request('POST', '/rest/v1/categories', [], $defaults);
    if (!$seed['ok']) json_out(['ok' => false, 'error' => $seed['error']], 500);

    // recarrega
    $res2 = supabase_request('GET', '/rest/v1/categories', [
      'select'   => 'id,name,color,created_at',
      'user_key' => 'eq.' . $userKey,
      'order'    => 'created_at.asc'
    ]);
    if (!$res2['ok']) json_out(['ok' => false, 'error' => $res2['error']], 500);

    $items = $res2['data'] ?? [];
  }

  json_out(['ok' => true, 'items' => $items]);
}

if ($action === 'upsert') {
  $raw = json_decode(file_get_contents('php://input'), true) ?? [];

  $id    = trim((string)($raw['id'] ?? ''));
  $name  = trim((string)($raw['name'] ?? ''));
  $color = trim((string)($raw['color'] ?? '#3b82f6'));

  if ($name === '') json_out(['ok' => false, 'error' => 'Nome obrigatório.'], 400);
  if (!preg_match('/^#([0-9a-fA-F]{6})$/', $color)) $color = '#3b82f6';

  // Se tiver ID -> UPDATE (mais seguro do que “on_conflict” sem headers Prefer)
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

  // sem ID -> INSERT
  $res = supabase_request('POST', '/rest/v1/categories', [], [
    'user_key' => $userKey,
    'name'     => $name,
    'color'    => $color,
  ]);

  if (!$res['ok']) json_out(['ok' => false, 'error' => $res['error']], 500);
  json_out(['ok' => true]);
}

if ($action === 'delete') {
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
