<?php
// app/bootstrap.php

// ------------------------------------------------------------
// 1) Carrega .env da RAIZ do projeto (../.env)
//    - Suporta linhas: KEY=VALUE
//    - Ignora comentários (# ...)
//    - Remove aspas "..." ou '...'
//    - NÃO sobrescreve variáveis já definidas no ambiente
// ------------------------------------------------------------
(function () {
  $root = realpath(__DIR__ . '/..'); // raiz do projeto (contém /app e /public)
  if (!$root) return;

  $envPath = $root . DIRECTORY_SEPARATOR . '.env';
  if (!is_file($envPath)) return;

  $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!$lines) return;

  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;

    // remove comentários no fim da linha (preserva # dentro de aspas)
    $clean = '';
    $inQuotes = false;
    $len = strlen($line);

    for ($i = 0; $i < $len; $i++) {
      $ch = $line[$i];
      if ($ch === '"' || $ch === "'") $inQuotes = !$inQuotes;
      if (!$inQuotes && $ch === '#') break;
      $clean .= $ch;
    }

    $line = trim($clean);
    if ($line === '' || !str_contains($line, '=')) continue;

    [$k, $v] = explode('=', $line, 2);
    $k = trim($k);
    $v = trim($v);
    if ($k === '') continue;

    // remove aspas
    if ($v !== '') {
      $first = $v[0];
      $last  = $v[strlen($v) - 1];
      if (
        ($first === '"' && $last === '"') ||
        ($first === "'" && $last === "'")
      ) {
        $v = substr($v, 1, -1);
      }
    }

    // NÃO sobrescreve se já existir COM valor.
    // Se existir mas estiver vazio, deixa o .env preencher (evita bug intermitente).
    $existing = getenv($k);
    if ($existing !== false && $existing !== '') continue;

    putenv("$k=$v");
    $_ENV[$k] = $v;
  }
})();

// ------------------------------------------------------------
// Helper env()
// ------------------------------------------------------------
function env(string $key, $default = null) {
  $v = getenv($key);

  // Fallback: em alguns ambientes, putenv/getenv pode ficar inconsistente por worker
  if ($v === false || $v === null || $v === '') {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
      $v = $_ENV[$key];
    } elseif (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
      $v = $_SERVER[$key];
    } else {
      return $default;
    }
  }

  $vl = strtolower(trim((string)$v));
  if ($vl === 'true')  return true;
  if ($vl === 'false') return false;
  if ($vl === 'null')  return null;

  return $v;
}

// ------------------------------------------------------------
// 2) Carrega config
// ------------------------------------------------------------
$config = require __DIR__ . '/config.php';

// Injeta defaults vindos do .env (se não existirem no config.php)
if (!is_array($config)) $config = [];

$config['supabase_url'] = $config['supabase_url'] ?? env('SUPABASE_URL');
$config['supabase_service_role_key'] = $config['supabase_service_role_key'] ?? env('SUPABASE_SERVICE_ROLE_KEY');
$config['onboarding_complete_webhook'] = $config['onboarding_complete_webhook'] ?? env('ONBOARDING_COMPLETE_WEBHOOK');

// ------------------------------------------------------------
// 3) Sessão (evita warning se já estiver iniciada)
//    + Blindagem (cookies, strict mode, etc.)
// ------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {

  // Detecta HTTPS de forma segura
  $https =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

  // Nome da sessão (se você já usa)
  if (!empty($config['session_name'])) {
    session_name($config['session_name']);
  }

  // Blindagens de sessão (sem quebrar OAuth)
  ini_set('session.use_strict_mode', '1');
  ini_set('session.cookie_httponly', '1');
  ini_set('session.cookie_secure', $https ? '1' : '0');

  // Use Lax por padrão (Strict pode quebrar callback de OAuth em alguns fluxos)
  // Se um dia quiser Strict, só faça depois de testar o Google OAuth.
  $cookieParams = [
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
  ];

  // PHP 7.3+ aceita array direto
  if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params($cookieParams);
  } else {
    // Fallback (não deve ser o seu caso, mas não atrapalha)
    session_set_cookie_params(
      $cookieParams['lifetime'],
      $cookieParams['path'] . '; samesite=' . $cookieParams['samesite'],
      $cookieParams['domain'],
      $cookieParams['secure'],
      $cookieParams['httponly']
    );
  }

  session_start();
}

// ------------------------------------------------------------
// 3.1) Headers de segurança (leves, sem CSP pra não quebrar)
// ------------------------------------------------------------
(function () {
  if (headers_sent()) return;

  // Proteções comuns e seguras
  header('X-Frame-Options: DENY');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

  // HSTS (somente se estiver em HTTPS)
  $https =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

  if ($https) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
  }
})();

// ------------------------------------------------------------
// 4) Helpers globais
// ------------------------------------------------------------
function app_config(string $key, $default = null)
{
  static $cfg = null;
  if ($cfg === null) {
    $cfg = require __DIR__ . '/config.php';
    if (!is_array($cfg)) $cfg = [];

    // merge com env defaults
    $cfg['supabase_url'] = $cfg['supabase_url'] ?? env('SUPABASE_URL');
    $cfg['supabase_service_role_key'] = $cfg['supabase_service_role_key'] ?? env('SUPABASE_SERVICE_ROLE_KEY');
    $cfg['onboarding_complete_webhook'] = $cfg['onboarding_complete_webhook'] ?? env('ONBOARDING_COMPLETE_WEBHOOK');
  }
  return $cfg[$key] ?? $default;
}

function is_api_request(): bool
{
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  return strpos($uri, '/api/') !== false;
}

function redirect(string $path): void
{
  $base = rtrim(app_config('base_url', ''), '/');
  header('Location: ' . $base . $path);
  exit;
}

function h($str): string
{
  return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function json_out($data, int $status = 200): void
{
  if (!headers_sent()) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  }
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

// ---------------------------
// CSRF (para fetch JSON)
// ---------------------------
function csrf_token(): string
{
  if (session_status() !== PHP_SESSION_ACTIVE) return '';
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return (string)$_SESSION['csrf_token'];
}

function require_csrf(): void
{
  // só exige em métodos que alteram estado
  $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
  if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) return;

  $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

  // fallback: se algum endpoint ainda usa form-urlencoded
  if (!$token && isset($_POST['csrf_token'])) $token = (string)$_POST['csrf_token'];

  $ok = is_string($token) && hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token);
  if (!$ok) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'CSRF inválido'], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

// ------------------------------------------------------------
// 5) DEBUG DEFINITIVO PARA /api
//    - Fatal error vira JSON (nunca tela branca)
// ------------------------------------------------------------
if (is_api_request()) {
  ini_set('display_errors', '0');
  error_reporting(E_ALL);

  register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e) return;

    $fatalTypes = [
      E_ERROR,
      E_PARSE,
      E_CORE_ERROR,
      E_COMPILE_ERROR,
    ];

    if (!in_array($e['type'], $fatalTypes, true)) return;

    if (!headers_sent()) {
      http_response_code(500);
      header('Content-Type: application/json; charset=utf-8');
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    echo json_encode([
      'ok' => false,
      'error' => 'fatal',
      'details' => [
        'type' => $e['type'],
        'message' => $e['message'],
        'file' => $e['file'],
        'line' => $e['line'],
      ],
    ], JSON_UNESCAPED_UNICODE);
  });
}