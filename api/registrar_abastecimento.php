<?php
/**
 * API: Registrar Abastecimento
 * POST /api/registrar_abastecimento.php
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['erro' => 'Método não permitido'], 405);
}

$b = json_input();

$veiculo_id   = intval($b['veiculo_id']    ?? 0);
$viagem_id    = intval($b['viagem_id']     ?? 0) ?: null;
$motorista_id = intval($b['motorista_id']  ?? $_SESSION['usuario_id']);
$km           = floatval($b['km_no_momento'] ?? 0);
$litros       = floatval($b['litros']      ?? 0);
$preco_litro  = floatval($b['preco_litro'] ?? 0);
$posto        = htmlspecialchars(trim($b['posto'] ?? ''), ENT_QUOTES, 'UTF-8');
$observacoes  = htmlspecialchars(trim($b['observacoes'] ?? ''), ENT_QUOTES, 'UTF-8');

$erros = [];
if (!$veiculo_id)  $erros[] = 'Veículo obrigatório';
if ($km <= 0)      $erros[] = 'KM obrigatório';
if ($litros <= 0)  $erros[] = 'Litros deve ser maior que zero';
if ($preco_litro <= 0) $erros[] = 'Preço por litro obrigatório';
if ($erros) json_response(['erro' => implode('; ', $erros)], 422);

try {
    $pdo = get_db();
    $pdo->prepare("
        INSERT INTO abastecimentos (viagem_id, veiculo_id, motorista_id, km_no_momento, litros, preco_litro, posto, observacoes)
        VALUES (?,?,?,?,?,?,?,?)
    ")->execute([$viagem_id, $veiculo_id, $motorista_id, $km, $litros, $preco_litro, $posto, $observacoes]);

    $abast_id = (int) $pdo->lastInsertId();

    // Atualizar KM do veículo
    $pdo->prepare("UPDATE veiculos SET km_atual=? WHERE id=? AND (km_atual IS NULL OR km_atual <= ?)")
        ->execute([$km, $veiculo_id, $km]);

    // Se tiver viagem, acumular litros e custo
    if ($viagem_id) {
        $valor = round($litros * $preco_litro, 2);
        $pdo->prepare("UPDATE viagens SET litros_abastecidos=litros_abastecidos+?, custo_combustivel=custo_combustivel+? WHERE id=?")
            ->execute([$litros, $valor, $viagem_id]);
    }

    json_response(['sucesso' => true, 'abastecimento_id' => $abast_id, 'valor_total' => round($litros * $preco_litro, 2)]);

} catch (PDOException $e) {
    error_log('[registrar_abastecimento] ' . $e->getMessage());
    json_response(['erro' => 'Erro de banco de dados'], 500);
}
