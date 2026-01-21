<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/auth.php';
require_once __DIR__ . '/../../../app/supabase.php';

require_auth();

$clientId     = env('GOOGLE_OAUTH_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));
$clientSecret = env('GOOGLE_OAUTH_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', ''));
$redirect     = env('GOOGLE_OAUTH_REDIRECT_URI', env('GOOGLE_OAUTH_REDIRECT_URI', ''));

if ($clientId === '' || $clientSecret === '' || $redirect === '') {
    http_response_code(500);
    echo "Google OAuth não configurado (CLIENT_ID/SECRET/REDIRECT).";
    exit;
}

$state = $_GET['state'] ?? '';
if ($state === '' || empty($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], $state)) {
    http_response_code(400);
    echo "State inválido.";
    exit;
}

if (!empty($_GET['error'])) {
    $return = $_SESSION['google_oauth_return'] ?? (rtrim(app_config('base_url', ''), '/') . '/app.php?page=account&tab=integracoes');
    header('Location: ' . $return);
    exit;
}

$code = $_GET['code'] ?? '';
if ($code === '') {
    http_response_code(400);
    echo "Code ausente.";
    exit;
}

$profileEmail = strtolower(trim((string)($_SESSION['user']['email'] ?? '')));
if ($profileEmail === '') {
    http_response_code(401);
    echo "Sessão inválida (sem email).";
    exit;
}

/** Troca code por tokens */
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_POSTFIELDS => http_build_query([
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirect,
        'grant_type' => 'authorization_code',
    ]),
]);

$raw = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($raw === false || $http < 200 || $http >= 300) {
    http_response_code(500);
    echo "Falha token HTTP={$http}. {$err}";
    exit;
}

$token = json_decode($raw, true);

$accessToken  = (string)($token['access_token'] ?? '');
$refreshToken = (string)($token['refresh_token'] ?? '');
$scope        = (string)($token['scope'] ?? '');
$tokenType    = (string)($token['token_type'] ?? '');
$expiresIn    = (int)($token['expires_in'] ?? 0);
$idToken      = (string)($token['id_token'] ?? ''); // ✅ pode existir se scope tiver openid

if ($accessToken === '') {
    http_response_code(500);
    echo "access_token vazio.";
    exit;
}

$expiresAt = null;
if ($expiresIn > 0) {
    $expiresAt = gmdate('c', time() + $expiresIn);
}

/** Pega o email do Google */
$googleEmail = '';

// 1) userinfo (como você já fazia)
$ch = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
]);
$uRaw = curl_exec($ch);
$uHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($uRaw !== false && $uHttp >= 200 && $uHttp < 300) {
    $u = json_decode($uRaw, true);
    $googleEmail = (string)($u['email'] ?? '');
}

// 2) fallback: extrair do id_token (JWT) se userinfo não trouxe
if ($googleEmail === '' && $idToken !== '') {
    $parts = explode('.', $idToken);
    if (count($parts) >= 2) {
        $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
        if ($payloadJson !== false) {
            $payload = json_decode($payloadJson, true);
            if (is_array($payload) && !empty($payload['email'])) {
                $googleEmail = (string)$payload['email'];
            }
        }
    }
}

// 3) fallback: tokeninfo (funciona bem pra pegar email quando aberto)
if ($googleEmail === '') {
    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?access_token=' . urlencode($accessToken));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $tRaw = curl_exec($ch);
    $tHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($tRaw !== false && $tHttp >= 200 && $tHttp < 300) {
        $t = json_decode($tRaw, true);
        // tokeninfo costuma devolver "email" quando o scope inclui email
        $googleEmail = (string)($t['email'] ?? $googleEmail);
    }
}

/** UPSERT na google_accounts (1 por profile_email) */
$now = gmdate('c');

$ins = supabase_request('POST', '/rest/v1/google_accounts', ['on_conflict' => 'profile_email', 'prefer' => ['return=minimal', 'resolution=merge-duplicates']], [[
    'profile_email' => $profileEmail,
    'google_email'  => $googleEmail,
    'refresh_token' => $refreshToken,
    'access_token'  => $accessToken,
    'token_type'    => $tokenType,
    'scope'         => $scope,
    'expires_at'    => $expiresAt,
    'provider'      => 'google',
    'calendar_id'   => null,
    'revoked_at'    => null,
    'updated_at'    => $now,
]]);

if (empty($ins['ok'])) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Falha ao salvar conta Google no Supabase.\n";
    echo "HTTP: " . ($ins['http'] ?? '') . "\n";
    echo "Erro: " . json_encode($ins['error'] ?? null, JSON_UNESCAPED_UNICODE) . "\n";
    exit;
}

/** Toggle do profiles: google_calendar_enabled = true */
supabase_request('PATCH', '/rest/v1/profiles', [
    'email' => 'eq.' . $profileEmail,
], [
    'google_calendar_enabled' => true,
]);

$return = $_SESSION['google_oauth_return'] ?? (rtrim(app_config('base_url', ''), '/') . '/app.php?page=account&tab=integracoes');
unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_return']);

header('Location: ' . $return);
exit;