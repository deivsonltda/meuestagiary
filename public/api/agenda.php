<?php
// public/api/agenda.php

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/supabase.php';
require_once __DIR__ . '/../../app/google_calendar.php'; // ✅ necessário pro delete no Google

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

function out($arr, int $code = 200)
{
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

function read_json_body(): array
{
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}

function is_uuid($s): bool
{
  return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string)$s);
}

function is_rfc3339($s): bool
{
  return (bool)preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+\-]\d{2}:\d{2})$/', (string)$s);
}

function parse_rfc3339_or_fail($s, $fieldName)
{
  $v = trim((string)$s);
  if ($v === '' || !is_rfc3339($v)) {
    out(["ok" => false, "error" => ["message" => "$fieldName inválido. Use RFC3339 (ex: 2026-01-19T21:00:00-03:00)"]], 400);
  }
  return $v;
}

function resolve_user_key(): string
{
  $uk = current_user_key();
  if ($uk) return $uk;

  $internal = trim((string)app_config('internal_api_key', ''));
  $hdr = trim((string)($_SERVER['HTTP_X_INTERNAL_API_KEY'] ?? ''));
  if ($internal === '' || $hdr === '' || !hash_equals($internal, $hdr)) {
    out(["ok" => false, "error" => ["message" => "Não autenticado"]], 401);
  }

  $b = read_json_body();
  $uk2 = trim((string)($b['user_key'] ?? ''));
  if ($uk2 === '') out(["ok" => false, "error" => ["message" => "user_key obrigatório"]], 400);
  return $uk2;
}

// Fallback de delete Google, caso você não tenha uma função pronta no google_calendar.php
if (!function_exists('gc_delete_calendar_event')) {
  function gc_delete_calendar_event(string $accessToken, string $calendarId, string $eventId): array
  {
    $url = "https://www.googleapis.com/calendar/v3/calendars/" . rawurlencode($calendarId) . "/events/" . rawurlencode($eventId);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_CUSTOMREQUEST => 'DELETE',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json',
      ],
      CURLOPT_TIMEOUT => 20,
    ]);

    $resp = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
      return ['ok' => false, 'error' => ['message' => 'Falha CURL', 'details' => $err], 'http' => $http];
    }

    // 204 ok, 404 já não existe -> trata como ok
    if ($http === 204 || $http === 404) return ['ok' => true, 'http' => $http];

    return ['ok' => false, 'http' => $http, 'error' => ['message' => 'Google Calendar recusou o delete', 'body' => $resp]];
  }
}

// ------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$table  = '/rest/v1/agenda_events';

if ($method === 'GET') {
  require_auth();
  $user_key = current_user_key();

  $month = trim((string)($_GET['month'] ?? ''));
  if (!preg_match('/^\d{4}-\d{2}$/', $month)) out(['ok' => false, 'error' => 'month inválido. Use YYYY-MM'], 400);

  $start = $month . '-01T00:00:00Z';
  $dt = new DateTime($start);
  $dt->modify('first day of next month');
  $end = $dt->format('Y-m-d\T00:00:00\Z');

  $q = [
    'select'   => 'id,title,start_at,end_at,reminder_minutes',
    'user_key' => 'eq.' . $user_key,
    'and'      => '(start_at.gte.' . $start . ',start_at.lt.' . $end . ')',
    'order'    => 'start_at.asc'
  ];

  $res = supabase_request('GET', $table, $q, []);
  if (!$res['ok']) out(['ok' => false, 'error' => 'Falha ao buscar eventos', 'details' => $res['error']], 500);

  out(['ok' => true, 'items' => $res['data'] ?? []]);
}

