<?php
// app/supabase.php

/**
 * Request padrão para REST (service_role no backend).
 * - Usa SUPABASE_SERVICE_ROLE_KEY por padrão (igual você já está usando).
 */
function supabase_request(string $method, string $path, ?array $query = [], $body = null): array
{
  // Alguns endpoints (GET/DELETE) chamam isso com null; normaliza.
  $query = $query ?? [];
  return supabase_request_with_bearer($method, $path, $query, $body, app_config('supabase_service_role_key'));
}

/**
 * Request com Bearer customizado (ex: access_token do usuário para reset de senha).
 * Se $bearer_key vier vazio, tenta usar service_role.
 */
function supabase_request_with_bearer(string $method, string $path, ?array $query = [], $body = null, ?string $bearer_key = null): array
{
  $query = $query ?? [];
  $base = rtrim(app_config('supabase_url'), '/');
  $url  = $base . $path;

  // reset Prefer header each call
  $GLOBALS['__SUPABASE_PREFER__'] = null;

  if (!empty($query)) {
    // Prefer headers (PostgREST) – remove keys from query string
    $preferParts = [];
    if (isset($query['prefer'])) {
      $prefer = $query['prefer'];
      unset($query['prefer']);
      if (is_array($prefer)) {
        $preferParts = array_merge($preferParts, $prefer);
      } elseif (is_string($prefer) && trim($prefer) !== '') {
        $preferParts[] = $prefer;
      }
    }

    if (!empty($preferParts)) {
      $GLOBALS['__SUPABASE_PREFER__'] = implode(',', $preferParts);
    }

    if (!empty($query)) {
      $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }
  }

  $service = app_config('supabase_service_role_key');

  // Airbag: se por algum motivo vier vazio, tenta ler direto do .env uma vez
  if (!$service) {
    $root = realpath(__DIR__ . '/..');
    $envPath = $root ? ($root . DIRECTORY_SEPARATOR . '.env') : null;

    if ($envPath && is_file($envPath)) {
      $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
      foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ($k === 'SUPABASE_SERVICE_ROLE_KEY' && $v !== '') {
          // remove aspas
          $first = $v[0];
          $last  = $v[strlen($v) - 1];
          if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $v = substr($v, 1, -1);
          }
          putenv("SUPABASE_SERVICE_ROLE_KEY=$v");
          $_ENV["SUPABASE_SERVICE_ROLE_KEY"] = $v;
          $service = $v;
          break;
        }
      }
    }

    if (!$service) {
      return [
        'ok' => false,
        'error' => ['message' => 'SUPABASE_SERVICE_ROLE_KEY não configurada']
      ];
    }
  }

  $bearer = $bearer_key ?: $service;

  $headers = [
    'apikey: ' . $service,
    'Authorization: Bearer ' . $bearer,
    'Content-Type: application/json',
  ];

  if (!empty($GLOBALS['__SUPABASE_PREFER__'])) {
    $headers[] = 'Prefer: ' . $GLOBALS['__SUPABASE_PREFER__'];
  }

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => strtoupper($method),
    CURLOPT_HTTPHEADER     => $headers,
  ]);

  if ($body !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
  }

  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($err) {
    return ['ok' => false, 'error' => ['message' => $err]];
  }

  $json = null;
  if ($resp !== false && $resp !== '') {
    $json = json_decode($resp, true);
  }

  if ($code >= 200 && $code < 300) {
    return ['ok' => true, 'status' => $code, 'data' => $json ?? $resp];
  }

  // tenta extrair mensagem supabase
  $msg = null;
  if (is_array($json)) {
    $msg = $json['message'] ?? $json['error_description'] ?? $json['hint'] ?? null;
  }

  return [
    'ok' => false,
    'status' => $code,
    'error' => [
      'message' => $msg ?: ('HTTP ' . $code),
      'raw' => $json ?? $resp
    ],
  ];
}
