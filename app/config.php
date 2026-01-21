<?php
// app/config.php

return [
  'app_name' => 'MeuEstagiário',

  // base da aplicação (ex: /estagiario/public)
  'base_url' => env('APP_URL', ''),

  // sessão
  'session_name' => 'estagiario_sess',

  // -------------------------------------------------
  // Supabase
  // -------------------------------------------------
  'supabase_url' => env('SUPABASE_URL', 'https://brytbhcpaiuvfhnxkvbq.supabase.co'),

  'supabase_anon' => env(
    'SUPABASE_ANON_KEY',
    'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJyeXRiaGNwYWl1dmZobnhrdmJxIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjgwNTc1OTIsImV4cCI6MjA4MzYzMzU5Mn0.PZ4vLI_-7yQ7GgDiZ4UnpK6e_qgDVuyd7dSvWyMOHf4'
  ),

  'supabase_service_role_key' => env(
    'SUPABASE_SERVICE_ROLE_KEY',
    null // ❗ nunca hardcode em produção
  ),

  // Token interno do n8n (protege endpoints sem sessão)
  'n8n_internal_token' => env('N8N_INTERNAL_TOKEN', ''),

  // -------------------------------------------------
  // Google OAuth (opcional consumir aqui também)
  // -------------------------------------------------
  'google_oauth' => [
    'client_id'     => env('GOOGLE_OAUTH_CLIENT_ID'),
    'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
    'redirect_uri'  => env('GOOGLE_OAUTH_REDIRECT_URI'),
  ],
];
