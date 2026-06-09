<?php
require_once __DIR__ . '/../includes/layout_header.php';
exigir_admin();
$pdo = get_db();
$veiculos   = $pdo->query("SELECT id,placa,modelo,consumo_km_l,tanque_litros,km_atual FROM veiculos WHERE ativo=1 ORDER BY placa")->fetchAll();
$motoristas = $pdo->query("SELECT id,nome,email,telefone FROM usuarios WHERE perfil='motorista' AND ativo=1 ORDER BY nome")->fetchAll();
?>

<style>
.badge-free{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.25);color:#34d399;font-size:.75rem;font-weight:700;padding:5px 14px;border-radius:20px}
#map-container{height:680px;border-radius:12px;overflow:hidden;position:sticky;top:80px;z-index:0}
.leaflet-container{background:#1a2744!important}
.ac-list{position:absolute;top:100%;left:0;right:0;background:var(--bg-card);border:1px solid var(--border);border-radius:0 0 8px 8px;z-index:9999;max-height:220px;overflow-y:auto;display:none;box-shadow:0 8px 24px rgba(0,0,0,.4)}
.ac-item{padding:9px 14px;cursor:pointer;font-size:.83rem;display:flex;align-items:flex-start;gap:8px;border-bottom:1px solid var(--border)}
.ac-item:last-child{border-bottom:none}
.ac-item:hover{background:var(--bg-card2)}
.ac-item .pin{color:var(--brand);flex-shrink:0;margin-top:1px}
.ac-item .name{font-weight:600;color:var(--text);margin-bottom:1px}
.ac-item .addr{font-size:.75rem;color:var(--text-sm)}
.ac-wrap{position:relative}
.cep-row{display:flex;gap:8px}
.cep-row input{flex:1}
.btn-cep{flex-shrink:0;padding:0 12px;height:40px;background:var(--brand);color:#fff;border:none;border-radius:var(--radius);cursor:pointer;font-size:.82rem;font-weight:700;white-space:nowrap}
.btn-cep:hover{opacity:.9}
.coord-badge{font-size:.72rem;color:var(--text-sm);margin-top:4px;display:flex;align-items:center;gap:4px}
.coord-badge.ok{color:#34d399}
.coord-badge.empty{color:var(--text-sm)}
</style>

<div class="page-header">
  <div><h1>🗺️ Nova Rota</h1><div class="subtitle">Planeje, calcule e envie a rota ao motorista</div></div>
  <div class="badge-free">🆓 OpenStreetMap · OSRM · 100% Gratuito</div>
</div>

<div style="display:grid;grid-template-columns:390px 1fr;gap:20px;align-items:start">

<!-- ═══ LEFT PANEL ═══ -->
<div style="display:flex;flex-direction:column;gap:16px">

  <!-- Veículo e Motorista -->
  <div class="card">
    <div class="card-header"><h2><span class="icon">🚚</span>Veículo e Motorista</h2></div>
    <div class="form-group">
      <label>Veículo</label>
      <select id="sel-veiculo" onchange="atualizarVeiculo()">
        <option value="">Selecionar veículo…</option>
        <?php foreach($veiculos as $v): ?>
        <option value="<?= $v['id'] ?>"
          data-consumo="<?= $v['consumo_km_l'] ?>"
          data-tanque="<?= $v['tanque_litros'] ?>"
          data-km="<?= $v['km_atual'] ?>">
          <?= htmlspecialchars($v['placa'].' — '.$v['modelo']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Motorista</label>
      <select id="sel-motorista">
        <option value="">Selecionar motorista…</option>
        <?php foreach($motoristas as $m): ?>
        <option value="<?= $m['id'] ?>"
          data-email="<?= htmlspecialchars($m['email']) ?>"
          data-tel="<?= htmlspecialchars($m['telefone']) ?>">
          <?= htmlspecialchars($m['nome']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Valor do Frete (R$)</label>
      <div class="input-wrap">
        <span class="input-prefix">R$</span>
        <input type="number" id="inp-frete" step="0.01" min="0" placeholder="0,00">
      </div>
    </div>
  </div>

  <!-- Combustível -->
  <div class="card">
    <div class="card-header"><h2><span class="icon">⛽</span>Combustível na Saída</h2></div>
    <div style="background:var(--bg-card2);border-radius:var(--radius);padding:14px;margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;margin-bottom:6px">
        <span style="font-size:.8rem;color:var(--text-sm)">Nível do tanque</span>
        <span style="font-size:.8rem;font-weight:700" id="tanque-pct">—</span>
      </div>
      <div class="fuel-bar-wrap fuel-level-high" id="fuel-bar-wrap">
        <div class="fuel-bar" id="fuel-bar" style="width:0%"></div>
      </div>
    </div>
    <div class="form-group">
      <label>Litros saindo da garagem</label>
      <div class="input-wrap has-suffix">
        <input type="number" id="inp-litros-saida" step="0.1" min="0" placeholder="ex: 450" oninput="syncFuel()">
        <span class="input-suffix">L</span>
      </div>
    </div>
    <div class="form-group">
      <label>Km hodômetro na saída</label>
      <div class="input-wrap has-suffix">
        <input type="number" id="inp-km-saida" step="1">
        <span class="input-suffix">km</span>
      </div>
    </div>
    <div class="form-group">
      <label>Preço do diesel (R$/L)</label>
      <div class="input-wrap">
        <span class="input-prefix">R$</span>
        <input type="number" id="inp-preco-diesel" step="0.01" value="<?= PRECO_MEDIO_DIESEL ?>">
      </div>
    </div>
  </div>

  <!-- Origem e Destino -->
  <div class="card">
    <div class="card-header"><h2><span class="icon">📍</span>Origem e Destino</h2></div>

    <!-- ORIGEM -->
    <div class="form-group">
      <label>Origem — busca por nome</label>
      <div class="ac-wrap">
        <input type="text" id="inp-origem" placeholder="Ex: São Paulo, SP" autocomplete="off" oninput="debounce('origem',this.value)">
        <div class="ac-list" id="ac-origem"></div>
      </div>
      <div style="margin-top:6px;font-size:.8rem;color:var(--text-sm)">ou buscar pelo CEP:</div>
      <div class="cep-row" style="margin-top:4px">
        <input type="text" id="cep-origem" placeholder="00000-000" maxlength="9" oninput="maskCep(this)">
        <button id="btn-cep-origem" class="btn-cep" onclick="buscarCep('origem')">🔍 Buscar CEP</button>
      </div>
      <div class="coord-badge" id="badge-origem"><span>📍</span><span id="badge-origem-txt">Nenhum local selecionado</span></div>
    </div>

    <!-- DESTINO -->
    <div class="form-group">
      <label>Destino Final — busca por nome</label>
      <div class="ac-wrap">
        <input type="text" id="inp-destino" placeholder="Ex: Rio de Janeiro, RJ" autocomplete="off" oninput="debounce('destino',this.value)">
        <div class="ac-list" id="ac-destino"></div>
      </div>
      <div style="margin-top:6px;font-size:.8rem;color:var(--text-sm)">ou buscar pelo CEP:</div>
      <div class="cep-row" style="margin-top:4px">
        <input type="text" id="cep-destino" placeholder="00000-000" maxlength="9" oninput="maskCep(this)">
        <button id="btn-cep-destino" class="btn-cep" onclick="buscarCep('destino')">🔍 Buscar CEP</button>
      </div>
      <div class="coord-badge" id="badge-destino"><span>🏁</span><span id="badge-destino-txt">Nenhum local selecionado</span></div>
    </div>

    <!-- Waypoints -->
    <div style="margin-bottom:12px">
      <div style="font-size:.8rem;font-weight:600;color:var(--text-sm);margin-bottom:8px">Paradas intermediárias</div>
      <div id="wp-list"></div>
      <button class="btn btn-ghost btn-sm" onclick="addWp()">+ Adicionar parada</button>
    </div>

    <button class="btn btn-primary" style="width:100%;justify-content:center" id="btn-calcular" onclick="calcularRota()">
      🔍 Calcular Rota
    </button>
  </div>

  <!-- Resumo -->
  <div class="route-summary card" id="route-summary">
    <div class="card-header" style="margin-bottom:12px">
      <h2><span class="icon">📊</span>Resumo da Rota</h2>
    </div>
    <div class="route-summary-kpis">
      <div class="rs-kpi"><strong id="rs-dist">—</strong><span>Distância</span></div>
      <div class="rs-kpi"><strong id="rs-dur">—</strong><span>Tempo est.</span></div>
      <div class="rs-kpi"><strong id="rs-comb">—</strong><span>Combustível</span></div>
      <div class="rs-kpi"><strong id="rs-pedagio">—</strong><span>Pedágio est.</span></div>
    </div>
    <div style="background:var(--bg-card2);border-radius:var(--radius);padding:12px;font-size:.82rem">
      <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border)">
        <span style="color:var(--text-sm)">Combustível (R$)</span>
        <span id="rs-val-comb" class="fw-700">—</span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border)">
        <span style="color:var(--text-sm)">Pedágio est. (R$)</span>
        <span id="rs-val-ped" class="fw-700">—</span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:4px 0">
        <span style="color:var(--text-sm)">Custo total est.</span>
        <span id="rs-val-total" class="fw-700 text-brand">—</span>
      </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
      <button id="btn-salvar" class="btn btn-primary" style="flex:1;justify-content:center" onclick="salvarViagem(this)">✅ Salvar Viagem</button>
      <button class="btn btn-ghost btn-sm" onclick="enviarWhatsApp()">📱 WhatsApp</button>
    </div>
  </div>

</div><!-- end left -->

<!-- ═══ MAP ═══ -->
<div>
  <div id="map-container"></div>
  <p style="font-size:.72rem;color:var(--text-sm);margin-top:6px;text-align:center">
    © <a href="https://www.openstreetmap.org/copyright" target="_blank" style="color:var(--brand)">OpenStreetMap</a> contributors &nbsp;·&nbsp;
    Rotas <a href="https://project-osrm.org" target="_blank" style="color:var(--brand)">OSRM</a> &nbsp;·&nbsp;
    CEP <a href="https://viacep.com.br" target="_blank" style="color:var(--brand)">ViaCEP</a>
  </p>
</div>

</div><!-- end grid -->

<!-- Waypoint template -->
<template id="tpl-wp">
  <div class="wp-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:flex-start">
    <div style="flex:1">
      <div class="ac-wrap">
        <input type="text" class="wp-inp" placeholder="Parada…" autocomplete="off" style="width:100%">
        <div class="ac-list wp-ac"></div>
      </div>
      <div class="coord-badge wp-badge"><span>⏸</span><span class="wp-badge-txt">Não selecionado</span></div>
    </div>
    <button class="btn btn-danger btn-icon btn-sm" onclick="removeWp(this)" style="margin-top:0">✕</button>
  </div>
</template>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// ─── Constantes do PHP ─────────────────────────────────────
const APP_URL       = '<?= APP_URL ?>';
const CONSUMO_PAD   = <?= CONSUMO_PADRAO_KM_L ?>;
const PRECO_DIESEL  = <?= PRECO_MEDIO_DIESEL ?>;
const CUSTO_PED_KM  = <?= CUSTO_PEDAGIO_KM ?>;

// ─── Dados de local selecionado ────────────────────────────
const geo = { origem: null, destino: null }; // {lat,lng,desc}
let routeData = null;

// ─── Mapa Leaflet ──────────────────────────────────────────
const map = L.map('map-container').setView([-15.78,-47.93], 5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
  attribution:'© OpenStreetMap contributors', maxZoom:19
}).addTo(map);

// Tile escuro alternativo (descomente se preferir):
// L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',{attribution:'© OpenStreetMap © CARTO',maxZoom:19}).addTo(map);

let routeLayer=null, mOrigin=null, mDest=null, mWps=[];

const mkIcon = (color,emoji) => L.divIcon({className:'',
  html:`<div style="background:${color};width:16px;height:16px;border-radius:50%;border:3px solid #fff;box-shadow:0 0 8px ${color}88;display:flex;align-items:center;justify-content:center;font-size:9px">${emoji}</div>`,
  iconSize:[16,16], iconAnchor:[8,8]});

// ─── Autocomplete (Nominatim — chamado do navegador) ────────
const timers = {};
function debounce(id, val) {
  clearTimeout(timers[id]);
  const list = document.getElementById('ac-'+id);
  if (val.length < 3) { list.style.display='none'; return; }
  timers[id] = setTimeout(() => nominatimSearch(val, list, id, null), 500);
}

async function nominatimSearch(q, list, geoId, wpData) {
  try {
    const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(q+', Brasil')}&format=jsonv2&limit=6&countrycodes=br&addressdetails=1`;
    const r = await fetch(url, { headers: { 'Accept-Language':'pt-BR,pt;q=0.9' } });
    const data = await r.json();
    if (!data.length) { list.style.display='none'; return; }

    list.innerHTML = data.map(d => {
      const parts = d.display_name.split(',');
      const name  = parts.slice(0,2).join(',').trim();
      const rest  = parts.slice(2).join(',').trim();
      return `<div class="ac-item" data-lat="${d.lat}" data-lng="${d.lon}" data-desc="${escAttr(d.display_name)}"
        onclick="selectPlace(this,'${geoId}',${wpData?'true':'false'})">
        <span class="pin">📍</span>
        <div><div class="name">${name}</div><div class="addr">${rest}</div></div>
      </div>`;
    }).join('');
    list.style.display='block';
  } catch(e) {
    list.innerHTML='<div class="ac-item"><span style="color:#ef4444">Erro ao buscar. Verifique conexão.</span></div>';
    list.style.display='block';
  }
}

function selectPlace(el, geoId, isWp) {
  const lat  = parseFloat(el.dataset.lat);
  const lng  = parseFloat(el.dataset.lng);
  const desc = el.dataset.desc;
  el.closest('.ac-list').style.display='none';

  if (!isWp) {
    geo[geoId] = { lat, lng, desc };
    document.getElementById('inp-'+geoId).value = desc;
    setBadge(geoId, lat, lng, desc);
    // Marcador no mapa
    if (geoId==='origem') {
      if (mOrigin) map.removeLayer(mOrigin);
      mOrigin = L.marker([lat,lng],{icon:mkIcon('#10b981','O')}).addTo(map).bindPopup('📍 '+desc.split(',')[0]);
    } else {
      if (mDest) map.removeLayer(mDest);
      mDest = L.marker([lat,lng],{icon:mkIcon('#f97316','D')}).addTo(map).bindPopup('🏁 '+desc.split(',')[0]);
    }
    map.setView([lat,lng],12,{animate:true});
  } else {
    // é waypoint — buscar o row pai
    const row = el.closest('.wp-row');
    row.querySelector('.wp-inp').value = desc;
    row.dataset.lat  = lat;
    row.dataset.lng  = lng;
    row.dataset.desc = desc;
    row.querySelector('.wp-badge-txt').textContent = desc.split(',').slice(0,2).join(',');
    row.querySelector('.wp-badge').classList.add('ok');
  }
}

function setBadge(id, lat, lng, desc) {
  const badge = document.getElementById('badge-'+id);
  const txt   = document.getElementById('badge-'+id+'-txt');
  badge.classList.add('ok');
  txt.textContent = `${desc.split(',').slice(0,2).join(',')} (${parseFloat(lat).toFixed(4)}, ${parseFloat(lng).toFixed(4)})`;
}

function escAttr(s){ return s.replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

// Fechar listas ao clicar fora
document.addEventListener('click', e => {
  if (!e.target.closest('.ac-wrap')) document.querySelectorAll('.ac-list').forEach(l=>l.style.display='none');
});

// ─── Busca por CEP (ViaCEP — também no navegador) ──────────
function maskCep(inp) {
  let v = inp.value.replace(/\D/g,'');
  if (v.length > 5) v = v.slice(0,5)+'-'+v.slice(5,8);
  inp.value = v;
}

async function buscarCep(geoId) {
  const cep = document.getElementById('cep-'+geoId).value.replace(/\D/g,'');
  if (cep.length !== 8) { showToast('Digite um CEP válido com 8 dígitos.','error'); return; }

  const btnId = geoId === 'origem' ? 'btn-cep-origem' : 'btn-cep-destino';
  const btn   = document.getElementById(btnId);
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Buscando…'; }
  showToast('Buscando CEP…','info');

  try {
    // 1. ViaCEP → dados do endereço
    const vr = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
    if (!vr.ok) throw new Error('ViaCEP indisponível');
    const vd = await vr.json();
    if (vd.erro) { showToast('CEP não encontrado. Verifique o número digitado.','error'); return; }

    const cidade  = vd.localidade + ' ' + vd.uf;
    // Montar query progressivamente: mais específica → mais genérica
    const queries = [
      [vd.logradouro, vd.bairro, vd.localidade, vd.uf, 'Brasil'].filter(Boolean).join(', '),
      [vd.bairro,     vd.localidade, vd.uf, 'Brasil'].filter(Boolean).join(', '),
      [vd.localidade, vd.uf, 'Brasil'].filter(Boolean).join(', '),
    ];

    let lat = null, lng = null, addrUsed = queries[0];

    for (const q of queries) {
      try {
        const nr = await fetch(
          `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(q)}&format=jsonv2&limit=1&countrycodes=br&addressdetails=1`,
          { headers: { 'Accept-Language': 'pt-BR,pt;q=0.9', 'User-Agent': 'AMM-Duarte-Frota/4.0' } }
        );
        const nd = await nr.json();
        if (nd.length) {
          lat = parseFloat(nd[0].lat);
          lng = parseFloat(nd[0].lon);
          addrUsed = q;
          break;
        }
      } catch(_) {}
    }

    if (lat === null) {
      // Fallback: tenta coordenada via Open Meteo Geocoding (gratuito, sem rate-limit)
      try {
        const gm = await fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(vd.localidade)}&count=1&language=pt&format=json`);
        const gd = await gm.json();
        if (gd.results?.length) {
          lat = gd.results[0].latitude;
          lng = gd.results[0].longitude;
          addrUsed = cidade + ', Brasil';
        }
      } catch(_) {}
    }

    if (lat === null) {
      showToast('CEP encontrado (' + cidade + ') mas não foi possível obter coordenadas. Tente pelo nome.','error');
      document.getElementById('inp-'+geoId).value = queries[0];
      return;
    }

    const desc = addrUsed;
    document.getElementById('inp-'+geoId).value = desc;
    document.getElementById('cep-'+geoId).value  = '';
    geo[geoId] = { lat, lng, desc };
    setBadge(geoId, lat, lng, desc);

    const zoom = vd.logradouro ? 16 : (vd.bairro ? 14 : 12);
    if (geoId === 'origem') {
      if (mOrigin) map.removeLayer(mOrigin);
      mOrigin = L.marker([lat,lng],{icon:mkIcon('#10b981','O')}).addTo(map).bindPopup('📍 '+cidade);
    } else {
      if (mDest) map.removeLayer(mDest);
      mDest = L.marker([lat,lng],{icon:mkIcon('#f97316','D')}).addTo(map).bindPopup('🏁 '+cidade);
    }
    map.setView([lat, lng], zoom, {animate:true});
    showToast('✅ CEP encontrado: ' + cidade,'success');

  } catch(e) {
    console.error('Erro CEP:', e);
    showToast('Erro ao buscar CEP. Verifique sua conexão e tente novamente.','error');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = '🔍 Buscar CEP'; }
  }
}

