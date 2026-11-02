/* Navigator Mind Maps — timeline (/timeline/, /timeline/?map_id=N). Markup: templates/timeline.php
 * Dated cards sit at their date; undated cards are spread evenly. Drag a card sideways to reschedule. */
(function() {
const root = document.getElementById('nmm-timeline');
if (!root) return;

const api = NMMCore.api, esc = NMMCore.esc, TAG_COLORS = NMMCore.TAG_COLORS;
const $   = function(id) { return document.getElementById(id); };

const KANBAN_ORDER = { done: 0, review: 1, sprint: 2, doing: 3, start: 4, '': 5 };
const NODE_W = 180, NODE_H = 80, SPINE_Y = 340, PAD_L = 80, PAD_R = 80;

let allMaps = [], selectedMapIds = [], editNode = null;
const mapNodes = {}; // map id -> node array, loaded on demand

// Default range: today to 30 days out
let tlStart = new Date(), tlEnd = new Date();
tlEnd.setDate(tlEnd.getDate() + 30);
$('nmm-tl-start').value = tlStart.toISOString().split('T')[0];
$('nmm-tl-end').value   = tlEnd.toISOString().split('T')[0];

async function loadAll() {
  allMaps = (await api('GET', 'maps')) || [];
  const fromUrl = NMMCore.mapIdsFromUrl().filter(function(id) { return allMaps.some(function(m) { return m.id === id; }); });
  NMMCore.mapFilter({
    button: $('nmm-tl-filter-btn'), panel: $('nmm-tl-filter-panel'), list: $('nmm-tl-filter-list'),
    maps: allMaps,
    selected: fromUrl.length ? fromUrl : allMaps.map(function(m) { return m.id; }),
    onChange: function(ids) { selectedMapIds = ids; updateLinks(); loadSelected().then(renderTimeline); }
  });
}

async function loadSelected() {
  const missing = selectedMapIds.filter(function(id) { return !mapNodes[id]; });
  if (missing.length) $('nmm-tl-loading').style.display = 'flex';
  await Promise.all(missing.map(async function(id) {
    const full = await api('GET', 'maps/' + id);
    const map  = allMaps.find(function(m) { return m.id === id; }) || {};
    mapNodes[id] = ((full && full.nodes) || []).filter(function(n) { return !n._readonly; }).map(function(n) {
      n._map_id = id; n._map_title = map.title || ''; n._map_color = map.color || '#0BBFBF'; return n;
    });
  }));
  $('nmm-tl-loading').style.display = 'none';
}

function updateLinks() {
  const ids = selectedMapIds.length === allMaps.length ? [] : selectedMapIds;
  history.replaceState(null, '', NMMCore.pageUrl(NMM.timelineUrl, ids));
  $('nmm-tl-kanban-link').href = NMMCore.pageUrl(NMM.kanbanUrl, ids);
  const mapLink = $('nmm-tl-map-link');
  mapLink.style.display = selectedMapIds.length === 1 ? '' : 'none';
  if (selectedMapIds.length === 1) mapLink.href = NMM.editorUrl + '?map_id=' + selectedMapIds[0];
}

$('nmm-tl-apply-btn').addEventListener('click', function() {
  const s = $('nmm-tl-start').value, e = $('nmm-tl-end').value;
  if (!s || !e) { alert('Please set both a start and end date.'); return; }
  if (new Date(s) >= new Date(e)) { alert('End date must be after start date.'); return; }
  tlStart = new Date(s); tlEnd = new Date(e);
  renderTimeline();
});

function renderTimeline() {
  const canvas = $('nmm-tl-canvas'), markers = $('nmm-tl-markers'), spine = $('nmm-tl-spine'), nodesEl = $('nmm-tl-nodes');
  markers.innerHTML = ''; spine.innerHTML = ''; nodesEl.innerHTML = '';

  const nodes = selectedMapIds.reduce(function(acc, id) { return acc.concat(mapNodes[id] || []); }, []);
  $('nmm-tl-canvas-wrap').style.display = nodes.length ? 'block' : 'none';
  $('nmm-tl-empty').style.display       = nodes.length ? 'none' : 'block';
  if (!nodes.length) return;

  const canvasW = Math.max(window.innerWidth - 40, 1200), usable = canvasW - PAD_L - PAD_R, span = tlEnd - tlStart;
  canvas.style.width = canvasW + 'px';

  // Sort by date, then by Kanban stage
  nodes.sort(function(a, b) {
    const ka = KANBAN_ORDER[a.tag || ''] ?? 5, kb = KANBAN_ORDER[b.tag || ''] ?? 5;
    if (a.date && b.date) return a.date.localeCompare(b.date) || ka - kb;
    if (a.date) return -1;
    if (b.date) return 1;
    return ka - kb;
  });

  const undated = nodes.filter(function(n) { return !n.date; });
  undated.forEach(function(n, i) {
    const frac = undated.length > 1 ? (i + 1) / (undated.length + 1) : 0.5;
    n._x = PAD_L + frac * usable;
  });
  nodes.filter(function(n) { return n.date; }).forEach(function(n) {
    const x = PAD_L + ((new Date(n.date) - tlStart) / span) * usable;
    n._x = Math.max(PAD_L, Math.min(canvasW - PAD_R - NODE_W, x));
  });

  // Alternate above / below the spine
  nodes.forEach(function(n, i) {
    n._above = i % 2 === 0;
    n._y = n._above ? SPINE_Y - NODE_H - 60 - (Math.floor(i / 2) % 2) * 40 : SPINE_Y + 60 + (Math.floor(i / 2) % 2) * 40;
  });
  const maxY = Math.max.apply(null, nodes.map(function(n) { return n._y + NODE_H; }).concat(SPINE_Y + 80));
  const minY = Math.min.apply(null, nodes.map(function(n) { return n._y; }).concat(SPINE_Y - 80));
  canvas.style.height = (maxY - minY + 80) + 'px';
  const yOff = Math.max(0, -minY + 40);
  canvas.style.paddingTop = yOff + 'px';

  spine.style.cssText = 'position:absolute;top:' + (SPINE_Y + yOff) + 'px;left:' + PAD_L + 'px;right:' + PAD_R + 'px;height:2px;background:var(--nmm-teal);opacity:0.4;';
  for (let i = 0; i <= 6; i++) {
    const frac = i / 6, d = new Date(tlStart.getTime() + frac * span);
    const mk = document.createElement('div');
    mk.style.cssText = 'position:absolute;left:' + (PAD_L + frac * usable) + 'px;top:' + (SPINE_Y + yOff - 8) + 'px;transform:translateX(-50%);';
    mk.innerHTML = '<div style="width:1px;height:16px;background:rgba(11,191,191,0.3);margin:0 auto"></div>' +
      '<div style="font-size:10.5px;color:#6b7280;white-space:nowrap;text-align:center;margin-top:2px">' +
      d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + '</div>';
    markers.appendChild(mk);
  }

  nodes.forEach(function(n) {
    const tc = TAG_COLORS[n.tag || ''];
    const el = document.createElement('div');
    el.className = 'nmm-tl-node';
    el.style.cssText = 'position:absolute;left:' + n._x + 'px;top:' + (n._y + yOff) + 'px;width:' + NODE_W + 'px;min-height:' + NODE_H + 'px;';
    el.style.borderColor = n._map_color;
    el.innerHTML =
      '<div style="font-size:12.5px;font-weight:600;color:#011c2f;line-height:1.3">' + esc(n.title) + '</div>' +
      (n.subs && n.subs.length ? '<div style="font-size:11px;color:#4b5563;margin-top:2px">' + esc(n.subs.slice(0, 2).join(' · ')) + '</div>' : '') +
      (n.date ? '<div data-date style="font-size:10.5px;color:#078f8f;margin-top:2px;font-weight:600">📅 ' + esc(n.date) + '</div>' : '') +
      (tc ? '<span style="display:inline-block;background:' + tc.bg + ';color:' + tc.text + ';font-size:9.5px;font-weight:600;padding:1px 6px;border-radius:3px;letter-spacing:0.05em;text-transform:uppercase;margin-top:3px">' + tc.label + '</span>' : '') +
      '<div style="font-size:10px;color:#6b7280;margin-top:3px">' + esc(n._map_title) + '</div>';

    const conn = document.createElement('div');
    const bottom = n._above ? n._y + yOff + NODE_H : n._y + yOff, spineTop = SPINE_Y + yOff;
    conn.style.cssText = 'position:absolute;left:' + (n._x + NODE_W / 2) + 'px;top:' + Math.min(bottom, spineTop) + 'px;width:1px;height:' + Math.abs(spineTop - bottom) + 'px;background:rgba(11,191,191,0.35);border-left:1px dashed rgba(11,191,191,0.5);';
    nodesEl.appendChild(conn);

    // Drag sideways to reschedule; a plain click opens the editor
    el.addEventListener('mousedown', function(e) {
      if (e.button !== 0) return;
      e.preventDefault();
      const startX = e.clientX, origX = n._x;
      let moved = false, newDate = n.date;
      function onMove(ev) {
        const x = Math.max(PAD_L, Math.min(canvasW - PAD_R - NODE_W, origX + ev.clientX - startX));
        if (Math.abs(ev.clientX - startX) > 3) moved = true;
        newDate = new Date(tlStart.getTime() + ((x - PAD_L) / usable) * span).toISOString().split('T')[0];
        n._x = x; el.style.left = x + 'px'; conn.style.left = (x + NODE_W / 2) + 'px';
      }
      function onUp() {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        if (!moved) { openEdit(n); return; }
        const oldDate = n.date;
        n.date = newDate; renderTimeline();
        NMMCore.patchNode(n._map_id, n.id, { date: newDate }).catch(function(err) {
          n.date = oldDate; renderTimeline();
          alert('Could not reschedule "' + n.title + '": ' + err.message);
        });
      }
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });
    nodesEl.appendChild(el);
  });
}

function openEdit(n) {
  editNode = n;
  $('nmm-tl-title').value = n.title || '';
  $('nmm-tl-sub').value   = (n.subs || []).join('\n');
  $('nmm-tl-date').value  = n.date || '';
  $('nmm-tl-tag').value   = n.tag || '';
  $('nmm-tl-edit-modal').classList.add('open');
}
window.nmmTlCloseEdit = function() { $('nmm-tl-edit-modal').classList.remove('open'); editNode = null; };
window.nmmTlSaveEdit = async function() {
  const n = editNode; if (!n) return;
  const btn = document.querySelector('#nmm-tl-edit-modal .nmm-btn-primary');
  const fields = {
    title: $('nmm-tl-title').value.trim() || n.title,
    subs:  $('nmm-tl-sub').value.trim().split('\n').filter(function(s) { return s.trim(); }),
    date:  $('nmm-tl-date').value || '',
    tag:   $('nmm-tl-tag').value || ''
  };
  btn.textContent = 'Saving...'; btn.disabled = true;
  try {
    await NMMCore.patchNode(n._map_id, n.id, fields);
    Object.assign(n, fields);
    nmmTlCloseEdit(); renderTimeline();
  } catch(err) {
    alert('Save failed: ' + err.message);
  }
  btn.textContent = 'Save'; btn.disabled = false;
};
$('nmm-tl-edit-modal').addEventListener('click', function(e) { if (e.target === this) nmmTlCloseEdit(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') nmmTlCloseEdit(); });

loadAll();
})();
