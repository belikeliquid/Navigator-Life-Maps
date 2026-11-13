/* Navigator Life Maps — map editor (/map-editor/?map_id=N). Markup: templates/editor.php */
(function() {
const root = document.getElementById('nmm-editor');
if (!root) return;

const MAP_ID    = parseInt(root.dataset.mapId, 10) || 0;
const DRAFT_KEY = 'nmm_draft_' + MAP_ID;
const api       = NMMCore.api;
const $         = function(id) { return document.getElementById(id); };

let nodes = [], settings = {}, mapTitle = '', mapCategory = '', linkedIslands = [];
let selectedId = null, editingId = null, dragState = null;
let saveTimer = null, isDirty = false;
// mapRev: server revision this editor is based on. changeSeq counts edits so a
// save only clears isDirty if nothing changed while it was in flight.
let mapRev = 0, changeSeq = 0, saving = false, saveQueued = false;
let undoStack = []; // max 10 steps
// baseBody: the map as the server last had it, so a save that crosses someone else's can be merged.
// shared: other people can edit this map, so check for their saves while idle.
let baseBody = null, shared = false;
const clone = function(o) { return JSON.parse(JSON.stringify(o)); };
const same  = function(a, b) { return JSON.stringify(a) === JSON.stringify(b); };
function bodyOf(map) {
  return { title: map.title || '', category: map.category || '', nodes: map.nodes || [], settings: map.settings || {}, islands: map.islands || [] };
}

const C = NMMCanvas.create({
  getNodes: function() { return nodes; },
  editable: true,
  selectedId: function() { return selectedId; },
  defaults: NMM.defaults,
  decorate: decorateNode
});
const find = C.find;

// Phones: the list view follows every redraw of the canvas
const outline = NMMOutline.create({
  root: root, getNodes: function() { return nodes; }, editable: true,
  onEdit: function(id) { nmmOpenNodeModal(id); },
  onAdd: function(id) { nmmAddChild(id); },
  onShowMap: function() { C.render(); C.autoCenter(); }
});
const drawCanvas = C.render;
C.render = function() { drawCanvas(); outline.render(); if (nextUp) nextUp.update(NMMCore.attention(nodes)); };

// Where a card sits in the map, for the hover card
function pathTo(n) {
  const names = [];
  let p = find(n.parent);
  while (p && names.length < 4) { names.unshift(p.title); p = find(p.parent); }
  return names.join(' › ');
}
NMMCore.hoverCard({ root: root, selector: '.nmm-node', delay: 600, info: function(el) {
  const n = find(el.dataset.id);
  return n && { title: n.title, subs: n.subs, date: n.date, end: n.end, tag: n.tag, sefirot: n.sefirot, where: pathTo(n) };
} });

// Next up: the dated card most in need of a look, bottom right. Opening it shows the card on the map.
const nextUp = NMMCore.notice({
  root: root, key: 'map_' + MAP_ID,
  label: function(it) { return it.days < 0 ? 'Needs attention' : 'Next up'; },
  detail: function(it) { return it.when + ' · ' + NMMCore.longDate(it.n.date); },
  onOpen: function(it) { openCard(it.n); }
});
nextUp.open = openCard;
function openCard(n) {
  const it = { n: n };
  {
    let p = find(it.n.parent), opened = false;
    while (p) { if (p.collapsed) { p.collapsed = false; opened = true; } p = find(p.parent); }
    selectedId = it.n.id;
    C.render();
    if (opened) markDirty();
    C.centerOn(it.n.id);
    nmmOpenNodeModal(it.n.id);
  }
}

// ============================================================
// UNDO
// ============================================================
function pushUndo() {
  undoStack.push(JSON.parse(JSON.stringify(nodes)));
  if (undoStack.length > 10) undoStack.shift();
  $('nmm-undo-btn').disabled = false;
}
window.nmmUndo = function() {
  if (!undoStack.length) return;
  nodes = undoStack.pop();
  if (!undoStack.length) $('nmm-undo-btn').disabled = true;
  markDirty(); C.render();
  setStatus('Undone', true);
  setTimeout(function() { if (!isDirty) setStatus(''); }, 2000);
};

// ============================================================
// LOAD
// ============================================================
async function loadMap() {
  if (!MAP_ID) { renderEmpty(); return; }
  const map = await api('GET', 'maps/' + MAP_ID);
  if (!map || !map.id) { renderEmpty(); return; }
  // A featured map someone else made: open it read only (it can be copied from there)
  if (map.can_edit === false) { location.replace(NMM.shareUrl + '?map_id=' + MAP_ID); return; }
  nodes         = map.nodes    || [];
  settings      = map.settings || {};
  mapTitle      = map.title    || 'Untitled';
  mapCategory   = map.category || '';
  linkedIslands = map.islands  || [];
  mapRev        = map.rev      || 0;
  baseBody      = clone(bodyOf(map));
  shared        = !!(map.shared_by || map.people);
  let changed   = restoreDraft(map);

  // New maps arrive empty. Outline mode passes its text in the URL.
  if (!nodes.length) {
    nodes = [{ id: 'c' + Date.now(), x: 800, y: 400, title: mapTitle, subs: [], parent: null, collapsed: false, date: '', tag: '', sefirot: '' }];
    const outline = new URLSearchParams(location.search).get('outline');
    if (outline) addOutline(parseOutline(outline));
    changed = true;
  }
  const arrange = new URLSearchParams(location.search).has('arrange');
  if (new URLSearchParams(location.search).has('outline') || arrange) history.replaceState(null, '', NMM.editorUrl + '?map_id=' + MAP_ID);

  $('nmm-map-title-display').textContent = mapTitle;

  C.setSettings(settings);
  showLayoutStyle();
  await C.loadLinkedIslands(linkedIslands, nodes);
  C.render();
  if (arrange) { // a new map from the creator: lay it out in its chosen style
    const live = nodes.filter(function(n) { return !n._readonly; });
    (layoutStyle() === 'tol' && !hasSefirot() ? tidyLayout : LAYOUT_STYLES[layoutStyle()])(live);
    keepOnCanvas();
    C.render();
    changed = true;
  }
  C.autoCenter();
  if (changed) markDirty();
  // Settings or Import an outline chosen from another view's More menu
  const want = new URLSearchParams(location.search).get('open');
  if (want === 'settings' || want === 'outline') {
    history.replaceState(null, '', NMM.editorUrl + '?map_id=' + MAP_ID);
    setTimeout(want === 'settings' ? window.nmmOpenSettings : window.nmmOpenOutlineImport, 300);
  }
  // Opened from the maps list's Next up: show that card and open it
  const card = new URLSearchParams(location.search).get('card');
  if (card && find(card)) {
    history.replaceState(null, '', NMM.editorUrl + '?map_id=' + MAP_ID);
    setTimeout(function() { nextUp.open(find(card)); }, 350);
  }
}

// Map picker: rename this map, or jump to another (the list loads the first time it opens)
window.nmmRenameMap = function() {
  const t = prompt('Rename map:', mapTitle);
  if (t && t.trim()) { mapTitle = t.trim(); $('nmm-map-title-display').textContent = mapTitle; markDirty(); }
};
let pickerLoaded = false;
NMMCore.dropdown($('nmm-map-picker'), $('nmm-picker-menu'), function() {
  if (pickerLoaded) return;
  pickerLoaded = true;
  api('GET', 'maps').then(function(maps) {
    const others = (Array.isArray(maps) ? maps : []).filter(function(m) { return m.id !== MAP_ID; });
    $('nmm-picker-list').innerHTML = others.length
      ? others.map(function(m) { return '<a role="menuitem" href="' + NMM.editorUrl + '?map_id=' + m.id + '"><span class="nmm-menu-dot" style="background:' + NMMCore.esc(m.color || '#0BBFBF') + '"></span>' + NMMCore.esc(m.title) + '</a>'; }).join('')
      : '<span class="nmm-menu-note">No other maps yet</span>';
  }).catch(function() { pickerLoaded = false; $('nmm-picker-list').innerHTML = '<span class="nmm-menu-note">Could not load your maps</span>'; });
});

function renderEmpty() {
  $('nmm-canvas').innerHTML = '<div style="padding:60px;text-align:center;color:#999;font-size:18px;">Map not found. <a href="' + NMM.mapsUrl + '">Return to maps</a>.</div>';
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
  nodes = b.nodes; settings = b.settings || {}; mapTitle = b.title || mapTitle;
  if (b.category !== undefined) mapCategory = b.category;
  linkedIslands = b.islands || [];
  return true;
}
function saveDraft(body) {
  try { localStorage.setItem(DRAFT_KEY, JSON.stringify({ rev: mapRev, at: Date.now(), body: body })); } catch(e) {}
}
function clearDraft() {
  try { localStorage.removeItem(DRAFT_KEY); } catch(e) {}
}

// ============================================================
// SAVE
// ============================================================
function saveBody() {
  return { title: mapTitle, category: mapCategory, nodes: nodes.filter(function(n) { return !n._readonly; }), settings: settings, islands: linkedIslands };
}
function markDirty() {
  isDirty = true; changeSeq++; setStatus('Unsaved changes');
  saveDraft(saveBody());
  clearTimeout(saveTimer);
  saveTimer = setTimeout(nmmSave, 2000);
}
function setStatus(msg, ok) {
  const el = $('nmm-save-status');
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
    if (isRetry === true) await NMMCore.refreshNonce();
    const sent = saveBody();
    const r = await fetch(NMM.restUrl + 'maps/' + MAP_ID, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NMM.nonce },
      body: JSON.stringify(Object.assign({}, sent, { base_rev: mapRev }))
    });
    const data = await r.json();
    saving = false;
    if (r.status === 409) { resolveConflict(); return; }
    if (!data.id) throw new Error('bad response');
    mapRev = data.rev;
    baseBody = clone(sent);
    if (seq === changeSeq) { isDirty = false; clearDraft(); setStatus('Saved ✓', true); setTimeout(function() { if (!isDirty) setStatus(''); }, 3000); }
    else saveDraft(saveBody()); // edits made during the save: keep the draft current for the next save
    if (saveQueued || seq !== changeSeq) { saveQueued = false; nmmSave(); }
  } catch(e) {
    saving = false;
    setStatus('Save failed — retrying...');
    saveTimer = setTimeout(function() { nmmSave(true); }, 5000);
  }
};

