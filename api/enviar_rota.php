<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
exigir_login(); exigir_admin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['motorista_id'])) {
    echo json_encode(['sucesso'=>false,'erro'=>'motorista_id obrigatório']); exit;
}

try {
    $pdo = get_db();
    $m = $pdo->prepare("SELECT nome,email,telefone FROM usuarios WHERE id=?");
    $m->execute([$data['motorista_id']]);
    $motorista = $m->fetch();
    if (!$motorista || !$motorista['email']) {
        echo json_encode(['sucesso'=>false,'erro'=>'Motorista sem e-mail cadastrado']); exit;
    }

    $origem    = $data['origem']      ?? '—';
    $destino   = $data['destino']     ?? '—';
    $distancia = $data['distancia_km'] ? number_format($data['distancia_km'],1,',','.') . ' km' : '—';
    $duracao   = $data['duracao']     ?? '—';
    $vid       = intval($data['viagem_id'] ?? 0);

    $mapsUrl = "https://www.google.com/maps/dir/?api=1&origin=".urlencode($origem)."&destination=".urlencode($destino)."&travelmode=driving";

    $subject = "🚛 AMM Duarte — Nova rota atribuída a você";
    $body = "
    <div style='font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;background:#0d1525;color:#e2e8f0;border-radius:12px;overflow:hidden'>
      <div style='background:#f97316;padding:24px 28px;'>
        <h1 style='margin:0;font-size:1.3rem;color:#fff'>🚛 AMM Duarte Transportadora</h1>
        <p style='margin:6px 0 0;color:rgba(255,255,255,.8);font-size:.9rem'>Nova rota atribuída</p>
      </div>
      <div style='padding:28px'>
        <p style='font-size:1rem;margin-bottom:20px'>Olá, <strong style='color:#f97316'>{$motorista['nome']}</strong>! Uma nova rota foi atribuída a você.</p>

        <div style='background:#162035;border-radius:10px;padding:18px;margin-bottom:18px'>
          <div style='margin-bottom:12px'>
            <span style='color:#64748b;font-size:.8rem;font-weight:600;text-transform:uppercase'>Origem</span>
            <div style='font-size:1rem;font-weight:700;margin-top:3px'>📍 {$origem}</div>
          </div>
          <div>
            <span style='color:#64748b;font-size:.8rem;font-weight:600;text-transform:uppercase'>Destino</span>
            <div style='font-size:1rem;font-weight:700;margin-top:3px'>🏁 {$destino}</div>
          </div>
        </div>

        <div style='display:flex;gap:12px;margin-bottom:20px'>
          <div style='flex:1;background:#162035;border-radius:8px;padding:14px;text-align:center'>
            <div style='font-size:1.3rem;font-weight:800;color:#3b82f6'>{$distancia}</div>
            <div style='font-size:.75rem;color:#64748b;margin-top:3px'>Distância</div>
          </div>
          <div style='flex:1;background:#162035;border-radius:8px;padding:14px;text-align:center'>
            <div style='font-size:1.3rem;font-weight:800;color:#10b981'>{$duracao}</div>
            <div style='font-size:.75rem;color:#64748b;margin-top:3px'>Tempo estimado</div>
          </div>
        </div>

        <a href='{$mapsUrl}' style='display:block;background:#f97316;color:#fff;text-align:center;padding:14px;border-radius:10px;text-decoration:none;font-weight:700;font-size:1rem;margin-bottom:20px'>
          🗺️ Abrir Rota no Google Maps
        </a>

        <p style='font-size:.8rem;color:#64748b'>Dúvidas? Entre em contato com a administração.</p>
      </div>
    </div>";

    // Try sending with mail() — in production use PHPMailer
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: ".SMTP_FROM_NAME." <".SMTP_FROM.">\r\n";

    $sent = @mail($motorista['email'], $subject, $body, $headers);

    // Log to file if mail fails (dev environment)
    if (!$sent) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        file_put_contents($logDir.'/emails.log',
            date('Y-m-d H:i:s')." | Para: {$motorista['email']} | Assunto: {$subject}\n"
            ."URL Maps: {$mapsUrl}\n\n", FILE_APPEND);
    }

    echo json_encode(['sucesso'=>true, 'email'=>$motorista['email'], 'maps_url'=>$mapsUrl]);
} catch(Exception $e) {
    echo json_encode(['sucesso'=>false,'erro'=>$e->getMessage()]);
}
