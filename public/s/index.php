<?php
// public/s/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/supabase.php';

function fail(int $code, string $msg): void {
  http_response_code($code);
  header('Content-Type: text/plain; charset=utf-8');
  echo $msg;
  exit;
}

// 1) Extrai o slug do path /s/{slug}
$uriPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

// suporta /s/abc, /s/abc/, e também fallback por query (?slug=abc ou ?code=abc)
$slug = '';
if (str_starts_with($uriPath, '/s/')) {
  $slug = trim(substr($uriPath, 3), "/ \t\n\r\0\x0B");
}

if ($slug === '') {
  $slug = trim((string)($_GET['slug'] ?? $_GET['code'] ?? ''));
}

if ($slug === '') {
  fail(404, 'Link curto inválido.');
}

// 2) Busca no Supabase: slug OU code
// (PostgREST OR): or=(slug.eq.xxx,code.eq.xxx)
$resp = supabase_request(
  'GET',
  '/rest/v1/short_links',
  [
    'select' => 'id,slug,code,status,target_url,full_url,token,user_key,wa_id',
    'or'     => '(slug.eq.' . $slug . ',code.eq.' . $slug . ')',
    'limit'  => '1',
  ]
);

if (!$resp['ok'] || empty($resp['data']) || !is_array($resp['data'])) {
  fail(404, 'Link curto não encontrado.');
}

$row = $resp['data'][0] ?? null;
if (!is_array($row)) {
  fail(404, 'Link curto não encontrado.');
}

// 3) Valida status
$status = strtolower(trim((string)($row['status'] ?? '')));
if ($status !== 'active') {
  // 410 = Gone (bom pra link expirado/desativado)
  fail(410, 'Este link está desativado ou expirou.');
}

// 4) Valida target_url
$target = trim((string)($row['target_url'] ?? ''));
if ($target === '') {
  fail(500, 'Link sem destino configurado.');
}

// bloqueia esquemas perigosos (javascript:, data:, etc.)
$scheme = strtolower((string)parse_url($target, PHP_URL_SCHEME));
if ($scheme !== 'http' && $scheme !== 'https') {
  fail(400, 'Destino inválido.');
}

// 5) Redireciona
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: ' . $target, true, 302);
exit;