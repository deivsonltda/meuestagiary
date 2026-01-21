<?php
// public/api/google/accounts.php  (ou /api/google/accounts.php)

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/auth.php';
require_once __DIR__ . '/../../../app/supabase.php';

function json_ok($data = [], int $status = 200) {
  json_out(array_merge(['ok' => true], $data), $status);
}

function json_err($message, $details = null, int $status = 400) {
  json_out([
    'ok' => false,
    'error' => $message,
    'details' => $details,
  ], $status);
}

try {
  // precisa estar logado
  $email = strtolower(trim((string)($_SESSION['user']['email'] ?? '')));
  if ($email === '') {
    json_err('unauthorized', 'Sessão sem e-mail.', 401);
  }

  $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

  // -------------------------------------------------------
  // GET => retorna conta conectada (a mais recente e não revogada)
  // -------------------------------------------------------
  if ($method === 'GET') {
    $res = supabase_request('GET', '/rest/v1/google_accounts', [
      'select' => 'id,google_email,provider,calendar_id,expires_at,created_at,updated_at,revoked_at',
      'profile_email' => 'eq.' . $email,
      'revoked_at' => 'is.null',
      'order' => 'created_at.desc',
      'limit' => 1,
    ]);

    if (empty($res['ok'])) {
      json_err('supabase_error', $res, 500);
    }

    $row = $res['data'][0] ?? null;
    json_ok(['account' => $row ?: null]);
  }

  // -------------------------------------------------------
  // DELETE => revoga uma conta (marca revoked_at)
  // Aceita id via:
  // - ?id=...
  // - JSON body { "id": "..." }
  // - form POST (fallback)
  // -------------------------------------------------------
  if ($method === 'DELETE') {
    $id = trim((string)($_GET['id'] ?? ''));

    if ($id === '') {
      $raw = file_get_contents('php://input');
      $body = json_decode($raw ?: '', true);
      if (is_array($body) && !empty($body['id'])) {
        $id = trim((string)$body['id']);
      }
    }

    if ($id === '') {
      json_err('id ausente', [
        'hint' => 'Envie ?id=UUID ou body JSON {"id":"UUID"}',
      ], 400);
    }

    // garante que esse id pertence ao usuário logado
    $check = supabase_request('GET', '/rest/v1/google_accounts', [
      'select' => 'id,profile_email,revoked_at',
      'id' => 'eq.' . $id,
      'limit' => 1,
    ]);

    if (empty($check['ok'])) {
      json_err('supabase_error_check', $check, 500);
    }

    $row = $check['data'][0] ?? null;
    if (!$row) {
      json_err('not_found', 'Conta não encontrada.', 404);
    }
    if (strtolower((string)($row['profile_email'] ?? '')) !== $email) {
      json_err('forbidden', 'Essa conta não pertence ao usuário logado.', 403);
    }

    // já revogada? retorna ok
    if (!empty($row['revoked_at'])) {
      json_ok(['revoked' => true, 'already' => true]);
    }

    // revoga
    $now = gmdate('c');

    $patch = supabase_request('PATCH', '/rest/v1/google_accounts', [
      'id' => 'eq.' . $id,
    ], [
      'revoked_at' => $now,
      'updated_at' => $now,
      // opcional: limpar calendar_id
      'calendar_id' => null,
    ]);

    if (empty($patch['ok'])) {
      json_err('supabase_error_patch', $patch, 500);
    }

    json_ok(['revoked' => true]);
  }

  json_err('method_not_allowed', $method, 405);

} catch (Throwable $e) {
  json_err('exception', [
    'message' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine(),
  ], 500);
}