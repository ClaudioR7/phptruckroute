<?php
require_once __DIR__ . '/../includes/layout_header.php';
$pdo = get_db();

$msg = $erro = '';

// Register fuel stop
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'abastecer') {
    $viagem_id   = intval($_POST['viagem_id']??0) ?: null;
    $veiculo_id  = intval($_POST['veiculo_id']??0);
    $motorista_id= intval($_POST['motorista_id']??0);
    $km          = floatval($_POST['km_no_momento']??0);
    $litros      = floatval($_POST['litros']??0);
    $preco       = floatval($_POST['preco_litro']??0);
    $posto       = trim($_POST['posto']??'');
    $obs         = trim($_POST['observacoes']??'');

    if ($veiculo_id && $motorista_id && $litros > 0 && $preco > 0) {
        $pdo->prepare("INSERT INTO abastecimentos (viagem_id,veiculo_id,motorista_id,km_no_momento,litros,preco_litro,posto,observacoes)
                        VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$viagem_id,$veiculo_id,$motorista_id,$km,$litros,$preco,$posto,$obs]);
        // Update trip fuel
        if ($viagem_id) {
            $pdo->prepare("UPDATE viagens SET litros_abastecidos=COALESCE(litros_abastecidos,0)+?, custo_combustivel=COALESCE(custo_combustivel,0)+? WHERE id=?")
                ->execute([$litros, $litros*$preco, $viagem_id]);
        }
        $msg = 'Abastecimento registrado com sucesso!';
    } else { $erro = 'Preencha todos os campos obrigatórios.'; }
}

// Filters
$mes  = $_GET['mes']  ?? date('Y-m');
$vid  = intval($_GET['veiculo_id'] ?? 0);

$params  = [$mes];
$where2  = "WHERE DATE_FORMAT(a.data_hora,'%Y-%m')=?";
if ($vid) { $where2 .= ' AND a.veiculo_id=?'; $params[] = $vid; }
if (!is_admin()) { $where2 .= ' AND a.motorista_id=?'; $params[] = $_SESSION['usuario_id']; }

