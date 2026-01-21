<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/supabase.php';

require_auth();
require_csrf();
enforce_trial_active_or_redirect();

header('Content-Type: application/json; charset=utf-8');

$user_key = current_user_key();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---------------------------------------------------------
// Helpers
// ---------------------------------------------------------
function bad($msg, $code = 400) {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

function ok($data = []) {
  echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
  exit;
}

function num($v) {
  // aceita "45.000,50" / "45000.50" / "45000"
  $s = preg_replace('/[^\d,.-]/', '', (string)$v);
  if (substr_count($s, ',') === 1 && substr_count($s, '.') >= 1) {
    $s = str_replace('.', '', $s);     // tira milhar
    $s = str_replace(',', '.', $s);    // vírgula vira decimal
  } elseif (substr_count($s, ',') === 1 && substr_count($s, '.') === 0) {
    $s = str_replace(',', '.', $s);
  }
  return is_numeric($s) ? (float)$s : 0.0;
}

// ---------------------------------------------------------
// Supabase table
// ---------------------------------------------------------
// Tabela: "cards"
// colunas: id (uuid pk), user_key (text), name (text), limit (numeric), closing_day (int), is_default (bool), created_at
$table_path = '/rest/v1/cards';

// ---------------------------------------------------------
// GET: list
// ---------------------------------------------------------
if ($method === 'GET') {
  $q = [
    'select'   => 'id,name,limit,closing_day,is_default,created_at',
    'user_key' => 'eq.' . $user_key,
    'order'    => 'created_at.desc',
  ];

  $res = supabase_request('GET', $table_path, $q);
  if (!$res['ok']) bad($res['error'] ?? 'Falha ao listar cartões', 500);

  ok(['items' => $res['data'] ?? []]);
}

// ---------------------------------------------------------
// Read body
// ---------------------------------------------------------
$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '[]', true);
if (!is_array($body)) $body = [];

// ✅ compat: aceita action no body OU na query (?action=delete|upsert)
$action = $body['action'] ?? '';
if ($action === '') $action = trim((string)($_GET['action'] ?? ''));

// ---------------------------------------------------------
// POST: upsert
// ---------------------------------------------------------
if ($method === 'POST' && $action === 'upsert') {
  $id          = trim((string)($body['id'] ?? ''));
  $name        = trim((string)($body['name'] ?? ''));
  $limit       = num($body['limit'] ?? 0);
  $closing_day = (int)($body['closing_day'] ?? 0);
  $is_default  = (bool)($body['is_default'] ?? false);

  if ($name === '') bad('Nome do cartão é obrigatório.');
  if ($closing_day < 1 || $closing_day > 28) bad('Dia de fechamento deve ser entre 1 e 28.');

  // Se marcar padrão: desmarca os outros antes (do mesmo usuário)
  if ($is_default) {
    $unsetRes = supabase_request(
      'PATCH',
      $table_path,
      ['user_key' => 'eq.' . $user_key],
      ['is_default' => false]
    );
    if (!$unsetRes['ok']) bad($unsetRes['error'] ?? 'Falha ao atualizar cartão padrão', 500);
  }

  $payload = [
    'name'        => $name,
    'limit'       => $limit,
    'closing_day' => $closing_day,
    'is_default'  => $is_default,
  ];

  // EDITAR
  if ($id !== '') {
    $q = [
      'id'       => 'eq.' . $id,
      'user_key' => 'eq.' . $user_key,
    ];

    $res = supabase_request('PATCH', $table_path, $q, $payload);
    if (!$res['ok']) bad($res['error'] ?? 'Falha ao atualizar cartão', 500);

    ok();
  }

  // INSERT
  $payloadInsert = $payload + [
    'user_key' => $user_key,
  ];

  $res = supabase_request(
    'POST',
    $table_path,
    [],
    [$payloadInsert]
  );

  if (!$res['ok']) bad($res['error'] ?? 'Falha ao salvar cartão', 500);

  ok();
}

// ---------------------------------------------------------
// POST: delete
// ---------------------------------------------------------
if ($method === 'POST' && $action === 'delete') {
  $id = trim((string)($body['id'] ?? ''));
  // ✅ compat: aceita id via query também (?id=...)
  if ($id === '') $id = trim((string)($_GET['id'] ?? ''));
  if ($id === '') bad('ID inválido.');

  $q = [
    'id'       => 'eq.' . $id,
    'user_key' => 'eq.' . $user_key,
  ];

  $res = supabase_request('DELETE', $table_path, $q);
  if (!$res['ok']) bad($res['error'] ?? 'Falha ao excluir cartão', 500);

  ok();
}

bad('Ação inválida.');