// Someone else (another person, tab, the Kanban board) saved since this editor loaded.
// Merge card by card: your changes go on top of theirs, and cards nobody here touched keep their version.
async function resolveConflict() {
  if (dragState) { setTimeout(resolveConflict, 500); return; } // never swap the cards under a drag
  setStatus('Merging with other changes...');
  let latest;
  try { latest = await api('GET', 'maps/' + MAP_ID); } catch(e) {}
  if (!latest || !latest.id) {
    setStatus('Save failed — retrying...');
    saveTimer = setTimeout(function() { nmmSave(true); }, 5000);
    return;
  }
  showBody(mergeBodies(baseBody, saveBody(), bodyOf(latest)), latest);
  nmmSave();
}

function mergeBodies(base, mine, theirs) {
  base = base || { nodes: [], settings: {} };
  const pick = function(k) { return same(mine[k], base[k]) ? theirs[k] : mine[k]; };
  const settings = Object.assign({}, theirs.settings), bs = base.settings || {}, ms = mine.settings || {};
  Object.keys(Object.assign({}, bs, ms)).forEach(function(k) {
    if (same(ms[k], bs[k])) return;
    if (ms[k] === undefined) delete settings[k]; else settings[k] = ms[k];
  });
  const byId = function(list) { const m = {}; (list || []).forEach(function(n) { m[n.id] = n; }); return m; };
  const b = byId(base.nodes), m = byId(mine.nodes);
  let out = (theirs.nodes || []).slice();
  (mine.nodes || []).forEach(function(n) {   // cards you added or changed
    if (b[n.id] && same(n, b[n.id])) return;
    const i = out.findIndex(function(x) { return x.id === n.id; });
    if (i >= 0) out[i] = n; else out.push(n);
  });
  const deleted = Object.keys(b).filter(function(id) { return !m[id]; });   // cards you deleted
  out = out.filter(function(n) { return deleted.indexOf(n.id) < 0; });
  const ids = byId(out);   // cards someone added under one you deleted move up to its parent
  out.forEach(function(n) {
    if (!n.parent || ids[n.parent]) return;
    let p = n.parent;
    while (p && !ids[p] && b[p]) p = b[p].parent;
    n.parent = p && ids[p] ? p : null;
  });
  return { title: pick('title'), category: pick('category'), islands: pick('islands'), settings: settings, nodes: out };
}

// Put a body from the server (or a merge) on screen. latest = the map as the server has it now.
function showBody(body, latest) {
  nodes = clone(body.nodes).concat(nodes.filter(function(n) { return n._readonly; }));
  settings = body.settings; mapTitle = body.title || mapTitle; mapCategory = body.category; linkedIslands = body.islands;
  mapRev = latest.rev; baseBody = clone(bodyOf(latest));
  undoStack = []; $('nmm-undo-btn').disabled = true;   // undo would bring back the other version
  $('nmm-map-title-display').textContent = mapTitle;
  C.setSettings(settings); showLayoutStyle(); C.render();
}