// ─── Waypoints ─────────────────────────────────────────────
let wpCount=0;
function addWp() {
  const tpl = document.getElementById('tpl-wp').content.cloneNode(true);
  const row = tpl.querySelector('.wp-row');
  const inp = tpl.querySelector('.wp-inp');
  const ac  = tpl.querySelector('.wp-ac');
  const uid = 'wp'+(++wpCount);
  inp.id  = uid;
  row.id  = 'row-'+uid;
  row.dataset.lat=''; row.dataset.lng=''; row.dataset.desc='';
  inp.addEventListener('input', () => {
    clearTimeout(timers[uid]);
    if (inp.value.length < 3) { ac.style.display='none'; return; }
    timers[uid] = setTimeout(() => nominatimSearch(inp.value, ac, uid, true), 500);
    // Pass wp context
    ac._wpRow = row;
  });
  // Override onclick para waypoints
  ac.addEventListener('click', e => {
    const el = e.target.closest('.ac-item'); if(!el) return;
    row.dataset.lat  = el.dataset.lat;
    row.dataset.lng  = el.dataset.lng;
    row.dataset.desc = el.dataset.desc;
    inp.value = el.dataset.desc;
    row.querySelector('.wp-badge-txt').textContent = el.dataset.desc.split(',').slice(0,2).join(',');
    row.querySelector('.wp-badge').classList.add('ok');
    ac.style.display='none';
  });
  document.getElementById('wp-list').appendChild(tpl);
}
function removeWp(btn) { btn.closest('.wp-row').remove(); }

