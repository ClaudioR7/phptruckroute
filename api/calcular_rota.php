<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/MapsAPI.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['erro' => 'Método não permitido'], 405);

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$origem    = $body['origem']   ?? [];   // {lat, lng, descricao}
$destino   = $body['destino']  ?? [];
$waypoints = $body['waypoints'] ?? [];  // array de {lat, lng}

if (empty($origem['lat']) || empty($destino['lat'])) {
    json_response(['erro' => 'Coordenadas de origem e destino são obrigatórias'], 400);
}

$api  = new MapsAPI();
$rota = $api->calcular_rota(
    ['lat' => $origem['lat'],  'lng' => $origem['lng']],
    ['lat' => $destino['lat'], 'lng' => $destino['lng']],
    array_map(fn($w) => ['lat' => $w['lat'], 'lng' => $w['lng']], $waypoints)
);

if (!$rota) json_response(['erro' => 'Não foi possível calcular a rota.'], 422);

json_response(['sucesso' => true, 'rota' => $rota]);
