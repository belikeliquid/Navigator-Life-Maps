<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="nmm-kanban" class="nmm-app">

  <!-- Header toolbar — matches editor style exactly -->
  <div class="nmm-editor-toolbar">
    <div class="nmm-logo">
      <svg width="22" height="22" viewBox="0 0 28 28" fill="none">
        <circle cx="14" cy="14" r="12" stroke="#0BBFBF" stroke-width="1.5"/>
        <circle cx="14" cy="14" r="3" fill="#0BBFBF"/>
        <line x1="14" y1="2" x2="14" y2="8" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="14" y1="20" x2="14" y2="26" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="2" y1="14" x2="8" y2="14" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="20" y1="14" x2="26" y2="14" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
      </svg>
      <span class="nmm-logo-text">NAVIGATOR KANBAN</span>
    </div>
    <span class="nmm-sep"></span>
    <!-- Multi-select map filter -->
    <div class="nmm-kb-filter-wrap" id="nmm-kb-filter-wrap">
      <button class="nmm-btn" id="nmm-kb-filter-btn">⊞ All maps ▾</button>
      <div class="nmm-kb-filter-panel" id="nmm-kb-filter-panel" style="display:none">
        <div class="nmm-kb-filter-actions">
          <button onclick="nmmKbSelectAll()">Select all</button>
          <button onclick="nmmKbSelectNone()">None</button>
        </div>
        <div id="nmm-kb-filter-list"></div>
      </div>
    </div>
    <span style="flex:1"></span>
    <a href="<?php echo home_url('/maps/'); ?>" class="nmm-btn nmm-btn-nav">← Mind Maps</a>
  </div>

  <!-- Kanban body -->
  <div class="nmm-kb-body">
    <div id="nmm-kb-loading" class="nmm-loading"><div class="nmm-spinner"></div><span>Loading your maps...</span></div>

    <!-- Triage row -->
    <div class="nmm-kb-triage-wrap" id="nmm-kb-triage-wrap" style="display:none">
      <div class="nmm-kb-triage-header">
        <span class="nmm-kb-triage-label">⬡ Triage</span>
        <span class="nmm-kb-triage-sub">Nodes without a tag</span>
      </div>
      <div id="nmm-kb-triage" class="nmm-kb-triage-cards" data-tag=""></div>
    </div>

    <!-- 5 Kanban columns -->
    <div class="nmm-kb-columns" id="nmm-kb-columns" style="display:none">
      <div class="nmm-kb-col" data-tag="start">
        <div class="nmm-kb-col-header nmm-kb-start">Start</div>
        <div class="nmm-kb-col-cards" data-tag="start"></div>
      </div>
      <div class="nmm-kb-col" data-tag="doing">
        <div class="nmm-kb-col-header nmm-kb-doing">Doing</div>
        <div class="nmm-kb-col-cards" data-tag="doing"></div>
      </div>
      <div class="nmm-kb-col" data-tag="sprint">
        <div class="nmm-kb-col-header nmm-kb-sprint">Sprint</div>
        <div class="nmm-kb-col-cards" data-tag="sprint"></div>
      </div>
      <div class="nmm-kb-col" data-tag="review">
        <div class="nmm-kb-col-header nmm-kb-review">Review</div>
        <div class="nmm-kb-col-cards" data-tag="review"></div>
      </div>
      <div class="nmm-kb-col" data-tag="done">
        <div class="nmm-kb-col-header nmm-kb-done">Done</div>
        <div class="nmm-kb-col-cards" data-tag="done"></div>
      </div>
    </div>

    <!-- Empty state -->
    <div id="nmm-kb-empty" style="display:none;text-align:center;padding:80px 20px;font-family:Barlow Condensed,sans-serif;color:#aaa">
      <div style="font-size:48px;margin-bottom:16px">⬡</div>
      <p style="font-size:18px;font-weight:600;color:#888">No nodes yet</p>
      <p style="font-size:14px">Open a mind map, add tags to your nodes, and they will appear here.</p>
      <a href="<?php echo home_url('/maps/'); ?>" class="nmm-btn nmm-btn-primary" style="margin-top:16px;display:inline-block">← Go to Mind Maps</a>
    </div>
  </div>

  <!-- Node edit modal -->
  <div class="nmm-modal-overlay" id="nmm-kb-edit-modal">
    <div class="nmm-modal">
      <h3>Edit Node</h3>
      <div class="nmm-field"><label>Title</label><input type="text" id="nmm-kb-title" class="nmm-input" /></div>
      <div class="nmm-field"><label>Subtitle lines (one per line)</label><textarea id="nmm-kb-sub" rows="2" class="nmm-input"></textarea></div>
      <div class="nmm-field"><label>Date</label><input type="date" id="nmm-kb-date" class="nmm-input" /></div>
      <div class="nmm-field"><label>Kanban tag</label>
        <select id="nmm-kb-tag" class="nmm-input">
          <option value="">No tag (Triage)</option>
          <option value="start">🔵 Start</option>
          <option value="doing">🟡 Doing</option>
          <option value="sprint">🟠 Sprint</option>
          <option value="review">🩷 Review</option>
          <option value="done">🟢 Done</option>
        </select>
      </div>
      <div class="nmm-field"><label>Tree of Life position</label>
        <select id="nmm-kb-sefirot" class="nmm-input">
          <option value="">None</option>
          <option value="kether">Kether — Crown · Vision</option>
          <option value="chochma">Chochma — Wisdom · Insight</option>
          <option value="binah">Binah — Understanding · Structure</option>
          <option value="chesed">Chesed — Love · Mercy</option>
          <option value="geburah">Geburah — Strength · Discipline</option>
          <option value="tiphereth">Tiphereth — Beauty · Center · Self</option>
          <option value="netzach">Netzach — Victory · Goals</option>
          <option value="hod">Hod — Splendour · Communication</option>
          <option value="yesod">Yesod — Foundation · Practice</option>
          <option value="malkuth">Malkuth — Kingdom · Material</option>
        </select>
      </div>
      <div class="nmm-modal-actions">
        <button class="nmm-btn" onclick="nmmKbCloseEdit()">Cancel</button>
        <button class="nmm-btn nmm-btn-primary" onclick="nmmKbSaveEdit()">Save</button>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
