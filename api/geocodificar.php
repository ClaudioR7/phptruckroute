<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/MapsAPI.php';
exigir_login();

$endereco = trim($_GET['endereco'] ?? '');
if (!$endereco) json_response(['erro' => 'Endereço obrigatório'], 400);

$api = new MapsAPI();
$geo = $api->geocodificar($endereco);
if (!$geo) json_response(['erro' => 'Endereço não encontrado'], 404);

json_response(['sucesso' => true, 'dados' => $geo]);
