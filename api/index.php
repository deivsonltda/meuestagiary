<?php
// api/index.php
// Router para servir os arquivos PHP do /public em Vercel (Serverless)

// Caminho absoluto da pasta /public no seu repo
$publicDir = realpath(__DIR__ . '/../public');
if (!$publicDir) {
  http_response_code(500);
  echo "public/ não encontrado";
  exit;
}

// Normaliza o path requisitado
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';

// Remove barra final (exceto /)
if ($path !== '/' && str_ends_with($path, '/')) {
  $path = rtrim($path, '/');
}

// Se pediu direto um arquivo dentro de /public
// Ex: /login.php -> public/login.php
$target = $publicDir . $path;

// Se não tem extensão, tenta .php (ex: /login -> public/login.php)
if (!pathinfo($target, PATHINFO_EXTENSION)) {
  $targetPhp = $target . '.php';
  if (is_file($targetPhp)) {
    $target = $targetPhp;
  }
}

// Se for "/" ou arquivo não existir, cai no index.php
if ($path === '/' || !is_file($target)) {
  $target = $publicDir . '/index.php';
}

// Segurança: impede path traversal
$realTarget = realpath($target);
if (!$realTarget || !str_starts_with($realTarget, $publicDir)) {
  http_response_code(404);
  echo "Not found";
  exit;
}

// Serve o PHP
require $realTarget;