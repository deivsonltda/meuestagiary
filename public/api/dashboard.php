<?php
// public/api/dashboard.php

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/supabase.php';

header('Content-Type: application/json; charset=utf-8');

// IMPORTANT: evita "Hoje/Semana" zerarem por timezone do servidor
date_default_timezone_set('America/Sao_Paulo');

function out($arr, int $code = 200) {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

$user_key = current_user_key();
if (!$user_key) out(["ok" => false, "error" => ["message" => "Não autenticado"]], 401);

$period = $_GET['period'] ?? 'month'; // week|month|today
$m = (int)($_GET['m'] ?? date('n'));
$y = (int)($_GET['y'] ?? date('Y'));
$m = max(1, min(12, $m));
$y = max(1970, $y);

function last_day_of_month(int $y, int $m): int {
  return cal_days_in_month(CAL_GREGORIAN, $m, $y);
}
function ymdate(int $y, int $m, int $d): string {
  return sprintf('%04d-%02d-%02d', $y, $m, $d);
}
function date_add_days(string $date, int $days): string {
  $ts = strtotime($date . ' 00:00:00');
  return date('Y-m-d', $ts + ($days * 86400));
}
function monday_of_week(string $date): string {
  $ts = strtotime($date . ' 00:00:00');
  $w = (int)date('N', $ts); // 1..7 (Mon..Sun)
  return date('Y-m-d', $ts - (($w - 1) * 86400));
}

$today = date('Y-m-d');

// Range atual
if ($period === 'today') {
  $start = $today;
  $end   = $today;

} elseif ($period === 'week') {
  // Últimos 7 dias (incluindo hoje)
  $end   = $today;
  $start = date_add_days($today, -6);

} else {
  // month
  $start = ymdate($y, $m, 1);
  $end   = ymdate($y, $m, last_day_of_month($y, $m));
}

// Range anterior (para delta)
$prevStart = null;
$prevEnd   = null;

if ($period === 'today') {
  $prevStart = date_add_days($today, -1);
  $prevEnd   = $prevStart;
} elseif ($period === 'week') {
  // 7 dias anteriores ao range atual: (start-7 .. start-1)
  $prevEnd   = date_add_days($start, -1);
  $prevStart = date_add_days($start, -7);
} else {
  // month: mês anterior inteiro
  $pm = $m - 1;
  $py = $y;
  if ($pm < 1) { $pm = 12; $py = $y - 1; }
  $prevStart = ymdate($py, $pm, 1);
  $prevEnd   = ymdate($py, $pm, last_day_of_month($py, $pm));
}

try {
  // 1) categories (nome + cor para donut)
  $cats = supabase_request('GET', '/rest/v1/categories', [
    'select'   => 'id,name,color',
    'user_key' => 'eq.' . $user_key,
    'limit'    => '5000',
  ], []);

  if (!$cats['ok']) out(["ok" => false, "error" => $cats['error'] ?? ["message" => "Erro Supabase (categories)"]], 500);

  $catById = [];
  $catColorById = [];
  foreach (($cats['data'] ?? []) as $c) {
    $id = (string)($c['id'] ?? '');
    if ($id === '') continue;
    $catById[$id] = (string)($c['name'] ?? 'Sem categoria');

    $col = trim((string)($c['color'] ?? ''));
    $catColorById[$id] = ($col !== '') ? $col : '#6b7280';
  }

  // Função: consulta transactions por range
  $fetchTxRange = function(string $startDate, string $endDate) use ($user_key) {
    $q = [
      'select'   => 'id,amount,type,status,date,category_id,created_at',
      'user_key' => 'eq.' . $user_key,
      'and'      => '(date.gte.' . $startDate . ',date.lte.' . $endDate . ')',
      'order'    => 'date.asc,created_at.asc',
      'limit'    => '50000',
    ];
    return supabase_request('GET', '/rest/v1/transactions', $q, []);
  };

  // 2) transactions do período atual
  $txRes = $fetchTxRange($start, $end);
  if (!$txRes['ok']) out(["ok" => false, "error" => $txRes['error'] ?? ["message" => "Erro Supabase (transactions)"]], 500);
  $tx = is_array($txRes['data']) ? $txRes['data'] : [];

  // 3) transactions do período anterior (para delta)
  $txPrev = [];
  if ($prevStart && $prevEnd) {
    $txPrevRes = $fetchTxRange($prevStart, $prevEnd);
    if (!$txPrevRes['ok']) out(["ok" => false, "error" => $txPrevRes['error'] ?? ["message" => "Erro Supabase (transactions prev)"]], 500);
    $txPrev = is_array($txPrevRes['data']) ? $txPrevRes['data'] : [];
  }

  // KPIs (atual)
  $entradas_realizado = 0.0;  // income + received
  $entradas_previsto  = 0.0;  // income + receivable
  $saidas_realizado   = 0.0;  // expense + paid
  $saidas_previsto    = 0.0;  // expense + due

  // Série diária (saldo acumulado) — apenas realizados
  $dailyNet = [];
  $days = [];
  for ($d = $start; $d <= $end; $d = date_add_days($d, 1)) {
    $dailyNet[$d] = 0.0;
    $days[] = $d;
  }

  // Donut despesas (com cor)
  $donutPago = [];
  $donutAPagar = [];

  foreach ($tx as $row) {
    $type   = (string)($row['type'] ?? '');
    $status = (string)($row['status'] ?? '');
    $date   = (string)($row['date'] ?? '');
    $amount = (float)($row['amount'] ?? 0);

    $catId = $row['category_id'] ? (string)$row['category_id'] : '';
    $catName  = ($catId && isset($catById[$catId])) ? $catById[$catId] : 'Sem categoria';
    $catColor = ($catId && isset($catColorById[$catId])) ? $catColorById[$catId] : '#6b7280';

    // Entradas
    if ($type === 'income') {
      if ($status === 'received')   $entradas_realizado += $amount;
      if ($status === 'receivable') $entradas_previsto  += $amount;
    }

    // Saídas
    if ($type === 'expense') {
      if ($status === 'paid') $saidas_realizado += $amount;
      if ($status === 'due')  $saidas_previsto  += $amount;
    }

    // Série de saldo (apenas realizados)
    if (isset($dailyNet[$date])) {
      if ($type === 'income'  && $status === 'received') $dailyNet[$date] += $amount;
      if ($type === 'expense' && $status === 'paid')     $dailyNet[$date] -= $amount;
    }

    // Donut (apenas despesas)
    if ($type === 'expense' && $amount > 0) {
      if ($status === 'paid') {
        if (!isset($donutPago[$catName])) {
          $donutPago[$catName] = ['value' => 0.0, 'color' => $catColor];
        }
        $donutPago[$catName]['value'] += $amount;
      }
      if ($status === 'due') {
        if (!isset($donutAPagar[$catName])) {
          $donutAPagar[$catName] = ['value' => 0.0, 'color' => $catColor];
        }
        $donutAPagar[$catName]['value'] += $amount;
      }
    }
  }

  $resultado = $entradas_realizado - $saidas_realizado;

  // KPIs (anterior) — só precisa de resultado anterior p/ delta
  $prev_entradas_realizado = 0.0;
  $prev_saidas_realizado   = 0.0;

  foreach ($txPrev as $row) {
    $type   = (string)($row['type'] ?? '');
    $status = (string)($row['status'] ?? '');
    $amount = (float)($row['amount'] ?? 0);

    if ($type === 'income'  && $status === 'received') $prev_entradas_realizado += $amount;
    if ($type === 'expense' && $status === 'paid')     $prev_saidas_realizado   += $amount;
  }

  $prev_resultado = $prev_entradas_realizado - $prev_saidas_realizado;

  // Delta % (comparando com período anterior). Se base == 0, não mostra.
  $delta_pct = null;
  if (abs($prev_resultado) >= 0.00001) {
    $delta_pct = (($resultado - $prev_resultado) / abs($prev_resultado)) * 100.0;
  }

  // Série acumulada
  $labels = [];
  $series = [];
  $acc = 0.0;
  foreach ($days as $d) {
    $acc += $dailyNet[$d] ?? 0.0;
    $labels[] = date('d', strtotime($d));
    $series[] = round($acc, 2);
  }

  // Mini: últimos 7 pontos
  $miniLabels = array_slice($labels, -7);
  $miniData   = array_slice($series, -7);

  // Top 5 preservando cor
  $mkTop = function(array $map): array {
    uasort($map, function($a, $b) {
      return ((float)($b['value'] ?? 0)) <=> ((float)($a['value'] ?? 0));
    });

    $out = [];
    $i = 0;
    foreach ($map as $label => $v) {
      $out[] = [
        'label' => (string)$label,
        'value' => round((float)($v['value'] ?? 0), 2),
        'color' => (string)($v['color'] ?? '#6b7280'),
      ];
      $i++;
      if ($i >= 5) break;
    }
    return $out;
  };

  out([
    "ok" => true,
    "range" => ["start" => $start, "end" => $end],
    "prev_range" => ["start" => $prevStart, "end" => $prevEnd],
    "kpis" => [
      "resultado" => round($resultado, 2),
      "prev_resultado" => round($prev_resultado, 2),
      "delta_pct" => is_null($delta_pct) ? null : round($delta_pct, 1),
      "entradas" => [
        "realizado" => round($entradas_realizado, 2),
        "previsto"  => round($entradas_previsto, 2),
      ],
      "saidas" => [
        "realizado" => round($saidas_realizado, 2),
        "previsto"  => round($saidas_previsto, 2),
      ],
    ],
    "charts" => [
      "saldo" => ["labels" => $labels, "data" => $series],
      "mini"  => ["labels" => $miniLabels, "data" => $miniData],
      "donut" => [
        "pago"   => $mkTop($donutPago),
        "apagar" => $mkTop($donutAPagar),
      ],
    ],
  ]);

} catch (Throwable $e) {
  out([
    "ok" => false,
    "error" => "fatal",
    "details" => [
      "message" => $e->getMessage(),
      "file" => $e->getFile(),
      "line" => $e->getLine(),
    ]
  ], 500);
}