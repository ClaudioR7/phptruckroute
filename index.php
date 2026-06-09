<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . APP_URL . '/pages/dashboard.php'); exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if ($email && $senha) {
        try {
            $pdo = get_db();
            $st = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1");
            $st->execute([$email]);
            $u = $st->fetch();
            if ($u && password_verify($senha, $u['senha'])) {
                $_SESSION['usuario_id'] = $u['id'];
                $_SESSION['nome']       = $u['nome'];
                $_SESSION['perfil']     = $u['perfil'];
                $_SESSION['email']      = $u['email'];
                header('Location: ' . APP_URL . '/pages/dashboard.php'); exit;
            } else {
                $erro = 'E-mail ou senha inválidos.';
            }
        } catch(Exception $e) {
            die("ERRO REAL: " . $e->getMessage());
        }
    } else {
        $erro = 'Preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — AMM Duarte Frota</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
</head>
<body style="margin-left:0">
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <div class="truck-icon">🚛</div>
      <h1>AMM Duarte</h1>
      <p>Sistema de Controle de Frota</p>
    </div>

    <?php if ($erro): ?>
    <div class="login-error">⚠️ <?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group mb-2">
        <label>E-mail</label>
        <input type="email" name="email" placeholder="seu@email.com.br"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group mb-2">
        <label>Senha</label>
        <div class="input-wrap">
          <input type="password" name="senha" id="inp-senha" placeholder="••••••••" required>
          <span class="input-suffix" style="cursor:pointer;pointer-events:all" onclick="toggleSenha()">👁</span>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:8px;justify-content:center">
        Entrar no Sistema
      </button>
    </form>

    <p style="text-align:center;margin-top:20px;font-size:.78rem;color:var(--text-sm)">
      AMM Duarte Transportadora &copy; <?= date('Y') ?>
    </p>
  </div>
</div>
<script>
function toggleSenha() {
  const inp = document.getElementById('inp-senha');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
