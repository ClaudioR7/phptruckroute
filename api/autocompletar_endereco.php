<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/MapsAPI.php';
exigir_login();

$input = trim($_GET['input'] ?? '');
if (strlen($input) < 3) {
    json_response(['sugestoes' => []]);
}
$api = new MapsAPI();
json_response(['sugestoes' => $api->autocompletar($input)]);