const REST  = NMM.restUrl;
let nonce   = NMM.nonce;
let allMaps = [], allNodes = [], draggingCard = null;
let selectedMapIds = []; // empty = all maps
let kbEditNode = null;

setInterval(function(){ fetch(REST+'nonce').then(function(r){return r.json();}).then(function(d){if(d.nonce)nonce=d.nonce;}); }, 600000);

function api(method, endpoint, data) {
  return fetch(REST+endpoint,{method,headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:data?JSON.stringify(data):undefined}).then(function(r){return r.json();});
}

const TAG_COLORS = {
  start:  {bg:'#B5D4F4',text:'#0C447C',label:'Start'},
  doing:  {bg:'#FAC775',text:'#633806',label:'Doing'},
  sprint: {bg:'#EF9F27',text:'#412402',label:'Sprint'},
  review: {bg:'#F4C0D1',text:'#4B1528',label:'Review'},
  done:   {bg:'#9FE1CB',text:'#04342C',label:'Done'},
};

async function loadAll() {
  const maps = await api('GET','maps');
  allMaps = maps || [];
  selectedMapIds = allMaps.map(function(m){ return m.id; }); // all selected by default

  // Build filter checkbox list
  const list = document.getElementById('nmm-kb-filter-list');
  allMaps.forEach(function(m) {
    const row = document.createElement('label');
    row.className = 'nmm-kb-filter-row';
    row.innerHTML = '<input type="checkbox" data-map-id="'+m.id+'" checked /> ' + m.title;
    row.querySelector('input').addEventListener('change', function() {
      if (this.checked) {
        if (!selectedMapIds.includes(m.id)) selectedMapIds.push(m.id);
      } else {
        selectedMapIds = selectedMapIds.filter(function(x){ return x!==m.id; });
      }
      updateFilterBtn();
      renderBoard();
    });
    list.appendChild(row);
  });

  // Load all nodes
  allNodes = [];
  for (const map of allMaps) {
    const full = await api('GET','maps/'+map.id);
    if (full && full.nodes) {
      full.nodes.forEach(function(n) {
        if (n._readonly) return; // skip linked islands
        n._map_id = map.id; n._map_title = map.title;
        allNodes.push(n);
      });
    }
  }

  document.getElementById('nmm-kb-loading').style.display = 'none';
  renderBoard();
}

