<?php
require_once __DIR__ . '/../includes/layout_header.php';
$pdo = get_db();

if (is_admin()) {
    $viagens = $pdo->query("
        SELECT v.id, v.origem, v.destino, v.distancia_km, v.data_saida, v.polyline,
               u.nome motorista_nome, ve.placa, ve.modelo, v.km_saida,
               (SELECT lat FROM posicoes p WHERE p.viagem_id=v.id ORDER BY p.registrado_em DESC LIMIT 1) lat,
               (SELECT lng FROM posicoes p WHERE p.viagem_id=v.id ORDER BY p.registrado_em DESC LIMIT 1) lng,
               (SELECT velocidade FROM posicoes p WHERE p.viagem_id=v.id ORDER BY p.registrado_em DESC LIMIT 1) velocidade
        FROM viagens v
        JOIN usuarios u  ON u.id  = v.motorista_id
        JOIN veiculos ve ON ve.id = v.veiculo_id
        WHERE v.status = 'em_andamento'
        ORDER BY v.data_saida DESC
    ")->fetchAll();
} else {
    $uid = $_SESSION['usuario_id'];
    $st  = $pdo->prepare("
        SELECT v.id, v.origem, v.destino, v.distancia_km, v.data_saida, v.polyline,
               u.nome motorista_nome, ve.placa, ve.modelo, v.km_saida,
               (SELECT lat FROM posicoes p WHERE p.viagem_id=v.id ORDER BY p.registrado_em DESC LIMIT 1) lat,
               (SELECT lng FROM posicoes p WHERE p.viagem_id=v.id ORDER BY p.registrado_em DESC LIMIT 1) lng,
               (SELECT velocidade FROM posicoes p WHERE p.viagem_id=v.id ORDER BY p.registrado_em DESC LIMIT 1) velocidade
        FROM viagens v
        JOIN usuarios u  ON u.id  = v.motorista_id
        JOIN veiculos ve ON ve.id = v.veiculo_id
        WHERE v.status='em_andamento' AND v.motorista_id=?
        ORDER BY v.data_saida DESC
    ");
    $st->execute([$uid]);
    $viagens = $st->fetchAll();
}
?>

<div class="page-header">
  <div><h1>📍 Rastreamento em Tempo Real</h1><div class="subtitle">Acompanhe todos os motoristas em rota</div></div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <span class="live-dot">Atualizando a cada 15s</span>
    <?php if (!is_admin()): ?>
    <button id="btn-gps" class="btn btn-primary btn-sm" onclick="this.textContent.includes('Ativo')?pararGPS():iniciarEnvioGPS()">
      📍 Ativar GPS
    </button>
    <?php endif; ?>
  </div>
</div>

<?php if (!is_admin()): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-header"><h2><span class="icon">🚛</span>Sua Viagem Ativa</h2></div>
  <div id="info-viagem-ativa" style="font-size:.85rem;color:var(--text-sm);padding:4px 0">Carregando…</div>
  <div id="itinerario-motorista" style="margin-top:12px"></div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start">

<div style="display:flex;flex-direction:column;gap:10px">
  <?php if(empty($viagens)): ?>
  <div class="empty-state">
    <div class="icon">🛣️</div>
    <h3>Nenhum motorista em rota</h3>
    <p>As viagens em andamento aparecerão aqui em tempo real.</p>
  </div>
  <?php else: ?>
  <?php foreach($viagens as $i => $v): ?>
  <div class="driver-card <?= $i===0?'selected':'' ?>" onclick="focusVeiculo(<?= $v['id'] ?>, this)"
       id="card-<?= $v['id'] ?>" data-id="<?= $v['id'] ?>"
       data-lat="<?= $v['lat'] ?>" data-lng="<?= $v['lng'] ?>">
    <div class="driver-avatar"><?= strtoupper(substr($v['motorista_nome'],0,1)) ?></div>
    <div class="driver-info" style="flex:1;min-width:0">
      <strong><?= htmlspecialchars($v['motorista_nome']) ?></strong>
      <span><?= htmlspecialchars($v['placa']) ?> — <?= htmlspecialchars($v['modelo']) ?></span>
      <span>→ <?= htmlspecialchars(mb_strimwidth($v['destino'],0,28,'…')) ?></span>
      <span>Saiu: <?= $v['data_saida']?date('d/m H:i',strtotime($v['data_saida'])):'—' ?></span>
      <?php if($v['velocidade']): ?>
      <span style="color:var(--brand);font-weight:700"><?= number_format($v['velocidade'],0,',','.') ?> km/h</span>
      <?php endif; ?>
    </div>
    <div>
      <span class="badge badge-em_andamento">em rota</span>
      <?php if(is_admin()): ?>
      <button class="btn btn-danger btn-sm mt-1" onclick="event.stopPropagation();encerrarViagem(<?= $v['id'] ?>)" style="display:block;width:100%;margin-top:6px">Encerrar</button>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- MAPA LEAFLET -->
<div>
  <div id="map-monitor" style="height:600px;border-radius:12px;overflow:hidden;position:sticky;top:80px"></div>
  <p style="font-size:.72rem;color:var(--text-sm);margin-top:6px;text-align:center">
    © <a href="https://www.openstreetmap.org/copyright" target="_blank" style="color:var(--brand)">OpenStreetMap</a> contributors
  </p>
</div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const APP_URL = '<?= APP_URL ?>';

const map = L.map('map-monitor').setView([-15.78,-47.93], 5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
  attribution:'© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  maxZoom:19
}).addTo(map);

const truckIcon = (placa) => L.divIcon({
  className:'',
  html:`<div style="background:#f97316;color:#fff;padding:3px 7px;border-radius:6px;font-size:11px;font-weight:700;white-space:nowrap;box-shadow:0 2px 8px rgba(0,0,0,.4)">🚛 ${placa}</div>`,
  iconAnchor:[30,20]
});

const routeIcon = L.divIcon({ className:'', html:'<div style="background:#3b82f6;width:10px;height:10px;border-radius:50%;border:2px solid #fff"></div>', iconSize:[10,10] });

// Dados das viagens do PHP
const viagens = <?= json_encode(array_map(fn($v) => [
    'id'       => $v['id'],
    'placa'    => $v['placa'],
    'motorista'=> $v['motorista_nome'],
    'origem'   => $v['origem'],
    'destino'  => $v['destino'],
    'lat'      => $v['lat'] ? (float)$v['lat'] : null,
    'lng'      => $v['lng'] ? (float)$v['lng'] : null,
    'polyline' => $v['polyline'] ? json_decode($v['polyline'], true) : [],
], $viagens)) ?>;

const markers = {};
const routes  = {};

viagens.forEach(v => {
  // Desenhar rota planejada
  if (v.polyline && v.polyline.length > 1) {
    routes[v.id] = L.polyline(v.polyline, { color:'#3b82f6', weight:3, opacity:.5, dashArray:'6,4' }).addTo(map);
  }
  // Marcador do veículo
  if (v.lat && v.lng) {
    markers[v.id] = L.marker([v.lat, v.lng], { icon: truckIcon(v.placa) })
      .addTo(map)
      .bindPopup(`<b>${v.placa}</b><br>${v.motorista}<br>${v.origem} → ${v.destino}`);
  }
});

// Fit bounds se houver marcadores
const allMarkers = Object.values(markers);
if (allMarkers.length > 0) {
  const group = L.featureGroup(allMarkers);
  map.fitBounds(group.getBounds().pad(.3));
}

// ─── Atualizar posições a cada 15s (ADMIN) ──────────────────
async function atualizarPosicoes() {
  try {
    const res  = await fetch(`${APP_URL}/api/get_rotas_ativas.php`);
    const data = await res.json();
    const rotas = data.rotas || [];

    rotas.forEach(r => {
      if (!r.lat || !r.lng) return;
      const pos = [parseFloat(r.lat), parseFloat(r.lng)];
      const velTxt = r.velocidade ? ` · ${parseFloat(r.velocidade).toFixed(0)} km/h` : '';
      const combustTxt = r.custo_combustivel
        ? `<br>⛽ R$ ${parseFloat(r.custo_combustivel).toFixed(2).replace('.',',')}` : '';

      if (markers[r.id]) {
        markers[r.id].setLatLng(pos);
        markers[r.id].setPopupContent(
          `<b>🚛 ${r.placa}</b><br>${r.motorista_nome}<br>${r.origem} → ${r.destino}${velTxt}${combustTxt}`
        );
      } else {
        markers[r.id] = L.marker(pos, { icon: truckIcon(r.placa) }).addTo(map)
          .bindPopup(`<b>🚛 ${r.placa}</b><br>${r.motorista_nome}<br>${r.origem} → ${r.destino}${velTxt}${combustTxt}`);
      }

      // Atualizar card lateral
      const card = document.getElementById(`card-${r.id}`);
      if (card) {
        const velEl = card.querySelector('.vel-live');
        if (velEl && r.velocidade) velEl.textContent = parseFloat(r.velocidade).toFixed(0) + ' km/h';
        const combEl = card.querySelector('.comb-live');
        if (combEl && r.custo_combustivel)
          combEl.textContent = 'R$ '+parseFloat(r.custo_combustivel).toFixed(2).replace('.',',');
      }
    });

    // Atualizar contador de veículos ativos
    document.title = `📍 ${rotas.length} em rota — Rastreamento`;
  } catch(e) { console.error('Erro ao atualizar posições', e); }
}
setInterval(atualizarPosicoes, 15000);

function focusVeiculo(id, el) {
  document.querySelectorAll('.driver-card').forEach(c=>c.classList.remove('selected'));
  el.classList.add('selected');
  if (markers[id]) {
    map.setView(markers[id].getLatLng(), 14, {animate:true});
    markers[id].openPopup();
  }
}

async function encerrarViagem(id) {
  if (!confirm('Encerrar esta viagem?')) return;
  window.location.href = `<?= APP_URL ?>/pages/rotas.php?encerrar=${id}`;
}

// ─── GPS automático do MOTORISTA ────────────────────────────
<?php if (!is_admin()): ?>
let _gpsAtivo = false;
let _viagemAtiva = null;

async function iniciarEnvioGPS() {
  if (!navigator.geolocation) {
    showToast('Geolocalização não suportada neste dispositivo.','error');
    return;
  }
  // Buscar viagem ativa
  try {
    const res = await fetch(`${APP_URL}/api/minha_viagem_ativa.php`);
    const d   = await res.json();
    if (!d.sucesso) { showToast('Nenhuma viagem ativa para rastrear.','error'); return; }
    _viagemAtiva = d.viagem;
    renderItinerarioMotorista(d.viagem);
    showToast('📍 GPS ativado! Enviando localização a cada 30s.','success');
  } catch(e) { showToast('Erro ao obter viagem ativa.','error'); return; }

  _gpsAtivo = true;
  document.getElementById('btn-gps').textContent = '📍 GPS Ativo (enviando…)';
  document.getElementById('btn-gps').style.background = 'var(--green,#10b981)';

  function enviarPosicao() {
    if (!_gpsAtivo) return;
    navigator.geolocation.getCurrentPosition(pos => {
      fetch(`${APP_URL}/api/update_posicao.php`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
          viagem_id: _viagemAtiva.id,
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
          velocidade: pos.coords.speed ? pos.coords.speed * 3.6 : null
        })
      }).then(r=>r.json()).then(d=>{
        if (!d.sucesso) console.warn('GPS err:', d.erro);
        else {
          // Atualizar marcador do próprio motorista
          const self = [pos.coords.latitude, pos.coords.longitude];
          if (markers[_viagemAtiva.id]) markers[_viagemAtiva.id].setLatLng(self);
          else {
            markers[_viagemAtiva.id] = L.marker(self, {
              icon: L.divIcon({className:'',
                html:`<div style="background:#10b981;color:#fff;padding:4px 8px;border-radius:8px;font-weight:700;font-size:11px;box-shadow:0 2px 8px rgba(0,0,0,.4)">📍 Você</div>`,
                iconAnchor:[30,20]})
            }).addTo(map).bindPopup('Sua posição atual');
          }
          map.setView(self, 14, {animate:true});
        }
      }).catch(e=>console.error(e));
    }, err => {
      console.warn('GPS error:', err.message);
      showToast('Erro GPS: '+err.message,'error');
    }, {enableHighAccuracy:true, timeout:10000, maximumAge:5000});
  }

  enviarPosicao(); // imediato
  window._gpsInterval = setInterval(enviarPosicao, 30000);
}

