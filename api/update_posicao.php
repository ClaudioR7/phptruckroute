<?php
/**
 * API: Atualizar posição GPS em tempo real
 * POST /api/update_posicao.php
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['erro' => 'Método não permitido'], 405);
}

$b = json_input();
$viagem_id  = intval($b['viagem_id']  ?? 0);
$veiculo_id = intval($b['veiculo_id'] ?? 0);
$lat        = floatval($b['lat']      ?? 0);
$lng        = floatval($b['lng']      ?? 0);
$vel        = isset($b['velocidade']) ? floatval($b['velocidade']) : null;
$direcao    = isset($b['direcao'])    ? intval($b['direcao'])      : null;

if (!$viagem_id || !$veiculo_id || !$lat || !$lng) {
    json_response(['erro' => 'viagem_id, veiculo_id, lat e lng são obrigatórios'], 422);
}

if (!pode_ver_viagem($viagem_id)) {
    json_response(['erro' => 'Acesso negado'], 403);
}

try {
    $pdo = get_db();

    // Verificar viagem ativa
    $check = $pdo->prepare("SELECT id FROM viagens WHERE id=? AND status='em_andamento'");
    $check->execute([$viagem_id]);
    if (!$check->fetch()) {
        json_response(['erro' => 'Viagem não encontrada ou não está em andamento'], 404);
    }

    $pdo->prepare("INSERT INTO posicoes (viagem_id, veiculo_id, lat, lng, velocidade, direcao) VALUES (?,?,?,?,?,?)")
        ->execute([$viagem_id, $veiculo_id, $lat, $lng, $vel, $direcao]);

    json_response(['sucesso' => true]);

} catch (PDOException $e) {
    error_log('[update_posicao] ' . $e->getMessage());
    json_response(['erro' => 'Erro ao salvar posição'], 500);
}