function updateFilterBtn() {
  const btn = document.getElementById('nmm-kb-filter-btn');
  if (selectedMapIds.length === allMaps.length) {
    btn.textContent = '⊞ All maps ▾';
  } else if (selectedMapIds.length === 0) {
    btn.textContent = '⊞ No maps ▾';
  } else {
    btn.textContent = '⊞ ' + selectedMapIds.length + ' map' + (selectedMapIds.length>1?'s':'') + ' ▾';
  }
}

window.nmmKbSelectAll = function() {
  selectedMapIds = allMaps.map(function(m){ return m.id; });
  document.querySelectorAll('#nmm-kb-filter-list input').forEach(function(cb){ cb.checked=true; });
  updateFilterBtn(); renderBoard();
};
window.nmmKbSelectNone = function() {
  selectedMapIds = [];
  document.querySelectorAll('#nmm-kb-filter-list input').forEach(function(cb){ cb.checked=false; });
  updateFilterBtn(); renderBoard();
};

// Toggle filter panel
document.getElementById('nmm-kb-filter-btn').addEventListener('click', function(e) {
  e.stopPropagation();
  const panel = document.getElementById('nmm-kb-filter-panel');
  panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
});
document.addEventListener('click', function() {
  document.getElementById('nmm-kb-filter-panel').style.display = 'none';
});

function renderBoard() {
  const nodes = allNodes.filter(function(n){ return selectedMapIds.includes(n._map_id); });
  const triage  = document.getElementById('nmm-kb-triage');
  const triageWrap = document.getElementById('nmm-kb-triage-wrap');
  const columns = document.getElementById('nmm-kb-columns');
  const empty   = document.getElementById('nmm-kb-empty');

  triage.innerHTML = '';
  document.querySelectorAll('.nmm-kb-col-cards').forEach(function(c){ c.innerHTML=''; });

  if (!nodes.length) {
    triageWrap.style.display = 'none'; columns.style.display = 'none'; empty.style.display = 'block'; return;
  }
  empty.style.display = 'none'; triageWrap.style.display = 'block'; columns.style.display = 'grid';

  nodes.forEach(function(n) {
    const card = makeCard(n);
    const tag  = (n.tag||'').toLowerCase();
    const zone = tag ? document.querySelector('.nmm-kb-col-cards[data-tag="'+tag+'"]') : null;
    if (zone) zone.appendChild(card);
    else triage.appendChild(card);
  });

  setupDragDrop();
}

function makeCard(n) {
  const card = document.createElement('div');
  card.className = 'nmm-kb-card';
  card.dataset.nodeId = n.id; card.dataset.mapId = n._map_id; card.dataset.tag = n.tag||'';
  card.draggable = true;

  const tag      = n.tag ? TAG_COLORS[n.tag] : null;
  const tagBadge = tag ? '<span class="nmm-kb-badge" style="background:'+tag.bg+';color:'+tag.text+'">'+tag.label+'</span>' : '';
  const sefirot  = n.sefirot ? '<span class="nmm-kb-sefirot-badge">✦ '+n.sefirot+'</span>' : '';
  const dateStr  = n.date ? '<div class="nmm-kb-date">📅 '+n.date+'</div>' : '';
  const subs     = (n.subs&&n.subs.length) ? '<div class="nmm-kb-subs">'+n.subs.slice(0,2).join(' · ')+'</div>' : '';

  card.innerHTML =
    '<div class="nmm-kb-card-header"><span class="nmm-kb-card-title">'+n.title+'</span>'+tagBadge+sefirot+'</div>'+
    subs+dateStr+
    '<div class="nmm-kb-map-label">'+n._map_title+'</div>';

  // Right-click to edit
  card.addEventListener('contextmenu', function(e) { e.preventDefault(); openKbEdit(n.id, n._map_id); });

  // Long-press for mobile
  let lpTimer = null;
  card.addEventListener('touchstart', function(){ lpTimer=setTimeout(function(){ openKbEdit(n.id,n._map_id); },500); },{passive:true});
  card.addEventListener('touchend',   function(){ clearTimeout(lpTimer); });
  card.addEventListener('touchmove',  function(){ clearTimeout(lpTimer); });

  return card;
}

