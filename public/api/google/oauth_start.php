<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/auth.php';

require_auth();

$clientId = env('GOOGLE_OAUTH_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));
$redirect = env('GOOGLE_OAUTH_REDIRECT_URI', env('GOOGLE_OAUTH_REDIRECT_URI', ''));

// ✅ AQUI: pedir também identidade (email/profile) + calendar
$scope = implode(' ', [
  'openid',
  'email',
  'profile',
  'https://www.googleapis.com/auth/calendar',
]);

if ($clientId === '' || $redirect === '') {
  http_response_code(500);
  echo "Google OAuth não configurado (CLIENT_ID / REDIRECT_URI).";
  exit;
}

$return = $_GET['return'] ?? (rtrim(app_config('base_url',''),'/') . '/app.php?page=account&tab=integracoes');

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state']   = $state;
$_SESSION['google_oauth_return']  = $return;

$params = [
  'client_id' => $clientId,
  'redirect_uri' => $redirect,
  'response_type' => 'code',
  'scope' => $scope,
  'access_type' => 'offline',
  'prompt' => 'consent',
  'include_granted_scopes' => 'true',
  'state' => $state,
];

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;