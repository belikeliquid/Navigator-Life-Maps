/* Navigator Mind Maps — Kanban board (/kanban/, /kanban/?map_id=N). Markup: templates/kanban.php
 * Cards marked as Kanban tasks (NMMCore.isTask) appear: islands, parents and children alike.
 * Untagged tasks sit in Triage; the tag decides the column. */
(function() {
const root = document.getElementById('nmm-kanban');
if (!root) return;

const api = NMMCore.api, esc = NMMCore.esc, TAG_COLORS = NMMCore.TAG_COLORS;
const $   = function(id) { return document.getElementById(id); };

let allMaps = [], selectedMapIds = [], draggingCard = null, kbEditNode = null;
const mapNodes = {}; // map id -> node array, loaded on demand

// Guidance only: nothing stops a card being dropped here. Matches limit_stages() in class-rest-api.php.
const LIMITS = {
  start:  { max: 5, warn: 'Too many tasks in Start will feel overwhelming. Keep the next 5 here and move the rest to Triage.' },
  doing:  { max: 5, warn: 'Too many tasks in Doing will slow down your progress. Finish some or move them back to Triage.' },
  sprint: { max: 3, warn: 'Too many items in Sprint will keep you from prioritizing. Choose the 3 that matter most.' },
  review: { max: 5, warn: 'Too many tasks in Review will hold up your progress. Clear a few before adding more.' }
};

async function loadAll() {
  allMaps = (await api('GET', 'maps')) || [];
  const fromUrl = NMMCore.mapIdsFromUrl().filter(function(id) { return allMaps.some(function(m) { return m.id === id; }); });
  NMMCore.mapFilter({
    button: $('nmm-kb-filter-btn'), panel: $('nmm-kb-filter-panel'), list: $('nmm-kb-filter-list'),
    maps: allMaps,
    selected: fromUrl.length ? fromUrl : allMaps.map(function(m) { return m.id; }),
    onChange: function(ids) { selectedMapIds = ids; updateLinks(); loadSelected().then(renderBoard); }
  });
}

// Only fetch the maps on the board; others load when they are ticked.
async function loadSelected() {
  const missing = selectedMapIds.filter(function(id) { return !mapNodes[id]; });
  if (missing.length) $('nmm-kb-loading').style.display = 'flex';
  await Promise.all(missing.map(async function(id) {
    const full = await api('GET', 'maps/' + id);
    const map  = allMaps.find(function(m) { return m.id === id; });
    mapNodes[id] = ((full && full.nodes) || []).filter(function(n) { return !n._readonly; }).map(function(n) {
      n._map_id = id; n._map_title = map ? map.title : ''; return n;
    });
  }));
  $('nmm-kb-loading').style.display = 'none';
}

// Keep the URL, the Mind Map button and the Timeline link on the same maps.
function updateLinks() {
  const all = selectedMapIds.length === allMaps.length;
  const ids = all ? [] : selectedMapIds;
  history.replaceState(null, '', NMMCore.pageUrl(NMM.kanbanUrl, ids));
  $('nmm-kb-timeline-link').href = NMMCore.pageUrl(NMM.timelineUrl, ids);
  const mapLink = $('nmm-kb-map-link');
  if (selectedMapIds.length === 1) {
    mapLink.href = NMM.editorUrl + '?map_id=' + selectedMapIds[0];
    mapLink.style.display = '';
  } else {
    mapLink.style.display = 'none';
  }
}

function selectedNodes() {
  return selectedMapIds.reduce(function(acc, id) { return acc.concat((mapNodes[id] || []).filter(NMMCore.isTask)); }, []);
}

// "Work › Divination" — where the card sits in its map
function pathOf(n) {
  const list = mapNodes[n._map_id] || [], names = [];
  let p = list.find(function(x) { return x.id === n.parent; });
  while (p && names.length < 4) { names.unshift(p.title); p = list.find(function(x) { return x.id === p.parent; }); }
  return names.join(' › ');
}

function renderBoard() {
  const nodes = selectedNodes();
  const triage = $('nmm-kb-triage');
  triage.innerHTML = '';
  document.querySelectorAll('.nmm-kb-col-cards').forEach(function(c) { c.innerHTML = ''; });

  const has = nodes.length > 0;
  $('nmm-kb-empty').style.display       = has ? 'none' : 'block';
  $('nmm-kb-triage-wrap').style.display = has ? 'block' : 'none';
  $('nmm-kb-columns').style.display     = has ? 'grid' : 'none';

  nodes.forEach(function(n) {
    const zone = n.tag ? document.querySelector('.nmm-kb-col-cards[data-tag="' + n.tag.toLowerCase() + '"]') : null;
    (zone || triage).appendChild(makeCard(n));
  });
  showColumnCounts();
}

function showColumnCounts() {
  document.querySelectorAll('.nmm-kb-col').forEach(function(col) {
    const limit = LIMITS[col.dataset.tag];
    const count = col.querySelectorAll('.nmm-kb-card').length;
    const over  = !!limit && count > limit.max;
    col.classList.toggle('nmm-kb-col-over', over);
    col.querySelector('.nmm-kb-col-count').textContent = limit ? count + ' / ' + limit.max : count;
    const warn = col.querySelector('.nmm-kb-col-warn');
    warn.textContent = over ? '⚠ ' + limit.warn : '';
    warn.style.display = over ? '' : 'none';
  });
}

function makeCard(n) {
  const card = document.createElement('div');
  card.className = 'nmm-kb-card';
  card.dataset.nodeId = n.id; card.dataset.mapId = n._map_id;
  card.draggable = true;

  const tag     = TAG_COLORS[n.tag];
  const badge   = tag ? '<span class="nmm-kb-badge" style="background:' + tag.bg + ';color:' + tag.text + '">' + tag.label + '</span>' : '';
  const sefirot = n.sefirot ? '<span class="nmm-kb-sefirot-badge">✦ ' + esc(n.sefirot) + '</span>' : '';
  const path    = pathOf(n);
  const where   = [selectedMapIds.length > 1 ? n._map_title : '', path].filter(Boolean).join(' · ');

  card.innerHTML =
    '<div class="nmm-kb-card-header"><span class="nmm-kb-card-title">' + esc(n.title) + '</span>' + badge + sefirot + '</div>' +
    ((n.subs && n.subs.length) ? '<div class="nmm-kb-subs">' + esc(n.subs.slice(0, 2).join(' · ')) + '</div>' : '') +
    (n.date ? '<div class="nmm-kb-date">📅 ' + esc(n.date) + '</div>' : '') +
    (where ? '<div class="nmm-kb-map-label">' + esc(where) + '</div>' : '');

  card.addEventListener('click', function() { openKbEdit(n); });
  card.addEventListener('dragstart', function(e) { draggingCard = card; card.style.opacity = '0.5'; e.dataTransfer.effectAllowed = 'move'; });
  card.addEventListener('dragend', function() {
    card.style.opacity = '1'; draggingCard = null;
    document.querySelectorAll('.nmm-kb-over').forEach(function(z) { z.classList.remove('nmm-kb-over'); });
  });
  return card;
}

function findNode(mapId, nodeId) {
  return (mapNodes[mapId] || []).find(function(x) { return x.id === nodeId; });
}

// Drop zones exist once in the markup, so wire them once.
document.querySelectorAll('.nmm-kb-col-cards,.nmm-kb-triage-cards').forEach(function(zone) {
  zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.classList.add('nmm-kb-over'); });
  zone.addEventListener('dragleave', function() { zone.classList.remove('nmm-kb-over'); });
  zone.addEventListener('drop', async function(e) {
    e.preventDefault(); zone.classList.remove('nmm-kb-over');
    if (!draggingCard) return;
    const card = draggingCard, n = findNode(+card.dataset.mapId, card.dataset.nodeId);
    if (!n) return;
    const oldTag = n.tag || '', newTag = zone.dataset.tag || '';
    if (oldTag === newTag) return;
    n.tag = newTag; renderBoard();
    try {
      await NMMCore.patchNode(n._map_id, n.id, { tag: newTag });
    } catch(err) {
      n.tag = oldTag; renderBoard();
      alert('Could not move "' + n.title + '": ' + err.message);
    }
  });
});

