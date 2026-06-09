<?php
/**
 * Setup — Instala o banco de dados
 * DELETE ESTE ARQUIVO após a instalação!
 */

// Bloquear acesso se já instalado (verifica se tabela usuarios existe)
define('DB_HOST',    $_POST['db_host']    ?? 'localhost');
define('DB_NAME',    $_POST['db_name']    ?? 'truckroute');
define('DB_USER',    $_POST['db_user']    ?? 'root');
define('DB_PASS',    $_POST['db_pass']    ?? '');
define('DB_CHARSET', 'utf8mb4');
define('APP_URL',    '');
define('TIMEZONE', 'America/Sao_Paulo');
date_default_timezone_set(TIMEZONE);
if (session_status() === PHP_SESSION_NONE) session_start();

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['instalar'])) {
    try {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $sql = file_get_contents(__DIR__ . '/database.sql');
        // Executar statement por statement
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt) $pdo->exec($stmt);
        }

        $sucesso = 'Banco de dados instalado com sucesso! <strong>Agora delete este arquivo setup.php!</strong>';
    } catch (PDOException $e) {
        $erro = 'Erro: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Setup — TruckRoute Pro</title>
<style>
body{font-family:system-ui,sans-serif;background:#0d1525;color:#e2e8f0;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}
.box{background:#1a2744;border-radius:12px;padding:36px;width:100%;max-width:480px}
h1{color:#f97316;margin:0 0 24px}
label{display:block;font-size:.85rem;color:#94a3b8;margin-bottom:4px;margin-top:16px}
input{width:100%;padding:10px 14px;background:#0d1525;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:.95rem;box-sizing:border-box}
button{width:100%;padding:14px;background:#f97316;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;margin-top:24px}
.erro{background:#7f1d1d;border:1px solid #ef4444;border-radius:8px;padding:14px;margin-top:16px}
.ok{background:#064e3b;border:1px solid #10b981;border-radius:8px;padding:14px;margin-top:16px}
.warn{background:#78350f;border:1px solid #f97316;border-radius:8px;padding:10px 14px;margin-bottom:20px;font-size:.85rem}
</style>
</head>
<body>
<div class="box">
  <h1>🚛 TruckRoute Pro — Setup</h1>
  <div class="warn">⚠️ <strong>Delete este arquivo</strong> após a instalação por segurança.</div>

  <?php if ($erro): ?>
    <div class="erro">❌ <?= $erro ?></div>
  <?php elseif ($sucesso): ?>
    <div class="ok">✅ <?= $sucesso ?></div>
    <a href="index.php" style="display:block;text-align:center;margin-top:16px;color:#f97316">→ Acessar o sistema</a>
  <?php else: ?>
  <form method="POST">
    <label>Host do banco</label>
    <input name="db_host" value="localhost" required>
    <label>Nome do banco</label>
    <input name="db_name" value="truckroute" required>
    <label>Usuário</label>
    <input name="db_user" value="root" required>
    <label>Senha</label>
    <input name="db_pass" type="password">
    <button name="instalar" value="1">🗄️ Instalar Banco de Dados</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