if ($method === 'POST') {
  $user_key = resolve_user_key();
  $b = read_json_body();

  $action = trim((string)($b['action'] ?? ''));
  if ($action === '') $action = trim((string)($_GET['action'] ?? ''));

  // update (mantém seu funcionamento atual)
  if ($action === 'update') {
    $id = trim((string)($b['id'] ?? ''));
    if (!is_uuid($id)) out(["ok" => false, "error" => ["message" => "id inválido"]], 400);

    $title = trim((string)($b['title'] ?? ''));
    if ($title === '') out(["ok" => false, "error" => ["message" => "title obrigatório"]], 400);

    $start_at = parse_rfc3339_or_fail($b['start'] ?? '', 'start');
    $end_at   = parse_rfc3339_or_fail($b['end'] ?? '', 'end');

    $tsS = strtotime($start_at);
    $tsE = strtotime($end_at);
    if ($tsS !== false && $tsE !== false && $tsE <= $tsS) {
      out(["ok" => false, "error" => ["message" => "end deve ser maior que start"]], 400);
    }

    $rem = $b['reminder_minutes'] ?? null;
    if ($rem !== null && $rem !== '') {
      $rem = (int)$rem;
      if ($rem < 0) $rem = 0;
    } else {
      $rem = null;
    }

    $patchRow = [
      'title'            => $title,
      'start_at'         => $start_at,
      'end_at'           => $end_at,
      'reminder_minutes' => $rem,
    ];

    $qPatch = [
      'id'       => 'eq.' . $id,
      'user_key' => 'eq.' . $user_key,
    ];

    $rPatch = supabase_request('PATCH', $table, $qPatch, $patchRow);
    if (!$rPatch['ok']) out(["ok" => false, "error" => $rPatch['error'] ?? ["message" => "Erro Supabase (update)"]], 500);

    out(["ok" => true]);
  }

  // upsert (mantém)
  if ($action === 'upsert') {
    // ... seu bloco de upsert aqui (igual ao que você já tem)
    out(["ok" => false, "error" => ["message" => "upsert não incluído aqui — mantenha o seu igual estava"]], 500);
  }

  // ✅ DELETE com Google Calendar
  if ($action === 'delete') {
    $id = trim((string)($b['id'] ?? ''));
    if ($id === '') $id = trim((string)($_GET['id'] ?? ''));
    if (!is_uuid($id)) out(["ok" => false, "error" => ["message" => "id inválido"]], 400);

    // 1) Buscar o evento local (pra pegar google_event_id)
    $qGet = [
      'select'   => 'id,title,google_event_id,google_calendar_id',
      'id'       => 'eq.' . $id,
      'user_key' => 'eq.' . $user_key,
      'limit'    => '1',
    ];
    $rGet = supabase_request('GET', $table, $qGet, []);
    if (!$rGet['ok']) out(["ok" => false, "error" => ["message" => "Falha ao buscar evento"], "details" => $rGet['error']], 500);

    $row = $rGet['data'][0] ?? null;
    if (!$row) out(["ok" => false, "error" => ["message" => "Evento não encontrado"]], 404);

    $googleEventId = trim((string)($row['google_event_id'] ?? ''));
    $googleCalId   = trim((string)($row['google_calendar_id'] ?? ''));
    if ($googleCalId === '') $googleCalId = 'primary';

    // 2) Se tiver google_event_id, deletar no Google primeiro
    if ($googleEventId !== '') {
      $profileEmail = gc_get_profile_email_by_user_key($user_key);
      $googleAcc = $profileEmail ? gc_get_google_account_by_profile_email($profileEmail) : null;
      $googleAccess = $googleAcc ? gc_ensure_access_token($googleAcc) : null;

      if (!$googleAccess) {
        out(["ok" => false, "error" => ["message" => "Conta Google não conectada/sem token. Não foi possível excluir no Google Calendar."]], 400);
      }

      $gDel = gc_delete_calendar_event((string)$googleAccess, $googleCalId, $googleEventId);

      if (!($gDel['ok'] ?? false)) {
        // não deleta no banco se falhar no Google (pra não divergir)
        out([
          "ok" => false,
          "error" => ["message" => "Falha ao excluir no Google Calendar"],
          "details" => $gDel
        ], 502);
      }
    }

    // 3) Deletar no Supabase
    $qDel = [
      'id'       => 'eq.' . $id,
      'user_key' => 'eq.' . $user_key,
    ];
    $rDel = supabase_request('DELETE', $table, $qDel, []);
    if (!$rDel['ok']) out(["ok" => false, "error" => $rDel['error'] ?? ["message" => "Erro Supabase (delete)"]], 500);

    out(["ok" => true]);
  }

  out(["ok" => false, "error" => ["message" => "Ação inválida"]], 400);
}

out(["ok" => false, "error" => ["message" => "Método inválido"]], 405);