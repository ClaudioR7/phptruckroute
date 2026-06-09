<?php
// Retorna a viagem ativa do motorista logado com itinerário
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
exigir_login();
header('Content-Type: application/json');

try {
    $pdo = get_db();
    $uid = $_SESSION['usuario_id'];

    $stmt = $pdo->prepare("
        SELECT v.id, v.origem, v.destino, v.distancia_km, v.duracao_estimada_s,
               v.data_saida, v.km_saida, v.litros_saida,
               v.custo_combustivel, v.custo_pedagio, v.outros_custos,
               v.litros_abastecidos, v.valor_frete,
               v.polyline, v.itinerario_json, v.waypoints_json,
               ve.placa, ve.modelo, ve.consumo_km_l,
               (SELECT COUNT(*) FROM posicoes p WHERE p.viagem_id=v.id) total_posicoes
        FROM viagens v
        JOIN veiculos ve ON ve.id = v.veiculo_id
        WHERE v.motorista_id = ? AND v.status = 'em_andamento'
        ORDER BY v.data_saida DESC LIMIT 1
    ");
    $stmt->execute([$uid]);
    $viagem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$viagem) {
        echo json_encode(['sucesso'=>false,'erro'=>'Nenhuma viagem ativa']);
        exit;
    }

    $viagem['polyline']      = $viagem['polyline'] ? json_decode($viagem['polyline'], true) : [];
    $viagem['itinerario']    = $viagem['itinerario_json'] ? json_decode($viagem['itinerario_json'], true) : [];
    $viagem['waypoints']     = $viagem['waypoints_json'] ? json_decode($viagem['waypoints_json'], true) : [];
    unset($viagem['polyline_json'], $viagem['itinerario_json'], $viagem['waypoints_json']);

    // Custo total atual
    $viagem['custo_total'] = round(
        floatval($viagem['custo_combustivel']) +
        floatval($viagem['custo_pedagio']) +
        floatval($viagem['outros_custos']), 2
    );
    $viagem['rentabilidade'] = round(floatval($viagem['valor_frete']) - $viagem['custo_total'], 2);

    echo json_encode(['sucesso'=>true, 'viagem'=>$viagem]);
} catch(Exception $e) {
    echo json_encode(['sucesso'=>false,'erro'=>$e->getMessage()]);
}
