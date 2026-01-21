<?php
// app/google_calendar.php

require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/bootstrap.php';

function gc_http_json(string $method, string $url, array $headers = [], $body = null): array
{
    $ch = curl_init($url);
    $h = array_merge(['Content-Type: application/json'], $headers);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $h,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) return ['ok' => false, 'status' => 0, 'error' => ['message' => $err]];

    $json = null;
    if ($resp !== false && $resp !== '') $json = json_decode($resp, true);

    if ($code >= 200 && $code < 300) {
        return ['ok' => true, 'status' => $code, 'data' => $json ?? $resp];
    }

    $msg = is_array($json) ? ($json['error']['message'] ?? $json['message'] ?? null) : null;

    return [
        'ok' => false,
        'status' => $code,
        'error' => ['message' => $msg ?: ('HTTP ' . $code), 'raw' => $json ?? $resp]
    ];
}

/**
 * 1) user_key -> profile_email (tabela profiles)
 */
function gc_get_profile_email_by_user_key(string $user_key): ?string
{
    $res = supabase_request('GET', '/rest/v1/profiles', [
        'select' => 'email',
        'user_key' => 'eq.' . $user_key,
        'limit' => '1',
    ], []);

    if (!$res['ok']) return null;
    $row = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
    $email = is_array($row) ? trim((string)($row['email'] ?? '')) : '';
    return $email !== '' ? $email : null;
}

/**
 * 2) profile_email -> google_accounts row
 */
function gc_get_google_account_by_profile_email(string $profile_email): ?array
{
    $res = supabase_request('GET', '/rest/v1/google_accounts', [
        'select' => 'id,profile_email,google_email,access_token,refresh_token,expires_at',
        'profile_email' => 'eq.' . $profile_email,
        'limit' => '1',
    ], []);

    if (!$res['ok']) return null;
    $row = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
    return is_array($row) ? $row : null;
}

/**
 * 3) Refresh token se necessário (e salva de volta no Supabase)
 */
function gc_ensure_access_token(array $acc): ?string
{
    $access  = trim((string)($acc['access_token'] ?? ''));
    $refresh = trim((string)($acc['refresh_token'] ?? ''));
    $id      = trim((string)($acc['id'] ?? ''));

    // expires_at no seu Supabase é string tipo "2026-01-17 02:04:38+00"
    $expiresRaw = (string)($acc['expires_at'] ?? '');
    $expiresAt  = $expiresRaw !== '' ? (int)strtotime($expiresRaw) : 0;

    // margem de segurança: 60s
    $now = time();
    if ($access !== '' && $expiresAt > ($now + 60)) {
        return $access;
    }

    // sem refresh_token não dá pra renovar
    if ($refresh === '' || $id === '') return null;

    // usa as MESMAS env vars do oauth_callback.php
    $clientId     = env('GOOGLE_OAUTH_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));
    $clientSecret = env('GOOGLE_OAUTH_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', ''));

    if ($clientId === '' || $clientSecret === '') return null;

    $tokenUrl = 'https://oauth2.googleapis.com/token';

    $post = http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refresh,
        'grant_type' => 'refresh_token',
    ]);

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => $post,
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || !$resp) return null;

    $j = json_decode((string)$resp, true);
    if (!($code >= 200 && $code < 300) || !is_array($j) || empty($j['access_token'])) {
        return null;
    }

    $newAccess  = (string)$j['access_token'];
    $expiresIn  = (int)($j['expires_in'] ?? 3600);
    $newExpires = gmdate('Y-m-d H:i:s+00', time() + max(60, $expiresIn));

    // salva no Supabase (mantendo seu padrão de coluna string)
    supabase_request('PATCH', '/rest/v1/google_accounts', [
        'id' => 'eq.' . $id,
    ], [
        'access_token' => $newAccess,
        'expires_at' => $newExpires,
        'updated_at' => gmdate('c'),
    ]);

    return $newAccess;
}

/**
 * 4) Cria evento no Google Calendar (primary)
 */
function gc_create_calendar_event(string $accessToken, array $payload): array
{
    $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    return gc_http_json('POST', $url, [
        'Authorization: Bearer ' . $accessToken,
    ], $payload);
}
