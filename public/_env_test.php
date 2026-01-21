<?php
require_once __DIR__ . '/../app/bootstrap.php';
var_dump(getenv('SUPABASE_URL'), getenv('SUPABASE_SERVICE_ROLE_KEY'));