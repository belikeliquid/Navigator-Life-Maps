<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="nmm-timeline" class="nmm-app">

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
      <span class="nmm-logo-text">NAVIGATOR TIMELINE</span>
    </div>
    <span class="nmm-sep"></span>
    <div class="nmm-tl-date-wrap">
      <label style="font-size:10px;color:rgba(255,255,255,0.6);letter-spacing:0.06em;text-transform:uppercase">From</label>
      <input type="date" id="nmm-tl-start" class="nmm-tl-date-input" />
    </div>
    <div class="nmm-tl-date-wrap">
      <label style="font-size:10px;color:rgba(255,255,255,0.6);letter-spacing:0.06em;text-transform:uppercase">To</label>
      <input type="date" id="nmm-tl-end" class="nmm-tl-date-input" />
    </div>
    <button class="nmm-btn nmm-btn-primary" id="nmm-tl-apply-btn">Apply</button>
    <span class="nmm-sep"></span>
    <div class="nmm-kb-filter-wrap" id="nmm-tl-filter-wrap">
      <button class="nmm-btn" id="nmm-tl-filter-btn">⊞ All maps ▾</button>
      <div class="nmm-kb-filter-panel" id="nmm-tl-filter-panel" style="display:none">
        <div class="nmm-kb-filter-actions">
          <button onclick="nmmTlSelectAll()">Select all</button>
          <button onclick="nmmTlSelectNone()">None</button>
        </div>
        <div id="nmm-tl-filter-list"></div>
      </div>
    </div>
    <span style="flex:1"></span>
    <a href="<?php echo home_url('/maps/'); ?>" class="nmm-btn nmm-btn-nav">← Mind Maps</a>
  </div>

  <!-- Timeline canvas -->
  <div class="nmm-tl-body" id="nmm-tl-body">
    <div id="nmm-tl-loading" class="nmm-loading"><div class="nmm-spinner"></div><span>Loading your maps...</span></div>
    <div id="nmm-tl-canvas-wrap" class="nmm-tl-canvas-wrap" style="display:none">
      <div id="nmm-tl-canvas" class="nmm-tl-canvas">
        <!-- Date markers -->
        <div id="nmm-tl-markers" class="nmm-tl-markers"></div>
        <!-- Spine -->
        <div id="nmm-tl-spine" class="nmm-tl-spine"></div>
        <!-- Nodes -->
        <div id="nmm-tl-nodes" class="nmm-tl-nodes"></div>
      </div>
    </div>
    <div id="nmm-tl-empty" style="display:none;text-align:center;padding:80px 20px;font-family:Barlow Condensed,sans-serif;color:#aaa">
      <div style="font-size:48px;margin-bottom:16px">⬡</div>
      <p style="font-size:18px;font-weight:600;color:#888">No nodes to display</p>
      <p style="font-size:14px">Set a date range and select maps to see your timeline.</p>
    </div>
  </div>

  <!-- Edit Node Modal -->
  <div class="nmm-modal-overlay" id="nmm-tl-edit-modal">
    <div class="nmm-modal">
      <h3>Edit Node</h3>
      <div class="nmm-field"><label>Title</label><input type="text" id="nmm-tl-title" class="nmm-input" /></div>
      <div class="nmm-field"><label>Subtitle lines (one per line)</label><textarea id="nmm-tl-sub" rows="2" class="nmm-input"></textarea></div>
      <div class="nmm-field"><label>Date</label><input type="date" id="nmm-tl-date" class="nmm-input" /></div>
      <div class="nmm-field"><label>Kanban tag</label>
        <select id="nmm-tl-tag" class="nmm-input">
          <option value="">No tag (Triage)</option>
          <option value="start">Start</option>
          <option value="doing">Doing</option>
          <option value="sprint">Sprint</option>
          <option value="review">Review</option>
          <option value="done">Done</option>
        </select>
      </div>
      <div class="nmm-modal-actions">
        <button class="nmm-btn" onclick="nmmTlCloseEdit()">Cancel</button>
        <button class="nmm-btn nmm-btn-primary" onclick="nmmTlSaveEdit()">Save</button>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
