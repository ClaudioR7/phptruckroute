<?php
require_once __DIR__ . '/../includes/layout_header.php';
exigir_admin();
$pdo = get_db();
$msg = $erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'criar') {
        $fields = ['placa','modelo','marca','ano','consumo_km_l','tanque_litros','capacidade_kg','km_atual'];
        $vals = array_map(fn($f) => trim($_POST[$f]??''), $fields);
        if ($vals[0] && $vals[1]) {
            try {
                $pdo->prepare("INSERT INTO veiculos (placa,modelo,marca,ano,consumo_km_l,tanque_litros,capacidade_kg,km_atual) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute($vals);
                $msg = 'Veículo cadastrado com sucesso!';
            } catch(Exception $e) { $erro = 'Placa já cadastrada ou erro: '.$e->getMessage(); }
        } else { $erro = 'Placa e modelo são obrigatórios.'; }
    }
    if ($action === 'toggle') {
        $pdo->prepare("UPDATE veiculos SET ativo=NOT ativo WHERE id=?")->execute([intval($_POST['id'])]);
        $msg = 'Status atualizado.';
    }
}

$veiculos = $pdo->query("SELECT * FROM veiculos ORDER BY ativo DESC, placa")->fetchAll();
?>
<div class="page-header">
  <div><h1>🚚 Veículos</h1><div class="subtitle">Frota cadastrada no sistema</div></div>
  <button class="btn btn-primary" onclick="document.getElementById('modal-veiculo').style.display='flex'">+ Novo Veículo</button>
</div>

<?php if($msg): ?><div class="toast toast-success" style="position:static;margin-bottom:16px;animation:none">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if($erro): ?><div class="toast toast-error" style="position:static;margin-bottom:16px;animation:none">❌ <?= htmlspecialchars($erro) ?></div><?php endif; ?>

<div class="card">
  <div class="table-wrap">
  <table>
    <thead>
      <tr><th>Placa</th><th>Modelo/Marca</th><th>Ano</th><th>Tanque</th><th>Consumo padrão</th><th>Km atual</th><th>Capacidade</th><th>Status</th><th>Ações</th></tr>
    </thead>
    <tbody>
    <?php foreach($veiculos as $v): ?>
    <tr>
      <td><code style="background:var(--bg-card2);padding:3px 8px;border-radius:4px;font-weight:700"><?= htmlspecialchars($v['placa']) ?></code></td>
      <td><strong><?= htmlspecialchars($v['modelo']) ?></strong><br><span class="text-muted"><?= htmlspecialchars($v['marca']??'') ?></span></td>
      <td><?= $v['ano'] ?: '—' ?></td>
      <td><?= $v['tanque_litros'] ? number_format($v['tanque_litros'],0,',','.').' L' : '—' ?></td>
      <td><?= number_format($v['consumo_km_l'],1,',','.') ?> km/L</td>
      <td><?= number_format($v['km_atual'],0,',','.') ?> km</td>
      <td><?= $v['capacidade_kg'] ? number_format($v['capacidade_kg'],0,',','.').' kg' : '—' ?></td>
      <td><span class="badge <?= $v['ativo']?'badge-ativo':'badge-inativo' ?>"><?= $v['ativo']?'Ativo':'Inativo' ?></span></td>
      <td>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= $v['id'] ?>">
          <button type="submit" class="btn <?= $v['ativo']?'btn-danger':'btn-success' ?> btn-sm"><?= $v['ativo']?'Desativar':'Ativar' ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="modal-overlay" id="modal-veiculo" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-header"><h2>🚚 Cadastrar Veículo</h2><button class="modal-close" onclick="document.getElementById('modal-veiculo').style.display='none'">×</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="criar">
      <div class="form-grid">
        <div class="form-group"><label>Placa *</label><input type="text" name="placa" required placeholder="ABC-1234"></div>
        <div class="form-group"><label>Modelo *</label><input type="text" name="modelo" required placeholder="Axor 2544"></div>
        <div class="form-group"><label>Marca</label><input type="text" name="marca" placeholder="Mercedes-Benz"></div>
        <div class="form-group"><label>Ano</label><input type="number" name="ano" placeholder="2022" min="1990" max="2030"></div>
        <div class="form-group"><label>Consumo (km/L)</label><input type="number" name="consumo_km_l" step="0.1" value="3.5"></div>
        <div class="form-group"><label>Tanque (litros)</label><input type="number" name="tanque_litros" step="1" placeholder="600"></div>
        <div class="form-group"><label>Capacidade (kg)</label><input type="number" name="capacidade_kg" step="1" placeholder="25000"></div>
        <div class="form-group"><label>KM atual (hodômetro)</label><input type="number" name="km_atual" step="1" placeholder="0"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-veiculo').style.display='none'">Cancelar</button>
        <button type="submit" class="btn btn-primary">✅ Cadastrar</button>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
