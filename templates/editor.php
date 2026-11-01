<?php if ( ! defined( 'ABSPATH' ) ) exit;
$map_id = isset($_GET['map_id']) ? intval($_GET['map_id']) : intval($map_id ?? 0);
?>
<div id="nmm-editor" class="nmm-app" data-map-id="<?php echo $map_id; ?>">

  <div id="nmm-editor-toolbar" class="nmm-editor-toolbar">
    <a href="<?php echo home_url('/maps/'); ?>" class="nmm-btn nmm-btn-nav">← Maps</a>
    <a href="<?php echo home_url('/kanban/'); ?>" class="nmm-btn nmm-btn-kanban">⬡ Kanban</a>
    <a href="<?php echo home_url('/timeline/'); ?>" class="nmm-btn nmm-btn-kanban">◷ Timeline</a>
    <span class="nmm-sep"></span>
    <span id="nmm-map-title-display" class="nmm-map-title-display" title="Click to rename"></span>
    <span class="nmm-sep"></span>
    <button class="nmm-btn nmm-btn-harmony" onclick="nmmHarmony()">✦ Harmony</button>
    <button class="nmm-btn" id="nmm-undo-btn" onclick="nmmUndo()" title="Undo (Ctrl+Z)" disabled>↩ Undo</button>
    <span class="nmm-sep"></span>
    <button class="nmm-btn nmm-btn-primary" onclick="nmmAddRoot()">+ Island</button>
    <button class="nmm-btn" onclick="autoCenter()" title="Re-center map">⊙ Center</button>
    <button class="nmm-btn" onclick="nmmExpandAll()">Expand all</button>
    <button class="nmm-btn" onclick="nmmCollapseAll()">Collapse</button>
    <span class="nmm-sep"></span>
    <button class="nmm-btn" onclick="nmmOpenSettings()">Settings</button>
    <button class="nmm-btn" onclick="nmmOpenOutlineImport()">📋 Import outline</button>
    <button class="nmm-btn" onclick="nmmOpenGCalModal()">→ GCal</button>
    <button class="nmm-btn" onclick="nmmExportPNG()">Export PNG</button>
    <button class="nmm-btn nmm-btn-save" id="nmm-save-btn" onclick="nmmSave()">Save</button>
    <span id="nmm-save-status" class="nmm-save-status"></span>
    <button class="nmm-btn nmm-mobile-menu-btn" id="nmm-mobile-menu-btn" onclick="nmmToggleMobileMenu()">☰</button>
  </div>

  <div id="nmm-mobile-menu" class="nmm-mobile-menu">
    <button onclick="nmmHarmony();nmmToggleMobileMenu()">✦ Harmony</button>
    <button onclick="nmmUndo();nmmToggleMobileMenu()">↩ Undo</button>
    <button onclick="nmmAddRoot();nmmToggleMobileMenu()">+ New Island</button>
    <button onclick="autoCenter();nmmToggleMobileMenu()">⊙ Re-center</button>
    <button onclick="nmmExpandAll();nmmToggleMobileMenu()">Expand all</button>
    <button onclick="nmmCollapseAll();nmmToggleMobileMenu()">Collapse all</button>
    <button onclick="nmmOpenSettings();nmmToggleMobileMenu()">Settings</button>
    <button onclick="nmmOpenOutlineImport();nmmToggleMobileMenu()">📋 Import</button>
    <button onclick="nmmOpenGCalModal();nmmToggleMobileMenu()">→ GCal</button>
    <button onclick="nmmExportPNG();nmmToggleMobileMenu()">Export PNG</button>
    <button onclick="nmmSave();nmmToggleMobileMenu()">Save</button>
  </div>

  <div id="nmm-canvas-wrap" class="nmm-canvas-wrap">
    <div id="nmm-bg-layer" class="nmm-bg-layer"></div>
    <div id="nmm-bg-overlay" class="nmm-bg-overlay"></div>
    <div id="nmm-canvas" class="nmm-canvas">
      <svg id="nmm-lines"></svg>
      <div id="nmm-motif" class="nmm-motif"></div>
    </div>
  </div>

  <!-- Node Edit Modal -->
  <div class="nmm-modal-overlay" id="nmm-node-modal">
    <div class="nmm-modal">
      <h3>Edit Node</h3>
      <div class="nmm-field"><label>Title</label><input type="text" id="nme-title" class="nmm-input" /></div>
      <div class="nmm-field"><label>Subtitle lines (one per line)</label><textarea id="nme-sub" rows="3" class="nmm-input"></textarea></div>
      <div class="nmm-field"><label>Date (Google Calendar compatible)</label><input type="date" id="nme-date" class="nmm-input" /></div>
      <div class="nmm-field">
        <label>Parent node</label>
        <select id="nme-parent" class="nmm-input"></select>
      </div>
      <div class="nmm-field">
        <label>Kanban tag</label>
        <select id="nme-tag" class="nmm-input">
          <option value="">No tag (Triage)</option>
          <option value="start">🔵 Start</option>
          <option value="doing">🟡 Doing</option>
          <option value="sprint">🟠 Sprint</option>
          <option value="review">🩷 Review</option>
          <option value="done">🟢 Done</option>
        </select>
      </div>
      <div class="nmm-field">
        <label>Tree of Life position (Sefirot)</label>
        <select id="nme-sefirot" class="nmm-input">
          <option value="">None</option>
          <option value="kether">Kether — Crown · Vision · Will</option>
          <option value="chochma">Chochma — Wisdom · Insight · Seed</option>
          <option value="binah">Binah — Understanding · Structure · Matrix</option>
          <option value="chesed">Chesed — Love · Mercy · Expansion</option>
          <option value="geburah">Geburah — Strength · Discipline · Boundaries</option>
          <option value="tiphereth">Tiphereth — Beauty · Center · Heart · Self</option>
          <option value="netzach">Netzach — Victory · Desire · Goals · Creativity</option>
          <option value="hod">Hod — Splendour · Communication · Expression</option>
          <option value="yesod">Yesod — Foundation · Daily Practice · Rhythm</option>
          <option value="malkuth">Malkuth — Kingdom · Material · Grounded · Reality</option>
        </select>
      </div>
      <div class="nmm-modal-actions">
        <button class="nmm-btn" onclick="nmmCloseNodeModal()">Cancel</button>
        <button class="nmm-btn nmm-btn-island" onclick="nmmMakeIsland()">↗ New Island</button>
        <button class="nmm-btn nmm-btn-danger" onclick="nmmDeleteEditing()">Delete</button>
        <button class="nmm-btn nmm-btn-primary" onclick="nmmSaveNode()">Save</button>
      </div>
    </div>
  </div>

  <!-- Settings Modal -->
  <div class="nmm-modal-overlay" id="nmm-settings-modal">
    <div class="nmm-modal">
      <h3>Map Settings</h3>
      <div class="nmm-field"><label>Map Title</label><input type="text" id="nms-title" class="nmm-input" /></div>
      <div class="nmm-field"><label>Category</label>
        <select id="nms-category" class="nmm-input">
          <option value="">No category</option>
          <option value="personal">Personal</option>
          <option value="client">Client</option>
          <option value="project">Project</option>
          <option value="team">Team</option>
        </select>
      </div>
      <div class="nmm-field"><label>Motif (always shown)</label><input type="text" id="nms-motif" class="nmm-input" /></div>
      <div class="nmm-field"><label>Random quotes (comma separated)</label><textarea id="nms-quotes" rows="2" class="nmm-input"></textarea></div>
      <div class="nmm-modal-actions">
        <button class="nmm-btn" onclick="nmmCloseSettings()">Cancel</button>
        <button class="nmm-btn nmm-btn-primary" onclick="nmmSaveSettings()">Save Settings</button>
      </div>
    </div>
  </div>

  <!-- Link Island Modal -->
  <div class="nmm-modal-overlay" id="nmm-island-modal">
    <div class="nmm-modal">
      <h3>Link a Shared Island</h3>
      <p style="font-size:12px;color:#666;margin-bottom:12px;">Linked islands are read-only here. Edit them in their home map.</p>
      <div id="nmm-island-list" class="nmm-island-list"></div>
      <div class="nmm-modal-actions"><button class="nmm-btn" onclick="nmmCloseIslandModal()">Close</button></div>
    </div>
  </div>

  <!-- Outline Import Modal -->
  <div class="nmm-modal-overlay" id="nmm-outline-modal">
    <div class="nmm-modal" style="width:500px;max-width:95vw">
      <h3>📋 Import Outline</h3>
      <p style="font-size:12px;color:#666;margin-bottom:12px">Paste an outline from Claude or any AI. Supports indented text, numbered lists, and bullet points. We'll build the node tree automatically.</p>
      <div class="nmm-field">
        <label>Paste your outline</label>
        <textarea id="nmm-outline-text" class="nmm-input" rows="8" placeholder="Vision&#10;  Goal one&#10;    Detail A&#10;    Detail B&#10;  Goal two&#10;Foundation&#10;  Daily practice&#10;  Weekly rhythm"></textarea>
      </div>
      <div id="nmm-outline-preview" class="nmm-outline-preview" style="display:none"></div>
      <div class="nmm-modal-actions">
        <button class="nmm-btn" onclick="nmmCloseOutlineImport()">Cancel</button>
        <button class="nmm-btn" onclick="nmmPreviewOutline()">Preview</button>
        <button class="nmm-btn nmm-btn-primary" onclick="nmmBuildFromOutline()">Build Map →</button>
      </div>
    </div>
  </div>

  <!-- GCal Export Modal -->
  <div class="nmm-modal-overlay" id="nmm-gcal-modal">
    <div class="nmm-modal">
      <h3>→ Export to Google Calendar</h3>
      <p style="font-size:12px;color:#666;margin-bottom:12px">All dated nodes are selected. Uncheck any you don't want to export.</p>
      <div id="nmm-gcal-list" class="nmm-gcal-list"></div>
      <div class="nmm-modal-actions">
        <button class="nmm-btn" onclick="nmmCloseGCalModal()">Cancel</button>
        <button class="nmm-btn nmm-btn-primary" onclick="nmmDoGCalExport()">Export Selected →</button>
      </div>
    </div>
  </div>

  <div id="nmm-export-overlay" class="nmm-export-overlay">Generating PNG...</div>