const REST  = NMM.restUrl;
let nonce   = NMM.nonce;
let allMaps = [], allNodes = [], selectedMapIds = [];
let tlStart = null, tlEnd = null;
let editNode = null, editMapId = null;

const KANBAN_ORDER = { done:0, review:1, sprint:2, doing:3, start:4, '':5 };
const TAG_COLORS = {
  start:  { bg:'#B5D4F4', text:'#0C447C' },
  doing:  { bg:'#FAC775', text:'#633806' },
  sprint: { bg:'#EF9F27', text:'#412402' },
  review: { bg:'#F4C0D1', text:'#4B1528' },
  done:   { bg:'#9FE1CB', text:'#04342C' },
};

const NODE_W = 180;
const NODE_H = 80;
const SPINE_Y = 340;
const TL_PAD_L = 80;
const TL_PAD_R = 80;

setInterval(function(){ fetch(REST+'nonce').then(function(r){return r.json();}).then(function(d){if(d.nonce)nonce=d.nonce;}); }, 600000);

function api(method, endpoint, data) {
  return fetch(REST+endpoint,{method,headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:data?JSON.stringify(data):undefined}).then(function(r){return r.json();});
}

// Set default date range — today to 30 days out
var today = new Date();
var future = new Date(); future.setDate(future.getDate() + 30);
document.getElementById('nmm-tl-start').value = today.toISOString().split('T')[0];
document.getElementById('nmm-tl-end').value   = future.toISOString().split('T')[0];
tlStart = today;
tlEnd   = future;

async function loadAll() {
  const maps = await api('GET','maps');
  allMaps = maps || [];
  selectedMapIds = allMaps.map(function(m){ return m.id; });

  // Build filter list
  const list = document.getElementById('nmm-tl-filter-list');
  allMaps.forEach(function(m) {
    const row = document.createElement('label');
    row.className = 'nmm-kb-filter-row';
    row.innerHTML = '<input type="checkbox" data-map-id="'+m.id+'" checked /> ' + m.title;
    row.querySelector('input').addEventListener('change', function() {
      if (this.checked) { if(!selectedMapIds.includes(m.id)) selectedMapIds.push(m.id); }
      else { selectedMapIds = selectedMapIds.filter(function(x){ return x!==m.id; }); }
      updateFilterBtn();
      renderTimeline();
    });
    list.appendChild(row);
  });

  // Load all nodes
  allNodes = [];
  for (const map of allMaps) {
    const full = await api('GET','maps/'+map.id);
    if (full && full.nodes) {
      full.nodes.forEach(function(n) {
        n._map_id = map.id; n._map_title = map.title; n._map_color = map.color || '#0BBFBF';
        allNodes.push(n);
      });
    }
  }

  document.getElementById('nmm-tl-loading').style.display = 'none';
  renderTimeline();
}

function updateFilterBtn() {
  const btn = document.getElementById('nmm-tl-filter-btn');
  if (selectedMapIds.length === allMaps.length) btn.textContent = '⊞ All maps ▾';
  else if (selectedMapIds.length === 0) btn.textContent = '⊞ No maps ▾';
  else btn.textContent = '⊞ ' + selectedMapIds.length + ' map' + (selectedMapIds.length>1?'s':'') + ' ▾';
}

window.nmmTlSelectAll = function() {
  selectedMapIds = allMaps.map(function(m){ return m.id; });
  document.querySelectorAll('#nmm-tl-filter-list input').forEach(function(cb){ cb.checked=true; });
  updateFilterBtn(); renderTimeline();
};
window.nmmTlSelectNone = function() {
  selectedMapIds = [];
  document.querySelectorAll('#nmm-tl-filter-list input').forEach(function(cb){ cb.checked=false; });
  updateFilterBtn(); renderTimeline();
};

