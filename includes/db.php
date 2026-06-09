<?php
/**
 * Conexão com banco de dados + helpers globais
 * TruckRoute Pro v5.0
 */

function get_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    var_dump($host, $port, $db, $user);
    exit;
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 10,
    ]);
    return $pdo;
}

/**
 * Envia resposta JSON e encerra o script.
 * ESTA FUNÇÃO ESTAVA FALTANDO — era o bug principal que impedia salvar viagens.
 */
function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Retorna input JSON do body da requisição de forma segura.
 */
function json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