// ─── Veículo ───────────────────────────────────────────────
function atualizarVeiculo() {
  const opt = document.getElementById('sel-veiculo').selectedOptions[0];
  if (!opt || !opt.value) return;
  const tanque = parseFloat(opt.dataset.tanque||0);
  const km     = parseFloat(opt.dataset.km||0);
  if (km)     document.getElementById('inp-km-saida').value = km;
  if (tanque) { document.getElementById('inp-litros-saida').value = tanque; updateFuelBar(tanque,tanque); }
}
function syncFuel() {
  const opt = document.getElementById('sel-veiculo').selectedOptions[0];
  const tanque = parseFloat(opt?.dataset.tanque||0);
  updateFuelBar(parseFloat(document.getElementById('inp-litros-saida').value)||0, tanque);
}
function updateFuelBar(l,t) {
  if(!t) return;
  const p=Math.min(100,Math.round(l/t*100));
  document.getElementById('fuel-bar').style.width=p+'%';
  document.getElementById('tanque-pct').textContent=p+'%';
  document.getElementById('fuel-bar-wrap').className='fuel-bar-wrap '+(p>60?'fuel-level-high':p>25?'fuel-level-mid':'fuel-level-low');
}

// ─── Cálculo de Pedágio Realista (Brasil) ──────────────────
// Modelo baseado em dados reais: ~1 praça a cada 50-80 km em rodovias pedagiadas.
// Tarifa média por axle: caminhão = ~R$ 22-45 por praça.
// Cobertura: ~65% das rodovias federais têm pedágio.
function calcularPedagio(distKm, origemLat, origemLng, destinoLat, destinoLng) {
  // Taxa base por km (média nacional) — ajustada para caminhão (eixo 3+)
  const TAXA_BASE_KM = 0.12; // R$/km médio para rodovias pedagiadas
  // Fator de cobertura: nem todas as estradas têm pedágio
  const FATOR_COBERTURA = 0.60;
  // Fator por região (estados mais caros: SP, PR, MG, RJ; mais baratos: N/NE)
  let fatorRegiao = 1.0;

  // Detectar região pelo centro da rota
  const midLat = (origemLat + destinoLat) / 2;
  const midLng = (origemLng + destinoLng) / 2;

  // Sul/Sudeste (SP, PR, SC, RS, RJ, MG) — pedágios mais caros
  if (midLat < -19 && midLng > -53) fatorRegiao = 1.35;
  // Centro-Oeste
  else if (midLat < -10 && midLng < -45) fatorRegiao = 0.95;
  // Norte/Nordeste — menos rodovias pedagiadas
  else if (midLat > -10) fatorRegiao = 0.55;
  // Demais
  else fatorRegiao = 1.0;

  const custoPed = distKm * TAXA_BASE_KM * FATOR_COBERTURA * fatorRegiao;

  // Arredondar para múltiplo de R$ 5 (simula praças discretas)
  const pracas = Math.round(distKm / 65); // ~1 praça a cada 65 km
  const tarifaPraca = (custoPed / Math.max(pracas, 1));
  return Math.round(custoPed / 5) * 5; // arredonda para R$ 5
}

