/* Navigator Life Maps — Calendar (/calendar/, /calendar/?map_id=1,2). Markup: templates/calendar.php
 * Dated Kanban tasks (NMMCore.isTask with a date) by month (default), week or list. A card with an
 * end date shows on every day it covers. Drag a card to another day to move it (its end moves with
 * it); click it to edit. Saves go through NMMCore.patchNode, like Kanban and the timeline. */
(function() {
const root = document.getElementById('nmm-calendar');
if (!root) return;

const esc = NMMCore.esc, TAG_COLORS = NMMCore.TAG_COLORS;
const $ = function(id) { return document.getElementById(id); };
const DAY = 86400000, MAX_IN_CELL = 3;
const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const MODE_KEY = 'nmm_cal_mode';

let allMaps = [], selectedMapIds = [], editNode = null, dragging = null;
const mapNodes = {}; // map id -> nodes, loaded on demand
const TODAY = NMMCore.today();
let mode = 'month', anchor = TODAY, firstRender = true;
try { mode = localStorage.getItem(MODE_KEY) || (matchMedia('(max-width: 700px)').matches ? 'list' : 'month'); } catch (e) {}

// ---------- days ----------
// Whole days since 1970 (UTC), as NMMCore.dayOf gives them. Day 0 was a Thursday.
function dayStr(d) { return new Date(d * DAY).toISOString().slice(0, 10); }
function parts(d) { const t = new Date(d * DAY); return { y: t.getUTCFullYear(), m: t.getUTCMonth(), date: t.getUTCDate() }; }
function weekday(d) { return (d + 4) % 7; } // 0 = Sunday
function monthStart(d) { const p = parts(d); return Date.UTC(p.y, p.m, 1) / DAY; }
function addMonths(d, n) { const p = parts(d); return Date.UTC(p.y, p.m + n, 1) / DAY; }
function weekStart(d) { return d - weekday(d); }
function longDay(d) { const p = parts(d); return WEEKDAYS[weekday(d)] + ', ' + MONTHS[p.m].slice(0, 3) + ' ' + p.date; }

// The days on screen, [first, last]
function range() {
  if (mode === 'week') { const s = weekStart(anchor); return [s, s + 6]; }
  const s = monthStart(anchor), e = addMonths(anchor, 1) - 1;
  if (mode === 'list') return [s, e];
  return [weekStart(s), weekStart(e) + 6]; // whole weeks around the month
}

// ---------- data ----------
async function loadAll() {
  allMaps = await NMMCore.listMaps();
  const fromUrl = NMMCore.mapIdsFromUrl().filter(function(id) { return allMaps.some(function(m) { return m.id === id; }); });
  NMMCore.mapFilter({
    button: $('nmm-cal-filter-btn'), panel: $('nmm-cal-filter-panel'), list: $('nmm-cal-filter-list'),
    maps: allMaps,
    selected: NMMCore.shareKey ? allMaps.map(function(m) { return m.id; }) : fromUrl.length ? fromUrl : allMaps.map(function(m) { return m.id; }),
    onChange: function(ids) { selectedMapIds = ids; updateLinks(); loadSelected().then(render); }
  });
}
async function loadSelected() {
  const missing = selectedMapIds.filter(function(id) { return !mapNodes[id]; });
  if (missing.length) $('nmm-cal-loading').style.display = 'flex';
  await Promise.all(missing.map(async function(id) {
    const full = await NMMCore.getMap(id);
    const map  = allMaps.find(function(m) { return m.id === id; }) || {};
    mapNodes[id] = ((full && full.nodes) || []).filter(function(n) { return !n._readonly; }).map(function(n) {
      n._map_id = id; n._map_title = map.title || ''; n._map_color = map.color || '#0BBFBF'; return n;
    });
  }));
  $('nmm-cal-loading').style.display = 'none';
}
function updateLinks() {
  const ids = selectedMapIds.length === allMaps.length && !allMaps.some(function(m) { return m.viewOnly; }) ? [] : selectedMapIds;
  history.replaceState(null, '', NMMCore.pageUrl(NMM.calendarUrl, ids));
  NMMCore.setViewTabs(ids, selectedMapIds);
}

// Dated tasks only: a card without a date, or one that isn't a Kanban task, stays off the calendar
function cards() {
  const out = [];
  selectedMapIds.forEach(function(id) {
    (mapNodes[id] || []).forEach(function(n) {
      if (!NMMCore.isTask(n)) return;
      const s = NMMCore.dayOf(n.date);
      if (s === null) return;
      const e = n.end ? NMMCore.dayOf(n.end) : null;
      out.push({ n: n, start: s, end: e !== null && e >= s ? e : s });
    });
  });
  return out.sort(function(a, b) { return a.start - b.start || (b.end - b.start) - (a.end - a.start) || String(a.n.title).localeCompare(b.n.title); });
}
function cardsOn(list, d) { return list.filter(function(c) { return c.start <= d && c.end >= d; }); }

// An older map: open on the month of its nearest card instead of an empty one
function jumpToCards(list) {
  const [a, b] = range();
  if (!list.length || list.some(function(c) { return c.end >= a && c.start <= b; })) return;
  const near = list.slice().sort(function(x, y) { return Math.abs(x.start - TODAY) - Math.abs(y.start - TODAY); })[0];
  anchor = near.start;
}

// ---------- drawing ----------
function chip(c, d, many) {
  const n = c.n, tag = TAG_COLORS[n.tag] || { bg: '#E7E4DB', text: '#011C2F', label: 'Triage' };
  const first = d === c.start, last = d === c.end, multi = c.end > c.start;
  const locked = NMMCore.isViewOnly(n._map_id);
  const cls = 'nmm-cal-card' + (n.tag === 'done' ? ' nmm-cal-done' : '') + (multi ? ' nmm-cal-multi' : '') +
    (multi && !first ? ' nmm-cal-cont' : '') + (multi && !last ? ' nmm-cal-goes' : '');
  return '<div class="' + cls + '" data-map="' + n._map_id + '" data-id="' + esc(n.id) + '" data-day="' + d + '"' +
    (locked ? '' : ' draggable="true"') + ' tabindex="0" role="button"' +
    ' style="background:' + tag.bg + ';color:' + tag.text + (many ? ';border-left-color:' + esc(n._map_color) : '') + '"' +
    ' aria-label="' + esc(n.title + ', ' + tag.label + ', ' + NMMCore.longDate(n.date)) + '">' +
    (multi && !first ? '<span class="nmm-cal-arrow" aria-hidden="true">→</span>' : '') + esc(n.title) + '</div>';
}
function render() {
  const list = cards();
  if (firstRender && list.length) { jumpToCards(list); firstRender = false; }
  const [a, b] = range(), grid = $('nmm-cal-grid'), many = selectedMapIds.length > 1;
  document.querySelectorAll('.nmm-cal-modes [data-mode]').forEach(function(btn) { btn.classList.toggle('active', btn.dataset.mode === mode); btn.setAttribute('aria-pressed', btn.dataset.mode === mode); });
  const p = parts(anchor);
  $('nmm-cal-label').textContent = mode === 'week'
    ? longDay(a).slice(5) + ' – ' + longDay(b).slice(5) + ', ' + parts(b).y
    : MONTHS[p.m] + ' ' + p.y;
  grid.className = 'nmm-cal-grid nmm-cal-' + mode;

  const has = list.length > 0;
  $('nmm-cal-empty').style.display = has ? 'none' : 'block';
  grid.style.display = has ? '' : 'none';
  const shown = list.filter(function(c) { return c.end >= a && c.start <= b; }).length;
  const note = $('nmm-cal-note');
  note.style.display = has && !shown ? '' : 'none';
  note.innerHTML = has && !shown ? 'No dated tasks in this ' + (mode === 'week' ? 'week' : 'month') + '. <button type="button" class="nmm-link-btn" id="nmm-cal-nearest">Go to the nearest one</button>' : '';
  const nearest = $('nmm-cal-nearest');
  if (nearest) nearest.onclick = function() { firstRender = true; render(); };
  if (!has) return;

  if (mode === 'list') {
    const days = [];
    for (let d = a; d <= b; d++) { const on = cardsOn(list, d); if (on.length) days.push([d, on]); }
    grid.innerHTML = days.length ? days.map(function(x) {
      return '<section class="nmm-cal-lday' + (x[0] === TODAY ? ' nmm-cal-today' : '') + '" data-day="' + x[0] + '"><h4>' + longDay(x[0]) + (x[0] === TODAY ? ' · Today' : '') + '</h4>' +
        x[1].map(function(c) { return chip(c, x[0], many); }).join('') + '</section>';
    }).join('') : '';
    return;
  }

  const p0 = parts(anchor).m;
  let html = '<div class="nmm-cal-head">' + WEEKDAYS.map(function(w) { return '<div>' + w + '</div>'; }).join('') + '</div><div class="nmm-cal-days">';
  for (let d = a; d <= b; d++) {
    const on = cardsOn(list, d), out = mode === 'month' && parts(d).m !== p0;
    const cap = mode === 'month' && on.length > MAX_IN_CELL ? MAX_IN_CELL - 1 : on.length;
    html += '<div class="nmm-cal-day' + (out ? ' nmm-cal-out' : '') + (d === TODAY ? ' nmm-cal-today' : '') + '" data-day="' + d + '">' +
      '<div class="nmm-cal-num">' + (mode === 'week' ? '<span>' + WEEKDAYS[weekday(d)] + '</span> ' : '') + parts(d).date + '</div>' +
      on.slice(0, cap).map(function(c) { return chip(c, d, many); }).join('') +
      (on.length > cap ? '<button type="button" class="nmm-cal-more" data-day="' + d + '">+' + (on.length - cap) + ' more</button>' : '') +
      '</div>';
  }
  grid.innerHTML = html + '</div>';
}

// ---------- moving around ----------
function step(dir) {
  anchor = mode === 'week' ? anchor + 7 * dir : addMonths(anchor, dir);
  render();
}
$('nmm-cal-prev').addEventListener('click', function() { step(-1); });
$('nmm-cal-next').addEventListener('click', function() { step(1); });
$('nmm-cal-today').addEventListener('click', function() { anchor = TODAY; render(); });
document.querySelectorAll('.nmm-cal-modes [data-mode]').forEach(function(btn) {
  btn.addEventListener('click', function() {
    mode = btn.dataset.mode;
    try { localStorage.setItem(MODE_KEY, mode); } catch (e) {}
    render();
  });
});
document.addEventListener('keydown', function(e) {
  if (e.target.closest('input,textarea,select') || document.querySelector('.nmm-modal-overlay.open')) return;
  if (e.key === 'ArrowLeft') step(-1);
  if (e.key === 'ArrowRight') step(1);
});

// ---------- cards: open, drag to another day ----------
function findCard(el) { return (mapNodes[+el.dataset.map] || []).find(function(n) { return n.id === el.dataset.id; }); }
const grid = $('nmm-cal-grid');
grid.addEventListener('click', function(e) {
  const more = e.target.closest('.nmm-cal-more');
  if (more) { openDay(+more.dataset.day); return; }
  const el = e.target.closest('.nmm-cal-card');
  if (el) { const n = findCard(el); if (n) openEdit(n); }
});
grid.addEventListener('keydown', function(e) {
  const el = e.target.closest('.nmm-cal-card');
  if (el && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); const n = findCard(el); if (n) openEdit(n); }
});
grid.addEventListener('dragstart', function(e) {
  const el = e.target.closest('.nmm-cal-card');
  if (!el) return;
  dragging = { n: findCard(el), from: +el.dataset.day };
  e.dataTransfer.effectAllowed = 'move';
  try { e.dataTransfer.setData('text/plain', el.dataset.id); } catch (err) {}
  requestAnimationFrame(function() { el.classList.add('nmm-cal-dragging'); });
});
grid.addEventListener('dragend', function() {
  dragging = null;
  grid.querySelectorAll('.nmm-cal-over, .nmm-cal-dragging').forEach(function(x) { x.classList.remove('nmm-cal-over', 'nmm-cal-dragging'); });
});
grid.addEventListener('dragover', function(e) {
  const cell = dragging && e.target.closest('.nmm-cal-day, .nmm-cal-lday');
  if (!cell) return;
  e.preventDefault();
  grid.querySelectorAll('.nmm-cal-over').forEach(function(x) { if (x !== cell) x.classList.remove('nmm-cal-over'); });
  cell.classList.add('nmm-cal-over');
});
grid.addEventListener('drop', async function(e) {
  const cell = dragging && e.target.closest('.nmm-cal-day, .nmm-cal-lday');
  if (!cell || !dragging.n) return;
  e.preventDefault();
  const n = dragging.n, shift = +cell.dataset.day - dragging.from;
  dragging = null;
  if (!shift) { render(); return; }
  // The whole card moves: its end keeps the same distance from its start
  const old = { date: n.date, end: n.end || '' };
  const fields = { date: dayStr(NMMCore.dayOf(n.date) + shift) };
  if (n.end && NMMCore.dayOf(n.end) !== null) fields.end = dayStr(NMMCore.dayOf(n.end) + shift);
  Object.assign(n, fields); render();
  try {
    await NMMCore.patchNode(n._map_id, n.id, fields);
  } catch (err) {
    Object.assign(n, old); render();
    if (!err.viewOnly) alert('Could not move "' + n.title + '": ' + err.message);
  }
});

NMMCore.hoverCard({ root: root, selector: '.nmm-cal-card', info: function(el) {
  const n = findCard(el);
  return n && { title: n.title, subs: n.subs, date: n.date, end: n.end, tag: n.tag, sefirot: n.sefirot, where: selectedMapIds.length > 1 ? n._map_title : '' };
} });

// ---------- a day with more cards than fit ----------
function openDay(d) {
  const many = selectedMapIds.length > 1;
  $('nmm-cal-day-title').textContent = longDay(d);
  $('nmm-cal-day-list').innerHTML = cardsOn(cards(), d).map(function(c) { return chip(c, d, many); }).join('');
  $('nmm-cal-day-modal').classList.add('open');
}
function closeDay() { $('nmm-cal-day-modal').classList.remove('open'); }
$('nmm-cal-day-close').addEventListener('click', closeDay);
$('nmm-cal-day-modal').addEventListener('click', function(e) {
  if (e.target === this) { closeDay(); return; }
  const el = e.target.closest('.nmm-cal-card');
  if (el) { const n = findCard(el); closeDay(); if (n) openEdit(n); }
});

// ---------- edit ----------
function pathOf(n) {
  const list = mapNodes[n._map_id] || [], names = [];
  let p = list.find(function(x) { return x.id === n.parent; });
  while (p && names.length < 4) { names.unshift(p.title); p = list.find(function(x) { return x.id === p.parent; }); }
  return names.join(' › ');
}
function openEdit(n) {
  editNode = n;
  $('nmm-cal-title').value = n.title || '';
  $('nmm-cal-sub').value   = (n.subs || []).join('\n');
  $('nmm-cal-date').value  = n.date || '';
  $('nmm-cal-end').value   = n.end || '';
  $('nmm-cal-tag').value   = n.tag || '';
  $('nmm-cal-where').textContent = [selectedMapIds.length > 1 ? n._map_title : '', pathOf(n)].filter(Boolean).join(' · ');
  $('nmm-cal-open-map').href = NMMCore.mapUrl(n._map_id);
  NMMCore.lockModal($('nmm-cal-edit-modal'), n._map_id);
  $('nmm-cal-edit-modal').classList.add('open');
  setTimeout(function() { if (!$('nmm-cal-title').disabled) $('nmm-cal-title').focus(); }, 50);
}
function closeEdit() { $('nmm-cal-edit-modal').classList.remove('open'); editNode = null; }
$('nmm-cal-cancel').addEventListener('click', closeEdit);
$('nmm-cal-edit-modal').addEventListener('click', function(e) { if (e.target === this) closeEdit(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { closeEdit(); closeDay(); } });
$('nmm-cal-save').addEventListener('click', async function() {
  const n = editNode; if (!n) return;
  const start = $('nmm-cal-date').value, end = $('nmm-cal-end').value;
  if (end && !start) { alert('Please set a start date as well.'); return; }
  if (start && end && end < start) { alert('The end date is before the start date.'); return; }
  const fields = {
    title: $('nmm-cal-title').value.trim() || n.title,
    subs:  $('nmm-cal-sub').value.trim().split('\n').filter(function(s) { return s.trim(); }),
    date:  start, end: end, tag: $('nmm-cal-tag').value || ''
  };
  const btn = this; btn.textContent = 'Saving...'; btn.disabled = true;
  try {
    await NMMCore.patchNode(n._map_id, n.id, fields);
    Object.assign(n, fields);
    closeEdit(); render();
  } catch (err) {
    if (!err.viewOnly) alert('Save failed: ' + err.message);
  }
  btn.textContent = 'Save'; btn.disabled = false;
});

// The More menu (shared with the other views)
NMMMore.init({
  view: 'calendar',
  maps: function() {
    return selectedMapIds.map(function(id) {
      const m = allMaps.find(function(x) { return x.id === id; }) || {};
      return { id: id, title: m.title || 'Untitled', category: m.category || '', nodes: mapNodes[id] || [] };
    });
  },
  imageEl: function() { return $('nmm-cal-grid'); },
  cards: function() { return Array.prototype.slice.call(root.querySelectorAll('.nmm-cal-card')); }
});

loadAll();
})();
