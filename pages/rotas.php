<?php
require_once __DIR__ . '/../includes/layout_header.php';
$pdo = get_db();

$status_filtro = $_GET['status'] ?? 'todas';
$where  = $status_filtro !== 'todas' ? 'WHERE v.status = ?' : '';
$params = $status_filtro !== 'todas' ? [$status_filtro] : [];

$viagens = $pdo->prepare("
    SELECT v.*, u.nome motorista_nome, ve.placa, ve.modelo
    FROM viagens v
    JOIN usuarios u  ON u.id  = v.motorista_id
    JOIN veiculos ve ON ve.id = v.veiculo_id
    " . ($status_filtro !== 'todas' && !is_admin() ? "WHERE v.status=? AND v.motorista_id={$_SESSION['usuario_id']}" :
         (!is_admin() ? "WHERE v.motorista_id={$_SESSION['usuario_id']}" :
         ($status_filtro !== 'todas' ? 'WHERE v.status=?' : ''))) . "
    ORDER BY v.criado_em DESC LIMIT 80
");
// Rebuild properly
$where2  = '';
$params2 = [];
if (!is_admin()) { $where2 .= " AND v.motorista_id=?"; $params2[] = $_SESSION['usuario_id']; }
if ($status_filtro !== 'todas') { $where2 .= " AND v.status=?"; $params2[] = $status_filtro; }
$where2 = $where2 ? 'WHERE 1=1'.$where2 : '';

$stmt = $pdo->prepare("
    SELECT v.*, u.nome motorista_nome, ve.placa, ve.modelo
    FROM viagens v
    JOIN usuarios u  ON u.id  = v.motorista_id
    JOIN veiculos ve ON ve.id = v.veiculo_id
    $where2
    ORDER BY v.criado_em DESC LIMIT 80
");
$stmt->execute($params2);
$lista = $stmt->fetchAll();
?>

<div class="page-header">
  <div><h1>🛣️ Viagens</h1><div class="subtitle">Histórico e gerenciamento de todas as viagens</div></div>
  <?php if(is_admin()): ?>
  <a href="<?= APP_URL ?>/pages/mapa_rotas.php" class="btn btn-primary">+ Nova Rota</a>
  <?php endif; ?>
</div>

<!-- Filters -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach(['todas'=>'Todas','planejada'=>'Planejadas','em_andamento'=>'Em andamento','concluida'=>'Concluídas','cancelada'=>'Canceladas'] as $s=>$l): ?>
  <a href="?status=<?= $s ?>" class="btn <?= $status_filtro===$s?'btn-primary':'btn-ghost' ?> btn-sm"><?= $l ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if(empty($lista)): ?>
  <div class="empty-state"><div class="icon">🛣️</div><h3>Nenhuma viagem encontrada</h3></div>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>#</th><th>Motorista</th><th>Placa</th>
        <th>Origem → Destino</th><th>Saída</th>
        <th>Km</th><th>Combustível</th><th>Pedágio</th>
        <th>Frete</th><th>Resultado</th><th>Status</th><th>Ações</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($lista as $v): ?>
    <tr>
      <td class="text-muted fw-700">#<?= $v['id'] ?></td>
      <td class="fw-700"><?= htmlspecialchars($v['motorista_nome']) ?></td>
      <td><code style="background:var(--bg-card2);padding:2px 6px;border-radius:4px;font-size:.78rem"><?= htmlspecialchars($v['placa']) ?></code></td>
      <td style="max-width:180px;font-size:.82rem">
        <div><?= htmlspecialchars(mb_strimwidth($v['origem'],0,25,'…')) ?></div>
        <div class="text-muted">→ <?= htmlspecialchars(mb_strimwidth($v['destino'],0,25,'…')) ?></div>
      </td>
      <td class="text-muted"><?= $v['data_saida']?date('d/m H:i',strtotime($v['data_saida'])):'—' ?></td>
      <td><?= $v['km_percorrido']?number_format($v['km_percorrido'],0,',','.').' km':'—' ?></td>
      <td>R$&nbsp;<?= number_format($v['custo_combustivel'],2,',','.') ?></td>
      <td>R$&nbsp;<?= number_format($v['custo_pedagio'],2,',','.') ?></td>
      <td>R$&nbsp;<?= number_format($v['valor_frete'],2,',','.') ?></td>
      <td class="<?= $v['rentabilidade']>=0?'positivo':'negativo' ?>">R$&nbsp;<?= number_format($v['rentabilidade'],2,',','.') ?></td>
      <td><span class="badge badge-<?= $v['status'] ?>"><?= $v['status'] ?></span></td>
      <td>
        <div style="display:flex;gap:4px">
          <?php if($v['status']==='em_andamento' && (is_admin() || $v['motorista_id'] == $_SESSION['usuario_id'])): ?>
          <button class="btn btn-primary btn-sm" onclick="encerrarViagem(<?= $v['id'] ?>)">🏁 Encerrar</button>
          <?php endif; ?>
          <?php if($v['status']==='planejada' && (is_admin() || $v['motorista_id'] == $_SESSION['usuario_id'])): ?>
          <button class="btn btn-success btn-sm" onclick="iniciarViagem(<?= $v['id'] ?>)">▶ Iniciar</button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<!-- Modal encerrar -->
<div class="modal-overlay" id="modal-encerrar" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-header"><h2>🏁 Encerrar Viagem</h2><button class="modal-close" onclick="document.getElementById('modal-encerrar').style.display='none'">×</button></div>
    <input type="hidden" id="enc-id">
    <div class="form-grid">
      <div class="form-group"><label>KM chegada (hodômetro)</label><input type="number" id="enc-km" step="1"></div>
      <div class="form-group"><label>Litros abastecidos (total)</label><input type="number" id="enc-litros" step="0.1" value="0"></div>
      <div class="form-group"><label>Custo combustível (R$)</label><input type="number" id="enc-comb" step="0.01" value="0"></div>
      <div class="form-group"><label>Custo pedágio (R$)</label><input type="number" id="enc-pedagio" step="0.01" value="0"></div>
      <div class="form-group"><label>Outros custos (R$)</label><input type="number" id="enc-outros" step="0.01" value="0"></div>
      <div class="form-group"><label>Observações</label><textarea id="enc-obs" rows="2"></textarea></div>
    </div>
    <div id="resumo-encerrar" style="background:var(--bg-card2);border-radius:var(--radius);padding:14px;margin-top:10px;font-size:.85rem"></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="document.getElementById('modal-encerrar').style.display='none'">Cancelar</button>
      <button class="btn btn-primary" onclick="confirmarEncerrar()">🏁 Confirmar Encerramento</button>
    </div>
  </div>
</div>

<script>
function encerrarViagem(id) {
  document.getElementById('enc-id').value = id;
  document.getElementById('modal-encerrar').style.display = 'flex';
}

async function iniciarViagem(id) {
  if (!confirm('Confirmar início da viagem #'+id+'?')) return;
  const res = await fetch('<?= APP_URL ?>/api/rastreamento.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ viagem_id: id, acao: 'iniciar' })
  });
  const data = await res.json();
  if (data.sucesso) { showToast('Viagem iniciada!','success'); setTimeout(()=>location.reload(),1200); }
  else showToast('Erro: '+data.erro,'error');
}

