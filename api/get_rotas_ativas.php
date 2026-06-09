<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
exigir_login();
header('Content-Type: application/json');

try {
    $pdo = get_db();
    // Se motorista, só retorna sua própria viagem
    $extraWhere = is_admin() ? '' : ' AND v.motorista_id=' . intval($_SESSION['usuario_id']);

    $rows = $pdo->query("
        SELECT v.id, v.origem, v.destino,
               v.distancia_km, v.data_saida,
               v.custo_combustivel, v.litros_abastecidos,
               u.nome motorista_nome, ve.placa, ve.modelo,
               (SELECT lat        FROM posicoes p WHERE p.viagem_id=v.id ORDER BY p.registrado_em DESC LIMIT 1) lat,
               (SELECT lng        FROM posicoes p WHERE p.viagem_id=v.id ORDER BY p.registrado_em DESC LIMIT 1) lng,
               (SELECT velocidade FROM posicoes p WHERE p.viagem_id=v.id ORDER BY p.registrado_em DESC LIMIT 1) velocidade,
               (SELECT COUNT(*)   FROM posicoes p WHERE p.viagem_id=v.id) total_posicoes
        FROM viagens v
        JOIN usuarios u  ON u.id  = v.motorista_id
        JOIN veiculos ve ON ve.id = v.veiculo_id
        WHERE v.status = 'em_andamento' $extraWhere
        ORDER BY v.data_saida DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['sucesso' => true, 'rotas' => $rows]);
} catch(Exception $e) {
    echo json_encode(['sucesso' => false, 'rotas' => [], 'erro' => $e->getMessage()]);
}
