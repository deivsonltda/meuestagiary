<?php
require_once __DIR__ . '/../app/auth.php';
require_auth();
require_csrf();
enforce_trial_active_or_redirect();

$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard','transactions','categories','agenda','cards','account'];

if (!in_array($page, $allowed, true)) $page = 'dashboard';

require __DIR__ . '/../app/layout/header.php';
require __DIR__ . '/../app/layout/topbar.php';
require __DIR__ . '/../app/layout/sidebar.php';

echo '<main class="app-main">';
$path = __DIR__ . '/../app/pages/' . $page . '.php';
if (file_exists($path)) {
  require $path;
} else {
  echo '<div class="card"><div class="card-pad">Página não encontrada.</div></div>';
}
echo '</main>';

require __DIR__ . '/../app/layout/footer.php';