// On a shared map, pick up other people's saves while you are not in the middle of something.
async function checkForChanges() {
  const busy = function() { return isDirty || saving || dragState || document.hidden || document.querySelector('.nmm-modal-overlay.open'); };
  if (!shared || busy()) return;
  let latest;
  try { latest = await api('GET', 'maps/' + MAP_ID); } catch(e) { return; }
  if (!latest || !latest.id || latest.rev <= mapRev || busy()) return;
  showBody(bodyOf(latest), latest);
  clearDraft();
  setStatus('Updated with ' + (latest.edited_by ? latest.edited_by + '’s' : 'new') + ' changes', true);
  setTimeout(function() { if (!isDirty) setStatus(''); }, 4000);
}
setInterval(checkForChanges, 15000);

// Leaving the page: send any unsaved changes with keepalive so the request
// survives the unload. The local draft covers the case where it doesn't arrive.
function saveOnLeave() {
  if (!isDirty || !MAP_ID || saving) return;
  try {
    fetch(NMM.restUrl + 'maps/' + MAP_ID, {
      method: 'PUT', keepalive: true,
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NMM.nonce },
      body: JSON.stringify(Object.assign(saveBody(), { base_rev: mapRev }))
    });
  } catch(e) {}
}
window.addEventListener('pagehide', saveOnLeave);
// Back/forward cache: the keepalive save may have moved the revision on, so reload.
window.addEventListener('pageshow', function(e) { if (e.persisted) location.reload(); });
// Switching tabs or apps: the page is still alive, so save normally right away.
document.addEventListener('visibilitychange', function() { if (document.visibilityState === 'hidden' && isDirty) nmmSave(); });
window.addEventListener('beforeunload', function(e) { if (isDirty) { e.preventDefault(); e.returnValue = ''; } });

// ============================================================
// NODE EVENTS (wired on every render)
// ============================================================
function decorateNode(el, n) {
  const ro = !!n._readonly;
  el.addEventListener('mousedown', function(e) {
    const t = e.target;
    if (t.dataset.action === 'edit') { nmmOpenNodeModal(n.id); return; }
    if (t.dataset.action === 'add')  { nmmAddChild(n.id); return; }
    if (t.dataset.action === 'del')  { nmmDeleteNode(n.id); return; }
    if (t.dataset.eid) { toggleCollapse(t.dataset.eid); return; }
    if (ro) return;
    startDrag(e, n.id);
  });
  el.addEventListener('dblclick', function() { if (!dragState || !dragState.moved) nmmOpenNodeModal(n.id); });
  el.addEventListener('touchstart', function(e) { handleTouchStart(e, n.id, ro); }, { passive: false });
  el.addEventListener('touchmove', handleTouchMove, { passive: false });
  el.addEventListener('touchend', handleTouchEnd);
}

// ============================================================
// DRAG (mouse) — drop onto another card to re-parent
// ============================================================
const wrap = $('nmm-canvas-wrap');
function startDrag(e, id) {
  e.preventDefault(); pushUndo();
  const n = find(id);
  const p = C.toCanvas(e.clientX, e.clientY);
  dragState = { id: id, moved: false, startMX: p.x, startMY: p.y, startNX: n.x, startNY: n.y };
  selectedId = id;
  document.addEventListener('mousemove', onDrag);
  document.addEventListener('mouseup', endDrag);
}
function onDrag(e) {
  if (!dragState) return;
  const p = C.toCanvas(e.clientX, e.clientY);
  const dx = p.x - dragState.startMX, dy = p.y - dragState.startMY;
  if (Math.abs(dx) > 3 || Math.abs(dy) > 3) dragState.moved = true;
  if (!dragState.moved) return;
  const n = find(dragState.id);
  n.x = Math.max(10, dragState.startNX + dx); n.y = Math.max(10, dragState.startNY + dy);
  const el = C.getEl(n.id); if (el) { el.style.left = n.x + 'px'; el.style.top = n.y + 'px'; }
  document.querySelectorAll('.nmm-node.nmm-drop-target').forEach(function(x) { x.classList.remove('nmm-drop-target'); });
  const t = getDropTarget(p.x, p.y, dragState.id);
  if (t) { const tel = C.getEl(t); if (tel) tel.classList.add('nmm-drop-target'); }
  C.drawLines();
}
function endDrag(e) {
  if (dragState && dragState.moved) {
    const p = C.toCanvas(e.clientX, e.clientY);
    const t = getDropTarget(p.x, p.y, dragState.id);
    if (t) find(dragState.id).parent = t;
    markDirty(); C.render();
  } else if (dragState) {
    undoStack.pop(); // a click, not a move
    if (!undoStack.length) $('nmm-undo-btn').disabled = true;
  }
  dragState = null;
  document.removeEventListener('mousemove', onDrag); document.removeEventListener('mouseup', endDrag);
}
function getDropTarget(mx, my, draggingId) {
  for (const n of nodes) {
    if (n.id === draggingId || !C.isVisible(n.id) || C.isDescendant(n.id, draggingId) || n._readonly) continue;
    const el = C.getEl(n.id); if (!el) continue;
    if (mx >= n.x && mx <= n.x + el.offsetWidth && my >= n.y && my <= n.y + el.offsetHeight) return n.id;
  }
  return null;
}

// ============================================================
// TOUCH — double-tap edits, drag moves, empty space pans
// ============================================================
let lastTap = 0, touchDragState = null, panState = null;
function handleTouchStart(e, nodeId, ro) {
  if (e.touches.length !== 1) { touchDragState = null; return; } // a second finger means pinch zoom
  const touch = e.touches[0], now = Date.now();
  if (now - lastTap < 300) { nmmOpenNodeModal(nodeId); lastTap = 0; return; }
  lastTap = now;
  if (ro) return;
  const n = find(nodeId); if (!n) return;
  const p = C.toCanvas(touch.clientX, touch.clientY);
  touchDragState = { id: nodeId, moved: false, startTX: p.x, startTY: p.y, startNX: n.x, startNY: n.y };
  e.stopPropagation();
}
function handleTouchMove(e) {
  if (!touchDragState || e.touches.length !== 1) return;
  e.preventDefault();
  const touch = e.touches[0];
  const p = C.toCanvas(touch.clientX, touch.clientY);
  const dx = p.x - touchDragState.startTX, dy = p.y - touchDragState.startTY;
  if (Math.abs(dx) > 5 || Math.abs(dy) > 5) touchDragState.moved = true;
  if (!touchDragState.moved) return;
  const n = find(touchDragState.id); if (!n) return;
  n.x = Math.max(10, touchDragState.startNX + dx); n.y = Math.max(10, touchDragState.startNY + dy);
  const el = C.getEl(n.id); if (el) { el.style.left = n.x + 'px'; el.style.top = n.y + 'px'; }
  C.drawLines();
}
function handleTouchEnd() {
  if (touchDragState && touchDragState.moved) { markDirty(); C.render(); }
  touchDragState = null;
}
wrap.addEventListener('touchstart', function(e) {
  if (e.target.closest('.nmm-node') || e.touches.length !== 1) return;
  panState = { x: e.touches[0].clientX, y: e.touches[0].clientY, sl: wrap.scrollLeft, st: wrap.scrollTop };
}, { passive: true });
wrap.addEventListener('touchmove', function(e) {
  if (!panState || e.touches.length !== 1 || e.target.closest('.nmm-node')) return;
  wrap.scrollLeft = panState.sl + (panState.x - e.touches[0].clientX);
  wrap.scrollTop  = panState.st + (panState.y - e.touches[0].clientY);
}, { passive: true });
wrap.addEventListener('touchend', function() { panState = null; });