function setupDragDrop() {
  document.querySelectorAll('.nmm-kb-card').forEach(function(card) {
    card.addEventListener('dragstart', function(e) {
      draggingCard = card; card.style.opacity='0.5'; e.dataTransfer.effectAllowed='move';
    });
    card.addEventListener('dragend', function() {
      card.style.opacity='1'; draggingCard=null;
      document.querySelectorAll('.nmm-kb-col-cards,.nmm-kb-triage-cards').forEach(function(z){ z.classList.remove('nmm-kb-over'); });
    });
  });

  document.querySelectorAll('.nmm-kb-col-cards,.nmm-kb-triage-cards').forEach(function(zone) {
    zone.addEventListener('dragover', function(e){ e.preventDefault(); zone.classList.add('nmm-kb-over'); });
    zone.addEventListener('dragleave', function(){ zone.classList.remove('nmm-kb-over'); });
    zone.addEventListener('drop', function(e) {
      e.preventDefault(); zone.classList.remove('nmm-kb-over');
      if (!draggingCard) return;
      const newTag  = zone.dataset.tag || '';
      const nodeId  = draggingCard.dataset.nodeId;
      const mapId   = draggingCard.dataset.mapId;
      zone.appendChild(draggingCard);
      draggingCard.dataset.tag = newTag;
      const n = allNodes.find(function(x){ return x.id===nodeId&&String(x._map_id)===String(mapId); });
      if (n) {
        n.tag = newTag;
        // Flash save feedback
        draggingCard.style.outline = '2px solid #0BBFBF';
        setTimeout(function(){ if(draggingCard) draggingCard.style.outline=''; },1200);
        saveNodeTag(mapId, nodeId, newTag);
      }
    });
  });
}

async function saveNodeTag(mapId, nodeId, newTag) {
  const map = await api('GET','maps/'+mapId);
  if (!map||!map.nodes) return;
  const node = map.nodes.find(function(n){ return n.id===nodeId; });
  if (node) node.tag = newTag;
  await api('PUT','maps/'+mapId,{nodes:map.nodes,title:map.title,settings:map.settings||{},islands:map.islands||[]});
}

// Edit modal
function openKbEdit(nodeId, mapId) {
  const n = allNodes.find(function(x){ return x.id===nodeId&&String(x._map_id)===String(mapId); });
  if (!n) return;
  kbEditNode = n;
  document.getElementById('nmm-kb-title').value   = n.title||'';
  document.getElementById('nmm-kb-sub').value     = (n.subs||[]).join('\n');
  document.getElementById('nmm-kb-date').value    = n.date||'';
  document.getElementById('nmm-kb-tag').value     = n.tag||'';
  document.getElementById('nmm-kb-sefirot').value = n.sefirot||'';
  document.getElementById('nmm-kb-edit-modal').classList.add('open');
}

window.nmmKbCloseEdit = function() {
  document.getElementById('nmm-kb-edit-modal').classList.remove('open'); kbEditNode=null;
};

window.nmmKbSaveEdit = async function() {
  if (!kbEditNode) return;
  const btn = document.querySelector('#nmm-kb-edit-modal .nmm-btn-primary');
  btn.textContent='Saving...'; btn.disabled=true;
  kbEditNode.title   = document.getElementById('nmm-kb-title').value.trim()||kbEditNode.title;
  kbEditNode.subs    = document.getElementById('nmm-kb-sub').value.trim().split('\n').filter(function(s){return s.trim();});
  kbEditNode.date    = document.getElementById('nmm-kb-date').value||'';
  kbEditNode.tag     = document.getElementById('nmm-kb-tag').value||'';
  kbEditNode.sefirot = document.getElementById('nmm-kb-sefirot').value||'';
  const mapId = kbEditNode._map_id;
  const map   = await api('GET','maps/'+mapId);
  if (map&&map.nodes) {
    const idx = map.nodes.findIndex(function(x){ return x.id===kbEditNode.id; });
    if (idx>-1) Object.assign(map.nodes[idx], {title:kbEditNode.title,subs:kbEditNode.subs,date:kbEditNode.date,tag:kbEditNode.tag,sefirot:kbEditNode.sefirot});
    await api('PUT','maps/'+mapId,{nodes:map.nodes,title:map.title,settings:map.settings||{},islands:map.islands||[]});
  }
  btn.textContent='Save'; btn.disabled=false;
  nmmKbCloseEdit();
  renderBoard();
};

document.getElementById('nmm-kb-edit-modal').addEventListener('click', function(e){ if(e.target===this) nmmKbCloseEdit(); });
document.addEventListener('keydown', function(e){ if(e.key==='Escape') nmmKbCloseEdit(); });

loadAll();
})();
</script>
