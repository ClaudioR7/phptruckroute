<?php
require_once __DIR__ . '/../includes/layout_header.php';
exigir_admin();
$pdo = get_db();

$msg = $erro = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'criar') {
        $nome    = trim($_POST['nome'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $senha   = $_POST['senha'] ?? '';
        $perfil  = $_POST['perfil'] ?? 'motorista';
        $cnh     = trim($_POST['cnh'] ?? '');
        $telefone= trim($_POST['telefone'] ?? '');
        if ($nome && $email && $senha) {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            try {
                $pdo->prepare("INSERT INTO usuarios (nome,email,senha,perfil,cnh,telefone) VALUES (?,?,?,?,?,?)")
                    ->execute([$nome,$email,$hash,$perfil,$cnh,$telefone]);
                $msg = "Usuário '$nome' criado com sucesso!";
            } catch(Exception $e) {
                $erro = 'E-mail já cadastrado ou erro: '.$e->getMessage();
            }
        } else { $erro = 'Preencha nome, e-mail e senha.'; }
    }

    if ($action === 'toggle') {
        $id = intval($_POST['id']);
        $pdo->prepare("UPDATE usuarios SET ativo = NOT ativo WHERE id=?")->execute([$id]);
        $msg = 'Status do usuário atualizado.';
    }

    if ($action === 'reset_senha') {
        $id    = intval($_POST['id']);
        $nova  = $_POST['nova_senha'] ?? '';
        if ($nova && strlen($nova) >= 6) {
            $hash = password_hash($nova, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE usuarios SET senha=? WHERE id=?")->execute([$hash,$id]);
            $msg = 'Senha atualizada com sucesso.';
        } else { $erro = 'Senha deve ter pelo menos 6 caracteres.'; }
    }
}

$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY perfil,nome")->fetchAll();
?>

<div class="page-header">
  <div><h1>👥 Usuários</h1><div class="subtitle">Gerencie motoristas e administradores do sistema</div></div>
  <button class="btn btn-primary" onclick="document.getElementById('modal-criar').style.display='flex'">+ Novo Usuário</button>
</div>

<?php if ($msg): ?>
<div class="toast toast-success" style="position:static;margin-bottom:16px;animation:none">✅ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($erro): ?>
<div class="toast toast-error" style="position:static;margin-bottom:16px;animation:none">❌ <?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr)">
  <?php
    $total  = count($usuarios);
    $admins = count(array_filter($usuarios, fn($u)=>$u['perfil']==='admin'));
    $motors = count(array_filter($usuarios, fn($u)=>$u['perfil']==='motorista'));
    $ativos = count(array_filter($usuarios, fn($u)=>$u['ativo']));
  ?>
  <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon-box">👥</div></div><div class="kpi-value"><?= $total ?></div><div class="kpi-label">Total usuários</div></div>
  <div class="kpi-card accent-purple"><div class="kpi-header"><div class="kpi-icon-box">⚙️</div></div><div class="kpi-value" style="color:var(--purple)"><?= $admins ?></div><div class="kpi-label">Administradores</div></div>
  <div class="kpi-card accent-blue"><div class="kpi-header"><div class="kpi-icon-box">🚚</div></div><div class="kpi-value"><?= $motors ?></div><div class="kpi-label">Motoristas</div></div>
  <div class="kpi-card accent-green"><div class="kpi-header"><div class="kpi-icon-box">✅</div></div><div class="kpi-value" style="color:var(--green)"><?= $ativos ?></div><div class="kpi-label">Ativos</div></div>
</div>

<div class="card">
  <div class="card-header">
    <h2><span class="icon">👤</span>Lista de usuários</h2>
  </div>
  <div class="table-wrap">
  <table>
    <thead>
      <tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>CNH</th><th>Telefone</th><th>Cadastrado em</th><th>Status</th><th>Ações</th></tr>
    </thead>
    <tbody>
    <?php foreach($usuarios as $u): ?>
    <tr>
      <td>
        <div class="flex" style="align-items:center;gap:10px">
          <div class="user-avatar" style="width:32px;height:32px;font-size:.8rem"><?= strtoupper(substr($u['nome'],0,1)) ?></div>
          <strong><?= htmlspecialchars($u['nome']) ?></strong>
        </div>
      </td>
      <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
      <td><span class="badge badge-<?= $u['perfil'] ?>"><?= $u['perfil'] === 'admin' ? 'Admin' : 'Motorista' ?></span></td>
      <td class="text-muted"><?= htmlspecialchars($u['cnh'] ?: '—') ?></td>
      <td class="text-muted"><?= htmlspecialchars($u['telefone'] ?: '—') ?></td>
      <td class="text-muted"><?= date('d/m/Y', strtotime($u['criado_em'])) ?></td>
      <td><span class="badge <?= $u['ativo'] ? 'badge-ativo' : 'badge-inativo' ?>"><?= $u['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
      <td>
        <div style="display:flex;gap:6px">
          <button class="btn btn-ghost btn-sm" onclick="abrirResetSenha(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nome']) ?>')">🔑 Senha</button>
          <?php if ($u['id'] != $_SESSION['usuario_id']): ?>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button type="submit" class="btn <?= $u['ativo']?'btn-danger':'btn-success' ?> btn-sm">
              <?= $u['ativo'] ? '🚫 Desativar' : '✅ Ativar' ?>
            </button>
          </form>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- Modal Criar Usuário -->
<div class="modal-overlay" id="modal-criar" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-header">
      <h2>➕ Criar Novo Usuário</h2>
      <button class="modal-close" onclick="document.getElementById('modal-criar').style.display='none'">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="criar">
      <div class="form-grid">
        <div class="form-group form-full">
          <label>Nome completo *</label>
          <input type="text" name="nome" required placeholder="João da Silva">
        </div>
        <div class="form-group">
          <label>E-mail *</label>
          <input type="email" name="email" required placeholder="joao@email.com">
        </div>
        <div class="form-group">
          <label>Senha *</label>
          <input type="password" name="senha" required placeholder="Mínimo 6 caracteres">
        </div>
        <div class="form-group">
          <label>Perfil *</label>
          <select name="perfil">
            <option value="motorista">🚚 Motorista</option>
            <option value="admin">⚙️ Administrador</option>
          </select>
        </div>
        <div class="form-group">
          <label>CNH</label>
          <input type="text" name="cnh" placeholder="Número da CNH">
        </div>
        <div class="form-group">
          <label>Telefone / WhatsApp</label>
          <input type="text" name="telefone" placeholder="(21) 99999-0000">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-criar').style.display='none'">Cancelar</button>
        <button type="submit" class="btn btn-primary">✅ Criar Usuário</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Reset Senha -->
<div class="modal-overlay" id="modal-senha" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-header">
      <h2>🔑 Redefinir Senha</h2>
      <button class="modal-close" onclick="document.getElementById('modal-senha').style.display='none'">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="reset_senha">
      <input type="hidden" name="id" id="reset-id">
      <div class="form-group">
        <label>Novo usuário: <strong id="reset-nome"></strong></label>
        <input type="password" name="nova_senha" required placeholder="Nova senha (mín. 6 caracteres)" minlength="6">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-senha').style.display='none'">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar Nova Senha</button>
      </div>
    </form>
  </div>
</div>

<script>
function abrirResetSenha(id, nome) {
  document.getElementById('reset-id').value = id;
  document.getElementById('reset-nome').textContent = nome;
  document.getElementById('modal-senha').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
