<?php
/**
 * API: Salvar Viagem
 * POST /api/salvar_viagem.php
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['erro' => 'Método não permitido'], 405);
}

$b = json_input();

$veiculo_id    = intval($b['veiculo_id']   ?? 0);
$motorista_id  = intval($b['motorista_id'] ?? $_SESSION['usuario_id']);
$origem_desc   = trim($b['origem_desc']    ?? '');
$destino_desc  = trim($b['destino_desc']   ?? '');
$origem_lat    = floatval($b['origem_lat']  ?? 0);
$origem_lng    = floatval($b['origem_lng']  ?? 0);
$destino_lat   = floatval($b['destino_lat'] ?? 0);
$destino_lng   = floatval($b['destino_lng'] ?? 0);
$distancia_km  = floatval($b['distancia_km'] ?? 0);
$duracao_s     = intval($b['duracao_s']    ?? 0);
$valor_frete   = floatval($b['valor_frete'] ?? 0);
$km_saida      = floatval($b['km_saida']   ?? 0);
$litros_saida  = floatval($b['litros_saida'] ?? 0);
$custo_comb    = floatval($b['custo_combustivel_est'] ?? 0);
$custo_ped     = floatval($b['custo_pedagio_est'] ?? 0);

$polyline_json  = json_encode($b['polyline_points'] ?? [], JSON_UNESCAPED_UNICODE);
$itinerario_json = isset($b['itinerario']) ? json_encode($b['itinerario'], JSON_UNESCAPED_UNICODE) : null;
$waypoints_json  = json_encode($b['waypoints'] ?? [], JSON_UNESCAPED_UNICODE);

// ─── Validações ───────────────────────────────────────────────
$erros = [];
if (!$veiculo_id)    $erros[] = 'Veículo não selecionado';
if (!$origem_desc)   $erros[] = 'Origem obrigatória';
if (!$destino_desc)  $erros[] = 'Destino obrigatório';
if ($erros) {
    json_response(['erro' => implode('; ', $erros), 'campos' => $erros], 422);
}

try {
    $pdo = get_db();

    // Verificar se veículo existe e está ativo
    $vCheck = $pdo->prepare("SELECT id FROM veiculos WHERE id=? AND ativo=1");
    $vCheck->execute([$veiculo_id]);
    if (!$vCheck->fetch()) {
        json_response(['erro' => 'Veículo não encontrado ou inativo'], 422);
    }

    // Motorista: admin pode definir qualquer um; motorista só pode criar para si
    if (!is_admin()) {
        $motorista_id = intval($_SESSION['usuario_id']);
    } else {
        $mCheck = $pdo->prepare("SELECT id FROM usuarios WHERE id=? AND perfil='motorista' AND ativo=1");
        $mCheck->execute([$motorista_id]);
        if (!$mCheck->fetch()) {
            json_response(['erro' => 'Motorista não encontrado ou inativo'], 422);
        }
    }

    $pdo->prepare("
        INSERT INTO viagens
            (veiculo_id, motorista_id, origem, destino, waypoints_json,
             distancia_km, duracao_estimada_s, polyline, itinerario_json,
             km_saida, litros_saida, custo_combustivel_est, custo_pedagio_est,
             valor_frete, status, data_saida)
        VALUES (?,?,?,?,?, ?,?,?,?, ?,?,?,?, ?, 'em_andamento', NOW())
    ")->execute([
        $veiculo_id, $motorista_id, $origem_desc, $destino_desc, $waypoints_json,
        $distancia_km, $duracao_s, $polyline_json, $itinerario_json,
        $km_saida ?: null, $litros_saida ?: null,
        $custo_comb, $custo_ped,
        $valor_frete ?: null,
    ]);

    $viagem_id = (int) $pdo->lastInsertId();

    // Atualizar KM do veículo se informado
    if ($km_saida > 0) {
        $pdo->prepare("UPDATE veiculos SET km_atual=? WHERE id=? AND (km_atual IS NULL OR km_atual <= ?)")
            ->execute([$km_saida, $veiculo_id, $km_saida]);
    }

    json_response(['sucesso' => true, 'viagem_id' => $viagem_id]);

} catch (PDOException $e) {
    error_log('[salvar_viagem] PDO: ' . $e->getMessage());
    json_response(['erro' => 'Erro ao salvar no banco de dados'], 500);
} catch (Throwable $e) {
    error_log('[salvar_viagem] ' . $e->getMessage());
    json_response(['erro' => 'Erro interno do servidor'], 500);
}
