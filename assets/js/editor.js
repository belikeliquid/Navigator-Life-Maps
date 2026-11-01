/* Navigator Mind Maps — map editor (/map-editor/?map_id=N). Markup: templates/editor.php */
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

const C = NMMCanvas.create({
  getNodes: function() { return nodes; },
  editable: true,
  selectedId: function() { return selectedId; },
  defaults: NMM.defaults,
  decorate: decorateNode
});
const find = C.find;

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
  nodes         = map.nodes    || [];
  settings      = map.settings || {};
  mapTitle      = map.title    || 'Untitled';
  mapCategory   = map.category || '';
  linkedIslands = map.islands  || [];
  mapRev        = map.rev      || 0;
  let changed   = restoreDraft(map);

  // New maps arrive empty. Outline mode passes its text in the URL.
  if (!nodes.length) {
    nodes = [{ id: 'c' + Date.now(), x: 800, y: 400, title: mapTitle, subs: [], parent: null, collapsed: false, date: '', tag: '', sefirot: '' }];
    const outline = new URLSearchParams(location.search).get('outline');
    if (outline) addOutline(parseOutline(outline));
    changed = true;
  }
  if (new URLSearchParams(location.search).has('outline')) history.replaceState(null, '', NMM.editorUrl + '?map_id=' + MAP_ID);

  const titleEl = $('nmm-map-title-display');
  titleEl.textContent = mapTitle;
  titleEl.onclick = function() {
    const t = prompt('Rename map:', mapTitle);
    if (t && t.trim()) { mapTitle = t.trim(); this.textContent = mapTitle; markDirty(); }
  };

  C.setSettings(settings);
  await C.loadLinkedIslands(linkedIslands, nodes);
  C.render();
  C.autoCenter();
  if (changed) markDirty();
}

function renderEmpty() {
  $('nmm-canvas').innerHTML = '<div style="padding:60px;text-align:center;color:#999;font-family:Barlow Condensed,sans-serif;font-size:18px;">Map not found. <a href="' + NMM.mapsUrl + '">Return to maps</a>.</div>';
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
    const r = await fetch(NMM.restUrl + 'maps/' + MAP_ID, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NMM.nonce },
      body: JSON.stringify(Object.assign(saveBody(), { base_rev: mapRev }))
    });
    const data = await r.json();
    saving = false;
    if (r.status === 409) { resolveConflict(data); return; }
    if (!data.id) throw new Error('bad response');
    mapRev = data.rev;
    if (seq === changeSeq) { isDirty = false; clearDraft(); setStatus('Saved ✓', true); setTimeout(function() { if (!isDirty) setStatus(''); }, 3000); }
    else saveDraft(saveBody()); // edits made during the save: keep the draft current for the next save
    if (saveQueued || seq !== changeSeq) { saveQueued = false; nmmSave(); }
  } catch(e) {
    saving = false;
    setStatus('Save failed — retrying...');
    saveTimer = setTimeout(function() { nmmSave(true); }, 5000);
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
  dragState = { id: id, moved: false, startMX: e.clientX + wrap.scrollLeft, startMY: e.clientY + wrap.scrollTop, startNX: n.x, startNY: n.y };
  selectedId = id;
  document.addEventListener('mousemove', onDrag);
  document.addEventListener('mouseup', endDrag);
}
function onDrag(e) {
  if (!dragState) return;
  const dx = (e.clientX + wrap.scrollLeft) - dragState.startMX, dy = (e.clientY + wrap.scrollTop) - dragState.startMY;
  if (Math.abs(dx) > 3 || Math.abs(dy) > 3) dragState.moved = true;
  if (!dragState.moved) return;
  const n = find(dragState.id);
  n.x = Math.max(10, dragState.startNX + dx); n.y = Math.max(10, dragState.startNY + dy);
  const el = C.getEl(n.id); if (el) { el.style.left = n.x + 'px'; el.style.top = n.y + 'px'; }
  document.querySelectorAll('.nmm-node.nmm-drop-target').forEach(function(x) { x.classList.remove('nmm-drop-target'); });
  const t = getDropTarget(e.clientX + wrap.scrollLeft, e.clientY + wrap.scrollTop, dragState.id);
  if (t) { const tel = C.getEl(t); if (tel) tel.classList.add('nmm-drop-target'); }
  C.drawLines();
}
function endDrag(e) {
  if (dragState && dragState.moved) {
    const t = getDropTarget(e.clientX + wrap.scrollLeft, e.clientY + wrap.scrollTop, dragState.id);
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
  if (e.touches.length !== 1) return;
  const touch = e.touches[0], now = Date.now();
  if (now - lastTap < 300) { nmmOpenNodeModal(nodeId); lastTap = 0; return; }
  lastTap = now;
  if (ro) return;
  const n = find(nodeId); if (!n) return;
  touchDragState = { id: nodeId, moved: false, startTX: touch.clientX + wrap.scrollLeft, startTY: touch.clientY + wrap.scrollTop, startNX: n.x, startNY: n.y };
  e.stopPropagation();
}
function handleTouchMove(e) {
  if (!touchDragState || e.touches.length !== 1) return;
  e.preventDefault();
  const touch = e.touches[0];
  const dx = (touch.clientX + wrap.scrollLeft) - touchDragState.startTX, dy = (touch.clientY + wrap.scrollTop) - touchDragState.startTY;
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
  if (!panState || e.target.closest('.nmm-node')) return;
  wrap.scrollLeft = panState.sl + (panState.x - e.touches[0].clientX);
  wrap.scrollTop  = panState.st + (panState.y - e.touches[0].clientY);
}, { passive: true });
wrap.addEventListener('touchend', function() { panState = null; });

