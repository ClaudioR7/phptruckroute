<?php
require_once __DIR__ . '/../includes/layout_header.php';
exigir_admin();
$pdo = get_db();

$mes = date('Y-m');
$k = $pdo->prepare("
    SELECT COUNT(*) total_viagens,
           COALESCE(SUM(distancia_km),0) total_km,
           COALESCE(SUM(litros_abastecidos),0) total_litros,
           COALESCE(SUM(custo_combustivel+custo_pedagio+outros_custos),0) total_custo,
           COALESCE(SUM(valor_frete),0) total_receita,
           COALESCE(AVG(consumo_real_km_l),0) media_consumo
    FROM viagens
    WHERE DATE_FORMAT(COALESCE(data_chegada,criado_em),'%Y-%m')=? AND status='concluida'
");
$k->execute([$mes]); $k = $k->fetch();
$rent = $k['total_receita'] - $k['total_custo'];

$em_and  = $pdo->query("SELECT COUNT(*) FROM viagens WHERE status='em_andamento'")->fetchColumn();
$total_v = $pdo->query("SELECT COUNT(*) FROM veiculos WHERE ativo=1")->fetchColumn();
$total_m = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil='motorista' AND ativo=1")->fetchColumn();

// Top motoristas do mês
$top = $pdo->prepare("
    SELECT u.nome, COUNT(v.id) viagens, COALESCE(SUM(v.distancia_km),0) km,
           COALESCE(SUM(v.valor_frete),0) frete,
           COALESCE(SUM(v.custo_combustivel+v.custo_pedagio+v.outros_custos),0) custo
    FROM usuarios u
    JOIN viagens v ON v.motorista_id=u.id AND v.status='concluida'
        AND DATE_FORMAT(COALESCE(v.data_chegada,v.criado_em),'%Y-%m')=?
    WHERE u.perfil='motorista'
    GROUP BY u.id ORDER BY km DESC LIMIT 5
");
$top->execute([$mes]); $top = $top->fetchAll();

// Recent alerts (trips with negative margin)
$alertas = $pdo->query("
    SELECT v.id, u.nome motorista, ve.placa, v.rentabilidade, v.destino
    FROM viagens v
    JOIN usuarios u ON u.id=v.motorista_id
    JOIN veiculos ve ON ve.id=v.veiculo_id
    WHERE v.rentabilidade < 0 AND v.status='concluida'
    ORDER BY v.criado_em DESC LIMIT 5
")->fetchAll();
?>

<div class="page-header">
  <div><h1>⚙️ Painel Administrativo</h1><div class="subtitle">Visão geral do sistema — <?= date('F Y') ?></div></div>
  <div style="display:flex;gap:8px">
    <a href="<?= APP_URL ?>/pages/usuarios.php" class="btn btn-ghost btn-sm">👥 Usuários</a>
    <a href="<?= APP_URL ?>/pages/veiculos.php" class="btn btn-ghost btn-sm">🚚 Veículos</a>
    <a href="<?= APP_URL ?>/pages/mapa_rotas.php" class="btn btn-primary">+ Nova Rota</a>
  </div>
</div>

<!-- KPIs -->
<div class="kpi-grid">
  <div class="kpi-card accent-yellow">
    <div class="kpi-header"><div class="kpi-icon-box">🔴</div><span class="live-dot" style="font-size:.65rem">LIVE</span></div>
    <div class="kpi-value" style="color:var(--yellow)"><?= $em_and ?></div>
    <div class="kpi-label">Em andamento agora</div>
  </div>
  <div class="kpi-card accent-blue">
    <div class="kpi-header"><div class="kpi-icon-box">🗺️</div></div>
    <div class="kpi-value"><?= number_format($k['total_km'],0,',','.') ?> km</div>
    <div class="kpi-label">KM no mês</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-header"><div class="kpi-icon-box">⛽</div></div>
    <div class="kpi-value"><?= number_format($k['total_litros'],0,',','.') ?>L</div>
    <div class="kpi-label">Litros consumidos</div>
  </div>
  <div class="kpi-card accent-red">
    <div class="kpi-header"><div class="kpi-icon-box">💸</div></div>
    <div class="kpi-value" style="color:var(--red)">R$&nbsp;<?= number_format($k['total_custo'],0,',','.') ?></div>
    <div class="kpi-label">Custo total mês</div>
  </div>
  <div class="kpi-card accent-green">
    <div class="kpi-header"><div class="kpi-icon-box">💼</div></div>
    <div class="kpi-value" style="color:var(--green)">R$&nbsp;<?= number_format($k['total_receita'],0,',','.') ?></div>
    <div class="kpi-label">Frete faturado mês</div>
  </div>
  <div class="kpi-card <?= $rent>=0?'accent-green':'accent-red' ?>">
    <div class="kpi-header"><div class="kpi-icon-box">📈</div><span class="kpi-change <?= $rent>=0?'up':'down' ?>"><?= $rent>=0?'▲':'▼' ?></span></div>
    <div class="kpi-value" style="color:<?= $rent>=0?'var(--green)':'var(--red)' ?>">R$&nbsp;<?= number_format(abs($rent),0,',','.') ?></div>
    <div class="kpi-label">Rentabilidade</div>
  </div>
  <div class="kpi-card accent-purple">
    <div class="kpi-header"><div class="kpi-icon-box">🚛</div></div>
    <div class="kpi-value" style="color:var(--purple)"><?= $total_v ?></div>
    <div class="kpi-label">Veículos ativos</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-header"><div class="kpi-icon-box">👤</div></div>
    <div class="kpi-value"><?= $total_m ?></div>
    <div class="kpi-label">Motoristas ativos</div>
  </div>
</div>

<div class="grid-2">

<!-- Top motoristas -->
<div class="card">
  <div class="card-header"><h2><span class="icon">🏆</span>Top motoristas do mês</h2></div>
  <?php if(empty($top)): ?>
  <div class="empty-state" style="padding:30px"><div class="icon" style="font-size:2rem">📊</div><p>Sem dados este mês</p></div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:12px">
    <?php foreach($top as $i => $t):
      $margem = $t['frete'] - $t['custo'];
    ?>
    <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--bg-card2);border-radius:var(--radius)">
      <div style="width:28px;height:28px;border-radius:50%;background:<?= ['#f97316','#3b82f6','#8b5cf6','#10b981','#f59e0b'][$i] ?>;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;flex-shrink:0"><?= $i+1 ?></div>
      <div style="flex:1">
        <div class="fw-700" style="font-size:.88rem"><?= htmlspecialchars($t['nome']) ?></div>
        <div class="text-muted" style="font-size:.75rem"><?= $t['viagens'] ?> viagens — <?= number_format($t['km'],0,',','.').' km' ?></div>
      </div>
      <div style="text-align:right">
        <div class="fw-700 <?= $margem>=0?'positivo':'negativo' ?>" style="font-size:.88rem">R$&nbsp;<?= number_format($margem,0,',','.') ?></div>
        <div class="text-muted" style="font-size:.72rem">margem</div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Alerts -->
<div class="card">
  <div class="card-header"><h2><span class="icon">⚠️</span>Viagens com resultado negativo</h2></div>
  <?php if(empty($alertas)): ?>
  <div class="empty-state" style="padding:30px"><div class="icon" style="font-size:2rem">✅</div><h3>Tudo OK!</h3><p>Nenhuma viagem com resultado negativo.</p></div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:8px">
    <?php foreach($alertas as $a): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:11px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:var(--radius)">
      <span style="font-size:1.2rem">⚠️</span>
      <div style="flex:1">
        <div class="fw-700" style="font-size:.85rem"><?= htmlspecialchars($a['motorista']) ?> — <?= htmlspecialchars($a['placa']) ?></div>
        <div class="text-muted" style="font-size:.75rem">→ <?= htmlspecialchars(mb_strimwidth($a['destino'],0,35,'…')) ?></div>
      </div>
      <div class="negativo fw-700">R$&nbsp;<?= number_format($a['rentabilidade'],0,',','.') ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

</div>

<!-- Quick actions -->
<div class="card mt-3">
  <div class="card-header"><h2><span class="icon">⚡</span>Ações rápidas</h2></div>
  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <a href="<?= APP_URL ?>/pages/mapa_rotas.php" class="btn btn-primary">🗺️ Nova Rota</a>
    <a href="<?= APP_URL ?>/pages/usuarios.php" class="btn btn-blue">👥 Gerenciar Usuários</a>
    <a href="<?= APP_URL ?>/pages/veiculos.php" class="btn btn-ghost">🚚 Gerenciar Veículos</a>
    <a href="<?= APP_URL ?>/pages/monitoramento_frota.php" class="btn btn-ghost">📍 Rastreamento</a>
    <a href="<?= APP_URL ?>/pages/combustivel.php" class="btn btn-ghost">⛽ Combustível</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
