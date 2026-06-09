<?php
/**
 * API: Rastreamento — Iniciar / Encerrar viagem, atualizar status
 * POST /api/rastreamento.php
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['erro' => 'Método não permitido'], 405);
}

$data = json_input();

if (empty($data['viagem_id'])) {
    json_response(['erro' => 'viagem_id obrigatório'], 422);
}

$id = intval($data['viagem_id']);

if (!pode_ver_viagem($id)) {
    json_response(['erro' => 'Acesso negado a esta viagem'], 403);
}

try {
    $pdo = get_db();

    if (($data['acao'] ?? '') === 'iniciar') {
        $stmt = $pdo->prepare("UPDATE viagens SET status='em_andamento', data_saida=NOW() WHERE id=? AND status='planejada'");
        $stmt->execute([$id]);
        json_response(['sucesso' => true, 'linhas' => $stmt->rowCount()]);
    }

    // Encerrar viagem
    $km_chegada   = floatval($data['km_chegada']        ?? 0);
    $litros       = floatval($data['litros_abastecidos'] ?? 0);
    $custo_comb   = floatval($data['custo_combustivel']  ?? 0);
    $custo_ped    = floatval($data['custo_pedagio']      ?? 0);
    $outros       = floatval($data['outros_custos']      ?? 0);
    $observacoes  = htmlspecialchars(trim($data['observacoes'] ?? ''), ENT_QUOTES, 'UTF-8');

    $trip = $pdo->prepare("SELECT * FROM viagens WHERE id=? AND status='em_andamento'");
    $trip->execute([$id]);
    $trip = $trip->fetch();

    if (!$trip) {
        json_response(['erro' => 'Viagem não encontrada ou já encerrada'], 404);
    }

    $km_saida  = floatval($trip['km_saida'] ?? 0);
    $km_perc   = ($km_chegada > 0 && $km_saida > 0) ? round($km_chegada - $km_saida, 2) : null;
    $consumo_real = ($litros > 0 && $km_perc > 0) ? round($km_perc / $litros, 2) : null;

    $pdo->prepare("
        UPDATE viagens SET
            status='concluida', data_chegada=NOW(),
            km_chegada=?, litros_abastecidos=?,
            custo_combustivel=?, custo_pedagio=?, outros_custos=?,
            consumo_real_km_l=?, observacoes=?
        WHERE id=?
    ")->execute([$km_chegada, $litros, $custo_comb, $custo_ped, $outros, $consumo_real, $observacoes, $id]);

    if ($km_chegada > 0) {
        $pdo->prepare("UPDATE veiculos SET km_atual=? WHERE id=? AND (km_atual IS NULL OR km_atual <= ?)")
            ->execute([$km_chegada, $trip['veiculo_id'], $km_chegada]);
    }

    json_response([
        'sucesso'           => true,
        'km_percorrido'     => $km_perc,
        'consumo_real_km_l' => $consumo_real,
        'custo_total'       => round($custo_comb + $custo_ped + $outros, 2),
    ]);

} catch (PDOException $e) {
    error_log('[rastreamento] PDO: ' . $e->getMessage());
    json_response(['erro' => 'Erro de banco de dados'], 500);
} catch (Throwable $e) {
    error_log('[rastreamento] ' . $e->getMessage());
    json_response(['erro' => 'Erro interno'], 500);
}
