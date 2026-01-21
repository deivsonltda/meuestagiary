<?php
// public/api/agenda_events.php
//
// ✅ Mantém o que já funciona: action=create, grava no Supabase e (se conectado) cria no Google.
// ✅ Correção principal: ao criar no Google, tenta salvar o google_event_id no Supabase
//    SEM QUEBRAR caso sua tabela ainda NÃO tenha as colunas (ignora erro 42703).
// ✅ Não muda seu esquema de segurança (X-Internal-Token opcional).

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/supabase.php';
require_once __DIR__ . '/../../app/google_calendar.php';

header('Content-Type: application/json; charset=utf-8');

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

function try_save_google_ids_no_break(?string $localId, ?string $googleEventId, ?string $googleCalendarId): void
{
  if (!$localId || !$googleEventId) return;

  $patch = [
    'google_event_id' => $googleEventId,
  ];
  if ($googleCalendarId) $patch['google_calendar_id'] = $googleCalendarId;

  $r = supabase_request(
    'PATCH',
    '/rest/v1/agenda_events',
    ['id' => 'eq.' . $localId],
    $patch,
    ['Prefer' => 'return=minimal']
  );

  // Se ainda não existe coluna no banco, IGNORA (não quebra fluxo).
  // PostgREST costuma retornar 42703 "column does not exist"
  if (!($r['ok'] ?? false)) {
    $code = $r['error']['raw']['code'] ?? $r['error']['code'] ?? null;
    if ((string)$code === '42703') return;
    // Se der qualquer outro erro, também não vamos quebrar o create (só não salva o vínculo).
    return;
  }
}

$action = (string)($_GET['action'] ?? '');

// 🔒 Proteção p/ n8n (sem mexer no login do site):
// coloque N8N_INTERNAL_TOKEN no .env e envie "X-Internal-Token" no n8n
$expected = trim((string)app_config('n8n_internal_token', ''));
if ($expected !== '') {
  $got = trim((string)($_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? ''));
  if (!hash_equals($expected, $got)) out(['ok' => false, 'error' => 'unauthorized'], 401);
}

if ($action !== 'create') out(['ok' => false, 'error' => 'Ação inválida.'], 400);

$b = read_json_body();

$user_key = trim((string)($b['user_key'] ?? ''));
$appointments = $b['appointments'] ?? null;

if ($user_key === '') out(['ok' => false, 'error' => 'user_key obrigatório'], 400);
if (!is_array($appointments) || !count($appointments)) out(['ok' => false, 'error' => 'appointments[] obrigatório'], 400);

// 1) Descobre email do perfil para achar google_accounts
$profileEmail = gc_get_profile_email_by_user_key($user_key);

// 2) Pega conta google (se existir)
$googleAcc = $profileEmail ? gc_get_google_account_by_profile_email($profileEmail) : null;
$googleAccess = $googleAcc ? gc_ensure_access_token($googleAcc) : null;
// Se não tiver googleAccess, a gente ainda grava no Supabase; só marca que não sincronizou.

$results = [];

foreach ($appointments as $i => $a) {
  $title = trim((string)($a['title'] ?? ''));
  $start = trim((string)($a['start'] ?? '')); // ISO com offset / RFC3339
  $end   = trim((string)($a['end'] ?? ''));   // ISO com offset / RFC3339
  $tz    = trim((string)($a['timezone'] ?? 'America/Sao_Paulo'));
  $rem   = isset($a['reminder_minutes']) ? (int)$a['reminder_minutes'] : null;

  if ($title === '' || $start === '' || $end === '') {
    $results[] = ['ok' => false, 'index' => $i, 'error' => 'title/start/end obrigatórios'];
    continue;
  }

  // 3) Grava no Supabase (agenda_events) — só colunas que existem
  $insert = [
    'user_key' => $user_key,
    'title' => $title,
    'start_at' => $start,
    'end_at' => $end,
  ];
  if ($rem !== null) $insert['reminder_minutes'] = $rem;

  $ins = supabase_request('POST', '/rest/v1/agenda_events', [
    'prefer' => ['return=representation'],
  ], $insert);

  if (!$ins['ok']) {
    $results[] = [
      'ok' => false,
      'index' => $i,
      'error' => 'Falha ao gravar no Supabase',
      'details' => $ins['error'] ?? null
    ];
    continue;
  }

  $row = (is_array($ins['data']) && isset($ins['data'][0])) ? $ins['data'][0] : null;
  $localId = is_array($row) ? ($row['id'] ?? null) : null;

  // 4) Cria no Google Calendar (se conectado)
  $googleEventId = null;
  $googleError = null;

  if ($googleAccess) {
    $gPayload = [
      'summary' => $title,
      'start' => [
        'dateTime' => $start,
        'timeZone' => ($tz !== '' ? $tz : 'America/Sao_Paulo'),
      ],
      'end' => [
        'dateTime' => $end,
        'timeZone' => ($tz !== '' ? $tz : 'America/Sao_Paulo'),
      ],
    ];

    // lembrete opcional
    if ($rem !== null && $rem > 0) {
      $gPayload['reminders'] = [
        'useDefault' => false,
        'overrides' => [
          ['method' => 'popup', 'minutes' => $rem]
        ]
      ];
    }

    $g = gc_create_calendar_event($googleAccess, $gPayload);
    if ($g['ok']) {
      $googleEventId = $g['data']['id'] ?? null;

      // ✅ tenta salvar vínculo no Supabase (SEM quebrar se coluna ainda não existe)
      $googleCalendarId = is_array($googleAcc) ? (string)($googleAcc['calendar_id'] ?? '') : '';
      if ($googleCalendarId === '') $googleCalendarId = 'primary';

      try_save_google_ids_no_break($localId ? (string)$localId : null, $googleEventId ? (string)$googleEventId : null, $googleCalendarId);
    } else {
      $googleError = $g['error'] ?? null;
    }
  } else {
    $googleError = $googleAcc ? 'Token indisponível/expirado' : 'Conta Google não conectada';
  }

  $results[] = [
    'ok' => true,
    'index' => $i,
    'agenda_event_id' => $localId,
    'google_event_id' => $googleEventId,
    'google_error' => $googleError,
  ];
}

out(['ok' => true, 'results' => $results]);