// ─── Calcular Rota (OSRM — navegador chama direto) ─────────
async function calcularRota() {
  if (!geo.origem) { showToast('Selecione a ORIGEM clicando em uma sugestão ou usando o CEP.','error'); return; }
  if (!geo.destino){ showToast('Selecione o DESTINO clicando em uma sugestão ou usando o CEP.','error'); return; }

  const wpsRows = [...document.querySelectorAll('.wp-row')].filter(r=>r.dataset.lat);
  const coords  = [
    `${geo.origem.lng},${geo.origem.lat}`,
    ...wpsRows.map(r=>`${r.dataset.lng},${r.dataset.lat}`),
    `${geo.destino.lng},${geo.destino.lat}`
  ].join(';');

  const btn = document.getElementById('btn-calcular');
  btn.disabled=true; btn.textContent='Calculando…';

  try {
    const r   = await fetch(`https://router.project-osrm.org/route/v1/driving/${coords}?overview=full&geometries=geojson&steps=true`);
    const d   = await r.json();
    if (d.code !== 'Ok') { showToast('Não foi possível calcular a rota. Tente endereços mais precisos.','error'); return; }

    const rota    = d.routes[0];
    const distKm  = rota.distance / 1000;
    const durS    = rota.duration;
    const points  = rota.geometry.coordinates.map(c=>[c[1],c[0]]);

    const opt     = document.getElementById('sel-veiculo').selectedOptions[0];
    const consumo = parseFloat(opt?.dataset.consumo||CONSUMO_PAD);
    const preco   = parseFloat(document.getElementById('inp-preco-diesel').value||PRECO_DIESEL);
    const litros  = distKm / consumo;
    const custoComb = litros * preco;
    const custoPed  = calcularPedagio(distKm, geo.origem.lat, geo.origem.lng, geo.destino.lat, geo.destino.lng);

    // Montar itinerário detalhado a partir dos steps do OSRM
    const itinerario = [];
    (rota.legs || []).forEach(leg => {
      (leg.steps || []).forEach(step => {
        const man = step.maneuver || {};
        const instrucao = osrmManeuvra(man.type, man.modifier, step.name);
        if (instrucao) {
          itinerario.push({
            instrucao,
            distancia_m: Math.round(step.distance),
            duracao_s:   Math.round(step.duration),
            nome_via:    step.name || '',
            tipo:        man.type || '',
          });
        }
      });
    });

    routeData = {
      distKm, durS, points, itinerario,
      origem: geo.origem, destino: geo.destino,
      wps: wpsRows.map(r=>({lat:parseFloat(r.dataset.lat),lng:parseFloat(r.dataset.lng),desc:r.dataset.desc})),
      litros, custoComb, custoPed,
    };

    // Exibir itinerário no painel
    renderItinerario(itinerario, distKm, durS);

    // Desenhar no mapa
    if (routeLayer) map.removeLayer(routeLayer);
    mWps.forEach(m=>map.removeLayer(m)); mWps=[];

    routeLayer = L.polyline(points,{color:'#f97316',weight:5,opacity:.9}).addTo(map);
    if (!mOrigin) mOrigin = L.marker([geo.origem.lat,geo.origem.lng],{icon:mkIcon('#10b981','O')}).addTo(map);
    if (!mDest)   mDest   = L.marker([geo.destino.lat,geo.destino.lng],{icon:mkIcon('#f97316','D')}).addTo(map);
    wpsRows.forEach(r=>{
      mWps.push(L.marker([parseFloat(r.dataset.lat),parseFloat(r.dataset.lng)],
        {icon:mkIcon('#3b82f6','P')}).addTo(map));
    });
    map.fitBounds(routeLayer.getBounds(),{padding:[40,40]});

    // Preencher resumo
    document.getElementById('rs-dist').textContent    = distKm.toLocaleString('pt-BR',{maximumFractionDigits:1})+' km';
    document.getElementById('rs-dur').textContent     = fmtDur(durS);
    document.getElementById('rs-comb').textContent    = litros.toFixed(1)+' L';
    document.getElementById('rs-pedagio').textContent = 'R$ '+custoPed.toFixed(2).replace('.',',');
    document.getElementById('rs-val-comb').textContent  = 'R$ '+custoComb.toFixed(2).replace('.',',');
    document.getElementById('rs-val-ped').textContent   = 'R$ '+custoPed.toFixed(2).replace('.',',');
    document.getElementById('rs-val-total').textContent = 'R$ '+(custoComb+custoPed).toFixed(2).replace('.',',');
    document.getElementById('route-summary').classList.add('visible');
    showToast('✅ Rota calculada com sucesso!','success');
  } catch(e) {
    showToast('Erro de conexão com OSRM. Verifique internet.','error');
    console.error(e);
  } finally {
    btn.disabled=false; btn.textContent='🔍 Calcular Rota';
  }
}

