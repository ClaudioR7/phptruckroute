<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
exigir_login();
$usuario = usuario_atual();
$initials = strtoupper(substr($usuario['nome'], 0, 1));
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Count active trips for badge
try {
    $pdo = get_db();
    $em_andamento = $pdo->query("SELECT COUNT(*) FROM viagens WHERE status='em_andamento'")->fetchColumn();
} catch(Exception $e) { $em_andamento = 0; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">🚛</div>
    <h2>AMM Duarte</h2>
    <span>Sistema de Frota v3.0</span>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>

    <?php if (is_admin()): ?>
    <a class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>" href="<?= APP_URL ?>/pages/dashboard.php">
      <span class="icon">📊</span> Dashboard
    </a>
    <?php endif; ?>

    <?php if (is_admin()): ?>
    <a class="nav-item <?= $currentPage==='mapa_rotas'?'active':'' ?>" href="<?= APP_URL ?>/pages/mapa_rotas.php">
      <span class="icon">🗺️</span> Nova Rota
    </a>
    <?php endif; ?>

    <a class="nav-item <?= $currentPage==='rotas'?'active':'' ?>" href="<?= APP_URL ?>/pages/rotas.php">
      <span class="icon">🛣️</span> <?= is_admin() ? 'Viagens' : 'Minhas Viagens' ?>
      <?php if($em_andamento>0): ?>
      <span class="badge-count"><?= $em_andamento ?></span>
      <?php endif; ?>
    </a>

    <a class="nav-item <?= $currentPage==='monitoramento_frota'?'active':'' ?>" href="<?= APP_URL ?>/pages/monitoramento_frota.php">
      <span class="icon">📍</span> Rastreamento
      <?php if($em_andamento>0): ?><span class="live-dot" style="margin-left:auto;font-size:.68rem"></span><?php endif; ?>
    </a>

    <a class="nav-item <?= $currentPage==='combustivel'?'active':'' ?>" href="<?= APP_URL ?>/pages/combustivel.php">
      <span class="icon">⛽</span> Combustível
    </a>

    <?php if (is_admin()): ?>
    <div class="nav-section-title">Administração</div>

    <a class="nav-item <?= $currentPage==='admin_dashboard'?'active':'' ?>" href="<?= APP_URL ?>/pages/admin_dashboard.php">
      <span class="icon">⚙️</span> Painel Admin
    </a>

    <a class="nav-item <?= $currentPage==='usuarios'?'active':'' ?>" href="<?= APP_URL ?>/pages/usuarios.php">
      <span class="icon">👥</span> Usuários
    </a>

    <a class="nav-item <?= $currentPage==='veiculos'?'active':'' ?>" href="<?= APP_URL ?>/pages/veiculos.php">
      <span class="icon">🚚</span> Veículos
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= $initials ?></div>
      <div class="user-info">
        <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
        <span><?= $usuario['perfil'] === 'admin' ? 'Administrador' : 'Motorista' ?></span>
      </div>
      <a href="<?= APP_URL ?>/api/logout.php" class="btn-logout-sm" title="Sair">✕</a>
    </div>
  </div>

  <!-- Gemini Assistant Button -->
  <button id="btn-gemini" onclick="toggleGemini()" title="Assistente de Rotas — Tirar dúvidas com IA"
    style="margin:12px 16px 16px;width:calc(100% - 32px);background:linear-gradient(135deg,#1a73e8,#7c4dff);color:#fff;border:none;border-radius:12px;padding:12px 16px;cursor:pointer;font-size:.85rem;font-weight:700;display:flex;align-items:center;gap:10px;box-shadow:0 4px 16px rgba(124,77,255,.35);transition:opacity .2s">
    <span style="font-size:1.2rem">✨</span>
    <span>Assistente IA</span>
    <span style="margin-left:auto;font-size:.7rem;background:rgba(255,255,255,.2);padding:2px 7px;border-radius:10px">Gemini</span>
  </button>
</aside>

<!-- MAIN -->
<div class="main-wrapper">
<header class="topbar">
  <button class="btn btn-ghost btn-icon" id="sidebar-toggle" style="display:none">☰</button>
  <div class="topbar-title" id="topbar-title">AMM Duarte — Controle de Frota</div>
  <div class="topbar-actions">
    <?php if($em_andamento>0): ?>
    <span class="live-dot"><?= $em_andamento ?> em rota</span>
    <?php endif; ?>
    <span class="badge badge-<?= $usuario['perfil'] ?>"><?= ucfirst($usuario['perfil']) ?></span>
  </div>
</header>
<div class="content">

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toast-container"></div>

<script>
function showToast(msg, type='info') {
  const tc = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  const icons = {success:'✅',error:'❌',info:'ℹ️'};
  t.innerHTML = `<span>${icons[type]||'ℹ️'}</span><span>${msg}</span>`;
  tc.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}
</script>

<!-- ═══ GEMINI ASSISTANT MODAL ═══ -->
<div id="gemini-overlay" onclick="if(event.target===this)toggleGemini()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9998;backdrop-filter:blur(4px)"></div>
<div id="gemini-panel" style="position:fixed;bottom:0;right:0;width:min(420px,100vw);height:min(600px,100vh);background:var(--bg-card);border-radius:20px 0 0 0;box-shadow:-4px -4px 40px rgba(0,0,0,.5);z-index:9999;flex-direction:column;overflow:hidden">
  <!-- Header -->
  <div style="background:linear-gradient(135deg,#1a73e8,#7c4dff);padding:16px 18px;display:flex;align-items:center;gap:12px">
    <div style="width:38px;height:38px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem">✨</div>
    <div style="flex:1">
      <div style="color:#fff;font-weight:700;font-size:.95rem">Assistente de Rotas</div>
      <div style="color:rgba(255,255,255,.75);font-size:.75rem">Powered by Gemini · Rotas, postos, dúvidas</div>
    </div>
    <button onclick="toggleGemini()" style="background:none;border:none;color:rgba(255,255,255,.8);font-size:1.3rem;cursor:pointer;padding:0 4px">✕</button>
  </div>
  <!-- Suggestions -->
  <div id="gem-suggestions" style="padding:12px 14px;display:flex;gap:6px;flex-wrap:wrap;border-bottom:1px solid var(--border)">
    <button class="gem-chip" onclick="gemSend(this.textContent)">🛣️ Melhor rota SP→RJ</button>
    <button class="gem-chip" onclick="gemSend(this.textContent)">⛽ Postos na BR-116</button>
    <button class="gem-chip" onclick="gemSend(this.textContent)">🚦 Restrições de horário</button>
    <button class="gem-chip" onclick="gemSend(this.textContent)">🅿️ Pontos de parada BR-101</button>
  </div>
  <!-- Messages -->
  <div id="gem-messages" style="flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px">
    <div class="gem-msg gem-bot">
      <span>👋 Olá! Sou seu assistente de rotas. Posso ajudar com:<br>
      • Dúvidas sobre trajetos e rodovias<br>
      • Postos de combustível no caminho<br>
      • Restrições de trânsito para caminhões<br>
      • Pontos de parada e descanso<br><br>
      <em>Como posso ajudar hoje?</em></span>
    </div>
  </div>
  <!-- Input -->
  <div style="padding:12px 14px;border-top:1px solid var(--border);display:flex;gap:8px">
    <input id="gem-input" type="text" placeholder="Pergunte sobre rotas ou postos…"
      style="flex:1;background:var(--bg-card2);border:1px solid var(--border);border-radius:10px;padding:10px 14px;color:var(--text);font-size:.85rem;outline:none"
      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();gemSend()}">
    <button onclick="gemSend()" id="gem-send"
      style="background:linear-gradient(135deg,#1a73e8,#7c4dff);border:none;border-radius:10px;width:42px;height:42px;color:#fff;font-size:1.1rem;cursor:pointer;flex-shrink:0">➤</button>
  </div>
