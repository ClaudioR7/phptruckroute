<?php require_once __DIR__ . '/includes/config.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Teste APIs Gratuitas</title>
<style>
body{font-family:monospace;background:#0d1117;color:#c9d1d9;padding:24px;margin:0}
h2{color:#f97316;margin-bottom:4px}
.card{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:16px;margin-bottom:16px}
.ok{color:#3fb950}.err{color:#f85149}.warn{color:#d29922}
button{background:#f97316;color:#fff;border:none;padding:8px 18px;border-radius:6px;cursor:pointer;font-size:14px;margin-right:8px;margin-top:8px}
button:hover{opacity:.9}
pre{white-space:pre-wrap;word-break:break-all;font-size:12px;margin:0}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:700px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<h2>🆓 Teste das APIs Gratuitas — AMM Duarte v4</h2>
<p style="color:#8b949e;margin-bottom:20px">Todos os testes são feitos direto do seu navegador. Sem necessidade de API Key.</p>

<div class="grid">

<div class="card">
  <h3 style="color:#58a6ff;margin:0 0 8px">1. Nominatim — Autocomplete</h3>
  <p style="color:#8b949e;font-size:13px;margin:0 0 8px">OpenStreetMap · Busca endereços</p>
  <input id="q-nom" type="text" value="Av. Paulista, São Paulo" style="width:100%;background:#0d1117;border:1px solid #30363d;color:#c9d1d9;padding:6px 10px;border-radius:4px;font-size:13px">
  <button onclick="testarNominatim()">Testar</button>
  <pre id="res-nom" style="margin-top:10px;color:#8b949e">Aguardando…</pre>
</div>

<div class="card">
  <h3 style="color:#58a6ff;margin:0 0 8px">2. ViaCEP — Busca por CEP</h3>
  <p style="color:#8b949e;font-size:13px;margin:0 0 8px">IBGE · CEP → endereço completo</p>
  <input id="q-cep" type="text" value="01310-100" style="width:100%;background:#0d1117;border:1px solid #30363d;color:#c9d1d9;padding:6px 10px;border-radius:4px;font-size:13px">
  <button onclick="testarCep()">Testar</button>
  <pre id="res-cep" style="margin-top:10px;color:#8b949e">Aguardando…</pre>
</div>

<div class="card">
  <h3 style="color:#58a6ff;margin:0 0 8px">3. OSRM — Cálculo de Rota</h3>
  <p style="color:#8b949e;font-size:13px;margin:0 0 8px">Project OSRM · SP → RJ por estrada</p>
  <button onclick="testarOSRM()">Testar SP → RJ</button>
  <pre id="res-osrm" style="margin-top:10px;color:#8b949e">Aguardando…</pre>
</div>

<div class="card">
  <h3 style="color:#58a6ff;margin:0 0 8px">4. Resumo</h3>
  <p style="color:#8b949e;font-size:13px;margin:0 0 8px">Status de cada serviço</p>
  <button onclick="testarTodos()">▶ Testar Todos</button>
  <pre id="res-all" style="margin-top:10px;color:#8b949e">Clique em "Testar Todos"</pre>
</div>

</div>

<div style="margin-top:8px">
  <a href="<?= APP_URL ?>" style="color:#f97316">← Voltar ao sistema</a>
</div>

<script>
async function testarNominatim() {
  const q = document.getElementById('q-nom').value;
  const el = document.getElementById('res-nom');
  el.textContent = 'Buscando…';
  try {
    const r = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(q+', Brasil')}&format=jsonv2&limit=3&countrycodes=br`,
      {headers:{'Accept-Language':'pt-BR'}});
    const d = await r.json();
    if (!d.length) { el.innerHTML='<span class="warn">Nenhum resultado encontrado.</span>'; return; }
    el.innerHTML = '<span class="ok">✅ '+d.length+' resultado(s):</span>\n' +
      d.map(x=>`  📍 ${x.display_name.substring(0,60)}…\n     lat: ${x.lat}, lng: ${x.lon}`).join('\n');
  } catch(e) { el.innerHTML=`<span class="err">❌ Erro: ${e.message}</span>`; }
}

async function testarCep() {
  const cep = document.getElementById('q-cep').value.replace(/\D/g,'');
  const el  = document.getElementById('res-cep');
  el.textContent = 'Buscando…';
  try {
    const r = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
    const d = await r.json();
    if (d.erro) { el.innerHTML='<span class="err">❌ CEP não encontrado.</span>'; return; }
    el.innerHTML = `<span class="ok">✅ CEP encontrado:</span>\n  Logradouro: ${d.logradouro||'—'}\n  Bairro: ${d.bairro||'—'}\n  Cidade: ${d.localidade}/${d.uf}\n  IBGE: ${d.ibge}`;
  } catch(e) { el.innerHTML=`<span class="err">❌ Erro: ${e.message}</span>`; }
}

async function testarOSRM() {
  const el = document.getElementById('res-osrm');
  el.textContent = 'Calculando rota SP → RJ…';
  try {
    // SP: -23.5505,-46.6333  RJ: -22.9068,-43.1729
    const r = await fetch('https://router.project-osrm.org/route/v1/driving/-46.6333,-23.5505;-43.1729,-22.9068?overview=false');
    const d = await r.json();
    if (d.code !== 'Ok') { el.innerHTML=`<span class="err">❌ ${d.code}</span>`; return; }
    const rota = d.routes[0];
    const km   = (rota.distance/1000).toFixed(1);
    const h    = Math.floor(rota.duration/3600), m = Math.floor((rota.duration%3600)/60);
    el.innerHTML = `<span class="ok">✅ Rota calculada:</span>\n  Distância: ${km} km\n  Duração:   ${h}h ${m}min\n  Pernas:    ${rota.legs.length}`;
  } catch(e) { el.innerHTML=`<span class="err">❌ Erro: ${e.message}</span>`; }
}

async function testarTodos() {
  const el = document.getElementById('res-all');
  el.textContent = 'Testando todos…';
  const results = [];

  try { const r=await fetch('https://nominatim.openstreetmap.org/search?q=Sao+Paulo,+Brasil&format=jsonv2&limit=1&countrycodes=br',{headers:{'Accept-Language':'pt-BR'}}); const d=await r.json(); results.push(d.length?'✅ Nominatim OK':'⚠️ Nominatim sem resultados'); } catch(e){ results.push('❌ Nominatim ERRO: '+e.message); }
  try { const r=await fetch('https://viacep.com.br/ws/01310100/json/'); const d=await r.json(); results.push(d.localidade?'✅ ViaCEP OK ('+d.localidade+')':'⚠️ ViaCEP sem dados'); } catch(e){ results.push('❌ ViaCEP ERRO: '+e.message); }
  try { const r=await fetch('https://router.project-osrm.org/route/v1/driving/-46.6333,-23.5505;-43.1729,-22.9068?overview=false'); const d=await r.json(); results.push(d.code==='Ok'?'✅ OSRM OK ('+(d.routes[0].distance/1000).toFixed(0)+' km)':'⚠️ OSRM código: '+d.code); } catch(e){ results.push('❌ OSRM ERRO: '+e.message); }

  el.innerHTML = results.map((r,i)=>`${['Nominatim','ViaCEP','OSRM'][i]}: <span class="${r.startsWith('✅')?'ok':r.startsWith('⚠')?'warn':'err'}">${r}</span>`).join('\n');
}
</script>
</body>
</html>
