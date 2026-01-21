<?php
// public/api/forgot_password.php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/supabase.php';

function back(string $msg = '', bool $ok = false): void {
  $base = rtrim((string)app_config('base_url', ''), '/');
  $q = $ok ? 'success=1' : http_build_query(['error' => $msg]);
  header("Location: {$base}/forgot.php?{$q}", true, 302);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') back('Método inválido.');

$email = strtolower(trim((string)($_POST['email'] ?? '')));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) back('E-mail inválido.');

// ✅ Redirect vem do .env (permite trocar fácil depois)
$redirectTo = trim((string)env('PASSWORD_RESET_REDIRECT_URL', ''));

// fallback seguro (caso esqueça de setar no .env)
if ($redirectTo === '') {
  $appUrl = rtrim((string)env('APP_URL', ''), '/');
  $redirectTo = $appUrl ? ($appUrl . '/reset.php') : '';
}

// Se ainda assim estiver vazio, não tem como mandar recovery certo
if ($redirectTo === '' || !preg_match('#^https?://#i', $redirectTo)) {
  // não vaza detalhes pro usuário (mas você deve corrigir o .env)
  back('', true);
}

$res = supabase_request('POST', '/auth/v1/recover', [], [
  'email' => $email,
  'redirect_to' => $redirectTo,
]);

// por segurança: sempre success (não enumera conta)
back('', true);