function fmtDur(s){ const h=Math.floor(s/3600),m=Math.floor((s%3600)/60); return (h>0?h+'h ':'')+m+'min'; }

// ─── Traduzir manobras OSRM ────────────────────────────────
function osrmManeuvra(tipo, mod, nome) {
  const viaLabel = nome ? ` em ${nome}` : '';
  const mods = {
    'left':'esquerda','right':'direita','sharp left':'esquerda acentuada',
    'sharp right':'direita acentuada','slight left':'levemente à esquerda',
    'slight right':'levemente à direita','straight':'em frente','uturn':'retorno'
  };
  const modLabel = mods[mod] || '';
  switch(tipo) {
    case 'depart':     return `🚀 Saia${viaLabel}`;
    case 'arrive':     return `🏁 Chegada ao destino${viaLabel}`;
    case 'turn':       return `↩️ Vire à ${modLabel}${viaLabel}`;
    case 'new name':   return `➡️ Continue${viaLabel}`;
    case 'continue':   return `⬆️ Continue${viaLabel}`;
    case 'merge':      return `🔀 Incorpore à via${viaLabel}`;
    case 'on ramp':    return `⬆️ Pegue a rampa${viaLabel}`;
    case 'off ramp':   return `⬇️ Saia pela rampa${viaLabel}`;
    case 'fork':       return `🍴 Na bifurcação, mantenha-se à ${modLabel}${viaLabel}`;
    case 'end of road':return `🔚 No fim da via, vire à ${modLabel}${viaLabel}`;
    case 'roundabout': return `🔄 Entre na rotatória${viaLabel}`;
    case 'rotary':     return `🔄 Entre na rotatória${viaLabel}`;
    default:           return tipo ? `➡️ ${tipo}${viaLabel}` : null;
  }
}