document.getElementById('nmm-tl-filter-btn').addEventListener('click', function(e) {
  e.stopPropagation();
  const panel = document.getElementById('nmm-tl-filter-panel');
  if (panel.style.display === 'none') {
    const rect = this.getBoundingClientRect();
    panel.style.position = 'fixed';
    panel.style.top  = rect.bottom + 4 + 'px';
    panel.style.left = rect.left + 'px';
    panel.style.display = 'block';
  } else {
    panel.style.display = 'none';
  }
});
document.addEventListener('click', function() {
  document.getElementById('nmm-tl-filter-panel').style.display = 'none';
});

document.getElementById('nmm-tl-apply-btn').addEventListener('click', function() {
  const s = document.getElementById('nmm-tl-start').value;
  const e = document.getElementById('nmm-tl-end').value;
  if (!s || !e) { alert('Please set both a start and end date.'); return; }
  tlStart = new Date(s);
  tlEnd   = new Date(e);
  if (tlStart >= tlEnd) { alert('End date must be after start date.'); return; }
  renderTimeline();
});

function dateToX(dateStr, canvasW) {
  if (!dateStr) return null;
  const d    = new Date(dateStr);
  const span = tlEnd - tlStart;
  const pos  = (d - tlStart) / span;
  return TL_PAD_L + pos * (canvasW - TL_PAD_L - TL_PAD_R);
}

