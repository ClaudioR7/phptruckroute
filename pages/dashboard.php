<?php
require_once __DIR__ . '/../includes/layout_header.php';

// Motoristas não têm acesso ao dashboard gerencial — redireciona para suas viagens
if (!is_admin()) {
    header('Location: ' . APP_URL . '/pages/rotas.php');
    exit;
}

$pdo = get_db();

$mes = date('Y-m');
$kpi = $pdo->prepare("
    SELECT
        COUNT(*)                                              AS total_viagens,
        COALESCE(SUM(distancia_km), 0)                        AS total_km,
        COALESCE(SUM(litros_abastecidos), 0)                  AS total_litros,
        COALESCE(SUM(custo_combustivel + custo_pedagio + outros_custos), 0) AS total_custo,
        COALESCE(SUM(valor_frete), 0)                         AS total_receita,
        COALESCE(AVG(consumo_real_km_l), 0)                   AS media_consumo
    FROM viagens
    WHERE DATE_FORMAT(COALESCE(data_chegada, criado_em), '%Y-%m') = ?
      AND status = 'concluida'
");
$kpi->execute([$mes]);
$k = $kpi->fetch();

$rentabilidade   = $k['total_receita'] - $k['total_custo'];
$em_andamento    = $pdo->query("SELECT COUNT(*) FROM viagens WHERE status='em_andamento'")->fetchColumn();
$total_motoristas = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil='motorista' AND ativo=1")->fetchColumn();
$total_veiculos  = $pdo->query("SELECT COUNT(*) FROM veiculos WHERE ativo=1")->fetchColumn();

// Last 10 trips
$ultimas = $pdo->query("
    SELECT v.*, u.nome motorista_nome, ve.placa, ve.modelo
    FROM viagens v
    JOIN usuarios u  ON u.id  = v.motorista_id
    JOIN veiculos ve ON ve.id = v.veiculo_id
    ORDER BY v.criado_em DESC LIMIT 10
")->fetchAll();

// Monthly chart data (last 6 months)
$meses = [];
for ($i = 5; $i >= 0; $i--) {
    $dt = new DateTime("first day of -$i month");
    $meses[] = $dt->format('Y-m');
}
$chart_labels = [];
$chart_km     = [];
$chart_custo  = [];
foreach ($meses as $m) {
    $st = $pdo->prepare("
        SELECT COALESCE(SUM(distancia_km),0) km,
               COALESCE(SUM(custo_combustivel+custo_pedagio+outros_custos),0) custo
        FROM viagens
        WHERE DATE_FORMAT(COALESCE(data_chegada,criado_em),'%Y-%m')=? AND status='concluida'
    ");
    $st->execute([$m]);
    $r = $st->fetch();
    $dt = DateTime::createFromFormat('Y-m', $m);
    $chart_labels[] = $dt->format('M/y');
    $chart_km[]     = round($r['km'], 1);
    $chart_custo[]  = round($r['custo'], 2);
}

// Active drivers on route
$motoristas_em_rota = $pdo->query("
    SELECT u.nome, ve.placa, v.origem, v.destino, v.data_saida,
           (SELECT lat FROM posicoes p WHERE p.viagem_id=v.id ORDER BY p.registrado_em DESC LIMIT 1) lat,
           (SELECT lng FROM posicoes p WHERE p.viagem_id=v.id ORDER BY p.registrado_em DESC LIMIT 1) lng
    FROM viagens v
    JOIN usuarios u  ON u.id  = v.motorista_id
    JOIN veiculos ve ON ve.id = v.veiculo_id
    WHERE v.status = 'em_andamento'
    ORDER BY v.data_saida DESC
    LIMIT 5
")->fetchAll();
?>

<div class="page-header">
  <div>
    <h1>📊 Dashboard</h1>
    <div class="subtitle">Resumo de <?= date('F Y') ?> — atualizado em tempo real</div>
  </div>
  <div class="flex gap-2">
    <?php if(is_admin()): ?>
    <a href="<?= APP_URL ?>/pages/mapa_rotas.php" class="btn btn-primary">+ Nova Rota</a>
    <?php endif; ?>
  </div>
</div>

<!-- KPIs -->
<div class="kpi-grid">
  <div class="kpi-card accent-yellow">
    <div class="kpi-header">
      <div class="kpi-icon-box">🚚</div>
      <?php if($em_andamento>0): ?><span class="live-dot" style="font-size:.7rem">AO VIVO</span><?php endif; ?>
    </div>
    <div class="kpi-value" style="color:var(--yellow)"><?= $em_andamento ?></div>
    <div class="kpi-label">Viagens em andamento</div>
  </div>

  <div class="kpi-card accent-blue">
    <div class="kpi-header">
      <div class="kpi-icon-box">🗺️</div>
    </div>
    <div class="kpi-value"><?= number_format($k['total_km'],0,',','.') ?></div>
    <div class="kpi-label">Km rodados no mês</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-header">
      <div class="kpi-icon-box">⛽</div>
    </div>
    <div class="kpi-value"><?= number_format($k['total_litros'],0,',','.') ?>L</div>
    <div class="kpi-label">Combustível consumido</div>
  </div>

  <div class="kpi-card accent-purple">
    <div class="kpi-header">
      <div class="kpi-icon-box">📈</div>
    </div>
    <div class="kpi-value" style="color:var(--purple)"><?= $k['media_consumo']>0?number_format($k['media_consumo'],1,',','.'):'—' ?></div>
    <div class="kpi-label">km/L médio da frota</div>
  </div>

  <div class="kpi-card accent-red">
    <div class="kpi-header">
      <div class="kpi-icon-box">💸</div>
    </div>
    <div class="kpi-value" style="color:var(--red)">R$&nbsp;<?= number_format($k['total_custo'],0,',','.') ?></div>
    <div class="kpi-label">Custo total no mês</div>
  </div>

  <div class="kpi-card <?= $rentabilidade>=0?'accent-green':'accent-red' ?>">
    <div class="kpi-header">
      <div class="kpi-icon-box">💹</div>
      <span class="kpi-change <?= $rentabilidade>=0?'up':'down' ?>"><?= $rentabilidade>=0?'▲':'▼' ?></span>
    </div>
    <div class="kpi-value" style="color:<?= $rentabilidade>=0?'var(--green)':'var(--red)' ?>">
      R$&nbsp;<?= number_format(abs($rentabilidade),0,',','.') ?>
    </div>
    <div class="kpi-label">Rentabilidade do mês</div>
  </div>

  <div class="kpi-card accent-green">
    <div class="kpi-header"><div class="kpi-icon-box">👤</div></div>
    <div class="kpi-value" style="color:var(--green)"><?= $total_motoristas ?></div>
    <div class="kpi-label">Motoristas ativos</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-header"><div class="kpi-icon-box">🚛</div></div>
    <div class="kpi-value"><?= $total_veiculos ?></div>
    <div class="kpi-label">Veículos cadastrados</div>
  </div>
</div>

<!-- Charts + Map -->
<div class="grid-2 mb-3">
  <!-- Monthly chart -->
  <div class="card">
    <div class="card-header">
      <h2><span class="icon">📈</span>Quilômetros — últimos 6 meses</h2>
    </div>
    <canvas id="chartKm" height="180"></canvas>
  </div>

  <!-- Cost chart -->
  <div class="card">
    <div class="card-header">
      <h2><span class="icon">💰</span>Custos — últimos 6 meses</h2>
    </div>
    <canvas id="chartCusto" height="180"></canvas>
  </div>
</div>

<!-- Active routes map + driver list -->
<?php if ($em_andamento > 0): ?>
<div class="card mb-3">
  <div class="card-header">
    <h2><span class="icon">📍</span>Motoristas em rota <span class="live-dot" style="margin-left:8px">AO VIVO</span></h2>
    <a href="<?= APP_URL ?>/pages/monitoramento_frota.php" class="btn btn-ghost btn-sm">Ver rastreamento completo →</a>
  </div>
  <div class="grid-2" style="gap:16px">
    <div style="display:flex;flex-direction:column;gap:10px">
      <?php foreach($motoristas_em_rota as $mr): ?>
      <div class="driver-card">
        <div class="driver-avatar"><?= strtoupper(substr($mr['nome'],0,1)) ?></div>
        <div class="driver-info">
          <strong><?= htmlspecialchars($mr['nome']) ?></strong>
          <span><?= htmlspecialchars($mr['placa']) ?> — <?= htmlspecialchars(mb_strimwidth($mr['destino'],0,30,'…')) ?></span>
          <span><?= $mr['data_saida']?date('d/m H:i',strtotime($mr['data_saida'])):'—' ?></span>
        </div>
        <div class="driver-status"><span class="badge badge-em_andamento">em rota</span></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div id="map-mini" style="height:280px;border-radius:var(--radius-lg);overflow:hidden;border:1px solid var(--border)"></div>
  </div>
</div>
<?php endif; ?>

<!-- Recent trips table -->
<div class="card">
  <div class="card-header">
    <h2><span class="icon">🕐</span>Últimas viagens</h2>
    <a href="<?= APP_URL ?>/pages/rotas.php" class="btn btn-ghost btn-sm">Ver todas →</a>
  </div>
  <?php if(empty($ultimas)): ?>
  <div class="empty-state"><div class="icon">🛣️</div><h3>Nenhuma viagem registrada</h3><p>Clique em "Nova Rota" para começar.</p></div>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>#</th><th>Motorista</th><th>Placa</th>
        <th>Origem → Destino</th><th>Saída</th>
        <th>Km</th><th>Combustível</th><th>Pedágio</th>
        <th>Frete</th><th>Resultado</th><th>Status</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($ultimas as $v): ?>
    <tr>
      <td class="text-muted">#<?= $v['id'] ?></td>
      <td class="fw-700"><?= htmlspecialchars($v['motorista_nome']) ?></td>
      <td><code style="background:var(--bg-card2);padding:2px 6px;border-radius:4px;font-size:.8rem"><?= htmlspecialchars($v['placa']) ?></code></td>
      <td style="max-width:200px">
        <div style="font-size:.82rem"><?= htmlspecialchars(mb_strimwidth($v['origem'],0,25,'…')) ?></div>
        <div style="font-size:.82rem;color:var(--text-sm)">→ <?= htmlspecialchars(mb_strimwidth($v['destino'],0,25,'…')) ?></div>
      </td>
      <td class="text-muted"><?= $v['data_saida']?date('d/m H:i',strtotime($v['data_saida'])):'—' ?></td>
      <td><?= $v['km_percorrido']?number_format($v['km_percorrido'],0,',','.').'km':'—' ?></td>
      <td>R$&nbsp;<?= number_format($v['custo_combustivel'],2,',','.') ?></td>
      <td>R$&nbsp;<?= number_format($v['custo_pedagio'],2,',','.') ?></td>
      <td>R$&nbsp;<?= number_format($v['valor_frete'],2,',','.') ?></td>
      <td class="<?= $v['rentabilidade']>=0?'positivo':'negativo' ?>">
        R$&nbsp;<?= number_format($v['rentabilidade'],2,',','.') ?>
      </td>
      <td><span class="badge badge-<?= $v['status'] ?>"><?= $v['status'] ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const labels = <?= json_encode($chart_labels) ?>;
const dataKm  = <?= json_encode($chart_km) ?>;
const dataCusto = <?= json_encode($chart_custo) ?>;

const commonOpts = {
  plugins: { legend: { display: false } },
  scales: {
    x: { grid: { color: '#1e3050' }, ticks: { color: '#64748b' } },
    y: { grid: { color: '#1e3050' }, ticks: { color: '#64748b' } }
  },
  animation: { duration: 700 }
};

new Chart(document.getElementById('chartKm'), {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      data: dataKm,
      backgroundColor: 'rgba(59,130,246,.55)',
      borderColor: '#3b82f6',
      borderWidth: 2,
      borderRadius: 6,
    }]
  },
  options: { ...commonOpts,
    scales: { ...commonOpts.scales,
      y: { ...commonOpts.scales.y, ticks: { ...commonOpts.scales.y.ticks, callback: v => v.toLocaleString('pt-BR')+'km' } }
    }
  }
});

new Chart(document.getElementById('chartCusto'), {
  type: 'line',
  data: {
    labels,
    datasets: [{
      data: dataCusto,
      borderColor: '#f97316',
      backgroundColor: 'rgba(249,115,22,.1)',
      borderWidth: 2,
      fill: true,
      tension: .4,
      pointBackgroundColor: '#f97316',
      pointRadius: 4,
    }]
  },
  options: { ...commonOpts,
    scales: { ...commonOpts.scales,
      y: { ...commonOpts.scales.y, ticks: { ...commonOpts.scales.y.ticks, callback: v => 'R$'+v.toLocaleString('pt-BR') } }
    }
  }
});

<?php if($em_andamento>0 && !empty($motoristas_em_rota)): ?>
function initMiniMap() {
  const center = { lat: <?= $motoristas_em_rota[0]['lat'] ?: -15.8 ?>, lng: <?= $motoristas_em_rota[0]['lng'] ?: -47.9 ?> };
  const map = new google.maps.Map(document.getElementById('map-mini'), {
    zoom: 6, center,
    styles: [
      { elementType:'geometry', stylers:[{color:'#0d1525'}] },
      { elementType:'labels.text.fill', stylers:[{color:'#94a3b8'}] },
      { elementType:'labels.text.stroke', stylers:[{color:'#0d1525'}] },
      { featureType:'road', elementType:'geometry', stylers:[{color:'#1e3050'}] },
      { featureType:'water', elementType:'geometry', stylers:[{color:'#060b14'}] }
    ],
    disableDefaultUI: true, zoomControl: true
  });
  const drivers = <?= json_encode(array_map(fn($m)=>['lat'=>(float)$m['lat'],'lng'=>(float)$m['lng'],'nome'=>$m['nome'],'placa'=>$m['placa']], array_filter($motoristas_em_rota,fn($m)=>$m['lat']))) ?>;
  drivers.forEach(d => {
    new google.maps.Marker({
      position: { lat: d.lat, lng: d.lng },
      map,
      title: d.nome + ' — ' + d.placa,
      icon: { path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW, scale: 6, fillColor: '#f97316', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 1 }
    });
  });
}
<?php endif; ?>
</script>
<?php if($em_andamento>0 && !empty($motoristas_em_rota)): ?>
<?php $apiKey = GOOGLE_MAPS_API_KEY; if ($apiKey && $apiKey !== 'SUA_CHAVE_GOOGLE_MAPS_AQUI'): ?>
<script>window.gm_authFailure=function(){const m=document.getElementById('map-mini');if(m)m.innerHTML='<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-sm);font-size:.82rem">⚠️ API Key inválida</div>';}</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($apiKey) ?>&callback=initMiniMap&loading=async" async defer></script>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