// ─── Renderizar itinerário ──────────────────────────────────
function renderItinerario(itinerario, distKm, durS) {
  let box = document.getElementById('itinerario-box');
  if (!box) {
    // Criar box após o route-summary
    const summary = document.getElementById('route-summary');
    box = document.createElement('div');
    box.id = 'itinerario-box';
    box.className = 'card';
    box.style.cssText = 'margin-top:0';
    summary.after(box);
  }
  const total = itinerario.length;
  const html = `
    <div class="card-header" style="margin-bottom:10px">
      <h2><span class="icon">🗺️</span>Itinerário Detalhado <span style="font-size:.75rem;color:var(--text-sm);font-weight:400">(${total} etapas)</span></h2>
    </div>
    <div style="max-height:360px;overflow-y:auto;display:flex;flex-direction:column;gap:2px">
      ${itinerario.map((s,i) => {
        const distStr = s.distancia_m >= 1000
          ? (s.distancia_m/1000).toFixed(1)+' km'
          : s.distancia_m+' m';
        return `<div style="display:flex;align-items:flex-start;gap:8px;padding:7px 8px;border-radius:6px;background:${i%2===0?'var(--bg-card2)':'transparent'};font-size:.81rem">
          <span style="color:var(--brand);font-weight:700;min-width:22px;flex-shrink:0">${i+1}</span>
          <span style="flex:1">${s.instrucao}</span>
          <span style="color:var(--text-sm);white-space:nowrap;flex-shrink:0">${distStr}</span>
        </div>`;
      }).join('')}
    </div>`;
  box.innerHTML = html;
  box.style.display = 'block';
}