// ============================================================
// COLLAPSE / EXPAND / CENTER
// ============================================================
function toggleCollapse(id) { const n = find(id); if (n) { pushUndo(); n.collapsed = !n.collapsed; markDirty(); C.render(); } }
window.nmmExpandAll   = function() { pushUndo(); nodes.forEach(function(n) { n.collapsed = false; }); markDirty(); C.render(); };
window.nmmCollapseAll = function() { pushUndo(); nodes.forEach(function(n) { if (C.hasChildren(n.id)) n.collapsed = true; }); markDirty(); C.render(); };
window.nmmCenter      = function() { C.autoCenter(); };

// ============================================================
// HARMONY — nudges cards toward a tidy layout; Tree of Life mode when sefirot are set
// ============================================================
const SEFIROT_POSITIONS = {
  kether: { x: 900, y: 120 }, chochma: { x: 580, y: 280 }, binah: { x: 1220, y: 280 },
  chesed: { x: 580, y: 520 }, geburah: { x: 1220, y: 520 }, tiphereth: { x: 900, y: 660 },
  netzach: { x: 580, y: 860 }, hod: { x: 1220, y: 860 }, yesod: { x: 900, y: 1020 }, malkuth: { x: 900, y: 1220 }
};
window.nmmHarmony = function() {
  pushUndo();
  if (nodes.some(function(n) { return n.sefirot && !n._readonly; })) {
    nodes.forEach(function(n) {
      const pos = !n._readonly && SEFIROT_POSITIONS[n.sefirot];
      if (!pos) return;
      n.x += (pos.x - n.x) * 0.3; // 30% of the way each press
      n.y += (pos.y - n.y) * 0.3;
    });
  } else {
    const T = 0.20, M = 200;
    const center = nodes.find(function(n) { return !n.parent; }); if (!center) return;
    const spineX = center.x + 80, rc = nodes.filter(function(n) { return n.parent === center.id; });
    const spine = rc.filter(function(n) { return Math.abs((n.x + 90) - spineX) < 150; });
    const left  = rc.filter(function(n) { return !spine.includes(n) && n.x < center.x; });
    const right = rc.filter(function(n) { return !spine.includes(n) && n.x >= center.x; });
    const nudge = function(n, tx, ty) { n.x += Math.max(-M, Math.min(M, (tx - n.x) * T)); n.y += Math.max(-M, Math.min(M, (ty - n.y) * T)); };
    const kids = function(pid, dir, lv) {
      const p = find(pid), ch = nodes.filter(function(n) { return n.parent === pid && !n._readonly; });
      ch.forEach(function(n, i) {
        let tx, ty;
        if (dir === 0) { const s = (i % 2 === 0) ? -1 : 1; tx = p.x + s * (180 + lv * 20); ty = p.y + Math.floor(i / 2) * 140; }
        else { tx = p.x + dir * (180 + lv * 30); ty = p.y + (i - (ch.length - 1) / 2) * 130; }
        nudge(n, tx, ty); kids(n.id, dir, lv + 1);
      });
    };
    const vs = 170, ho = 340;
    spine.forEach(function(n, i) { nudge(n, spineX - 90, center.y + 100 + i * vs); kids(n.id, 0, 1); });
    left.forEach(function(n, i) { nudge(n, spineX - ho - 140, center.y - (left.length - 1) * vs / 2 + i * vs); kids(n.id, -1, 1); });
    right.forEach(function(n, i) { nudge(n, spineX + ho, center.y - (right.length - 1) * vs / 2 + i * vs); kids(n.id, 1, 1); });
  }
  markDirty(); C.render();
};

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
  $('nme-tag').value     = n.tag || '';
  $('nme-sefirot').value = n.sefirot || '';
  buildParentDropdown(id);
  $('nme-parent').value = n.parent || '';
  $('nmm-node-modal').classList.add('open');
  setTimeout(function() { $('nme-title').focus(); }, 50);
};
window.nmmCloseNodeModal = function() { $('nmm-node-modal').classList.remove('open'); editingId = null; };
window.nmmSaveNode = function() {
  const n = find(editingId);
  if (!n) { nmmCloseNodeModal(); return; }
  pushUndo();
  const t = $('nme-title').value.trim(); if (t) n.title = t;
  const sub = $('nme-sub').value.trim();
  n.subs    = sub ? sub.split('\n').filter(function(s) { return s.trim(); }) : [];
  n.date    = $('nme-date').value || '';
  n.tag     = $('nme-tag').value || '';
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
window.nmmExportPNG = function() { C.exportPNG('mindmap.png'); };

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
// GOOGLE CALENDAR (.ics) EXPORT
// ============================================================
window.nmmOpenGCalModal = function() {
  const dated = nodes.filter(function(n) { return n.date && !n._readonly; });
  if (!dated.length) { alert('No nodes have dates. Edit nodes and add dates first.'); return; }
  const list = $('nmm-gcal-list'); list.innerHTML = '';
  dated.forEach(function(n) {
    const row = document.createElement('div');
    row.className = 'nmm-gcal-item';
    row.innerHTML = '<input type="checkbox" id="gcal_' + n.id + '" data-nid="' + n.id + '" checked />' +
      '<label for="gcal_' + n.id + '"><strong>' + NMMCore.esc(n.title) + '</strong><span class="nmm-gcal-date"> · 📅 ' + n.date + '</span></label>';
    list.appendChild(row);
  });
  $('nmm-gcal-modal').classList.add('open');
};
window.nmmCloseGCalModal = function() { $('nmm-gcal-modal').classList.remove('open'); };
window.nmmDoGCalExport = function() {
  const checked = document.querySelectorAll('#nmm-gcal-list input[type=checkbox]:checked');
  if (!checked.length) { alert('No nodes selected.'); return; }
  const lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//NavigatorMindMaps//EN'];
  checked.forEach(function(cb) {
    const n = find(cb.dataset.nid); if (!n) return;
    const d = n.date.replace(/-/g, '');
    lines.push('BEGIN:VEVENT', 'DTSTART;VALUE=DATE:' + d, 'DTEND;VALUE=DATE:' + d, 'SUMMARY:' + n.title, 'DESCRIPTION:' + (n.subs || []).join('\\n'), 'END:VEVENT');
  });
  lines.push('END:VCALENDAR');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([lines.join('\r\n')], { type: 'text/calendar' }));
  a.download = 'mindmap_events.ics'; a.click();
  nmmCloseGCalModal();
};

// ============================================================
// KEYBOARD + MODAL DISMISS
// ============================================================
function closeModals() { nmmCloseNodeModal(); nmmCloseSettings(); nmmCloseOutlineImport(); nmmCloseGCalModal(); }
document.addEventListener('keydown', function(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.target.closest('input,textarea')) { e.preventDefault(); nmmUndo(); return; }
  if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); nmmSave(); return; }
  if (e.key === 'Escape') closeModals();
  if (e.key === 'Enter' && $('nmm-node-modal').classList.contains('open') && e.target.tagName !== 'TEXTAREA') nmmSaveNode();
});
document.querySelectorAll('.nmm-modal-overlay').forEach(function(o) {
  o.addEventListener('click', function(e) { if (e.target === o) closeModals(); });
});

loadMap();
})();