</div>

<style>
.gem-chip{background:var(--bg-card2);border:1px solid var(--border);color:var(--text-sm);border-radius:20px;padding:5px 11px;font-size:.75rem;cursor:pointer;white-space:nowrap;transition:background .15s}
.gem-chip:hover{background:var(--brand);color:#fff;border-color:var(--brand)}
.gem-msg{max-width:88%;padding:10px 14px;border-radius:14px;font-size:.83rem;line-height:1.55}
.gem-bot{background:var(--bg-card2);border:1px solid var(--border);color:var(--text);align-self:flex-start;border-radius:4px 14px 14px 14px}
.gem-user{background:linear-gradient(135deg,#1a73e8,#7c4dff);color:#fff;align-self:flex-end;border-radius:14px 4px 14px 14px}
.gem-typing{opacity:.6;font-style:italic}
#gemini-panel{ display:none; }
#gemini-panel.open{ display:flex; }
</style>

<script>
let gemHistory = [];

function toggleGemini() {
  const panel   = document.getElementById('gemini-panel');
  const overlay = document.getElementById('gemini-overlay');
  const isOpen  = panel.classList.contains('open');
  panel.classList.toggle('open', !isOpen);
  overlay.style.display = isOpen ? 'none' : 'block';
  if (!isOpen) setTimeout(() => document.getElementById('gem-input').focus(), 100);
}

function gemAppend(text, role) {
  const box = document.getElementById('gem-messages');
  const div = document.createElement('div');
  div.className = 'gem-msg ' + (role === 'user' ? 'gem-user' : 'gem-bot');
  div.innerHTML = text.replace(/\n/g, '<br>');
  box.appendChild(div);
  box.scrollTop = box.scrollHeight;
  return div;
}

async function gemSend(overrideText) {
  const inp = document.getElementById('gem-input');
  const msg = (overrideText || inp.value).trim();
  if (!msg) return;

  document.getElementById('gem-suggestions').style.display = 'none';
  inp.value = '';
  gemAppend(msg, 'user');
  gemHistory.push({ role: 'user', content: msg });

  const typing = gemAppend('✨ Pensando…', 'bot');
  typing.classList.add('gem-typing');
  document.getElementById('gem-send').disabled = true;

  try {
    const res = await fetch('<?= APP_URL ?>/api/assistente_ia.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ messages: gemHistory })
    });
    const data = await res.json();
    if (data.erro) throw new Error(data.erro);
    const reply = data.resposta || 'Sem resposta.';
    gemHistory.push({ role: 'assistant', content: reply });
    typing.innerHTML = reply.replace(/\n/g,'<br>');
    typing.classList.remove('gem-typing');
  } catch(e) {
    typing.innerHTML = '❌ ' + (e.message || 'Erro de conexão. Tente novamente.');
    typing.classList.remove('gem-typing');
    // Remove última msg do histórico em caso de erro
    gemHistory.pop();
  } finally {
    document.getElementById('gem-send').disabled = false;
    document.getElementById('gem-messages').scrollTop = 9999;
  }
}
</script>