// ─── Salvar Viagem ─────────────────────────────────────────
async function salvarViagem(btn) {
  if (!routeData) { showToast('Calcule a rota primeiro.','error'); return; }
  const vid = document.getElementById('sel-veiculo').value;
  const mid = document.getElementById('sel-motorista').value;
  if (!vid)  { showToast('Selecione um veículo.','error'); return; }
  if (!mid)  { showToast('Selecione um motorista.','error'); return; }

  const btnEl = btn || document.getElementById('btn-salvar');
  const originalText = btnEl.textContent;
  btnEl.disabled = true;
  btnEl.textContent = '⏳ Salvando…';

  try {
    const res = await fetch(`${APP_URL}/api/salvar_viagem.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({
        veiculo_id:   parseInt(vid),
        motorista_id: parseInt(mid),
        origem_desc:  routeData.origem.desc,
        origem_lat:   routeData.origem.lat,
        origem_lng:   routeData.origem.lng,
        destino_desc: routeData.destino.desc,
        destino_lat:  routeData.destino.lat,
        destino_lng:  routeData.destino.lng,
        waypoints:    routeData.wps || [],
        distancia_km: routeData.distKm,
        duracao_s:    routeData.durS,
        polyline_points: routeData.points || [],
        valor_frete:  parseFloat(document.getElementById('inp-frete')?.value || 0),
        km_saida:     parseFloat(document.getElementById('inp-km-saida')?.value || 0),
        litros_saida: parseFloat(document.getElementById('inp-litros-saida')?.value || 0),
        custo_combustivel_est: routeData.custoComb || 0,
        custo_pedagio_est:     routeData.custoPed  || 0,
        itinerario: routeData.itinerario || [],
      })
    });

    if (!res.ok && res.status !== 422) {
      throw new Error(`Servidor retornou HTTP ${res.status}`);
    }

    const data = await res.json();

    if (data.sucesso) {
      window._viagemId = data.viagem_id;
      showToast(`✅ Viagem #${data.viagem_id} salva com sucesso!`, 'success');
      btnEl.textContent = '✅ Salvo!';
      btnEl.style.background = 'var(--success, #10b981)';
      setTimeout(() => {
        btnEl.textContent = originalText;
        btnEl.style.background = '';
        btnEl.disabled = false;
      }, 3000);
      return;
    } else {
      showToast('Erro ao salvar: ' + (data.erro || 'Erro desconhecido'), 'error');
    }

  } catch (e) {
    console.error('[salvarViagem]', e);
    showToast('Falha de conexão ao salvar viagem. Verifique o servidor.', 'error');
  }

  btnEl.disabled = false;
  btnEl.textContent = originalText;
}