function renderTimeline() {
  const wrap    = document.getElementById('nmm-tl-canvas-wrap');
  const empty   = document.getElementById('nmm-tl-empty');
  const canvas  = document.getElementById('nmm-tl-canvas');
  const markers = document.getElementById('nmm-tl-markers');
  const spine   = document.getElementById('nmm-tl-spine');
  const nodesEl = document.getElementById('nmm-tl-nodes');

  markers.innerHTML = ''; spine.innerHTML = ''; nodesEl.innerHTML = '';

  // Filter nodes
  var nodes = allNodes.filter(function(n) {
    return selectedMapIds.includes(n._map_id);
  });

  if (!nodes.length) { wrap.style.display='none'; empty.style.display='block'; return; }
  wrap.style.display='block'; empty.style.display='none';

  // Canvas width based on viewport
  var canvasW = Math.max(window.innerWidth - 40, 1200);
  canvas.style.width = canvasW + 'px';

  // Sort by date then kanban
  nodes.sort(function(a,b) {
    var ka = KANBAN_ORDER[a.tag||''] ?? 5;
    var kb = KANBAN_ORDER[b.tag||''] ?? 5;
    if (a.date && b.date) return a.date.localeCompare(b.date) || ka - kb;
    if (a.date) return -1;
    if (b.date) return 1;
    return ka - kb;
  });

  // Assign X positions
  var dated   = nodes.filter(function(n){ return n.date; });
  var undated = nodes.filter(function(n){ return !n.date; });
  var span    = tlEnd - tlStart;

  // Evenly distribute undated nodes
  undated.forEach(function(n, i) {
    var frac = undated.length > 1 ? (i+1) / (undated.length+1) : 0.5;
    var d = new Date(tlStart.getTime() + frac * span);
    n._assigned_date = d.toISOString().split('T')[0];
    n._x = TL_PAD_L + frac * (canvasW - TL_PAD_L - TL_PAD_R);
  });
  dated.forEach(function(n) {
    n._assigned_date = n.date;
    n._x = dateToX(n.date, canvasW) || TL_PAD_L;
    // Clamp to canvas
    n._x = Math.max(TL_PAD_L, Math.min(canvasW - TL_PAD_R - NODE_W, n._x));
  });

  // Assign Y — alternate above/below spine, group by proximity
  nodes.forEach(function(n, i) {
    n._above = (i % 2 === 0);
    n._y = n._above ? SPINE_Y - NODE_H - 60 - (Math.floor(i/2) % 2) * 40
                    : SPINE_Y + 60 + (Math.floor(i/2) % 2) * 40;
  });

  // Canvas height
  var maxY = Math.max(...nodes.map(function(n){ return n._y + NODE_H; }), SPINE_Y + 80);
  var minY = Math.min(...nodes.map(function(n){ return n._y; }), SPINE_Y - 80);
  canvas.style.height = (maxY - minY + 80) + 'px';
  var yOffset = Math.max(0, -minY + 40);
  canvas.style.paddingTop = yOffset + 'px';

  // Draw spine
  spine.style.cssText = 'position:absolute;top:'+(SPINE_Y+yOffset)+'px;left:'+TL_PAD_L+'px;right:'+TL_PAD_R+'px;height:2px;background:var(--nmm-teal);opacity:0.4;';

  // Draw date markers
  var markerCount = 6;
  for (var i=0; i<=markerCount; i++) {
    var frac = i / markerCount;
    var d    = new Date(tlStart.getTime() + frac * span);
    var x    = TL_PAD_L + frac * (canvasW - TL_PAD_L - TL_PAD_R);
    var mk   = document.createElement('div');
    mk.style.cssText = 'position:absolute;left:'+x+'px;top:'+(SPINE_Y+yOffset-8)+'px;transform:translateX(-50%);';
    mk.innerHTML = '<div style="width:1px;height:16px;background:rgba(11,191,191,0.3);margin:0 auto"></div>' +
      '<div style="font-size:9px;font-family:Barlow Condensed,sans-serif;color:#aaa;white-space:nowrap;text-align:center;margin-top:2px">'+
      d.toLocaleDateString('en-US',{month:'short',day:'numeric'})+'</div>';
    markers.appendChild(mk);
  }

  // Draw nodes
  nodes.forEach(function(n) {
    var tag     = n.tag || '';
    var tagColor = TAG_COLORS[tag];
    var tagBadge = tagColor ? '<span style="display:inline-block;background:'+tagColor.bg+';color:'+tagColor.text+';font-size:8px;font-weight:700;padding:1px 6px;border-radius:3px;letter-spacing:0.05em;text-transform:uppercase;margin-top:3px">'+tag+'</span>' : '';
    var dateBadge = n.date ? '<div style="font-size:9px;color:var(--nmm-teal);margin-top:2px;font-weight:600">📅 '+n.date+'</div>' : '';

    var el = document.createElement('div');
    el.className = 'nmm-tl-node';
    el.style.cssText = 'position:absolute;left:'+n._x+'px;top:'+(n._y+yOffset)+'px;width:'+NODE_W+'px;min-height:'+NODE_H+'px;';
    el.style.borderColor = n._map_color;
    el.innerHTML =
      '<div style="font-size:11px;font-weight:600;font-family:Montserrat,sans-serif;color:#111;line-height:1.3">'+n.title+'</div>'+
      (n.subs&&n.subs.length ? '<div style="font-size:9px;color:#777;margin-top:2px;font-family:Montserrat,sans-serif">'+n.subs.slice(0,2).join(' · ')+'</div>' : '')+
      dateBadge + tagBadge +
      '<div style="font-size:8px;color:#bbb;margin-top:3px">'+n._map_title+'</div>';

    // Connector line to spine
    // (drawn via border-left on the node)
    var connEl = document.createElement('div');
    var nodeCenter = n._x + NODE_W/2;
    var nodeBottom = n._above ? n._y + yOffset + NODE_H : n._y + yOffset;
    var spineTop = SPINE_Y + yOffset;
    var connH = Math.abs(spineTop - nodeBottom);
    connEl.style.cssText = 'position:absolute;left:'+(nodeCenter)+'px;top:'+Math.min(nodeBottom,spineTop)+'px;width:1px;height:'+connH+'px;background:rgba(11,191,191,0.35);border-left:1px dashed rgba(11,191,191,0.5);';
    nodesEl.appendChild(connEl);

    // Drag to reschedule
    el.addEventListener('mousedown', function(e) {
      if (e.button !== 0) return;
      var startX = e.clientX;
      var origX  = n._x;
      function onMove(ev) {
        var dx   = ev.clientX - startX;
        var newX = Math.max(TL_PAD_L, Math.min(canvasW - TL_PAD_R - NODE_W, origX + dx));
        var frac = (newX - TL_PAD_L) / (canvasW - TL_PAD_L - TL_PAD_R);
        var newDate = new Date(tlStart.getTime() + frac * span);
        n._x = newX;
        n._assigned_date = newDate.toISOString().split('T')[0];
        el.style.left = newX + 'px';
        connEl.style.left = (newX + NODE_W/2) + 'px';
      }
      function onUp() {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        // Save new date back to map
        n.date = n._assigned_date;
        saveNodeDate(n._map_id, n.id, n._assigned_date);
        // Update date badge
        el.querySelector('[data-date]') && (el.querySelector('[data-date]').textContent = '📅 ' + n._assigned_date);
      }
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
      e.preventDefault();
    });

    // Click to edit
    el.addEventListener('click', function(e) {
      if (e.detail > 1) return; // ignore drag-end clicks
      openEdit(n);
    });

    nodesEl.appendChild(el);
  });
}