// ============================================================
// EDIT MODAL — click a card
// ============================================================
function openKbEdit(n) {
  kbEditNode = n;
  $('nmm-kb-title').value   = n.title || '';
  $('nmm-kb-sub').value     = (n.subs || []).join('\n');
  $('nmm-kb-date').value    = n.date || '';
  $('nmm-kb-tag').value     = n.tag || '';
  $('nmm-kb-task').checked  = true;
  $('nmm-kb-sefirot').value = n.sefirot || '';
  $('nmm-kb-open-map').href = NMM.editorUrl + '?map_id=' + n._map_id;
  $('nmm-kb-edit-modal').classList.add('open');
}
window.nmmKbCloseEdit = function() { $('nmm-kb-edit-modal').classList.remove('open'); kbEditNode = null; };
window.nmmKbSaveEdit = async function() {
  const n = kbEditNode; if (!n) return;
  const btn = document.querySelector('#nmm-kb-edit-modal .nmm-btn-primary');
  const fields = {
    title:   $('nmm-kb-title').value.trim() || n.title,
    subs:    $('nmm-kb-sub').value.trim().split('\n').filter(function(s) { return s.trim(); }),
    date:    $('nmm-kb-date').value || '',
    task:    $('nmm-kb-task').checked,
    tag:     $('nmm-kb-task').checked ? $('nmm-kb-tag').value || '' : '',
    sefirot: $('nmm-kb-sefirot').value || ''
  };
  btn.textContent = 'Saving...'; btn.disabled = true;
  try {
    await NMMCore.patchNode(n._map_id, n.id, fields);
    Object.assign(n, fields);
    nmmKbCloseEdit(); renderBoard();
  } catch(err) {
    alert('Save failed: ' + err.message);
  }
  btn.textContent = 'Save'; btn.disabled = false;
};
$('nmm-kb-edit-modal').addEventListener('click', function(e) { if (e.target === this) nmmKbCloseEdit(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') nmmKbCloseEdit(); });

loadAll();
})();
