/* Navigator Mind Maps — roadmap (/roadmap/?map_id=N). Markup: templates/roadmap.php
 * One map at a time. Each dated card is a bar from its date (the start) to its end date; a card
 * with no end gets a 3-week bar drawn fading out, since the end is only assumed.
 * Lanes are either phases the person names (settings.roadmap.lanes, card.lane) or the map's main
 * branches. Undated cards wait in the tray; dragging one onto a lane asks for its dates.
 * Saves go through NMMCore.patchNode / patchMap (fresh GET, then PUT), like Kanban and the timeline. */
(function() {
const root = document.getElementById('nmm-roadmap');
if (!root) return;

const api = NMMCore.api, esc = NMMCore.esc, TAG_COLORS = NMMCore.TAG_COLORS;
const $ = function(id) { return document.getElementById(id); };
const DAY_MS = 86400000, DEFAULT_DAYS = 21, LABEL_W = 190, BAR_H = 34, ROW_GAP = 8, LANE_PAD = 12, MIN_BAR_PX = 96;
const SCALES = { week: 26, month: 9, quarter: 3.2 }; // pixels per day
const PRESETS = {
  project: { label: 'Project',                                lanes: ['Organize', 'Research', 'Plan', 'Execute', 'Launch'] },
  launch:  { label: 'Launch (business, product or program)',  lanes: ['Research', 'Plan', 'Build', 'Launch', 'Grow'] },
  healing: { label: 'Healing',                                lanes: ['Awareness', 'Release', 'Practice', 'Integration', 'Embodiment'] },
  growth:  { label: 'Self-growth',                            lanes: ['Vision', 'Learn', 'Practice', 'Integrate', 'Share'] },
  career:  { label: 'Career path',                            lanes: ['Explore', 'Learn', 'Apply', 'Grow', 'Lead'] },
  blank:   { label: 'Blank',                                  lanes: ['Phase 1', 'Phase 2', 'Phase 3'] }
};
const STAGES = [['', 'No stage'], ['start', 'Start'], ['doing', 'Doing'], ['sprint', 'Sprint'], ['review', 'Review'], ['done', 'Done']];

let maps = [], map = null, nodes = [], rm = null, editable = true;
let view = null; // the layout of the last render: { start, ppd, lanes }

// ---------- dates ----------
const dayOf = NMMCore.dayOf, today = NMMCore.today;
function dayStr(d) { return new Date(d * DAY_MS).toISOString().slice(0, 10); }
function span(n) {
  const s = dayOf(n.date);
  if (s === null) return null;
  const e = n.end && dayOf(n.end) !== null && dayOf(n.end) >= s ? dayOf(n.end) : null;
  return { start: s, end: e === null ? s + DEFAULT_DAYS - 1 : e, assumed: e === null };
}

// ---------- map structure ----------
function find(id) { return nodes.find(function(n) { return n.id === id; }); }
function childrenOf(id) {
  return nodes.filter(function(n) { return (n.parent || null) === id; })
    .sort(function(a, b) { return Math.round(a.y / 100) - Math.round(b.y / 100) || a.x - b.x; });
}
function roots() { return nodes.filter(function(n) { return !n.parent || !find(n.parent); }); }
function pathOf(n) {
  const names = [];
  let p = find(n.parent);
  while (p && names.length < 4) { names.unshift(p.title); p = find(p.parent); }
  return names.join(' › ');
}
function lanes() {
  if (rm.group === 'branch') {
    const list = [];
    roots().forEach(function(r) { childrenOf(r.id).forEach(function(b) { list.push({ id: b.id, label: b.title }); }); });
    return list;
  }
  return rm.lanes || [];
}
function laneOf(n) {
  if (rm.group === 'branch') {
    let c = n, p = find(n.parent);
    if (!p) return '_top';
    while (p && p.parent && find(p.parent)) { c = p; p = find(p.parent); }
    return c.id;
  }
  return (rm.lanes || []).some(function(l) { return l.id === n.lane; }) ? n.lane : '_unsorted';
}
// Undated cards worth scheduling: Kanban tasks and cards with nothing under them (not headings)
function trayCards() {
  return nodes.filter(function(n) {
    return !n.date && n.parent && find(n.parent) && (NMMCore.isTask(n) || !nodes.some(function(c) { return c.parent === n.id; }));
  });
}

// ---------- loading ----------
async function load() {
  maps = (await api('GET', 'maps')) || [];
  if (!Array.isArray(maps)) maps = [];
  const wanted = NMMCore.mapIdsFromUrl()[0];
  const pick = maps.find(function(m) { return m.id === wanted; }) || maps[0];
  if (!pick) { $('nmm-rm-loading').style.display = 'none'; $('nmm-rm-empty').style.display = 'block'; return; }
  await openMap(pick.id);
}
async function openMap(id) {
  $('nmm-rm-loading').style.display = 'flex';
  const m = await api('GET', 'maps/' + id);
  $('nmm-rm-loading').style.display = 'none';
  if (!m || !m.id) { alert((m && m.message) || 'Could not open this map.'); return; }
  map = m; nodes = m.nodes || []; editable = m.can_edit !== false;
  rm = (m.settings && m.settings.roadmap) || null;
  if (!editable && !rm) rm = { group: 'branch', lanes: [] };
  history.replaceState(null, '', NMMCore.pageUrl(NMM.roadmapUrl, [id]));
  NMMCore.setViewTabs([id], [id]);
  $('nmm-rm-map-name').textContent = m.title;
  render(true);
}
function reloadFrom(saved) {
  if (!saved || !saved.id) return;
  map = saved; nodes = saved.nodes || [];
  rm = (saved.settings && saved.settings.roadmap) || rm;
  render();
}

// Map picker
NMMCore.dropdown($('nmm-rm-picker'), $('nmm-rm-picker-menu'), function() {
  $('nmm-rm-picker-menu').innerHTML = '<div class="nmm-menu-label" style="border:0;margin:0">Show the roadmap of</div>' +
    (maps.length ? maps.map(function(m) {
      return '<button type="button" role="menuitem" data-id="' + m.id + '"' + (map && m.id === map.id ? ' class="nmm-menu-current"' : '') + '>' +
        '<span class="nmm-menu-dot" style="background:' + esc(m.color || '#0BBFBF') + '"></span>' + esc(m.title) + '</button>';
    }).join('') : '<span class="nmm-menu-note">No maps yet</span>');
});
$('nmm-rm-picker-menu').addEventListener('click', function(e) {
  const b = e.target.closest('[data-id]');
  if (b) openMap(+b.dataset.id);
});

// ---------- settings: lanes and grouping ----------
async function saveRoadmap() {
  try {
    reloadFrom(await NMMCore.patchMap(map.id, function(m) { m.settings = m.settings || {}; m.settings.roadmap = rm; }));
  } catch (err) { alert('Could not save the roadmap: ' + err.message); }
}
function laneId() { return 'l' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6); }
// Applying a preset keeps existing lanes in place (renamed), so cards stay in the same position
function applyPreset(key) {
  const labels = PRESETS[key].lanes, old = (rm && rm.lanes) || [];
  rm = { group: 'phase', preset: key, lanes: labels.map(function(label, i) { return { id: old[i] ? old[i].id : laneId(), label: label }; }) };
  saveRoadmap();
}
function showSetup(show) {
  $('nmm-rm-setup').style.display = show ? 'block' : 'none';
  if (!show) return;
  $('nmm-rm-presets').innerHTML = Object.keys(PRESETS).map(function(k) {
    return '<button type="button" class="nmm-rm-preset" data-preset="' + k + '"><strong>' + esc(PRESETS[k].label) + '</strong><span>' +
      esc(PRESETS[k].lanes.join(' · ')) + '</span></button>';
  }).join('') +
    '<button type="button" class="nmm-rm-preset nmm-rm-preset-branch" data-preset="_branch"><strong>Use the map\'s branches</strong><span>' +
      esc(roots().reduce(function(a, r) { return a.concat(childrenOf(r.id).map(function(b) { return b.title; })); }, []).slice(0, 6).join(' · ') || 'Each main branch becomes a lane') + '</span></button>';
  $('nmm-rm-setup-cancel').style.display = rm ? '' : 'none';
}
$('nmm-rm-presets').addEventListener('click', function(e) {
  const b = e.target.closest('[data-preset]');
  if (!b) return;
  if (b.dataset.preset === '_branch') { rm = Object.assign({}, rm || { lanes: [] }, { group: 'branch' }); saveRoadmap(); }
  else applyPreset(b.dataset.preset);
});
$('nmm-rm-setup-cancel').addEventListener('click', function() { render(); });
$('nmm-rm-lanes-btn').addEventListener('click', function() { if (editable) { showSetup(true); $('nmm-rm-scroll').style.display = 'none'; $('nmm-rm-tray').style.display = 'none'; } });

$('nmm-rm-group').addEventListener('click', function(e) {
  const b = e.target.closest('[data-group]');
  if (!b || !rm || b.dataset.group === rm.group) return;
  if (b.dataset.group === 'phase' && !(rm.lanes || []).length) { rm.group = 'phase'; render(); showSetup(true); return; }
  rm.group = b.dataset.group;
  if (editable) saveRoadmap(); else render();
});

// Lane menu (phases only): rename, move, remove
let menuLane = null;
const laneMenu = $('nmm-rm-lane-menu');
function openLaneMenu(id, btn) {
  menuLane = id;
  const i = rm.lanes.findIndex(function(l) { return l.id === id; });
  laneMenu.innerHTML = '<button type="button" data-act="rename">✎ Rename lane</button>' +
    (i > 0 ? '<button type="button" data-act="up">↑ Move up</button>' : '') +
    (i < rm.lanes.length - 1 ? '<button type="button" data-act="down">↓ Move down</button>' : '') +
    '<button type="button" data-act="remove">Remove lane</button>';
  laneMenu.classList.add('open');
  const r = btn.getBoundingClientRect();
  laneMenu.style.top = r.bottom + 4 + 'px';
  laneMenu.style.left = Math.max(8, Math.min(r.left, innerWidth - laneMenu.offsetWidth - 8)) + 'px';
}
document.addEventListener('click', function(e) { if (!laneMenu.contains(e.target) && !e.target.closest('.nmm-rm-lane-btn')) laneMenu.classList.remove('open'); });
laneMenu.addEventListener('click', function(e) {
  const b = e.target.closest('[data-act]');
  if (!b) return;
  laneMenu.classList.remove('open');
  const i = rm.lanes.findIndex(function(l) { return l.id === menuLane; }), lane = rm.lanes[i];
  if (!lane) return;
  if (b.dataset.act === 'rename') {
    const t = prompt('Lane name:', lane.label);
    if (!t || !t.trim()) return;
    lane.label = t.trim();
  } else if (b.dataset.act === 'up' || b.dataset.act === 'down') {
    const j = b.dataset.act === 'up' ? i - 1 : i + 1;
    rm.lanes.splice(j, 0, rm.lanes.splice(i, 1)[0]);
  } else if (b.dataset.act === 'remove') {
    if (!confirm('Remove the lane "' + lane.label + '"? Its cards move to Unsorted.')) return;
    rm.lanes.splice(i, 1);
  }
  saveRoadmap();
});
function addLane() {
  const t = prompt('Name of the new lane:');
  if (!t || !t.trim()) return;
  rm.lanes = (rm.lanes || []).concat({ id: laneId(), label: t.trim() });
  saveRoadmap();
}

// Scale
function chosenScale() { try { return localStorage.getItem('nmm_rm_scale') || ''; } catch (e) { return ''; } }
$('nmm-rm-scale').addEventListener('click', function(e) {
  const b = e.target.closest('[data-scale]');
  if (!b) return;
  try { localStorage.setItem('nmm_rm_scale', b.dataset.scale); } catch (err) {}
  render();
});
$('nmm-rm-today').addEventListener('click', function() { scrollToDay(today(), 'smooth'); });
function scrollToDay(d) {
  if (!view) return;
  const sc = $('nmm-rm-scroll');
  sc.scrollLeft = Math.max(0, (d - view.start) * view.ppd - (sc.clientWidth - LABEL_W) / 3);
}

// ---------- rendering ----------
function render(first) {
  if (!map) return;
  laneMenu.classList.remove('open');
  if (!rm) { showSetup(true); $('nmm-rm-scroll').style.display = 'none'; $('nmm-rm-tray').style.display = 'none'; syncTools(); return; }
  showSetup(false);
  syncTools();
  const sc = $('nmm-rm-scroll');
  const keepX = sc.scrollLeft, keepY = sc.scrollTop;
  sc.style.display = '';

  // Bars and range
  const bars = nodes.map(function(n) { const s = span(n); return s && Object.assign({ n: n, lane: laneOf(n) }, s); }).filter(Boolean);
  const t = today();
  let start = t - 7, end = t + 56;
  bars.forEach(function(b) { start = Math.min(start, b.start - 7); end = Math.max(end, b.end + 14); });
  let scale = chosenScale();
  if (!SCALES[scale]) scale = end - start <= 80 ? 'week' : end - start <= 300 ? 'month' : 'quarter';
  const ppd = SCALES[scale];
  // Start on a Monday so week lines fall on whole weeks
  start -= (new Date(start * DAY_MS).getUTCDay() + 6) % 7;
  const days = end - start + 1, width = Math.ceil(days * ppd);
  $('nmm-rm-scale').querySelectorAll('[data-scale]').forEach(function(b) { b.classList.toggle('active', b.dataset.scale === scale); });

  // Lanes, with Unsorted / Whole map only when something is in them
  const laneList = lanes().slice();
  if (bars.some(function(b) { return b.lane === '_unsorted'; })) laneList.push({ id: '_unsorted', label: 'Unsorted', extra: true });
  if (bars.some(function(b) { return b.lane === '_top'; })) laneList.push({ id: '_top', label: 'Whole map', extra: true });

  // Pack each lane's bars into rows; narrow bars still need room for a title
  const minDays = Math.ceil(MIN_BAR_PX / ppd);
  laneList.forEach(function(l) {
    const mine = bars.filter(function(b) { return b.lane === l.id; }).sort(function(a, b) { return a.start - b.start || b.end - a.end; });
    const rowsEnd = [];
    mine.forEach(function(b) {
      const visEnd = Math.max(b.end, b.start + minDays - 1);
      let r = rowsEnd.findIndex(function(e) { return e < b.start; });
      if (r < 0) { r = rowsEnd.length; rowsEnd.push(0); }
      rowsEnd[r] = visEnd + 1;
      b.row = r;
    });
    l.bars = mine; l.rows = Math.max(1, rowsEnd.length);
  });
  view = { start: start, ppd: ppd, lanes: laneList };

  // Time axis: months on top, week starts below
  let months = '', weeks = '', lines = '', labelled = false;
  for (let d = start; d <= end; d++) {
    const date = new Date(d * DAY_MS), x = (d - start) * ppd;
    // A month gets a label where it starts; the first, partial month only if there is room for it
    if (date.getUTCDate() === 1 || (d === start && (32 - date.getUTCDate()) * ppd > 70)) {
      const label = date.toLocaleDateString('en-US', { month: 'short', timeZone: 'UTC' }) + (date.getUTCMonth() === 0 || !labelled ? ' ' + date.getUTCFullYear() : '');
      labelled = true;
      months += '<span class="nmm-rm-month" style="left:' + x + 'px">' + label + '</span>';
      if (date.getUTCDate() === 1) lines += '<i class="nmm-rm-mline" style="left:' + x + 'px"></i>';
    }
    if (date.getUTCDay() === 1 && scale !== 'quarter') {
      weeks += '<span class="nmm-rm-week" style="left:' + x + 'px">' + date.getUTCDate() + '</span>';
      lines += '<i class="nmm-rm-wline" style="left:' + x + 'px"></i>';
    }
  }

  const lanesHtml = laneList.map(function(l) {
    const h = LANE_PAD * 2 + l.rows * (BAR_H + ROW_GAP) - ROW_GAP;
    const canEditLane = editable && rm.group === 'phase' && !l.extra;
    return '<div class="nmm-rm-lane' + (l.extra ? ' nmm-rm-lane-extra' : '') + '" data-lane="' + esc(l.id) + '">' +
      '<div class="nmm-rm-label" style="width:' + LABEL_W + 'px">' +
        (canEditLane ? '<button type="button" class="nmm-rm-lane-btn" data-lane="' + esc(l.id) + '" title="Rename, move or remove">' + esc(l.label) + ' <span aria-hidden="true">▾</span></button>' : '<span class="nmm-rm-lane-name">' + esc(l.label) + '</span>') +
        '<span class="nmm-rm-count">' + (l.bars.length ? l.bars.length + (l.bars.length === 1 ? ' card' : ' cards') : 'Drop cards here') + '</span>' +
      '</div>' +
      '<div class="nmm-rm-track" data-lane="' + esc(l.id) + '" style="width:' + width + 'px;height:' + h + 'px">' +
        l.bars.map(function(b) {
          const tc = TAG_COLORS[b.n.tag], w = Math.max((b.end - b.start + 1) * ppd, MIN_BAR_PX);
          const style = 'left:' + (b.start - start) * ppd + 'px;top:' + (LANE_PAD + b.row * (BAR_H + ROW_GAP)) + 'px;width:' + w + 'px;' +
            (tc ? '--rm-bg:' + tc.bg + ';--rm-fg:' + tc.text : '');
          return '<div class="nmm-rm-bar' + (b.assumed ? ' nmm-rm-assumed' : '') + (b.n.tag === 'done' ? ' nmm-rm-done' : '') + (editable ? '' : ' nmm-rm-locked') + '" data-id="' + esc(b.n.id) + '" style="' + style + '" tabindex="0" role="button" aria-label="' + esc(b.n.title + ', ' + NMMCore.longDate(b.n.date)) + '">' +
            '<span class="nmm-rm-title">' + esc(b.n.title || 'Untitled') + '</span>' +
            (editable ? '<span class="nmm-rm-handle" title="Drag to change the end date"></span>' : '') + '</div>';
        }).join('') +
      '</div></div>';
  }).join('');

  const gridH = 'auto';
  $('nmm-rm-grid').style.width = LABEL_W + width + 'px';
  $('nmm-rm-grid').innerHTML =
    '<div class="nmm-rm-head"><div class="nmm-rm-corner" style="width:' + LABEL_W + 'px">' + (rm.group === 'branch' ? 'Branches' : 'Phases') + '</div>' +
      '<div class="nmm-rm-axis" style="width:' + width + 'px"><div class="nmm-rm-months">' + months + '</div><div class="nmm-rm-weeks">' + weeks + '</div></div></div>' +
    '<div class="nmm-rm-lanes">' +
      '<div class="nmm-rm-lines" style="left:' + LABEL_W + 'px;width:' + width + 'px">' + lines +
        (t >= start && t <= end ? '<i class="nmm-rm-today" style="left:' + ((t - start) * ppd + ppd / 2) + 'px"><b>Today</b></i>' : '') + '</div>' +
      (laneList.length ? lanesHtml : '<div class="nmm-rm-nolanes">This map has no main branches yet. Add branches in the mind map, or choose phases with Lanes.</div>') +
      (editable && rm.group === 'phase' ? '<div class="nmm-rm-lane nmm-rm-addrow"><div class="nmm-rm-label" style="width:' + LABEL_W + 'px"><button type="button" class="nmm-rm-add" id="nmm-rm-add-lane">+ Add lane</button></div></div>' : '') +
    '</div>';
  void gridH;
  const add = $('nmm-rm-add-lane');
  if (add) add.addEventListener('click', addLane);

  if (first) scrollToDay(bars.length ? Math.min(t, Math.min.apply(null, bars.map(function(b) { return b.start; }))) : t);
  else { sc.scrollLeft = keepX; sc.scrollTop = keepY; }

  renderTray();
  nextUp.update(NMMCore.attention(nodes));
}

function syncTools() {
  const g = rm ? rm.group : '';
  $('nmm-rm-group').querySelectorAll('[data-group]').forEach(function(b) { b.classList.toggle('active', b.dataset.group === g); });
  $('nmm-rm-lanes-btn').style.display = editable ? '' : 'none';
}

function renderTray() {
  const tray = $('nmm-rm-tray'), list = trayCards();
  tray.style.display = editable && list.length ? '' : 'none';
  if (!editable || !list.length) return;
  $('nmm-rm-tray-cards').innerHTML = list.map(function(n) {
    const tc = TAG_COLORS[n.tag];
    return '<div class="nmm-rm-tcard" data-id="' + esc(n.id) + '" tabindex="0" role="button" style="' + (tc ? 'border-left-color:' + tc.bg : '') + '">' +
      '<span class="nmm-rm-tcard-title">' + esc(n.title || 'Untitled') + '</span>' +
      (tc ? '<span class="nmm-hc-tag" style="background:' + tc.bg + ';color:' + tc.text + '">' + tc.label + '</span>' : '') + '</div>';
  }).join('');
  $('nmm-rm-tray-count').textContent = list.length + (list.length === 1 ? ' card' : ' cards');
}

// ---------- dragging ----------
function dayAt(clientX, track) { return view.start + Math.floor((clientX - track.getBoundingClientRect().left) / view.ppd); }
function trackAt(x, y) { const el = document.elementFromPoint(x, y); return el && el.closest('.nmm-rm-track'); }
let drag = null;

$('nmm-rm-grid').addEventListener('pointerdown', function(e) {
  const laneBtn = e.target.closest('.nmm-rm-lane-btn');
  if (laneBtn) { e.stopPropagation(); openLaneMenu(laneBtn.dataset.lane, laneBtn); return; }
  const bar = e.target.closest('.nmm-rm-bar');
  if (!bar || e.button > 0) return;
  const n = find(bar.dataset.id), s = n && span(n);
  if (!s) return;
  drag = { kind: e.target.closest('.nmm-rm-handle') ? 'end' : 'move', bar: bar, n: n, s: s, x: e.clientX, y: e.clientY, moved: false, dd: 0, lane: laneOf(n), toLane: null, type: e.pointerType };
  if (editable && (e.pointerType === 'mouse' || drag.kind === 'end')) { try { bar.setPointerCapture(e.pointerId); } catch (err) {} e.preventDefault(); }
});
document.addEventListener('pointermove', function(e) {
  if (!drag) return;
  if (drag.kind === 'tray') return moveGhost(e);
  if (!editable || (drag.type !== 'mouse' && drag.kind !== 'end')) return;
  const dx = e.clientX - drag.x, dy = e.clientY - drag.y;
  if (!drag.moved && Math.abs(dx) < 4 && Math.abs(dy) < 4) return;
  drag.moved = true;
  drag.dd = Math.round(dx / view.ppd);
  drag.bar.classList.add('nmm-rm-dragging');
  if (drag.kind === 'end') {
    const newEnd = Math.max(drag.s.start, drag.s.end + drag.dd);
    drag.bar.style.width = Math.max((newEnd - drag.s.start + 1) * view.ppd, MIN_BAR_PX) + 'px';
    drag.bar.title = 'Ends ' + NMMCore.longDate(dayStr(newEnd));
  } else {
    drag.bar.style.transform = 'translate(' + drag.dd * view.ppd + 'px,' + (rm.group === 'phase' ? dy : 0) + 'px)';
    drag.bar.title = 'Starts ' + NMMCore.longDate(dayStr(drag.s.start + drag.dd));
    document.querySelectorAll('.nmm-rm-track.nmm-rm-over').forEach(function(t) { t.classList.remove('nmm-rm-over'); });
    drag.toLane = null;
    if (rm.group === 'phase') {
      drag.bar.style.pointerEvents = 'none';
      const t = trackAt(e.clientX, e.clientY);
      drag.bar.style.pointerEvents = '';
      if (t && t.dataset.lane !== drag.lane && t.dataset.lane !== '_top') { t.classList.add('nmm-rm-over'); drag.toLane = t.dataset.lane; }
    }
  }
});
document.addEventListener('pointerup', function(e) {
  if (!drag) return;
  const d = drag; drag = null;
  if (d.kind === 'tray') return dropTray(d, e);
  document.querySelectorAll('.nmm-rm-track.nmm-rm-over').forEach(function(t) { t.classList.remove('nmm-rm-over'); });
  if (!d.moved) { openEdit(d.n); return; }
  const fields = {};
  if (d.kind === 'end') fields.end = dayStr(Math.max(d.s.start, d.s.end + d.dd));
  else {
    if (d.dd) { fields.date = dayStr(d.s.start + d.dd); if (!d.s.assumed) fields.end = dayStr(d.s.end + d.dd); }
    if (d.toLane) fields.lane = d.toLane === '_unsorted' ? '' : d.toLane;
  }
  if (Object.keys(fields).length) save(d.n, fields); else render();
});
document.addEventListener('pointercancel', function() { if (drag && drag.ghost) drag.ghost.remove(); drag = null; render(); });

// Tray: drag a card onto a lane, or tap it, to give it dates
$('nmm-rm-tray-cards').addEventListener('pointerdown', function(e) {
  const card = e.target.closest('.nmm-rm-tcard');
  if (!card || e.button > 0) return;
  drag = { kind: 'tray', card: card, n: find(card.dataset.id), x: e.clientX, y: e.clientY, moved: false };
});
function moveGhost(e) {
  if (!drag.moved && Math.abs(e.clientX - drag.x) < 5 && Math.abs(e.clientY - drag.y) < 5) return;
  if (!drag.moved) {
    drag.moved = true;
    drag.ghost = drag.card.cloneNode(true);
    drag.ghost.className += ' nmm-rm-ghost';
    root.appendChild(drag.ghost);
  }
  drag.ghost.style.left = e.clientX + 8 + 'px'; drag.ghost.style.top = e.clientY + 8 + 'px';
  document.querySelectorAll('.nmm-rm-track.nmm-rm-over').forEach(function(t) { t.classList.remove('nmm-rm-over'); });
  const t = trackAt(e.clientX, e.clientY);
  if (t) t.classList.add('nmm-rm-over');
}
function dropTray(d, e) {
  if (d.ghost) d.ghost.remove();
  document.querySelectorAll('.nmm-rm-track.nmm-rm-over').forEach(function(t) { t.classList.remove('nmm-rm-over'); });
  if (!d.n) return;
  if (!d.moved) { openEdit(d.n, { start: today() }); return; }
  const t = trackAt(e.clientX, e.clientY);
  if (!t) return;
  const start = dayAt(e.clientX, t);
  openEdit(d.n, { start: start, lane: t.dataset.lane });
}

// ---------- saving ----------
async function save(n, fields) {
  const before = {};
  Object.keys(fields).forEach(function(k) { before[k] = n[k]; });
  Object.assign(n, fields);
  render();
  try {
    reloadFrom(await NMMCore.patchNode(map.id, n.id, fields));
  } catch (err) {
    Object.assign(n, before); render();
    alert('Could not save "' + n.title + '": ' + err.message);
  }
}

// ---------- edit / add dialog ----------
let editing = null;
function openEdit(n, add) {
  editing = n;
  const s = span(n);
  $('nmm-rm-modal-title').textContent = add ? 'Add to the roadmap' : 'Edit card';
  $('nmm-rm-title').value = n.title || '';
  $('nmm-rm-sub').value = (n.subs || []).join('\n');
  $('nmm-rm-start').value = add ? dayStr(add.start) : (n.date || '');
  $('nmm-rm-end').value = add ? dayStr(add.start + DEFAULT_DAYS - 1) : (s && !s.assumed ? n.end : '');
  $('nmm-rm-end').placeholder = '';
  $('nmm-rm-tag').innerHTML = STAGES.map(function(o) { return '<option value="' + o[0] + '"' + ((n.tag || '') === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('');
  const laneField = $('nmm-rm-lane-field');
  if (rm.group === 'phase') {
    laneField.style.display = '';
    const cur = add && add.lane ? add.lane : laneOf(n);
    $('nmm-rm-lane').innerHTML = '<option value="">Unsorted</option>' + rm.lanes.map(function(l) {
      return '<option value="' + esc(l.id) + '"' + (l.id === cur ? ' selected' : '') + '>' + esc(l.label) + '</option>';
    }).join('');
  } else laneField.style.display = 'none';
  $('nmm-rm-where').textContent = pathOf(n) ? 'In ' + pathOf(n) : '';
  $('nmm-rm-clear').style.display = add || !n.date ? 'none' : '';
  $('nmm-rm-open-map').href = NMM.editorUrl + '?map_id=' + map.id;
  ['nmm-rm-title', 'nmm-rm-sub', 'nmm-rm-start', 'nmm-rm-end', 'nmm-rm-tag', 'nmm-rm-lane'].forEach(function(id) { $(id).disabled = !editable; });
  $('nmm-rm-save').style.display = editable ? '' : 'none';
  $('nmm-rm-modal').classList.add('open');
}
function closeEdit() { $('nmm-rm-modal').classList.remove('open'); editing = null; }
$('nmm-rm-cancel').addEventListener('click', closeEdit);
$('nmm-rm-modal').addEventListener('click', function(e) { if (e.target === this) closeEdit(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeEdit(); });
$('nmm-rm-save').addEventListener('click', function() {
  const n = editing; if (!n) return;
  const start = $('nmm-rm-start').value, end = $('nmm-rm-end').value;
  if (end && !start) { alert('Please set a start date as well.'); return; }
  if (start && end && end < start) { alert('The end date is before the start date.'); return; }
  const fields = {
    title: $('nmm-rm-title').value.trim() || n.title,
    subs: $('nmm-rm-sub').value.split('\n').map(function(s) { return s.trim(); }).filter(Boolean),
    date: start, end: start ? end : '', tag: $('nmm-rm-tag').value
  };
  if (fields.tag && !n.task) fields.task = true;
  if (rm.group === 'phase') fields.lane = $('nmm-rm-lane').value;
  closeEdit();
  save(n, fields);
});
$('nmm-rm-clear').addEventListener('click', function() {
  const n = editing; if (!n) return;
  closeEdit();
  save(n, { date: '', end: '' });
});

// ---------- hover card and next up ----------
NMMCore.hoverCard({ root: root, selector: '.nmm-rm-bar, .nmm-rm-tcard', info: function(el) {
  const n = find(el.dataset.id);
  return n && { title: n.title, subs: n.subs, date: n.date, end: n.end, tag: n.tag, sefirot: n.sefirot, where: pathOf(n) };
} });
const nextUp = NMMCore.notice({
  root: root, key: 'roadmap_' + location.search,
  label: function(it) { return it.days < 0 ? 'Needs attention' : 'Next up'; },
  detail: function(it) { return it.when + ' · ' + NMMCore.longDate(it.n.date); },
  onOpen: function(it) {
    const s = span(it.n);
    if (s) scrollToDay(s.start);
    const bar = document.querySelector('.nmm-rm-bar[data-id="' + CSS.escape(it.n.id) + '"]');
    if (bar) bar.scrollIntoView({ block: 'nearest' });
    openEdit(it.n);
  }
});

// Keyboard: Enter on a focused bar or tray card opens it
root.addEventListener('keydown', function(e) {
  if (e.key !== 'Enter') return;
  const el = e.target.closest('.nmm-rm-bar, .nmm-rm-tcard');
  if (el) openEdit(find(el.dataset.id), el.classList.contains('nmm-rm-tcard') ? { start: today() } : null);
});

window.addEventListener('resize', function() { if (map && rm) render(); });
load();
})();