async function saveNodeDate(mapId, nodeId, newDate) {
  const map = await api('GET','maps/'+mapId);
  if (!map||!map.nodes) return;
  const node = map.nodes.find(function(n){ return n.id===nodeId; });
  if (node) node.date = newDate;
  await api('PUT','maps/'+mapId,{nodes:map.nodes,title:map.title,settings:map.settings||{},islands:map.islands||[]});
}

function openEdit(n) {
  editNode  = n;
  editMapId = n._map_id;
  document.getElementById('nmm-tl-title').value = n.title||'';
  document.getElementById('nmm-tl-sub').value   = (n.subs||[]).join('\n');
  document.getElementById('nmm-tl-date').value  = n.date||'';
  document.getElementById('nmm-tl-tag').value   = n.tag||'';
  document.getElementById('nmm-tl-edit-modal').classList.add('open');
}

window.nmmTlCloseEdit = function() {
  document.getElementById('nmm-tl-edit-modal').classList.remove('open');
  editNode = null;
};

window.nmmTlSaveEdit = async function() {
  if (!editNode) return;
  var btn = document.querySelector('#nmm-tl-edit-modal .nmm-btn-primary');
  btn.textContent='Saving...'; btn.disabled=true;
  editNode.title = document.getElementById('nmm-tl-title').value.trim() || editNode.title;
  editNode.subs  = document.getElementById('nmm-tl-sub').value.trim().split('\n').filter(function(s){return s.trim();});
  editNode.date  = document.getElementById('nmm-tl-date').value || '';
  editNode.tag   = document.getElementById('nmm-tl-tag').value || '';
  const map = await api('GET','maps/'+editMapId);
  if (map&&map.nodes) {
    const idx = map.nodes.findIndex(function(x){ return x.id===editNode.id; });
    if (idx>-1) Object.assign(map.nodes[idx],{title:editNode.title,subs:editNode.subs,date:editNode.date,tag:editNode.tag});
    await api('PUT','maps/'+editMapId,{nodes:map.nodes,title:map.title,settings:map.settings||{},islands:map.islands||[]});
  }
  btn.textContent='Save'; btn.disabled=false;
  nmmTlCloseEdit();
  renderTimeline();
};

document.getElementById('nmm-tl-edit-modal').addEventListener('click', function(e){ if(e.target===this) nmmTlCloseEdit(); });
document.addEventListener('keydown', function(e){ if(e.key==='Escape') nmmTlCloseEdit(); });

loadAll();
})();
</script>