// ============================================================
// COLLAPSE / EXPAND / CENTER
// ============================================================
function toggleCollapse(id) { const n = find(id); if (n) { pushUndo(); n.collapsed = !n.collapsed; markDirty(); C.render(); } }
window.nmmExpandAll   = function() { pushUndo(); nodes.forEach(function(n) { n.collapsed = false; }); markDirty(); C.render(); };
window.nmmAnimate = function() {
  if (document.getElementById('nmm-editor').classList.contains('nmm-list-mode')) document.querySelector('.nmm-view-switch [data-view="map"]').click();
  C.animate({ restore: true });
};
window.nmmCollapseAll = function() { pushUndo(); nodes.forEach(function(n) { if (C.hasChildren(n.id)) n.collapsed = true; }); markDirty(); C.render(); };
window.nmmCenter      = function() { C.autoCenter(); };

// ============================================================
// HARMONY — lays the map out so nothing overlaps
// ============================================================
// The layout style is picked from the menu next to Harmony and saved in the
// map's settings (settings.layout). Without one, a map with Tree of Life
// positions uses Tree of Life and any other map uses Flow Chart.
// Flow Chart (tidy): each card sits centred above its children, which keep their
// current left-to-right order. A card whose children are all leaves gets them
// in one stacked column; with mixed children, 3+ leaves share one stacked
// column and each branch gets its own column. Card sizes are measured.
// Mind Map: the main card sits in the middle and its branches grow out to the
// left and right; each card is centred beside its children, which stack top
// to bottom in their current order.
// Tree of Life: each sefirot is a slot in the
// three-pillar shape; cards sharing a slot sit side by side, each with its own
// branch laid out beneath it; rows of the tree grow to fit what they hold.
const LAYOUT = { gapX: 40, gapY: 70, stackGap: 22, islandGap: 140, margin: 40, pillarGap: 40, sideGap: 60 };
const SEFIROT_ROWS = [
  [['kether', 'mid']],
  [['chochma', 'left'], ['binah', 'right']],
  [['chesed', 'left'], ['geburah', 'right']],
  [['tiphereth', 'mid']],
  [['netzach', 'left'], ['hod', 'right']],
  [['yesod', 'mid']],
  [['malkuth', 'mid']]
];

// Measures and places subtrees. `stop(c)` marks children that start their own
// group elsewhere (Tree of Life slots) and are left out of their parent's subtree.
// Collapsed branches get space too, so expanding them later never causes overlaps.
// Render everything expanded once to measure every card, then restore.
function measureSizes(live) {
  const collapsed = live.filter(function(n) { return n.collapsed; });
  collapsed.forEach(function(n) { n.collapsed = false; });
  C.render();
  const sizes = {};
  live.forEach(function(n) { const el = C.getEl(n.id); sizes[n.id] = { w: el ? el.offsetWidth : 180, h: el ? el.offsetHeight : 50 }; });
  collapsed.forEach(function(n) { n.collapsed = true; });
  return sizes;
}

function treeEngine(live, stop) {
  const sizes = measureSizes(live);

  // By centre, then top: stacked cards share a centre, so they keep their top-to-bottom order
  function centre(c) { return c.x + sizes[c.id].w / 2; }
  function byPosition(a, b) { const d = centre(a) - centre(b); return Math.abs(d) > 20 ? d : a.y - b.y; }
  function kids(n) {
    return live.filter(function(c) { return c.parent === n.id && !(stop && stop(c)); }).sort(byPosition);
  }
  function isLeaf(n) { return !kids(n).length; }

  const box = {};
  function measure(n) {
    const s = sizes[n.id], ch = kids(n);
    const b = box[n.id] = { w: s.w, h: s.h, nodeW: s.w, nodeH: s.h, cols: [] };
    if (!ch.length) return b;
    ch.forEach(measure);
    const leaves = ch.filter(isLeaf);
    const stackAll = leaves.length === ch.length && ch.length > 1;
    const stackLeaves = !stackAll && leaves.length > 2;
    let stack = null;
    ch.forEach(function(c) {
      if (stackAll || (stackLeaves && isLeaf(c))) {
        if (!stack) { stack = { items: [] }; b.cols.push(stack); }
        stack.items.push(c);
      } else {
        b.cols.push({ items: [c] });
      }
    });
    let rowW = 0, rowH = 0;
    b.cols.forEach(function(col) {
      col.w = Math.max.apply(null, col.items.map(function(c) { return box[c.id].w; }));
      col.h = col.items.reduce(function(sum, c) { return sum + box[c.id].h; }, 0) + LAYOUT.stackGap * (col.items.length - 1);
      rowW += col.w; rowH = Math.max(rowH, col.h);
    });
    b.rowW = rowW + LAYOUT.gapX * (b.cols.length - 1);
    b.w = Math.max(s.w, b.rowW);
    b.h = s.h + LAYOUT.gapY + rowH;
    return b;
  }

  // cx = centre line of the subtree, top = its top edge
  function place(n, cx, top) {
    const b = box[n.id];
    n.x = cx - b.nodeW / 2; n.y = top;
    let left = cx - (b.rowW || 0) / 2;
    const y0 = top + b.nodeH + LAYOUT.gapY;
    b.cols.forEach(function(col) {
      const colCx = left + col.w / 2;
      let y = y0;
      col.items.forEach(function(c) { place(c, colCx, y); y += box[c.id].h + LAYOUT.stackGap; });
      left += col.w + LAYOUT.gapX;
    });
  }

  // Lays several subtrees side by side; returns the row's width and height.
  function row(list) {
    list.forEach(measure);
    const w = list.reduce(function(s, n) { return s + box[n.id].w; }, 0) + LAYOUT.gapX * 2 * Math.max(0, list.length - 1);
    const h = Math.max.apply(null, [0].concat(list.map(function(n) { return box[n.id].h; })));
    return { w: w, h: h, place: function(left, top) {
      list.forEach(function(n) { place(n, left + box[n.id].w / 2, top); left += box[n.id].w + LAYOUT.gapX * 2; });
    } };
  }

  return { box: box, kids: kids, measure: measure, place: place, row: row, byPosition: byPosition };
}