// Live cost preview
['enc-km','enc-litros','enc-comb','enc-pedagio','enc-outros'].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('input', atualizarResumo);
});

function atualizarResumo() {
  const litros  = parseFloat(document.getElementById('enc-litros').value||0);
  const comb    = parseFloat(document.getElementById('enc-comb').value||0);
  const ped     = parseFloat(document.getElementById('enc-pedagio').value||0);
  const outros  = parseFloat(document.getElementById('enc-outros').value||0);
  const total   = comb + ped + outros;
  document.getElementById('resumo-encerrar').innerHTML = `
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center">
      <div><strong style="color:var(--brand)">R$ ${comb.toFixed(2).replace('.',',')}</strong><br><span style="color:var(--text-sm);font-size:.75rem">Combustível</span></div>
      <div><strong style="color:var(--brand)">R$ ${ped.toFixed(2).replace('.',',')}</strong><br><span style="color:var(--text-sm);font-size:.75rem">Pedágio</span></div>
      <div><strong style="color:var(--red)">R$ ${total.toFixed(2).replace('.',',')}</strong><br><span style="color:var(--text-sm);font-size:.75rem">Total</span></div>
    </div>`;
}

async function confirmarEncerrar() {
  const body = {
    viagem_id: document.getElementById('enc-id').value,
    km_chegada: document.getElementById('enc-km').value,
    litros_abastecidos: document.getElementById('enc-litros').value,
    custo_combustivel: document.getElementById('enc-comb').value,
    custo_pedagio: document.getElementById('enc-pedagio').value,
    outros_custos: document.getElementById('enc-outros').value,
    observacoes: document.getElementById('enc-obs').value,
  };
  const res  = await fetch('<?= APP_URL ?>/api/rastreamento.php', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)
  });
  const data = await res.json();
  if (data.sucesso) {
    showToast('Viagem encerrada! '+
      (data.km_percorrido?data.km_percorrido+' km percorridos':'')+
      (data.consumo_real_km_l?' | Consumo: '+data.consumo_real_km_l+' km/L':''),
      'success');
    setTimeout(()=>location.reload(),1800);
  } else showToast('Erro: '+data.erro,'error');
  document.getElementById('modal-encerrar').style.display='none';
}
</script>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