$abast = $pdo->prepare("
    SELECT a.*, ve.placa, ve.modelo, u.nome motorista_nome,
           (a.litros * a.preco_litro) valor_total
    FROM abastecimentos a
    JOIN veiculos ve ON ve.id = a.veiculo_id
    JOIN usuarios  u  ON u.id  = a.motorista_id
    $where2
    ORDER BY a.data_hora DESC LIMIT 100
");
$abast->execute($params);
$lista = $abast->fetchAll();

// KPIs
$kp = $pdo->prepare("
    SELECT COALESCE(SUM(litros),0) total_litros,
           COALESCE(SUM(litros*preco_litro),0) total_custo,
           COALESCE(AVG(preco_litro),0) preco_medio,
           COUNT(*) total_abast
    FROM abastecimentos a $where2
");
$kp->execute($params);
$k = $kp->fetch();

// Vehicles & drivers for form
$veiculos   = $pdo->query("SELECT id,placa,modelo FROM veiculos WHERE ativo=1")->fetchAll();
$motoristas = $pdo->query("SELECT id,nome FROM usuarios WHERE perfil='motorista' AND ativo=1")->fetchAll();

// Active trips for form — motorista only sees their own
$viagens_ativas = $pdo->prepare("
    SELECT v.id, v.origem, v.destino, u.nome motorista_nome, ve.placa, v.veiculo_id, v.motorista_id
    FROM viagens v
    JOIN usuarios u ON u.id=v.motorista_id
    JOIN veiculos ve ON ve.id=v.veiculo_id
    WHERE v.status='em_andamento'" . (!is_admin() ? " AND v.motorista_id=?" : "") . "
");
$viagens_ativas->execute(!is_admin() ? [$_SESSION['usuario_id']] : []);
$viagens_ativas = $viagens_ativas->fetchAll();
?>

<div class="page-header">
  <div><h1>⛽ Combustível</h1><div class="subtitle">Controle de abastecimentos e consumo da frota</div></div>
  <button class="btn btn-primary" onclick="document.getElementById('modal-abastecer').style.display='flex'">+ Registrar Abastecimento</button>
</div>

<?php if($msg): ?>
<div class="toast toast-success" style="position:static;margin-bottom:16px;animation:none">✅ <?= htmlspecialchars($msg) ?></div>
<?php elseif($erro): ?>
<div class="toast toast-error" style="position:static;margin-bottom:16px;animation:none">❌ <?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<!-- KPIs -->
<div class="kpi-grid">
  <div class="kpi-card accent-blue">
    <div class="kpi-header"><div class="kpi-icon-box">⛽</div></div>
    <div class="kpi-value"><?= number_format($k['total_litros'],0,',','.') ?>L</div>
    <div class="kpi-label">Litros abastecidos no mês</div>
  </div>
  <div class="kpi-card accent-red">
    <div class="kpi-header"><div class="kpi-icon-box">💸</div></div>
    <div class="kpi-value" style="color:var(--red)">R$&nbsp;<?= number_format($k['total_custo'],0,',','.') ?></div>
    <div class="kpi-label">Custo combustível mês</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-header"><div class="kpi-icon-box">🏷️</div></div>
    <div class="kpi-value">R$&nbsp;<?= number_format($k['preco_medio'],2,',','.') ?></div>
    <div class="kpi-label">Preço médio/litro</div>
  </div>
  <div class="kpi-card accent-green">
    <div class="kpi-header"><div class="kpi-icon-box">📋</div></div>
    <div class="kpi-value" style="color:var(--green)"><?= $k['total_abast'] ?></div>
    <div class="kpi-label">Abastecimentos no mês</div>
  </div>
</div>

<!-- Fuel report by vehicle (admin) -->
<?php if(is_admin()): ?>
<div class="card mb-3">
  <div class="card-header">
    <h2><span class="icon">🚚</span>Consumo por veículo</h2>
    <form method="GET" style="display:flex;gap:10px;align-items:center">
      <input type="month" name="mes" value="<?= htmlspecialchars($mes) ?>" style="background:var(--bg-input);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);padding:6px 10px">
      <select name="veiculo_id" style="background:var(--bg-input);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);padding:6px 10px">
        <option value="">Todos veículos</option>
        <?php foreach($veiculos as $v): ?>
        <option value="<?= $v['id'] ?>" <?= $vid==$v['id']?'selected':'' ?>><?= htmlspecialchars($v['placa']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-ghost btn-sm">Filtrar</button>
    </form>
  </div>
  <div class="table-wrap">
  <table>
    <thead>
      <tr><th>Placa/Modelo</th><th>Viagens</th><th>Km rodados</th><th>Litros abast.</th><th>Consumo real</th><th>Custo comb.</th><th>Custo pedágio</th><th>Total</th><th>Variação</th></tr>
    </thead>
    <tbody>
    <?php
    $rel_params = [$mes];
    $rel_where  = "AND DATE_FORMAT(COALESCE(v.data_chegada,v.criado_em),'%Y-%m')=?";
    if ($vid) { $rel_where .= ' AND ve.id=?'; $rel_params[] = $vid; }
    $rel = $pdo->prepare("
        SELECT ve.placa, ve.modelo, ve.consumo_km_l consumo_padrao,
               COUNT(v.id) viagens,
               COALESCE(SUM(v.km_percorrido),0) total_km,
               COALESCE(SUM(v.litros_abastecidos),0) total_litros,
               COALESCE(AVG(v.consumo_real_km_l),0) media_consumo,
               COALESCE(SUM(v.custo_combustivel),0) custo_comb,
               COALESCE(SUM(v.custo_pedagio),0) custo_ped
        FROM veiculos ve
        LEFT JOIN viagens v ON v.veiculo_id=ve.id AND v.status='concluida' $rel_where
        WHERE ve.ativo=1 GROUP BY ve.id
    ");
    $rel->execute($rel_params);
    foreach($rel->fetchAll() as $r):
        $variacao = ($r['consumo_padrao']>0 && $r['media_consumo']>0)
            ? round((($r['media_consumo']-$r['consumo_padrao'])/$r['consumo_padrao'])*100,1) : null;
    ?>
    <tr>
      <td><strong><?= htmlspecialchars($r['placa']) ?></strong><br><span class="text-muted"><?= htmlspecialchars($r['modelo']) ?></span></td>
      <td><?= $r['viagens'] ?></td>
      <td><?= number_format($r['total_km'],0,',','.').' km' ?></td>
      <td><?= number_format($r['total_litros'],1,',','.').' L' ?></td>
      <td><?= $r['media_consumo']>0?number_format($r['media_consumo'],2,',','.').' km/L':'—' ?></td>
      <td>R$&nbsp;<?= number_format($r['custo_comb'],2,',','.') ?></td>
      <td>R$&nbsp;<?= number_format($r['custo_ped'],2,',','.') ?></td>
      <td class="fw-700">R$&nbsp;<?= number_format($r['custo_comb']+$r['custo_ped'],2,',','.') ?></td>
      <td class="<?= $variacao!==null?($variacao>=0?'positivo':'negativo'):'neutro' ?>">
        <?= $variacao!==null?($variacao>=0?'+':'').$variacao.'%':'—' ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<!-- Fuel history -->
<div class="card">
  <div class="card-header"><h2><span class="icon">📋</span>Histórico de abastecimentos</h2></div>
  <?php if(empty($lista)): ?>
  <div class="empty-state"><div class="icon">⛽</div><h3>Nenhum abastecimento registrado</h3></div>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <thead>
      <tr><th>Data/Hora</th><th>Motorista</th><th>Placa</th><th>KM</th><th>Litros</th><th>R$/L</th><th>Total</th><th>Posto</th></tr>
    </thead>
    <tbody>
    <?php foreach($lista as $a): ?>
    <tr>
      <td class="text-muted"><?= date('d/m/Y H:i',strtotime($a['data_hora'])) ?></td>
      <td class="fw-700"><?= htmlspecialchars($a['motorista_nome']) ?></td>
      <td><code style="background:var(--bg-card2);padding:2px 6px;border-radius:4px;font-size:.78rem"><?= htmlspecialchars($a['placa']) ?></code></td>
      <td><?= number_format($a['km_no_momento'],0,',','.') ?></td>
      <td><?= number_format($a['litros'],1,',','.') ?>L</td>
      <td>R$&nbsp;<?= number_format($a['preco_litro'],2,',','.') ?></td>
      <td class="fw-700 text-brand">R$&nbsp;<?= number_format($a['valor_total'],2,',','.') ?></td>
      <td class="text-muted"><?= htmlspecialchars($a['posto']?:'—') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<!-- Modal Registrar Abastecimento -->
<div class="modal-overlay" id="modal-abastecer" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-header">
      <h2>⛽ Registrar Abastecimento</h2>
      <button class="modal-close" onclick="document.getElementById('modal-abastecer').style.display='none'">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="abastecer">
      <?php if(!is_admin()): ?>
      <input type="hidden" name="motorista_id" value="<?= $_SESSION['usuario_id'] ?>">
      <?php endif; ?>
      <div class="form-grid">

        <?php if(!empty($viagens_ativas)): ?>
        <div class="form-group form-full">
          <label>Viagem em andamento <?= !is_admin() ? '*' : '(opcional)' ?></label>
          <select name="viagem_id" id="sel-viagem-abast" onchange="syncViagemAbast(this)" <?= !is_admin() ? 'required' : '' ?>>
            <option value="">— Selecionar viagem —</option>
            <?php foreach($viagens_ativas as $va): ?>
            <option value="<?= $va['id'] ?>"
              data-veiculo="<?= $va['veiculo_id'] ?>"
              data-placa="<?= htmlspecialchars($va['placa']) ?>">
              #<?= $va['id'] ?> · <?= htmlspecialchars($va['placa']) ?> · <?= htmlspecialchars(mb_strimwidth($va['destino'],0,35,'…')) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php else: ?>
        <input type="hidden" name="viagem_id" value="">
        <?php if(!is_admin()): ?>
        <div class="form-group form-full">
          <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:var(--radius);padding:14px;font-size:.85rem;color:var(--text-sm)">
            ℹ️ Nenhuma viagem em andamento. O abastecimento será registrado sem vinculação a uma viagem.
          </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if(is_admin()): ?>
        <div class="form-group">
          <label>Veículo *</label>
          <select name="veiculo_id" id="sel-veiculo-abast" required>
            <option value="">Selecionar…</option>
            <?php foreach($veiculos as $v): ?>
            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['placa'].' — '.$v['modelo']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Motorista *</label>
          <select name="motorista_id" required>
            <option value="">Selecionar…</option>
            <?php foreach($motoristas as $m): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php else: ?>
        <input type="hidden" name="veiculo_id" id="hid-veiculo-abast"
          value="<?= !empty($viagens_ativas) ? $viagens_ativas[0]['veiculo_id'] : '' ?>">
        <div class="form-group form-full" id="box-placa-info">
          <label>Veículo</label>
          <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;font-size:.88rem;font-weight:700" id="txt-placa-info">
            <?= !empty($viagens_ativas) ? htmlspecialchars($viagens_ativas[0]['placa']) : '—' ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="form-group">
          <label>KM hodômetro *</label>
          <input type="number" name="km_no_momento" required step="1" placeholder="ex: 125400">
        </div>
        <div class="form-group">
          <label>Litros abastecidos *</label>
          <input type="number" name="litros" required step="0.1" min="1" placeholder="ex: 300" oninput="calcTotalAbast()">
        </div>
        <div class="form-group">
          <label>Preço por litro (R$) *</label>
          <input type="number" name="preco_litro" id="inp-preco-litro" required step="0.01" value="<?= PRECO_MEDIO_DIESEL ?>" oninput="calcTotalAbast()">
        </div>
        <div class="form-group">
          <label>Valor total estimado</label>
          <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;font-weight:700;color:var(--brand)" id="txt-total-abast">R$ —</div>
        </div>
        <div class="form-group form-full">
          <label>Nome do posto</label>
          <input type="text" name="posto" placeholder="ex: Posto Ipiranga — BR-116 km 205">
        </div>
        <div class="form-group form-full">
          <label>Observações</label>
          <textarea name="observacoes" rows="2" placeholder="Tanque cheio, abastecimento parcial, nota fiscal nº…"></textarea>
        </div>
      </div>
      <div id="sync-feedback" style="display:none;padding:10px;border-radius:var(--radius);margin-bottom:8px;font-size:.85rem"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-abastecer').style.display='none'">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="registrarAbastAjax()">✅ Registrar Abastecimento</button>
      </div>
    </form>
  </div>
</div>

<script>
const APP_URL_COMB = '<?= APP_URL ?>';

function syncViagemAbast(sel) {
  const opt = sel.selectedOptions[0];
  if (!opt || !opt.value) return;
  <?php if(!is_admin()): ?>
  const vId   = opt.dataset.veiculo || '';
  const placa = opt.dataset.placa   || '—';
  document.getElementById('hid-veiculo-abast').value  = vId;
  document.getElementById('txt-placa-info').textContent = placa;
  <?php endif; ?>
}

function calcTotalAbast() {
  const litros = parseFloat(document.querySelector('[name=litros]')?.value || 0);
  const preco  = parseFloat(document.getElementById('inp-preco-litro')?.value || 0);
  const total  = litros * preco;
  const el = document.getElementById('txt-total-abast');
  if (el) el.textContent = total > 0 ? 'R$ ' + total.toFixed(2).replace('.',',') : 'R$ —';
}

// Registrar via Ajax para sincronizar em tempo real com a viagem
async function registrarAbastAjax() {
  const form = document.querySelector('#modal-abastecer form');
  const fd = new FormData(form);

  const litros = parseFloat(fd.get('litros') || 0);
  const preco  = parseFloat(fd.get('preco_litro') || 0);
  const km     = parseFloat(fd.get('km_no_momento') || 0);

  if (!litros || !preco) {
    showSyncFeedback('Informe litros e preço por litro.', 'error'); return;
  }

  const payload = {
    viagem_id:    fd.get('viagem_id') ? parseInt(fd.get('viagem_id')) : null,
    veiculo_id:   parseInt(fd.get('veiculo_id') || 0),
    litros,
    preco_litro:  preco,
    km_no_momento: km,
    posto:        fd.get('posto') || '',
    observacoes:  fd.get('observacoes') || '',
  };

  const btn = form.querySelector('.btn-primary');
  btn.disabled = true; btn.textContent = 'Registrando…';

  try {
    const res  = await fetch(`${APP_URL_COMB}/api/registrar_abastecimento.php`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const data = await res.json();

    if (data.sucesso) {
      let msg = `✅ Abastecimento registrado! Valor: R$ ${parseFloat(data.valor_total).toFixed(2).replace('.',',')}`;
      if (data.resumo_viagem) {
        const rv = data.resumo_viagem;
        msg += ` | Viagem: combustível acumulado R$ ${parseFloat(rv.custo_combustivel||0).toFixed(2).replace('.',',')}`;
      }
      showToast(msg, 'success');
      document.getElementById('modal-abastecer').style.display = 'none';
      setTimeout(() => location.reload(), 1500);
    } else {
      showSyncFeedback('Erro: ' + data.erro, 'error');
    }
  } catch(e) {
    showSyncFeedback('Erro de conexão: ' + e.message, 'error');
  } finally {
    btn.disabled = false; btn.textContent = '✅ Registrar Abastecimento';
  }
}

function showSyncFeedback(msg, tipo) {
  const el = document.getElementById('sync-feedback');
  el.style.display = 'block';
  el.style.background = tipo === 'error' ? 'rgba(239,68,68,.15)' : 'rgba(16,185,129,.15)';
  el.style.color = tipo === 'error' ? '#f87171' : '#34d399';
  el.style.border = `1px solid ${tipo === 'error' ? 'rgba(239,68,68,.3)' : 'rgba(16,185,129,.3)'}`;
  el.textContent = msg;
}

<?php if(!is_admin() && !empty($viagens_ativas)): ?>
document.addEventListener('DOMContentLoaded', () => {
  const sel = document.getElementById('sel-viagem-abast');
  if (sel && sel.options.length > 1) { sel.selectedIndex = 1; syncViagemAbast(sel); }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
