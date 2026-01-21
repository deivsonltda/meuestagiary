<?php
// public/api/transactions.php

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/supabase.php';

header('Content-Type: application/json; charset=utf-8');

function read_json_body(): array {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}

function out($arr, int $code = 200) {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

$user_key = current_user_key();
if (!$user_key) {
  out(["ok" => false, "error" => ["message" => "Não autenticado"]], 401);
}

$action = $_GET['action'] ?? 'list';

try {
  if ($action === 'list') {
    $q = [
      'select' => 'id,user_key,item,amount,category_id,type,status,date,created_at',
      'user_key' => 'eq.' . $user_key,
      'order' => 'date.desc,created_at.desc',
    ];

    $res = supabase_request('GET', '/rest/v1/transactions', $q, null);

    if (!$res['ok']) {
      out(["ok" => false, "error" => $res['error'] ?? ["message" => "Erro Supabase"]], 500);
    }

    $items = is_array($res['data']) ? $res['data'] : [];
    out(["ok" => true, "items" => $items]);
  }

  if ($action === 'upsert') {
    $b = read_json_body();

    $id = trim((string)($b['id'] ?? ''));
    $item = trim((string)($b['item'] ?? ''));
    $amount = $b['amount'] ?? 0;
    $type = trim((string)($b['type'] ?? 'expense'));
    $status = trim((string)($b['status'] ?? 'paid'));
    $date = trim((string)($b['date'] ?? ''));
    $category_id = $b['category_id'] ?? null;

    if ($item === '') out(["ok" => false, "error" => ["message" => "Descrição obrigatória"]], 400);
    if ($date === '') out(["ok" => false, "error" => ["message" => "Data obrigatória"]], 400);

    if ($category_id !== null) {
      $category_id = trim((string)$category_id);
      if ($category_id === '') $category_id = null;
    }

    $payload = [
      "user_key" => $user_key,
      "item" => $item,
      "amount" => (float)$amount,
      "category_id" => $category_id,
      "type" => $type,
      "status" => $status,
      "date" => $date,
    ];

    if ($id !== '') {
      // UPDATE
      $q = [
        "id" => "eq." . $id,
        "user_key" => "eq." . $user_key,
        // ✅ Prefer header (não vai pra querystring)
        "prefer" => "return=representation",
      ];

      $res = supabase_request('PATCH', '/rest/v1/transactions', $q, $payload);

      if (!$res['ok']) {
        out(["ok" => false, "error" => $res['error'] ?? ["message" => "Erro Supabase"]], 500);
      }

      $row = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
      out(["ok" => true, "item" => $row]);
    }

    // INSERT
    $q = [
      // ✅ Prefer header (não vai pra querystring)
      "prefer" => "return=representation",
    ];

    $res = supabase_request('POST', '/rest/v1/transactions', $q, $payload);

    if (!$res['ok']) {
      out(["ok" => false, "error" => $res['error'] ?? ["message" => "Erro Supabase"]], 500);
    }

    $row = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
    out(["ok" => true, "item" => $row]);
  }

  if ($action === 'delete') {
    $b = read_json_body();

    // ✅ aceita id via JSON ou via querystring
    $id = trim((string)($b['id'] ?? ($_GET['id'] ?? '')));
    if ($id === '') out(["ok" => false, "error" => ["message" => "ID obrigatório"]], 400);

    $q = [
      "id" => "eq." . $id,
      "user_key" => "eq." . $user_key,
      // ✅ Prefer header (não vai pra querystring)
      "prefer" => "return=minimal",
    ];

    $res = supabase_request('DELETE', '/rest/v1/transactions', $q, null);

    if (!$res['ok']) {
      out(["ok" => false, "error" => $res['error'] ?? ["message" => "Erro Supabase"]], 500);
    }

    out(["ok" => true]);
  }

  out(["ok" => false, "error" => ["message" => "Ação inválida"]], 400);

} catch (Throwable $e) {
  out([
    "ok" => false,
    "error" => "fatal",
    "details" => [
      "type" => $e->getCode(),
      "message" => $e->getMessage(),
      "file" => $e->getFile(),
      "line" => $e->getLine(),
    ]
  ], 500);
}