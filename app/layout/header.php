<?php
require_once __DIR__ . '/../bootstrap.php';
$user = $_SESSION['user'] ?? null;

$css_ver = @filemtime(__DIR__ . '/../../public/assets/css/app.css') ?: time();
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
  <title>MeuEstagiário<?= isset($pageTitle) ? ' • ' . h($pageTitle) : '' ?></title>
  <link rel="icon" type="image/png" href="<?= h(app_config('base_url', '')) ?>/assets/img/favicon.png">
  <link rel="apple-touch-icon" href="<?= h(app_config('base_url', '')) ?>/assets/img/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(app_config('base_url')) ?>/assets/css/app.css?v=<?= $css_ver ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>

<body class="app-body">
  <div class="app-shell" id="appShell">