</div>

<script>
(function() {

// ============================================================
// CONFIG
// ============================================================
const MAP_ID = <?php echo $map_id; ?>;
const REST   = NMM.restUrl;
let nonce    = NMM.nonce;

let nodes = [], settings = {}, mapTitle = '', linkedIslands = [];
let selectedId = null, editingId = null, dragState = null;
let quoteIdx = 0, saveTimer = null, isDirty = false;
// mapRev: server revision this editor is based on. changeSeq counts edits so a
// save only clears isDirty if nothing changed while it was in flight.
let mapRev = 0, changeSeq = 0, saving = false, saveQueued = false;
const DRAFT_KEY = 'nmm_draft_' + MAP_ID;

// Undo stack — max 10 steps
let undoStack = [];

// Plugin-level defaults (from Settings page)
var pluginDefaults = {
  bgUrl:  '<?php echo esc_js( NMM_Settings::get("default_bg_url", "") ); ?>',
  motif:  '<?php echo esc_js( NMM_Settings::get("default_motif", "In the ocean of life, you are the navigator") ); ?>',
  quotes: '<?php echo esc_js( NMM_Settings::get("default_quotes", "Navigate by stars not by fear,Roots run deep branches reach far") ); ?>'
};

// ============================================================
// NONCE REFRESH — every 10min for SG Optimizer
// ============================================================
setInterval(function() {
  fetch(REST + 'nonce').then(function(r){ return r.json(); }).then(function(d){ if(d.nonce) nonce=d.nonce; });
}, 600000);

function api(method, endpoint, data) {
  return fetch(REST + endpoint, {
    method: method,
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
    body: data ? JSON.stringify(data) : undefined
  }).then(function(r) { return r.json(); });
}

// ============================================================
// UNDO
// ============================================================
function pushUndo() {
  undoStack.push(JSON.parse(JSON.stringify(nodes)));
  if (undoStack.length > 10) undoStack.shift();
  document.getElementById('nmm-undo-btn').disabled = false;
}

window.nmmUndo = function() {
  if (!undoStack.length) return;
  nodes = undoStack.pop();
  if (!undoStack.length) document.getElementById('nmm-undo-btn').disabled = true;
  render();
  setStatus('Undone', true);
  setTimeout(function() { setStatus(''); }, 2000);
};

// ============================================================
// LOAD MAP
// ============================================================
async function loadMap() {
  if (!MAP_ID) { renderEmpty(); return; }
  const map   = await api('GET', 'maps/' + MAP_ID);
  nodes         = map.nodes    || getDefaultNodes();
  settings      = map.settings || {};
  mapTitle      = map.title    || 'Untitled';
  linkedIslands = map.islands  || [];

  const titleEl = document.getElementById('nmm-map-title-display');
  titleEl.textContent = mapTitle;
  titleEl.onclick = function() {
    const t = prompt('Rename map:', mapTitle);
    if (t && t.trim()) { mapTitle = t.trim(); this.textContent = mapTitle; markDirty(); }
  };

  // Load linked island nodes
  for (const islandId of linkedIslands) {
    const island = await api('GET', 'islands/' + islandId);
    if (island && island.nodes) {
      island.nodes.forEach(function(n) {
        n._island_id = islandId; n._island_title = island.title; n._readonly = true;
        if (!nodes.find(function(x){ return x.id===n.id; })) nodes.push(n);
      });
    }
  }
  render();
  autoCenter();
}

// A draft is kept in this browser until the server confirms the save.
// Returns true if unsaved work from a previous visit was restored.
function restoreDraft(map) {
  let draft;
  try { draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch(e) { return false; }
  if (!draft || !draft.body) return false;
  const b = draft.body;
  const same = b.title === map.title && JSON.stringify(b.nodes) === JSON.stringify(map.nodes || []) &&
               JSON.stringify(b.settings) === JSON.stringify(map.settings || {});
  if (same) { clearDraft(); return false; }
  const when = new Date(draft.at).toLocaleString();
  const msg = draft.rev === mapRev
    ? 'Unsaved changes to this map from ' + when + ' were found in this browser. Restore them?'
    : 'Unsaved changes to this map from ' + when + ' were found in this browser, but the map has been saved somewhere else since then.\n\nOK = restore your unsaved version (replaces the newer one)\nCancel = discard it and keep the saved map';
  if (!confirm(msg)) { clearDraft(); return false; }
  nodes = b.nodes; settings = b.settings || {}; mapTitle = b.title || mapTitle; linkedIslands = b.islands || [];
  return true;
}
function saveDraft(body) {
  try { localStorage.setItem(DRAFT_KEY, JSON.stringify({ rev: mapRev, at: Date.now(), body: body })); } catch(e) {}
}
function clearDraft() {
  try { localStorage.removeItem(DRAFT_KEY); } catch(e) {}
}

function getDefaultNodes() {
  return [{ id:'c', x:800, y:400, title:mapTitle||'New Map', subs:['Click to edit'], parent:null, collapsed:false, date:'' }];
}
function renderEmpty() {
  document.getElementById('nmm-canvas').innerHTML = '<div style="padding:60px;text-align:center;color:#999;font-family:Barlow Condensed,sans-serif;font-size:18px;">Map not found. <a href="<?php echo home_url('/maps/'); ?>">Return to maps</a>.</div>';
}

// ============================================================
// AUTO CENTER
// ============================================================
function autoCenter() {
  setTimeout(function() {
    var wrap = document.getElementById('nmm-canvas-wrap');
    if (!wrap || !nodes.length) return;
    var minX=Infinity, minY=Infinity, maxX=0, maxY=0;
    nodes.forEach(function(n) {
      if (!isVisible(n.id)) return;
      var el = getEl(n.id);
      minX = Math.min(minX, n.x);
      minY = Math.min(minY, n.y);
      maxX = Math.max(maxX, n.x + (el ? el.offsetWidth  : 180));
      maxY = Math.max(maxY, n.y + (el ? el.offsetHeight : 80));
    });
    if (minX === Infinity) return;
    wrap.scrollLeft = Math.max(0, (minX+maxX)/2 - wrap.clientWidth/2);
    wrap.scrollTop  = Math.max(0, (minY+maxY)/2 - wrap.clientHeight/2);
  }, 300);
}

// ============================================================
// SAVE
// ============================================================
function saveBody() {
  return { title:mapTitle, nodes:nodes.filter(function(n){return !n._readonly;}), settings:settings, islands:linkedIslands };
}
function markDirty() {
  isDirty = true; changeSeq++; setStatus('Unsaved changes');
  saveDraft(saveBody());
  clearTimeout(saveTimer);
  saveTimer = setTimeout(nmmSave, 2000);
}
function setStatus(msg, ok) {
  const el = document.getElementById('nmm-save-status');
  el.textContent = msg;
  el.style.color = ok ? '#0BBFBF' : '#EF9F27';
}
window.nmmSave = async function(isRetry) {
  if (!MAP_ID) return;
  if (saving) { saveQueued = true; return; }
  clearTimeout(saveTimer);
  saving = true;
  const seq = changeSeq;
  setStatus('Saving...');
  try {
    // On retry, always fetch a fresh nonce first — stale nonce is the most common save failure
    if (isRetry) {
      try {
        const nd = await fetch(REST + 'nonce').then(function(r){ return r.json(); });
        if (nd.nonce) nonce = nd.nonce;
      } catch(e) {}
    }
    const r = await fetch(REST + 'maps/' + MAP_ID, {
      method: 'PUT',
      headers: { 'Content-Type':'application/json', 'X-WP-Nonce':nonce },
      body: JSON.stringify(Object.assign(saveBody(), { base_rev: mapRev }))
    });
    const data = await r.json();
    saving = false;
    if (r.status === 409) { resolveConflict(data); return; }
    if (!data.id) throw new Error('bad response');
    mapRev = data.rev;
    if (seq === changeSeq) { isDirty=false; clearDraft(); setStatus('Saved ✓',true); setTimeout(function(){ if(!isDirty) setStatus(''); },3000); }
    else saveDraft(saveBody()); // edits made during the save: keep the draft current for the next save
    if (saveQueued || seq !== changeSeq) { saveQueued = false; nmmSave(); }
  } catch(e) {
    saving = false;
    setStatus('Save failed — retrying...');
    // Pass isRetry=true so next attempt fetches fresh nonce
    saveTimer = setTimeout(function(){ nmmSave(true); }, 5000);
  }
};

function resolveConflict(data) {
  const keepMine = confirm('This map was changed somewhere else (another tab, the Kanban board or the maps list) since you opened it.\n\nOK = save your version over it\nCancel = discard your unsaved changes and load the newer version');
  if (keepMine) {
    mapRev = (data.data && data.data.rev) || 0;
    nmmSave();
  } else {
    isDirty = false; clearDraft(); location.reload();
  }
}

// Leaving the page: send any unsaved changes with keepalive so the request
// survives the unload. The local draft covers the case where it doesn't arrive.
function saveOnLeave() {
  if (!isDirty || !MAP_ID || saving) return;
  try {
    fetch(REST + 'maps/' + MAP_ID, {
      method: 'PUT', keepalive: true,
      headers: { 'Content-Type':'application/json', 'X-WP-Nonce':nonce },
      body: JSON.stringify(Object.assign(saveBody(), { base_rev: mapRev }))
    });
  } catch(e) {}
}
window.addEventListener('pagehide', saveOnLeave);
// Back/forward cache: the keepalive save may have moved the revision on, so reload.
window.addEventListener('pageshow', function(e){ if (e.persisted) location.reload(); });
// Switching tabs or apps: the page is still alive, so save normally right away.
document.addEventListener('visibilitychange', function(){ if (document.visibilityState === 'hidden' && isDirty) nmmSave(); });

// ============================================================
// DEPTH + VISIBILITY
// ============================================================
function getDepth(id) {
  let d=0, n=nodes.find(function(x){return x.id===id;});
  while(n&&n.parent){d++;n=nodes.find(function(x){return x.id===n.parent;});}
  return d;
}
function isVisible(id) {
  let n=nodes.find(function(x){return x.id===id;});
  if(!n) return false; if(!n.parent) return true;
  const p=nodes.find(function(x){return x.id===n.parent;});
  if(!p||p.collapsed) return false;
  return isVisible(p.id);
}
function hasChildren(id) { return nodes.some(function(n){return n.parent===id;}); }
function getEl(id) { return document.querySelector('.nmm-node[data-id="'+id+'"]'); }

// ============================================================
// RENDER
// ============================================================
function render() {
  const canvas = document.getElementById('nmm-canvas');
  canvas.querySelectorAll('.nmm-node').forEach(function(n){n.remove();});
  nodes.forEach(function(n) {
    if (!isVisible(n.id)) return;
    const depth      = Math.min(getDepth(n.id),4);
    const isReadonly = !!n._readonly;
    const el         = document.createElement('div');
    el.className     = 'nmm-node'+(selectedId===n.id?' nmm-selected':'')+(isReadonly?' nmm-linked':'');
    el.dataset.depth = depth; el.dataset.id = n.id;
    el.style.left    = n.x+'px'; el.style.top = n.y+'px';

    const expander    = hasChildren(n.id)?'<span class="nmm-expander" data-eid="'+n.id+'">'+(n.collapsed?'+':'−')+'</span>':'';
    const subs        = (n.subs&&n.subs.length)?'<div class="nmm-node-sub">'+n.subs.map(function(s){return '<span>'+s+'</span>';}).join('')+'</div>':'';
    const dateStr     = n.date?'<div class="nmm-node-date">📅 '+n.date+'</div>':'';
    const linkedBadge = isReadonly?'<div class="nmm-linked-badge">⛓ <a href="<?php echo home_url("/map-editor/?map_id="); ?>'+n._island_id+'" target="_blank" style="color:inherit">Edit source: '+n._island_title+'</a></div>':'';
    const actions     = !isReadonly?'<div class="nmm-node-actions"><button class="nmm-na-btn" data-action="edit" data-nid="'+n.id+'">Edit</button><button class="nmm-na-btn" data-action="add" data-nid="'+n.id+'">+ Child</button><button class="nmm-na-btn nmm-na-red" data-action="del" data-nid="'+n.id+'">Delete</button></div>':'';

    el.innerHTML = '<div class="nmm-node-title-row"><span class="nmm-node-title">'+n.title+'</span>'+expander+'</div>'+subs+dateStr+linkedBadge+actions;

    el.addEventListener('mousedown', function(e) {
      const t=e.target;
      if(t.dataset.action==='edit'){nmmOpenNodeModal(n.id);return;}
      if(t.dataset.action==='add'){nmmAddChild(n.id);return;}
      if(t.dataset.action==='del'){nmmDeleteNode(n.id);return;}
      if(t.dataset.eid){nmmToggleCollapse(t.dataset.eid);return;}
      if(isReadonly) return;
      startDrag(e,n.id);
    });
    el.addEventListener('dblclick',function(){if(!dragState||!dragState.moved)nmmOpenNodeModal(n.id);});

    // Touch support
    el.addEventListener('touchstart',function(e){handleTouchStart(e,n.id,isReadonly);},{passive:false});
    el.addEventListener('touchmove', handleTouchMove, {passive:false});
    el.addEventListener('touchend',  function(e){handleTouchEnd(e,n.id);});

    canvas.appendChild(el);
  });
  drawLines(); renderMotif(); applyBackground();
}

// ============================================================
// LINES
// ============================================================
function drawLines() {
  const svg=document.getElementById('nmm-lines'); svg.innerHTML='';
  const styles=[{stroke:'#0BBFBF',width:2,dash:'5 4'},{stroke:'#0BBFBF',width:1.5,dash:'5 4'},{stroke:'#5DCAA5',width:1.5,dash:'4 4'},{stroke:'#EF9F27',width:1.5,dash:'4 4'},{stroke:'#FAC775',width:1,dash:'3 4'}];
  nodes.forEach(function(n){
    if(!n.parent||!isVisible(n.id))return;
    const parent=nodes.find(function(p){return p.id===n.parent;});
    if(!parent||parent.collapsed)return;
    const pEl=getEl(parent.id),cEl=getEl(n.id);if(!pEl||!cEl)return;
    const px=parent.x+pEl.offsetWidth/2,py=parent.y+pEl.offsetHeight,cx=n.x+cEl.offsetWidth/2,cy=n.y;
    const dx=Math.abs(cx-px),dy=Math.abs(cy-py);
    const s=styles[Math.min(getDepth(n.id),styles.length-1)];
    const d=dy>0&&dx/dy<0.10?'M'+px+','+py+' L'+px+','+cy:'M'+px+','+py+' C'+px+','+(py+cy)/2+' '+cx+','+(py+cy)/2+' '+cx+','+cy;
    const path=document.createElementNS('http://www.w3.org/2000/svg','path');
    path.setAttribute('d',d);path.setAttribute('fill','none');path.setAttribute('stroke',s.stroke);
    path.setAttribute('stroke-width',s.width);path.setAttribute('stroke-dasharray',s.dash);path.setAttribute('stroke-linecap','round');
    svg.appendChild(path);
  });
}

// ============================================================
// DRAG (mouse)
// ============================================================
function startDrag(e,id) {
  e.preventDefault(); pushUndo();
  const wrap=document.getElementById('nmm-canvas-wrap');
  const n=nodes.find(function(x){return x.id===id;});
  dragState={id,moved:false,startMX:e.clientX+wrap.scrollLeft,startMY:e.clientY+wrap.scrollTop,startNX:n.x,startNY:n.y};
  selectedId=id;
  document.addEventListener('mousemove',onDrag);
  document.addEventListener('mouseup',endDrag);
}
function onDrag(e) {
  if(!dragState)return;
  const wrap=document.getElementById('nmm-canvas-wrap');
  const dx=(e.clientX+wrap.scrollLeft)-dragState.startMX,dy=(e.clientY+wrap.scrollTop)-dragState.startMY;
  if(Math.abs(dx)>3||Math.abs(dy)>3)dragState.moved=true;
  if(!dragState.moved)return;
  const n=nodes.find(function(x){return x.id===dragState.id;});
  n.x=Math.max(10,dragState.startNX+dx);n.y=Math.max(10,dragState.startNY+dy);
  const el=getEl(n.id);if(el){el.style.left=n.x+'px';el.style.top=n.y+'px';}
  document.querySelectorAll('.nmm-node.nmm-drop-target').forEach(function(x){x.classList.remove('nmm-drop-target');});
  const t=getDropTarget(e.clientX+wrap.scrollLeft,e.clientY+wrap.scrollTop,dragState.id);
  if(t){const tel=getEl(t);if(tel)tel.classList.add('nmm-drop-target');}
  drawLines();
}
function endDrag(e) {
  if(dragState&&dragState.moved){
    const wrap=document.getElementById('nmm-canvas-wrap');
    const t=getDropTarget(e.clientX+wrap.scrollLeft,e.clientY+wrap.scrollTop,dragState.id);
    if(t){const n=nodes.find(function(x){return x.id===dragState.id;});n.parent=t;document.querySelectorAll('.nmm-node.nmm-drop-target').forEach(function(x){x.classList.remove('nmm-drop-target');});}
    markDirty();render();
  }
  dragState=null;
  document.removeEventListener('mousemove',onDrag);document.removeEventListener('mouseup',endDrag);
}
function getDropTarget(mx,my,draggingId) {
  for(const n of nodes){
    if(n.id===draggingId||!isVisible(n.id)||isDescendant(n.id,draggingId)||n._readonly)continue;
    const el=getEl(n.id);if(!el)continue;
    if(mx>=n.x&&mx<=n.x+el.offsetWidth&&my>=n.y&&my<=n.y+el.offsetHeight)return n.id;
  }
  return null;
}
function isDescendant(id,ancestorId){
  let n=nodes.find(function(x){return x.id===id;});
  while(n&&n.parent){if(n.parent===ancestorId)return true;n=nodes.find(function(x){return x.id===n.parent;});}
  return false;
}

// ============================================================
// TOUCH SUPPORT
// ============================================================
let lastTap=0, touchDragState=null, canvasPanState=null;

function handleTouchStart(e,nodeId,isReadonly){
  if(e.touches.length===1){
    const touch=e.touches[0],now=Date.now();
    if(now-lastTap<300){nmmOpenNodeModal(nodeId);lastTap=0;return;}
    lastTap=now;
    if(isReadonly)return;
    const wrap=document.getElementById('nmm-canvas-wrap');
    const n=nodes.find(function(x){return x.id===nodeId;});
    if(!n)return;
    touchDragState={id:nodeId,moved:false,startTX:touch.clientX+wrap.scrollLeft,startTY:touch.clientY+wrap.scrollTop,startNX:n.x,startNY:n.y};
    e.stopPropagation();
  }
}
function handleTouchMove(e){
  if(!touchDragState||e.touches.length!==1)return;
  e.preventDefault();
  const touch=e.touches[0],wrap=document.getElementById('nmm-canvas-wrap');
  const dx=(touch.clientX+wrap.scrollLeft)-touchDragState.startTX;
  const dy=(touch.clientY+wrap.scrollTop)-touchDragState.startTY;
  if(Math.abs(dx)>5||Math.abs(dy)>5)touchDragState.moved=true;
  if(!touchDragState.moved)return;
  const n=nodes.find(function(x){return x.id===touchDragState.id;});
  if(!n)return;
  n.x=Math.max(10,touchDragState.startNX+dx);n.y=Math.max(10,touchDragState.startNY+dy);
  const el=getEl(n.id);if(el){el.style.left=n.x+'px';el.style.top=n.y+'px';}
  drawLines();
}
function handleTouchEnd(e,nodeId){
  if(touchDragState&&touchDragState.moved){markDirty();render();}
  touchDragState=null;
}

// Canvas pan on empty space
const canvasWrap=document.getElementById('nmm-canvas-wrap');
if(canvasWrap){
  canvasWrap.addEventListener('touchstart',function(e){
    if(e.target.closest('.nmm-node'))return;
    if(e.touches.length===1){canvasPanState={startX:e.touches[0].clientX,startY:e.touches[0].clientY,scrollX:this.scrollLeft,scrollY:this.scrollTop};}
  },{passive:true});
  canvasWrap.addEventListener('touchmove',function(e){
    if(!canvasPanState||e.target.closest('.nmm-node'))return;
    this.scrollLeft=canvasPanState.scrollX+(canvasPanState.startX-e.touches[0].clientX);
    this.scrollTop =canvasPanState.scrollY+(canvasPanState.startY-e.touches[0].clientY);
  },{passive:true});
  canvasWrap.addEventListener('touchend',function(){canvasPanState=null;});
}

// ============================================================
// COLLAPSE / EXPAND
// ============================================================
function nmmToggleCollapse(id){const n=nodes.find(function(x){return x.id===id;});if(n){pushUndo();n.collapsed=!n.collapsed;markDirty();render();}}
window.nmmExpandAll  = function(){pushUndo();nodes.forEach(function(n){n.collapsed=false;});markDirty();render();};
window.nmmCollapseAll = function(){pushUndo();nodes.forEach(function(n){if(hasChildren(n.id))n.collapsed=true;});markDirty();render();};

// ============================================================
// HARMONY
// ============================================================
window.nmmHarmony = function(){
  pushUndo();
  const T=0.20,M=200;
  const center=nodes.find(function(n){return !n.parent;});if(!center)return;
  const spineX=center.x+80,rc=nodes.filter(function(n){return n.parent===center.id;});
  const spine=rc.filter(function(n){return Math.abs((n.x+90)-spineX)<150;});
  const left =rc.filter(function(n){return !spine.includes(n)&&n.x<center.x;});
  const right=rc.filter(function(n){return !spine.includes(n)&&n.x>=center.x;});
  function nudge(n,tx,ty){n.x+=Math.max(-M,Math.min(M,(tx-n.x)*T));n.y+=Math.max(-M,Math.min(M,(ty-n.y)*T));}
  function kids(pid,dir,lv){
    const p=nodes.find(function(n){return n.id===pid;}),ch=nodes.filter(function(n){return n.parent===pid&&!n._readonly;});
    ch.forEach(function(n,i){
      var tx,ty;
      if(dir===0){const s=(i%2===0)?-1:1;tx=p.x+s*(180+lv*20);ty=p.y+Math.floor(i/2)*140;}
      else{tx=p.x+dir*(180+lv*30);ty=p.y+(i-(ch.length-1)/2)*130;}
      n.x+=Math.max(-M,Math.min(M,(tx-n.x)*T));n.y+=Math.max(-M,Math.min(M,(ty-n.y)*T));kids(n.id,dir,lv+1);
    });
  }
  const vs=170,ho=340;
  spine.forEach(function(n,i){nudge(n,spineX-90,center.y+100+i*vs);kids(n.id,0,1);});
  left.forEach(function(n,i){nudge(n,spineX-ho-140,center.y-(left.length-1)*vs/2+i*vs);kids(n.id,-1,1);});
  right.forEach(function(n,i){nudge(n,spineX+ho,center.y-(right.length-1)*vs/2+i*vs);kids(n.id,1,1);});
  markDirty();render();
};

// ============================================================
// NODE MODAL — with parent dropdown
// ============================================================
function buildParentDropdown(excludeId){
  const sel=document.getElementById('nme-parent');sel.innerHTML='';
  const none=document.createElement('option');none.value='';none.textContent='— No parent (standalone island) —';sel.appendChild(none);
  function addOpts(parentId,depth){
    nodes.filter(function(n){return n.parent===parentId&&n.id!==excludeId&&!n._readonly;}).forEach(function(n){
      if(isDescendant(n.id,excludeId))return;
      const opt=document.createElement('option');opt.value=n.id;
      opt.textContent='  '.repeat(depth)+(depth>0?'→ ':'')+n.title;sel.appendChild(opt);
      addOpts(n.id,depth+1);
    });
  }
  nodes.filter(function(n){return !n.parent&&n.id!==excludeId;}).forEach(function(root){
    const opt=document.createElement('option');opt.value=root.id;opt.textContent=root.title;sel.appendChild(opt);addOpts(root.id,1);
  });
}

window.nmmOpenNodeModal = function(id){
  const n=nodes.find(function(x){return x.id===id;});if(!n||n._readonly)return;
  editingId=id;
  document.getElementById('nme-title').value=n.title;
  document.getElementById('nme-sub').value=(n.subs||[]).join('\n');
  document.getElementById('nme-date').value=n.date||'';
  buildParentDropdown(id);
  document.getElementById('nme-parent').value=n.parent||'';
  document.getElementById('nmm-node-modal').classList.add('open');
  setTimeout(function(){document.getElementById('nme-title').focus();},50);
};
window.nmmCloseNodeModal = function(){document.getElementById('nmm-node-modal').classList.remove('open');editingId=null;};
window.nmmSaveNode = function(){
  const n=nodes.find(function(x){return x.id===editingId;});
  const t=document.getElementById('nme-title').value.trim();if(t)n.title=t;
  const sub=document.getElementById('nme-sub').value.trim();
  n.subs=sub?sub.split('\n').filter(function(s){return s.trim();}):[];
  n.date=document.getElementById('nme-date').value||'';
  const newParent=document.getElementById('nme-parent').value||null;
  if(newParent!==n.parent){pushUndo();n.parent=newParent;}
  nmmCloseNodeModal();markDirty();render();
};
window.nmmDeleteEditing = function(){if(editingId){pushUndo();nmmRemoveNode(editingId);nmmCloseNodeModal();}};
window.nmmMakeIsland = function(){if(!editingId)return;pushUndo();const n=nodes.find(function(x){return x.id===editingId;});n.parent=null;n.x+=200;n.y+=300;nmmCloseNodeModal();markDirty();render();};

// ============================================================
// ADD / DELETE
// ============================================================
window.nmmAddChild = function(parentId){
  pushUndo();
  const parent=nodes.find(function(n){return n.id===parentId;});
  const children=nodes.filter(function(n){return n.parent===parentId;});
  const newId='n'+Date.now();parent.collapsed=false;
  nodes.push({id:newId,x:parent.x+children.length*200-60,y:parent.y+160,title:'New node',subs:[],parent:parentId,collapsed:false,date:''});
  markDirty();render();setTimeout(function(){nmmOpenNodeModal(newId);},60);
};
window.nmmAddRoot = function(){
  pushUndo();
  const newId='isl'+Date.now();
  nodes.push({id:newId,x:600+Math.random()*400,y:1400,title:'New island',subs:['Click Edit to update'],parent:null,collapsed:false,date:''});
  markDirty();render();setTimeout(function(){nmmOpenNodeModal(newId);},60);
};
window.nmmDeleteNode = function(id){if(!confirm('Delete this node and all its children?'))return;pushUndo();nmmRemoveNode(id);};
function nmmRemoveNode(id){
  const toRemove=[id];
  function collect(pid){nodes.filter(function(n){return n.parent===pid;}).forEach(function(n){toRemove.push(n.id);collect(n.id);});}
  collect(id);nodes=nodes.filter(function(n){return !toRemove.includes(n.id);});
  if(toRemove.includes(selectedId))selectedId=null;markDirty();render();
}

// ============================================================
// LINKED ISLANDS
// ============================================================
window.nmmAddLinkedIsland = async function(){
  const list=document.getElementById('nmm-island-list');
  list.innerHTML='<p style="color:#999;font-size:12px">Loading islands...</p>';
  document.getElementById('nmm-island-modal').classList.add('open');
  const islands=await api('GET','islands');
  if(!islands.length){list.innerHTML='<p style="color:#999;font-size:12px">No islands yet. Use "↗ New Island" in the node editor to create one.</p>';return;}
  list.innerHTML='';
  islands.forEach(function(island){
    const isLinked=linkedIslands.includes(island.id);
    const row=document.createElement('div');row.style.cssText='display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee;';
    row.innerHTML='<span style="font-size:12px;font-weight:600">'+island.title+'</span><button class="nmm-btn nmm-btn-sm" style="'+(isLinked?'color:#cc3333;border-color:#cc3333;':'')+'">'+( isLinked?'Remove':'Add')+'</button>';
    row.querySelector('button').addEventListener('click',async function(){
      if(isLinked){linkedIslands=linkedIslands.filter(function(x){return x!==island.id;});nodes=nodes.filter(function(n){return n._island_id!==island.id;});}
      else{linkedIslands.push(island.id);const full=await api('GET','islands/'+island.id);if(full&&full.nodes){full.nodes.forEach(function(n){n._island_id=island.id;n._island_title=island.title;n._readonly=true;if(!nodes.find(function(x){return x.id===n.id;}))nodes.push(n);});}}
      markDirty();render();nmmCloseIslandModal();
    });
    list.appendChild(row);
  });
};
window.nmmCloseIslandModal = function(){document.getElementById('nmm-island-modal').classList.remove('open');};

// ============================================================
// SETTINGS
// ============================================================
window.nmmOpenSettings = function(){
  document.getElementById('nms-title').value=mapTitle;
  document.getElementById('nms-category').value=settings.category||'';
  document.getElementById('nms-motif').value=settings.motif||'';
  document.getElementById('nms-quotes').value=settings.quotes||'';
  document.getElementById('nmm-settings-modal').classList.add('open');
};
window.nmmCloseSettings = function(){document.getElementById('nmm-settings-modal').classList.remove('open');};
window.nmmSaveSettings = function(){
  mapTitle=document.getElementById('nms-title').value.trim()||mapTitle;
  document.getElementById('nmm-map-title-display').textContent=mapTitle;
  settings.category=document.getElementById('nms-category').value;
  settings.motif=document.getElementById('nms-motif').value.trim();
  settings.quotes=document.getElementById('nms-quotes').value.trim();
  nmmCloseSettings();markDirty();render();autoCenter();
};

function applyBackground(){
  // Background is handled entirely by CSS on .nmm-app and .nmm-landing-page
  // No per-map background overrides
}
function renderMotif(){
  const el=document.getElementById('nmm-motif');
  const motif=settings.motif||pluginDefaults.motif,quotes=settings.quotes||pluginDefaults.quotes;
  if(motif){el.textContent=motif;}
  else if(quotes){const qs=quotes.split(',').map(function(q){return q.trim();}).filter(Boolean);el.textContent=qs.length?qs[quoteIdx%qs.length]:'';}
  else{el.textContent='';}
}
setInterval(function(){quoteIdx++;renderMotif();},8000);

// ============================================================
// POP OUT — standalone window
// ============================================================
window.nmmPopOut = function(){
  window.open(window.location.href,'nmm_'+MAP_ID,'width='+(screen.availWidth||1400)+',height='+(screen.availHeight||900)+',toolbar=no,menubar=no,scrollbars=no,location=no,status=no');
};

// Mobile menu
window.nmmToggleMobileMenu = function(){document.getElementById('nmm-mobile-menu').classList.toggle('open');};

// ============================================================
// GCAL + PNG EXPORT
// ============================================================
window.nmmExportGCal = function(){
  const dated=nodes.filter(function(n){return n.date;});
  if(!dated.length){alert('No nodes have dates. Edit nodes and add dates first.');return;}
  const lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//NavigatorMindMaps//EN'];
  dated.forEach(function(n){const d=n.date.replace(/-/g,'');lines.push('BEGIN:VEVENT','DTSTART;VALUE=DATE:'+d,'DTEND;VALUE=DATE:'+d,'SUMMARY:'+n.title,'DESCRIPTION:'+(n.subs||[]).join('\\n'),'END:VEVENT');});
  lines.push('END:VCALENDAR');
  const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([lines.join('\r\n')],{type:'text/calendar'}));a.download='mindmap_events.ics';a.click();
};
window.nmmExportPNG = function(){
  const overlay=document.getElementById('nmm-export-overlay');overlay.style.display='flex';
  setTimeout(function(){
    let minX=Infinity,minY=Infinity,maxX=0,maxY=0;
    nodes.forEach(function(n){if(!isVisible(n.id))return;const el=getEl(n.id);if(!el)return;minX=Math.min(minX,n.x);minY=Math.min(minY,n.y);maxX=Math.max(maxX,n.x+el.offsetWidth);maxY=Math.max(maxY,n.y+el.offsetHeight);});
    const pad=80;
    html2canvas(document.getElementById('nmm-canvas'),{backgroundColor:'#e8e4db',x:minX-pad,y:minY-pad,width:maxX-minX+pad*2,height:maxY-minY+pad*2,scale:2,logging:false})
    .then(function(c){overlay.style.display='none';const a=document.createElement('a');a.download='mindmap.png';a.href=c.toDataURL('image/png');a.click();})
    .catch(function(){overlay.style.display='none';});
  },150);
};

// ============================================================
// KEYBOARD
// ============================================================
document.addEventListener('keydown',function(e){
  if((e.ctrlKey||e.metaKey)&&e.key==='z'){e.preventDefault();nmmUndo();return;}
  if((e.ctrlKey||e.metaKey)&&e.key==='s'){e.preventDefault();nmmSave();return;}
  if(e.key==='Escape'){nmmCloseNodeModal();nmmCloseSettings();nmmCloseIslandModal();}
  if(e.key==='Enter'&&document.getElementById('nmm-node-modal').classList.contains('open')&&e.target.tagName!=='TEXTAREA')nmmSaveNode();
});
document.querySelectorAll('.nmm-modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o){nmmCloseNodeModal();nmmCloseSettings();nmmCloseIslandModal();}});});
window.addEventListener('beforeunload',function(e){if(isDirty){e.preventDefault();e.returnValue='';}});

// ============================================================
// TAG + SEFIROT CONSTANTS
// ============================================================

const TAG_COLORS = {
  start:  { bg:'#B5D4F4', text:'#0C447C', label:'Start'  },
  doing:  { bg:'#FAC775', text:'#633806', label:'Doing'  },
  sprint: { bg:'#EF9F27', text:'#412402', label:'Sprint' },
  review: { bg:'#F4C0D1', text:'#4B1528', label:'Review' },
  done:   { bg:'#9FE1CB', text:'#04342C', label:'Done'   },
};

const SEFIROT_LABELS = {
  kether:'Kether', chochma:'Chochma', binah:'Binah', chesed:'Chesed',
  geburah:'Geburah', tiphereth:'Tiphereth', netzach:'Netzach',
  hod:'Hod', yesod:'Yesod', malkuth:'Malkuth'
};

const SEFIROT_POSITIONS = {
  kether:    { x: 900,  y: 120  },
  chochma:   { x: 580,  y: 280  },
  binah:     { x: 1220, y: 280  },
  chesed:    { x: 580,  y: 520  },
  geburah:   { x: 1220, y: 520  },
  tiphereth: { x: 900,  y: 660  },
  netzach:   { x: 580,  y: 860  },
  hod:       { x: 1220, y: 860  },
  yesod:     { x: 900,  y: 1020 },
  malkuth:   { x: 900,  y: 1220 },
};

// ============================================================
// NODE MODAL — open with tag + sefirot populated
// ============================================================

// Override nmmOpenNodeModal to also populate tag + sefirot fields
window.nmmOpenNodeModal = function(id) {
  const n = nodes.find(function(x){ return x.id===id; });
  if (!n || n._readonly) return;
  editingId = id;
  document.getElementById('nme-title').value = n.title;
  document.getElementById('nme-sub').value   = (n.subs || []).join('\n');
  document.getElementById('nme-date').value  = n.date || '';
  // Tag + sefirot
  const tagEl = document.getElementById('nme-tag');
  const sefEl = document.getElementById('nme-sefirot');
  if (tagEl) tagEl.value = n.tag     || '';
  if (sefEl) sefEl.value = n.sefirot || '';
  // Parent dropdown
  buildParentDropdown(id);
  document.getElementById('nme-parent').value = n.parent || '';
  document.getElementById('nmm-node-modal').classList.add('open');
  setTimeout(function(){ document.getElementById('nme-title').focus(); }, 50);
};

// Override nmmSaveNode to include tag + sefirot
window.nmmSaveNode = function() {
  const n = nodes.find(function(x){ return x.id===editingId; });
  if (!n) { nmmCloseNodeModal(); return; }
  const t = document.getElementById('nme-title').value.trim();
  if (t) n.title = t;
  const sub = document.getElementById('nme-sub').value.trim();
  n.subs = sub ? sub.split('\n').filter(function(s){ return s.trim(); }) : [];
  n.date = document.getElementById('nme-date').value || '';
  // Save tag + sefirot
  const tagEl = document.getElementById('nme-tag');
  const sefEl = document.getElementById('nme-sefirot');
  if (tagEl) n.tag     = tagEl.value || '';
  if (sefEl) n.sefirot = sefEl.value || '';
  // Parent change
  const newParent = document.getElementById('nme-parent').value || null;
  if (newParent !== n.parent) { pushUndo(); n.parent = newParent; }
  nmmCloseNodeModal(); markDirty(); renderWithBadges();
};

// ============================================================
// RENDER WITH BADGES — single clean render function
// No function overriding — called everywhere instead of render()
// ============================================================

function renderWithBadges() {
  // Run the base render
  render();
  // After render, inject tag + sefirot badges into each node element
  nodes.forEach(function(n) {
    if (!isVisible(n.id)) return;
    const el = getEl(n.id); if (!el) return;
    // Tag badge
    if (n.tag && TAG_COLORS[n.tag]) {
      const tc    = TAG_COLORS[n.tag];
      const badge = document.createElement('span');
      badge.className = 'nmm-node-tag nmm-tag-' + n.tag;
      badge.style.cssText = 'background:'+tc.bg+';color:'+tc.text;
      badge.textContent = tc.label;
      el.appendChild(badge);
    }
    // Sefirot badge
    if (n.sefirot && SEFIROT_LABELS[n.sefirot]) {
      const sb = document.createElement('div');
      sb.className   = 'nmm-node-sefirot';
      sb.textContent = '✦ ' + SEFIROT_LABELS[n.sefirot];
      el.appendChild(sb);
    }
  });
}

// ============================================================
// HARMONY — Tree of Life mode when sefirot assigned
// ============================================================

window.nmmHarmony = function() {
  const hasSefirot = nodes.some(function(n){ return n.sefirot && !n._readonly; });
  if (hasSefirot) {
    // Tree of Life snap mode
    pushUndo();
    nodes.forEach(function(n) {
      if (!n.sefirot || n._readonly) return;
      const pos = SEFIROT_POSITIONS[n.sefirot];
      if (!pos) return;
      // Nudge 30% toward Sefirot position each press
      n.x += (pos.x - n.x) * 0.3;
      n.y += (pos.y - n.y) * 0.3;
    });
    markDirty(); renderWithBadges();
  } else {
    // Standard spine harmony
    pushUndo();
    const T=0.20, M=200;
    const center=nodes.find(function(n){ return !n.parent; }); if(!center)return;
    const spineX=center.x+80, rc=nodes.filter(function(n){ return n.parent===center.id; });
    const spine=rc.filter(function(n){ return Math.abs((n.x+90)-spineX)<150; });
    const left =rc.filter(function(n){ return !spine.includes(n)&&n.x<center.x; });
    const right=rc.filter(function(n){ return !spine.includes(n)&&n.x>=center.x; });
    function nudge(n,tx,ty){ n.x+=Math.max(-M,Math.min(M,(tx-n.x)*T)); n.y+=Math.max(-M,Math.min(M,(ty-n.y)*T)); }
    function kids(pid,dir,lv){
      const p=nodes.find(function(n){return n.id===pid;}),ch=nodes.filter(function(n){return n.parent===pid&&!n._readonly;});
      ch.forEach(function(n,i){
        var tx,ty;
        if(dir===0){const s=(i%2===0)?-1:1;tx=p.x+s*(180+lv*20);ty=p.y+Math.floor(i/2)*140;}
        else{tx=p.x+dir*(180+lv*30);ty=p.y+(i-(ch.length-1)/2)*130;}
        n.x+=Math.max(-M,Math.min(M,(tx-n.x)*T));n.y+=Math.max(-M,Math.min(M,(ty-n.y)*T));kids(n.id,dir,lv+1);
      });
    }
    const vs=170,ho=340;
    spine.forEach(function(n,i){nudge(n,spineX-90,center.y+100+i*vs);kids(n.id,0,1);});
    left.forEach(function(n,i){nudge(n,spineX-ho-140,center.y-(left.length-1)*vs/2+i*vs);kids(n.id,-1,1);});
    right.forEach(function(n,i){nudge(n,spineX+ho,center.y-(right.length-1)*vs/2+i*vs);kids(n.id,1,1);});
    markDirty(); renderWithBadges();
  }
};

// Patch all existing calls that use render() to use renderWithBadges()
// by re-assigning key functions that call render()
const _baseExpandAll   = window.nmmExpandAll;
const _baseCollapseAll = window.nmmCollapseAll;
window.nmmExpandAll    = function(){ pushUndo(); nodes.forEach(function(n){n.collapsed=false;}); markDirty(); renderWithBadges(); };
window.nmmCollapseAll  = function(){ pushUndo(); nodes.forEach(function(n){if(hasChildren(n.id))n.collapsed=true;}); markDirty(); renderWithBadges(); };

// ============================================================
// OUTLINE IMPORT
// ============================================================

window.nmmOpenOutlineImport = function() {
  document.getElementById('nmm-outline-text').value = '';
  document.getElementById('nmm-outline-preview').style.display = 'none';
  document.getElementById('nmm-outline-modal').classList.add('open');
  setTimeout(function(){ document.getElementById('nmm-outline-text').focus(); }, 50);
};
window.nmmCloseOutlineImport = function() {
  document.getElementById('nmm-outline-modal').classList.remove('open');
};

function parseOutline(text) {
  const lines = text.split('\n').filter(function(l){ return l.trim(); });
  const items = [];
  lines.forEach(function(line) {
    // Strip list markers (-, *, •, numbers) and measure indent
    const raw    = line.match(/^(\s*)/)[1];
    const indent = raw.replace(/\t/g, '  ').length;
    const level  = Math.floor(indent / 2);
    const clean  = line.trim().replace(/^[-*•]\s+/, '').replace(/^\d+\.?\s+/, '');
    if (clean) items.push({ text: clean, level: level });
  });
  return items;
}

window.nmmPreviewOutline = function() {
  const items = parseOutline(document.getElementById('nmm-outline-text').value);
  const prev  = document.getElementById('nmm-outline-preview');
  if (!items.length) { prev.style.display='none'; return; }
  prev.style.display = 'block';
  prev.innerHTML = items.map(function(item) {
    return '<div class="level-'+Math.min(item.level,3)+'">'+
      '  '.repeat(item.level)+(item.level>0?'→ ':'')+item.text+'</div>';
  }).join('');
};

window.nmmBuildFromOutline = function() {
  const text  = document.getElementById('nmm-outline-text').value.trim();
  if (!text) { alert('Please paste an outline first.'); return; }
  const items = parseOutline(text);
  if (!items.length) { alert('No content detected.'); return; }
  pushUndo();

  const root   = nodes.find(function(n){ return !n.parent; });
  const startX = root ? root.x : 900;
  const startY = root ? root.y + 250 : 500;
  const stack  = [];

  items.forEach(function(item, idx) {
    const newId = 'ol_' + Date.now() + '_' + idx;
    // Find parent from stack
    let parentId = root ? root.id : null;
    for (let i = stack.length-1; i >= 0; i--) {
      if (stack[i].level < item.level) { parentId = stack[i].id; break; }
    }
    while (stack.length && stack[stack.length-1].level >= item.level) stack.pop();

    nodes.push({
      id: newId, parent: parentId, collapsed: false,
      title: item.text, subs: [], date: '', tag: '', sefirot: '',
      x: startX + (item.level % 2 === 0 ? -1 : 1) * item.level * 220 + (Math.random()*30-15),
      y: startY + idx * 130,
    });
    stack.push({ id: newId, level: item.level });
  });

  nmmCloseOutlineImport();
  markDirty();
  renderWithBadges();
  setTimeout(window.nmmHarmony, 150);
  setTimeout(autoCenter, 400);
};

document.getElementById('nmm-outline-modal').addEventListener('click', function(e){ if(e.target===this) nmmCloseOutlineImport(); });

// ============================================================
// GCAL EXPORT MODAL
// ============================================================

window.nmmOpenGCalModal = function() {
  const dated = nodes.filter(function(n){ return n.date && !n._readonly; });
  if (!dated.length) { alert('No nodes have dates. Edit nodes and add dates first.'); return; }
  const list = document.getElementById('nmm-gcal-list');
  list.innerHTML = '';
  dated.forEach(function(n) {
    const row = document.createElement('div');
    row.className = 'nmm-gcal-item';
    row.innerHTML =
      '<input type="checkbox" id="gcal_'+n.id+'" data-nid="'+n.id+'" checked />'+
      '<label for="gcal_'+n.id+'"><strong>'+n.title+'</strong>'+
      '<span class="nmm-gcal-date"> · 📅 '+n.date+'</span></label>';
    list.appendChild(row);
  });
  document.getElementById('nmm-gcal-modal').classList.add('open');
};
window.nmmCloseGCalModal = function() { document.getElementById('nmm-gcal-modal').classList.remove('open'); };
window.nmmDoGCalExport = function() {
  const checked = document.querySelectorAll('#nmm-gcal-list input[type=checkbox]:checked');
  if (!checked.length) { alert('No nodes selected.'); return; }
  const lines = ['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//NavigatorMindMaps//EN'];
  checked.forEach(function(cb) {
    const n = nodes.find(function(x){ return x.id===cb.dataset.nid; }); if (!n) return;
    const d = n.date.replace(/-/g,'');
    lines.push('BEGIN:VEVENT','DTSTART;VALUE=DATE:'+d,'DTEND;VALUE=DATE:'+d,
      'SUMMARY:'+n.title,'DESCRIPTION:'+(n.subs||[]).join('\\n'),'END:VEVENT');
  });
  lines.push('END:VCALENDAR');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([lines.join('\r\n')],{type:'text/calendar'}));
  a.download='mindmap_events.ics'; a.click();
  nmmCloseGCalModal();
};
document.getElementById('nmm-gcal-modal').addEventListener('click', function(e){ if(e.target===this) nmmCloseGCalModal(); });

// Additional ESC handler for new modals
document.addEventListener('keydown', function(e) {
  if (e.key==='Escape') { nmmCloseOutlineImport(); nmmCloseGCalModal(); }
});

// ============================================================
// INIT — use renderWithBadges for initial load
// ============================================================

// Patch loadMap to use renderWithBadges on completion
async function loadMapWithBadges() {
  if (!MAP_ID) { renderEmpty(); return; }
  const map   = await api('GET', 'maps/' + MAP_ID);
  nodes         = map.nodes    || getDefaultNodes();
  settings      = map.settings || {};
  mapTitle      = map.title    || 'Untitled';
  linkedIslands = map.islands  || [];
  mapRev        = map.rev      || 0;
  const restored = restoreDraft(map);

  const titleEl = document.getElementById('nmm-map-title-display');
  titleEl.textContent = mapTitle;
  titleEl.onclick = function() {
    const t = prompt('Rename map:', mapTitle);
    if (t && t.trim()) { mapTitle=t.trim(); this.textContent=mapTitle; markDirty(); }
  };

  for (const islandId of linkedIslands) {
    const island = await api('GET', 'islands/' + islandId);
    if (island && island.nodes) {
      island.nodes.forEach(function(n) {
        n._island_id=islandId; n._island_title=island.title; n._readonly=true;
        if (!nodes.find(function(x){return x.id===n.id;})) nodes.push(n);
      });
    }
  }
  renderWithBadges();
  autoCenter();
  if (restored) markDirty();
}

loadMapWithBadges();
})();
</script>
