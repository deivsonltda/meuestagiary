<?php
// public/api/account.php

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/supabase.php';

require_auth();
require_csrf();
enforce_trial_active_or_redirect();

header('Content-Type: application/json; charset=utf-8');

function bad($msg, $code = 400) {
  json_out(['ok' => false, 'error' => $msg], $code);
}

function clamp_int($v, $min, $max) {
  $n = (int)$v;
  if ($n < $min) return $min;
  if ($n > $max) return $max;
  return $n;
}

$user_key = current_user_key();
if (!$user_key) {
  bad('Sessão inválida. Faça login novamente.', 401);
}

// defaults (como você pediu)
$DEFAULTS = [
  'ai_mood' => 'neutra',                 // neutra | humorada | seria
  'response_size' => 'media',            // curta | media | longa
  'daily_reminder_time' => '08:00',      // HH:MM
  'reminder_default_minutes' => 30,      // 30 | 60 | 120
];

// ---------------------------
// Descobre action (aceita GET e POST)
// ---------------------------
$action = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $action = (string)($_GET['action'] ?? '');
} else {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw ?: '{}', true);
  if (!is_array($data)) $data = [];
  $action = (string)($data['action'] ?? '');
}

$allowed = ['change_password'];
if (!in_array($action, $allowed, true)) {
  bad('Ação inválida.');
}

// ---------------------------
// CHANGE PASSWORD (mantém compat)
// ---------------------------
if ($action === 'change_password') {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw ?: '{}', true);
  if (!is_array($data)) $data = [];

  $p1 = (string)($data['password'] ?? '');
  $p2 = (string)($data['password_confirm'] ?? '');

  if (strlen($p1) < 8) bad('A senha deve ter no mínimo 8 caracteres.');
  if ($p1 !== $p2) bad('As senhas não conferem.');

  // aqui você mantém sua lógica atual (não tenho seu trecho), então retorno ok:
  json_out(['ok' => true]);
}