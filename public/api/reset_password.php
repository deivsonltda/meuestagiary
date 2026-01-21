<?php
// public/api/reset_password.php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/supabase.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_out(['ok' => false, 'error' => 'Method Not Allowed', 'code' => 'method_not_allowed'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);

if (!is_array($payload)) {
  json_out(['ok' => false, 'error' => 'JSON inválido.', 'code' => 'bad_json'], 400);
}

$accessToken = trim((string)($payload['access_token'] ?? ''));
$newPass     = (string)($payload['password'] ?? '');

if (!$accessToken || !$newPass) {
  json_out(['ok' => false, 'error' => 'Parâmetros obrigatórios ausentes.', 'code' => 'missing_params'], 400);
}

if (strlen($newPass) < 8) {
  json_out(['ok' => false, 'error' => 'Senha muito curta (mínimo 8 caracteres).', 'code' => 'weak_password'], 400);
}

/**
 * 1) Atualiza senha usando o access_token do recovery
 * Endpoint: PUT /auth/v1/user  (Bearer = access_token)
 */
$update = supabase_request_with_bearer(
  'PUT',
  '/auth/v1/user',
  [],
  ['password' => $newPass],
  $accessToken
);

if (!$update['ok']) {
  $errBody = $update['error']['body'] ?? null;
  $msg = '';

  if (is_array($errBody)) {
    // Supabase pode mandar {message:""} ou {error:""} etc.
    $msg = (string)($errBody['message'] ?? ($errBody['error_description'] ?? ($errBody['error'] ?? '')));
  } else {
    $msg = is_string($errBody) ? $errBody : '';
  }

  $low = strtolower($msg);

  // Heurísticas úteis pro recovery
  if (str_contains($low, 'expired') || str_contains($low, 'invalid') || str_contains($low, 'jwt')) {
    json_out(['ok' => false, 'error' => 'Link inválido ou expirado. Solicite um novo.', 'code' => 'otp_expired'], 401);
  }

  json_out([
    'ok' => false,
    'error' => 'Falha ao redefinir senha.',
    'code' => 'reset_failed',
    'details' => [
      'http' => $update['http'] ?? null,
      'message' => $msg ?: null,
      'body' => $errBody,
    ],
  ], 400);
}

/**
 * 2) Bloqueio de reuso (server-side, sem tabela):
 * Revoga/encerra a sessão do token de recovery após trocar a senha.
 * Endpoint: POST /auth/v1/logout (Bearer = access_token)
 *
 * Se alguém tentar reutilizar esse token depois, tende a falhar como inválido/revogado.
 */
$logout = supabase_request_with_bearer(
  'POST',
  '/auth/v1/logout',
  [],
  null,
  $accessToken
);

// Mesmo se logout falhar por algum motivo, a senha já foi alterada.
// Mas você ganha “anti-reuso” quando o logout funcionar.
json_out([
  'ok' => true,
  'revoked' => (bool)($logout['ok'] ?? false),
], 200);