// ─── WhatsApp ──────────────────────────────────────────────
function enviarWhatsApp() {
  if (!routeData) { showToast('Calcule a rota primeiro.','error'); return; }
  const opt = document.getElementById('sel-motorista').selectedOptions[0];
  const tel = opt?.dataset.tel?.replace(/\D/g,'');
  if (!tel) { showToast('Motorista sem telefone cadastrado.','error'); return; }
  const osmUrl = `https://www.openstreetmap.org/directions?engine=osrm_car&route=${routeData.origem.lat},${routeData.origem.lng};${routeData.destino.lat},${routeData.destino.lng}`;
  const msg = encodeURIComponent(
    `🚛 *AMM Duarte — Nova Rota*\n\n📍 *Origem:* ${routeData.origem.desc.split(',').slice(0,2).join(',')}\n`+
    `🏁 *Destino:* ${routeData.destino.desc.split(',').slice(0,2).join(',')}\n`+
    `📏 *Distância:* ${routeData.distKm.toFixed(1)} km\n`+
    `⏱ *Tempo est.:* ${fmtDur(routeData.durS)}\n\n`+
    `🗺 Ver rota: ${osmUrl}`
  );
  window.open(`https://wa.me/55${tel}?text=${msg}`,'_blank');
}
</script>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