function isRoot(n) { return !n.parent || !find(n.parent); }

function tidyLayout(live) {
  const T = treeEngine(live);
  const roots = live.filter(isRoot);
  if (!roots.length) return;
  // The root with the most cards stays where it is; other islands go in a row underneath
  function count(n) { return 1 + T.kids(n).reduce(function(s, c) { return s + count(c); }, 0); }
  roots.forEach(T.measure);
  const main = roots.slice().sort(function(a, b) { return count(b) - count(a); })[0];
  const mb = T.box[main.id], cx = main.x + mb.nodeW / 2;
  T.place(main, cx, main.y);
  const others = T.row(roots.filter(function(r) { return r !== main; }).sort(T.byPosition));
  others.place(cx - mb.w / 2, main.y + mb.h + LAYOUT.islandGap);
}

function mindMapLayout(live) {
  const sizes = measureSizes(live);
  function kids(n) { return live.filter(function(c) { return c.parent === n.id; }); }
  function byTop(a, b) { return a.y - b.y || a.x - b.x; }
  function centreX(n) { return n.x + sizes[n.id].w / 2; }
  // Height of a branch growing sideways: its children stacked, or the card itself
  const H = {};
  function height(n) {
    const ch = kids(n);
    const stacked = ch.reduce(function(sum, c) { return sum + height(c); }, 0) + LAYOUT.stackGap * Math.max(0, ch.length - 1);
    return (H[n.id] = Math.max(sizes[n.id].h, stacked));
  }
  function stackHeight(list) { return list.reduce(function(sum, c) { return sum + H[c.id]; }, 0) + LAYOUT.stackGap * Math.max(0, list.length - 1); }
  // Places a branch: side 1 grows right from `edge`, side -1 grows left; cy = its vertical centre
  function place(n, edge, cy, side) {
    const sz = sizes[n.id];
    // Whole pixels, so pressing Harmony again reproduces exactly the same positions
    n.x = Math.round(side > 0 ? edge : edge - sz.w); n.y = Math.round(cy - sz.h / 2);
    placeStack(kids(n).sort(byTop), side > 0 ? n.x + sz.w + LAYOUT.sideGap : n.x - LAYOUT.sideGap, cy, side);
  }
  function placeStack(list, edge, cy, side) {
    let y = cy - stackHeight(list) / 2;
    list.forEach(function(c) { place(c, edge, y + H[c.id] / 2, side); y += H[c.id] + LAYOUT.stackGap; });
  }
  // One island: its root stays put; branches already on the left stay on the left
  function layoutIsland(r) {
    height(r);
    const sz = sizes[r.id], rcx = centreX(r), cy = r.y + sz.h / 2;
    const ch = kids(r);
    let right = ch.filter(function(c) { return centreX(c) >= rcx; }), left = ch.filter(function(c) { return centreX(c) < rcx; });
    if (ch.length > 1 && (!right.length || !left.length)) {
      // Everything on one side (e.g. coming from Flow Chart): split in order, balancing the heights
      const ordered = ch.slice().sort(function(a, b) { return a.x - b.x || a.y - b.y; });
      const half = stackHeight(ordered) / 2;
      right = []; left = [];
      let acc = 0;
      ordered.forEach(function(c) { (acc < half ? right : left).push(c); acc += H[c.id] + LAYOUT.stackGap; });
    }
    placeStack(right.sort(byTop), r.x + sz.w + LAYOUT.sideGap, cy, 1);
    placeStack(left.sort(byTop), r.x - LAYOUT.sideGap, cy, -1);
  }
  function islandNodes(r) { return live.filter(function(n) { return n === r || C.isDescendant(n.id, r.id); }); }
  function bounds(list) {
    return {
      x0: Math.min.apply(null, list.map(function(n) { return n.x; })), y0: Math.min.apply(null, list.map(function(n) { return n.y; })),
      x1: Math.max.apply(null, list.map(function(n) { return n.x + sizes[n.id].w; })), y1: Math.max.apply(null, list.map(function(n) { return n.y + sizes[n.id].h; }))
    };
  }

  const roots = live.filter(isRoot);
  if (!roots.length) return;
  // The root with the most cards stays where it is; other islands go in a row underneath
  const main = roots.slice().sort(function(a, b) { return islandNodes(b).length - islandNodes(a).length; })[0];
  layoutIsland(main);
  const mb = bounds(islandNodes(main));
  let x = mb.x0;
  roots.filter(function(r) { return r !== main; }).sort(function(a, b) { return a.x - b.x || a.y - b.y; }).forEach(function(r) {
    layoutIsland(r);
    const list = islandNodes(r), b = bounds(list), dx = x - b.x0, dy = mb.y1 + LAYOUT.islandGap - b.y0;
    list.forEach(function(n) { n.x += dx; n.y += dy; });
    x += b.x1 - b.x0 + LAYOUT.gapX * 2;
  });
}

function treeOfLifeLayout(live) {
  const isSlotted = function(n) { return !!NMMCore.SEFIROT_LABELS[n.sefirot]; };
  const T = treeEngine(live, isSlotted);
  // Root cards without a sefirot (usually the map's centre) go in a row above Kether
  const loose = live.filter(function(n) { return isRoot(n) && !isSlotted(n); });
  const cx = 900;
  let top = LAYOUT.margin;
  const looseRow = T.row(loose.sort(T.byPosition));
  if (loose.length) { looseRow.place(cx - looseRow.w / 2, top); top += looseRow.h + LAYOUT.gapY * 1.5; }

  SEFIROT_ROWS.forEach(function(slots) {
    const groups = slots.map(function(s) {
      return { side: s[1], g: T.row(live.filter(function(n) { return n.sefirot === s[0]; }).sort(T.byPosition)) };
    }).filter(function(x) { return x.g.w > 0; });
    if (!groups.length) return;
    groups.forEach(function(x) {
      const left = x.side === 'left' ? cx - LAYOUT.pillarGap - x.g.w : x.side === 'right' ? cx + LAYOUT.pillarGap : cx - x.g.w / 2;
      x.g.place(left, top);
    });
    top += Math.max.apply(null, groups.map(function(x) { return x.g.h; })) + LAYOUT.gapY * 1.5;
  });
}

function hasSefirot() { return nodes.some(function(n) { return !n._readonly && NMMCore.SEFIROT_LABELS[n.sefirot]; }); }
function layoutStyle() {
  return LAYOUT_STYLES[settings.layout] ? settings.layout : hasSefirot() ? 'tol' : 'flow';
}
const LAYOUT_STYLES = { flow: tidyLayout, mind: mindMapLayout, tol: treeOfLifeLayout };