function pararGPS() {
  _gpsAtivo = false;
  clearInterval(window._gpsInterval);
  document.getElementById('btn-gps').textContent = '📍 Ativar GPS';
  document.getElementById('btn-gps').style.background = '';
  showToast('GPS desativado.','info');
}

function renderItinerarioMotorista(viagem) {
  const box = document.getElementById('itinerario-motorista');
  if (!box) return;
  const it = viagem.itinerario || [];
  if (!it.length) { box.innerHTML = '<p style="color:var(--text-sm);font-size:.82rem">Itinerário não disponível.</p>'; return; }
  box.innerHTML = `
    <div style="font-weight:700;margin-bottom:8px;color:var(--text)">🗺️ ${viagem.origem} → ${viagem.destino}</div>
    <div style="font-size:.8rem;color:var(--text-sm);margin-bottom:10px">
      📏 ${parseFloat(viagem.distancia_km).toFixed(1)} km &nbsp;·&nbsp;
      ⛽ R$ ${parseFloat(viagem.custo_combustivel||0).toFixed(2).replace('.',',')} gasto
    </div>
    <div style="max-height:300px;overflow-y:auto;display:flex;flex-direction:column;gap:2px">
      ${it.map((s,i)=>{
        const dist = s.distancia_m>=1000?(s.distancia_m/1000).toFixed(1)+' km':s.distancia_m+' m';
        return `<div style="display:flex;align-items:flex-start;gap:6px;padding:6px 6px;border-radius:5px;background:${i%2===0?'var(--bg-card2)':'transparent'};font-size:.79rem">
          <span style="color:var(--brand);font-weight:700;min-width:20px">${i+1}</span>
          <span style="flex:1">${s.instrucao}</span>
          <span style="color:var(--text-sm);white-space:nowrap">${dist}</span>
        </div>`;
      }).join('')}
    </div>`;
}

// Auto-iniciar GPS se tiver viagem ativa
window.addEventListener('load', () => {
  fetch(`${APP_URL}/api/minha_viagem_ativa.php`)
    .then(r=>r.json())
    .then(d=>{
      if (d.sucesso) {
        _viagemAtiva = d.viagem;
        renderItinerarioMotorista(d.viagem);
        document.getElementById('info-viagem-ativa').innerHTML =
          `<strong>${d.viagem.origem}</strong> → <strong>${d.viagem.destino}</strong><br>` +
          `📏 ${parseFloat(d.viagem.distancia_km).toFixed(1)} km &nbsp;|&nbsp; ` +
          `⛽ R$ ${parseFloat(d.viagem.custo_combustivel||0).toFixed(2).replace('.',',')}`;
      } else {
        document.getElementById('btn-gps').disabled = true;
        document.getElementById('btn-gps').title = 'Nenhuma viagem em andamento';
      }
    }).catch(()=>{});
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
