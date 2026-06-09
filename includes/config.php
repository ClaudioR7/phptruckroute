<?php
/**
 * AMM Duarte Transportadora — Configurações v5.0
 * TruckRoute Pro — 100% gratuito: OpenStreetMap + OSRM + Nominatim
 *
 * INSTRUÇÕES:
 * 1. Copie este arquivo para config.local.php e edite APENAS o local
 * 2. Nunca commite config.local.php no git (está no .gitignore)
 */

// ─── Banco de Dados ───────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: 'truckroute');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

// ─── APIs Gratuitas (sem chave necessária) ────────────────────
define('NOMINATIM_URL', 'https://nominatim.openstreetmap.org');
define('OSRM_URL',      'https://router.project-osrm.org');
define('OSM_TILE_URL',  'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
define('NOMINATIM_UA',  'TruckRoute-Pro/5.0 (ammduarte.com.br)');

// ─── Combustível (padrões ajustáveis pelo admin) ──────────────
define('PRECO_MEDIO_DIESEL',   6.50);
define('CONSUMO_PADRAO_KM_L',  3.5);
define('CUSTO_PEDAGIO_KM',     0.12);

// ─── E-mail (SMTP) ────────────────────────────────────────────
define('SMTP_HOST',      getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      getenv('SMTP_USER') ?: 'seuemail@gmail.com');
define('SMTP_PASS',      getenv('SMTP_PASS') ?: '');
define('SMTP_FROM',      getenv('SMTP_FROM') ?: 'seuemail@gmail.com');
define('SMTP_FROM_NAME', 'AMM Duarte Transportadora');

// ─── IA (Anthropic Claude) ───────────────────────────────────
define('ANTHROPIC_API_KEY', getenv('ANTHROPIC_API_KEY') ?: '');

// ─── Aplicação ────────────────────────────────────────────────
define('APP_NAME',    'AMM Duarte — Controle de Frota');
define('APP_VERSION', '5.0.0');
// Em produção, troque pela sua URL real (sem barra no final)
define('APP_URL',     rtrim(getenv('APP_URL') ?: 'http://localhost/truckroute', '/'));
define('TIMEZONE',    'America/Sao_Paulo');
define('APP_ENV',     getenv('APP_ENV') ?: 'production'); // 'development' | 'production'

date_default_timezone_set(TIMEZONE);

// Configurar erros com base no ambiente
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