window.nmmHarmony = function() {
  const live = nodes.filter(function(n) { return !n._readonly; });
  if (!live.length) return;
  const style = layoutStyle();
  if (style === 'tol' && !hasSefirot()) {
    alert('No cards have a Tree of Life position yet. Edit a card and pick its position, then try again.');
    return;
  }
  pushUndo();
  LAYOUT_STYLES[style](live);
  keepOnCanvas();
  markDirty(); C.render(); C.autoCenter();
};
// The canvas can't scroll to negative positions
function keepOnCanvas() {
  const minX = Math.min.apply(null, nodes.map(function(n) { return n.x; }));
  const minY = Math.min.apply(null, nodes.map(function(n) { return n.y; }));
  const sx = Math.max(0, LAYOUT.margin - minX), sy = Math.max(0, LAYOUT.margin - minY);
  nodes.forEach(function(n) { n.x = Math.round(n.x + sx); n.y = Math.round(n.y + sy); });
}

// Layout style menu next to Harmony. Picking a style saves it with the map and applies it.
const layoutMenu = $('nmm-layout-menu');
function showLayoutStyle() {
  const style = layoutStyle(), opt = layoutMenu.querySelector('[data-layout="' + style + '"]');
  $('nmm-layout-icon').innerHTML = opt.querySelector('svg').outerHTML;
  $('nmm-layout-btn').title = 'Layout: ' + opt.querySelector('strong').textContent;
  layoutMenu.querySelectorAll('[data-layout]').forEach(function(b) { b.classList.toggle('active', b === opt); });
}
window.nmmToggleLayoutMenu = function(e) {
  e.stopPropagation();
  if (layoutMenu.classList.toggle('open')) {
    const r = $('nmm-layout-btn').getBoundingClientRect();
    layoutMenu.style.left = Math.min(r.left, innerWidth - layoutMenu.offsetWidth - 8) + 'px';
    layoutMenu.style.top = (r.bottom + 6) + 'px';
  }
};
layoutMenu.querySelectorAll('[data-layout]').forEach(function(b) {
  b.addEventListener('click', function() {
    layoutMenu.classList.remove('open');
    if (b.dataset.layout === 'tol' && !hasSefirot()) {
      alert('No cards have a Tree of Life position yet. Edit a card and pick its position, then choose Tree of Life again.');
      return;
    }
    settings.layout = b.dataset.layout;
    C.setSettings(settings); showLayoutStyle();
    window.nmmHarmony();
    markDirty(); // the style is saved even when the layout itself didn't change
  });
});
document.addEventListener('click', function(e) { if (!layoutMenu.contains(e.target)) layoutMenu.classList.remove('open'); });

// ============================================================
// NODE MODAL
// ============================================================
function buildParentDropdown(excludeId) {
  const sel = $('nme-parent'); sel.innerHTML = '';
  const none = document.createElement('option'); none.value = ''; none.textContent = '— No parent (standalone island) —'; sel.appendChild(none);
  function addOpts(parentId, depth) {
    nodes.filter(function(n) { return n.parent === parentId && n.id !== excludeId && !n._readonly; }).forEach(function(n) {
      if (C.isDescendant(n.id, excludeId)) return;
      const opt = document.createElement('option'); opt.value = n.id;
      opt.textContent = '  '.repeat(depth) + '→ ' + n.title; sel.appendChild(opt);
      addOpts(n.id, depth + 1);
    });
  }
  nodes.filter(function(n) { return !n.parent && n.id !== excludeId && !n._readonly; }).forEach(function(r) {
    const opt = document.createElement('option'); opt.value = r.id; opt.textContent = r.title; sel.appendChild(opt); addOpts(r.id, 1);
  });
}
window.nmmOpenNodeModal = function(id) {
  const n = find(id); if (!n || n._readonly) return;
  editingId = id;
  $('nme-title').value   = n.title;
  $('nme-sub').value     = (n.subs || []).join('\n');
  $('nme-date').value    = n.date || '';
  $('nme-end').value     = n.end || '';
  $('nme-tag').value     = n.tag || '';
  $('nme-task').checked  = NMMCore.isTask(n);
  showTagField();
  $('nme-sefirot').value = n.sefirot || '';
  buildParentDropdown(id);
  $('nme-parent').value = n.parent || '';
  $('nmm-node-modal').classList.add('open');
  setTimeout(function() { $('nme-title').focus(); }, 50);
};
function showTagField() { $('nme-tag-field').style.display = $('nme-task').checked ? '' : 'none'; }
$('nme-task').addEventListener('change', showTagField);
window.nmmCloseNodeModal = function() { $('nmm-node-modal').classList.remove('open'); editingId = null; };
window.nmmSaveNode = function() {
  const n = find(editingId);
  if (!n) { nmmCloseNodeModal(); return; }
  pushUndo();
  const t = $('nme-title').value.trim(); if (t) n.title = t;
  const sub = $('nme-sub').value.trim();
  n.subs    = sub ? sub.split('\n').filter(function(s) { return s.trim(); }) : [];
  n.date    = $('nme-date').value || '';
  n.end     = n.date && $('nme-end').value >= n.date ? $('nme-end').value : ''; // an end needs a start, and can't come before it
  n.task    = $('nme-task').checked;
  n.tag     = n.task ? $('nme-tag').value || '' : '';
  n.sefirot = $('nme-sefirot').value || '';
  n.parent  = $('nme-parent').value || null;
  nmmCloseNodeModal(); markDirty(); C.render();
};
window.nmmDeleteEditing = function() { if (editingId) { pushUndo(); removeNode(editingId); nmmCloseNodeModal(); } };
window.nmmMakeIsland = function() {
  const n = find(editingId); if (!n) return;
  pushUndo(); n.parent = null; n.x += 200; n.y += 300;
  nmmCloseNodeModal(); markDirty(); C.render();
};

// ============================================================
// ADD / DELETE
// ============================================================
function newNode(fields) {
  return Object.assign({ id: 'n' + Date.now(), x: 0, y: 0, title: 'New node', subs: [], parent: null, collapsed: false, date: '', tag: '', sefirot: '' }, fields);
}
window.nmmAddChild = function(parentId) {
  pushUndo();
  const parent = find(parentId), count = nodes.filter(function(n) { return n.parent === parentId; }).length;
  parent.collapsed = false;
  const n = newNode({ x: parent.x + count * 200 - 60, y: parent.y + 160, parent: parentId });
  nodes.push(n);
  markDirty(); C.render(); setTimeout(function() { nmmOpenNodeModal(n.id); }, 60);
};
window.nmmAddRoot = function() {
  pushUndo();
  const n = newNode({ id: 'isl' + Date.now(), x: 600 + Math.random() * 400, y: 1400, title: 'New island', subs: ['Click Edit to update'] });
  nodes.push(n);
  markDirty(); C.render(); setTimeout(function() { nmmOpenNodeModal(n.id); }, 60);
};
window.nmmDeleteNode = function(id) { if (!confirm('Delete this node and all its children?')) return; pushUndo(); removeNode(id); };
function removeNode(id) {
  const gone = [id];
  (function collect(pid) { nodes.filter(function(n) { return n.parent === pid; }).forEach(function(n) { gone.push(n.id); collect(n.id); }); })(id);
  nodes = nodes.filter(function(n) { return !gone.includes(n.id); });
  if (gone.includes(selectedId)) selectedId = null;
  markDirty(); C.render();
}

