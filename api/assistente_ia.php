<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
exigir_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

$b        = json_decode(file_get_contents('php://input'), true) ?? [];
$messages = $b['messages'] ?? [];

if (empty($messages)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Mensagens não informadas']);
    exit;
}

// Sanitiza: apenas role user/assistant e content string
$msgs_clean = [];
foreach ($messages as $m) {
    $role    = $m['role'] === 'assistant' ? 'assistant' : 'user';
    $content = substr(trim($m['content'] ?? ''), 0, 2000);
    if ($content !== '') $msgs_clean[] = ['role' => $role, 'content' => $content];
}
if (empty($msgs_clean)) {
    echo json_encode(['erro' => 'Nenhuma mensagem válida']);
    exit;
}

$system = "Você é um assistente especializado em logística e transporte rodoviário de cargas no Brasil, integrado ao sistema de controle de frota AMM Duarte. Responda sempre em português brasileiro. Seja objetivo e prático. Foque em:\n- Rotas e rodovias federais/estaduais brasileiras\n- Postos de combustível (redes: Petrobras, Shell, Ipiranga, Raízen)\n- Restrições de circulação para caminhões (Lei do Silêncio, blitz, horários)\n- Pontos de parada, áreas de descanso (postos truck)\n- Pedágios estimados e rotas alternativas\n- Condições de estrada e obras\n- Dicas de segurança para motoristas de caminhão\nMantenha respostas curtas e diretas (máx 3 parágrafos). Use emojis para facilitar leitura.";

$payload = [
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 800,
    'system'     => $system,
    'messages'   => $msgs_clean,
];

// Pega a chave da config ou variável de ambiente
$apiKey = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : (getenv('ANTHROPIC_API_KEY') ?: '');

if (!$apiKey) {
    echo json_encode(['resposta' => 'ℹ️ Assistente IA não configurado. Adicione ANTHROPIC_API_KEY no config.php para ativar.']);
    exit;
}

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    http_response_code(502);
    echo json_encode(['erro' => 'Erro de conexão com a API: ' . $err]);
    exit;
}

$data = json_decode($raw, true);
if ($code !== 200 || empty($data['content'])) {
    $msg = $data['error']['message'] ?? 'Erro desconhecido da API';
    http_response_code(502);
    echo json_encode(['erro' => $msg]);
    exit;
}

$resposta = implode('', array_map(fn($b) => $b['text'] ?? '', $data['content']));
echo json_encode(['resposta' => $resposta]);