// ============================================================
// SETTINGS
// ============================================================
window.nmmOpenSettings = function() {
  $('nms-title').value    = mapTitle;
  $('nms-category').value = mapCategory;
  $('nms-motif').value    = settings.motif || '';
  $('nms-quotes').value   = settings.quotes || '';
  $('nmm-settings-modal').classList.add('open');
};
window.nmmCloseSettings = function() { $('nmm-settings-modal').classList.remove('open'); };
window.nmmSaveSettings = function() {
  mapTitle = $('nms-title').value.trim() || mapTitle;
  $('nmm-map-title-display').textContent = mapTitle;
  mapCategory     = $('nms-category').value;
  settings.motif  = $('nms-motif').value.trim();
  settings.quotes = $('nms-quotes').value.trim();
  C.setSettings(settings);
  nmmCloseSettings(); markDirty(); C.render(); C.autoCenter();
};

window.nmmToggleMobileMenu = function() { $('nmm-mobile-menu').classList.toggle('open'); };
// ============================================================
// OUTLINE IMPORT — indented text, bullets or numbered lists
// ============================================================
function parseOutline(text) {
  const items = [];
  text.split('\n').forEach(function(line) {
    if (!line.trim()) return;
    const indent = line.match(/^(\s*)/)[1].replace(/\t/g, '  ').length;
    const clean  = line.trim().replace(/^[-*•]\s+/, '').replace(/^\d+\.?\s+/, '');
    if (clean) items.push({ text: clean, level: Math.floor(indent / 2) });
  });
  return items;
}
function addOutline(items) {
  const rootNode = nodes.find(function(n) { return !n.parent; });
  const startX = rootNode ? rootNode.x : 900, startY = rootNode ? rootNode.y + 250 : 500;
  const stack = [];
  items.forEach(function(item, idx) {
    let parentId = rootNode ? rootNode.id : null;
    for (let i = stack.length - 1; i >= 0; i--) { if (stack[i].level < item.level) { parentId = stack[i].id; break; } }
    while (stack.length && stack[stack.length - 1].level >= item.level) stack.pop();
    const n = newNode({ id: 'ol_' + Date.now() + '_' + idx, parent: parentId, title: item.text,
      x: startX + (item.level % 2 === 0 ? -1 : 1) * item.level * 220 + (Math.random() * 30 - 15), y: startY + idx * 130 });
    nodes.push(n);
    stack.push({ id: n.id, level: item.level });
  });
}
window.nmmOpenOutlineImport = function() {
  $('nmm-outline-text').value = '';
  $('nmm-outline-preview').style.display = 'none';
  $('nmm-outline-modal').classList.add('open');
  setTimeout(function() { $('nmm-outline-text').focus(); }, 50);
};
window.nmmCloseOutlineImport = function() { $('nmm-outline-modal').classList.remove('open'); };
window.nmmPreviewOutline = function() {
  const items = parseOutline($('nmm-outline-text').value), prev = $('nmm-outline-preview');
  if (!items.length) { prev.style.display = 'none'; return; }
  prev.style.display = 'block';
  prev.innerHTML = items.map(function(item) {
    return '<div class="level-' + Math.min(item.level, 3) + '">' + '  '.repeat(item.level) + (item.level > 0 ? '→ ' : '') + NMMCore.esc(item.text) + '</div>';
  }).join('');
};
window.nmmBuildFromOutline = function() {
  const items = parseOutline($('nmm-outline-text').value.trim());
  if (!items.length) { alert('Please paste an outline first.'); return; }
  pushUndo();
  addOutline(items);
  nmmCloseOutlineImport(); markDirty(); C.render();
  setTimeout(window.nmmHarmony, 150);
  setTimeout(C.autoCenter, 400);
};

// ============================================================
// REORGANIZE WITH AI — the member says what they want, answers a few questions,
// and the server rewrites the map (one AI action). The map as it was is kept on
// the server, so it can be brought back from this dialog.
// ============================================================
const reorg = { step: 'ask', questions: [], questionsFor: '', busy: false };
function reorgError(msg) { const el = $('nmm-reorg-error'); el.textContent = msg || ''; el.style.display = msg ? 'block' : 'none'; }
function reorgStep(step) {
  reorg.step = step;
  ['ask', 'questions', 'working'].forEach(function(s) { $('nmm-reorg-step-' + s).style.display = s === step ? '' : 'none'; });
  $('nmm-reorg-actions').style.display = step === 'working' ? 'none' : '';
  $('nmm-reorg-cancel').textContent = step === 'questions' ? 'Back' : 'Cancel';
  $('nmm-reorg-next').textContent = step === 'questions' ? 'Rewrite my map' : 'Next';
  $('nmm-reorg-next').disabled = false;
}
function allowanceText(ai) {
  if (!ai) return '';
  if (!ai.limit) return 'No limit for admins. AI actions this week: ' + ai.used + '.';
  const left = Math.max(0, ai.limit - ai.used);
  if (!left) return 'You have used your ' + ai.limit + ' AI actions for this ' + ai.period + '. Your next one is available on ' + ai.next + '.';
  return 'Uses 1 of your AI actions. You have ' + left + ' of ' + ai.limit + ' left this ' + ai.period + '.';
}
window.nmmOpenReorganize = function() {
  layoutMenu.classList.remove('open');
  if (!MAP_ID) return;
  reorgError(''); reorgStep('ask');
  $('nmm-reorg-allowance').textContent = '';
  $('nmm-reorg-restore').style.display = 'none';
  $('nmm-reorganize-modal').classList.add('open');
  setTimeout(function() { $('nmm-reorg-wish').focus(); }, 50);
  api('GET', 'auth/me').then(function(me) {
    const ai = me && me.ai;
    $('nmm-reorg-allowance').textContent = allowanceText(ai);
    if (ai && ai.limit && ai.used >= ai.limit && reorg.step === 'ask') $('nmm-reorg-next').disabled = true; // none left: nothing to send
  }).catch(function() {});
  api('GET', 'maps/' + MAP_ID + '/reorganize/before').then(function(b) {
    if (!b || !b.at || !Array.isArray(b.nodes)) return;
    const el = $('nmm-reorg-restore');
    el.innerHTML = 'Want the earlier version back? <button type="button" class="nmm-link-btn">Bring back the map from before the last Reorganize</button> (' + NMMCore.esc(b.when) + ').';
    el.style.display = '';
    el.querySelector('button').onclick = function() { restoreBefore(b); };
  }).catch(function() {});
};
window.nmmCloseReorganize = function() { if (!reorg.busy) $('nmm-reorganize-modal').classList.remove('open'); };

document.querySelectorAll('.nmm-reorg-ideas .nmm-chip').forEach(function(chip) {
  chip.addEventListener('click', function() {
    const box = $('nmm-reorg-wish'), t = box.value.trim();
    box.value = t ? t.replace(/[.\s]*$/, '') + '. ' + chip.textContent : chip.textContent;
    box.focus();
  });
});
$('nmm-reorg-cancel').addEventListener('click', function() {
  reorgError('');
  if (reorg.step === 'questions') { reorgStep('ask'); return; }
  nmmCloseReorganize();
});
$('nmm-reorg-next').addEventListener('click', function() {
  reorgError('');
  if (reorg.step === 'questions') { runReorganize(); return; }
  const wish = $('nmm-reorg-wish').value.trim();
  if (!wish) { reorgError('Say what you would like changed first.'); return; }
  // Going back and forward with the same request doesn't ask (or pay) for new questions
  if (reorg.questionsFor === wish) { reorgStep('questions'); return; }
  const btn = this; btn.disabled = true; btn.textContent = 'Reading your map...';
  api('POST', 'maps/' + MAP_ID + '/reorganize/questions', { wish: wish }).then(function(data) {
    if (data && data.code) { reorgError(data.message); reorgStep('ask'); return; }
    showReorgQuestions(wish, (data && data.questions) || []);
  }).catch(function() { showReorgQuestions(wish, []); });
});
function showReorgQuestions(wish, questions) {
  reorg.questions = questions; reorg.questionsFor = wish;
  $('nmm-reorg-questions').innerHTML = questions.map(function(q, i) {
    return '<div class="nmm-field"><label for="nmm-reorg-a' + i + '">' + NMMCore.esc(q) + '</label>' +
      '<textarea id="nmm-reorg-a' + i + '" class="nmm-input" rows="2" placeholder="Your answer (optional)"></textarea></div>';
  }).join('');
  reorgStep('questions');
  const first = $('nmm-reorg-a0'); if (first) first.focus();
}

// Waits until what's on screen is saved, so the AI works on the same map
async function saveFirst() {
  for (let i = 0; i < 60; i++) {
    if (!isDirty && !saving) return true;
    if (!saving) await nmmSave();
    await new Promise(function(r) { setTimeout(r, 300); });
  }
  return !isDirty && !saving;
}

async function runReorganize() {
  const wish = $('nmm-reorg-wish').value.trim();
  const answers = reorg.questions.map(function(q, i) { const a = $('nmm-reorg-a' + i); return { question: q, answer: a ? a.value.trim() : '' }; });
  reorg.busy = true; reorgStep('working');
  try {
    if (!(await saveFirst())) throw new Error('Your latest changes could not be saved, so the map was not changed.');
    const data = await api('POST', 'maps/' + MAP_ID + '/reorganize', { wish: wish, answers: answers, base_rev: mapRev });
    if (!data || !Array.isArray(data.nodes)) throw new Error((data && data.message) || 'No cards came back, so the map was not changed.');
    applyReorganized(data.nodes);
    reorg.busy = false; reorg.questionsFor = '';
    $('nmm-reorg-wish').value = '';
    $('nmm-reorganize-modal').classList.remove('open');
    setStatus('Map reorganized ✓', true);
  } catch (e) {
    reorg.busy = false;
    // A timeout from the server comes back as an HTML page, which shows up here as a JSON error
    reorgError(e instanceof SyntaxError ? 'The AI took too long to answer, so the map was not changed. Please try again.' : e.message);
    reorgStep('questions');
  }
}

// The new cards keep everything the AI didn't send (colors, lanes, etc.) and get laid out fresh
function applyReorganized(fresh) {
  pushUndo();
  const old = {};
  nodes.forEach(function(n) { if (!n._readonly) old[n.id] = n; });
  const live = fresh.map(function(r) {
    const o = old[r.id];
    return Object.assign({}, o || { x: 0, y: 0 }, r, { collapsed: false, x: o ? o.x : 0, y: o ? o.y : 0 });
  });
  nodes = live.concat(nodes.filter(function(n) { return n._readonly; }));
  replaceCards(live);
}
function replaceCards(live) {
  if (live.length) (layoutStyle() === 'tol' && !hasSefirot() ? tidyLayout : LAYOUT_STYLES[layoutStyle()])(live);
  keepOnCanvas();
  C.render(); C.autoCenter();
  markDirty();
}
function restoreBefore(b) {
  if (!confirm('Bring back the map as it was before the last Reorganize (' + b.when + ')?\n\nThe cards on screen now are replaced. Undo brings them back.')) return;
  pushUndo();
  const live = clone(b.nodes);
  nodes = live.concat(nodes.filter(function(n) { return n._readonly; }));
  C.render(); C.autoCenter(); markDirty();
  $('nmm-reorganize-modal').classList.remove('open');
  setStatus('Earlier version restored ✓', true);
}

// ============================================================
// KEYBOARD + MODAL DISMISS
// ============================================================
function closeModals() { nmmCloseNodeModal(); nmmCloseSettings(); nmmCloseOutlineImport(); nmmCloseGCalModal(); nmmClosePromptExport(); nmmCloseReorganize(); }
document.addEventListener('keydown', function(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.target.closest('input,textarea')) { e.preventDefault(); nmmUndo(); return; }
  if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); nmmSave(); return; }
  if (e.key === 'Escape') closeModals();
  if (e.key === 'Enter' && $('nmm-node-modal').classList.contains('open') && e.target.tagName !== 'TEXTAREA') nmmSaveNode();
});
document.querySelectorAll('.nmm-modal-overlay').forEach(function(o) {
  o.addEventListener('click', function(e) { if (e.target === o) closeModals(); });
});

// The More menu (shared with Kanban, Timeline and Roadmap): exports, Google Calendar, Export Prompt
NMMMore.init({
  view: 'map',
  maps: function() { return [{ id: MAP_ID, title: mapTitle, category: mapCategory, layout: settings.layout, nodes: nodes }]; },
  image: function(print, name) { C.exportPNG(name, { print: print }); }
});

loadMap();
})();
