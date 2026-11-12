/* Navigator Mind Maps — timeline (/timeline/, /timeline/?map_id=N). Markup: templates/timeline.php
 * Cards with a date inside the chosen range appear at their date, each in the nearest free spot
 * above or below the line, so they never overlap. Drag a card sideways to reschedule, click it
 * to see its notes and edit it, drag empty space to slide along the line. */
(function() {
const root = document.getElementById('nmm-timeline');
if (!root) return;

const api = NMMCore.api, esc = NMMCore.esc, TAG_COLORS = NMMCore.TAG_COLORS;
const $   = function(id) { return document.getElementById(id); };

const KANBAN_ORDER = { done: 0, review: 1, sprint: 2, doing: 3, start: 4, '': 5 };
const CARD_W = 190, CARD_GAP = 10, PAD = CARD_W / 2 + 24, MARGIN = 28;
const GAP_ABOVE = 30, GAP_BELOW = 52; // space from the line to the first card; below it also holds the date labels
const CARDS_PER_WIDTH = 4;            // a crowded range gets wider, so about this many cards share each card's width
const MAX_DAY_PX = 30, MAX_WIDTH = 6000; // the most a busy stretch can spread out
const DAY_MS = 86400000;

// Dates are whole days, kept as day numbers so time zones can't shift a card
function dayNum(s) { const p = s.split('-'); return Date.UTC(+p[0], p[1] - 1, +p[2]) / DAY_MS; }
function dayStr(n) { return new Date(n * DAY_MS).toISOString().split('T')[0]; }
function dayLabel(n, withYear) {
  const o = { month: 'short', day: 'numeric', timeZone: 'UTC' };
  if (withYear) o.year = 'numeric';
  return new Date(n * DAY_MS).toLocaleDateString('en-US', o);
}

let allMaps = [], selectedMapIds = [], editNode = null;
const mapNodes = {}; // map id -> node array, loaded on demand

// Default range: today to 30 days out. If none of the selected cards fall in it (an older map),
// the range fits the cards' own dates instead, until the member picks dates themselves.
const now = new Date();
const TODAY = Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()) / DAY_MS;
let tlStart = TODAY, tlEnd = TODAY + 30, rangeChosen = false;
function fitRange(dated) {
  const days = dated.map(function(n) { return dayNum(n.date); });
  const first = Math.min.apply(null, days), last = Math.max.apply(null, days);
  const pad = Math.max(2, Math.ceil((14 - (last - first)) / 2)); // at least two weeks wide, cards centred
  tlStart = first - pad; tlEnd = last + pad;
  showRange();
}
function showRange() { $('nmm-tl-start').value = dayStr(tlStart); $('nmm-tl-end').value = dayStr(tlEnd); }
showRange();

async function loadAll() {
  allMaps = (await api('GET', 'maps')) || [];
  const fromUrl = NMMCore.mapIdsFromUrl().filter(function(id) { return allMaps.some(function(m) { return m.id === id; }); });
  NMMCore.mapFilter({
    button: $('nmm-tl-filter-btn'), panel: $('nmm-tl-filter-panel'), list: $('nmm-tl-filter-list'),
    maps: allMaps,
    selected: fromUrl.length ? fromUrl : allMaps.map(function(m) { return m.id; }),
    onChange: function(ids) {
      selectedMapIds = ids; updateLinks();
      if (!rangeChosen) { tlStart = TODAY; tlEnd = TODAY + 30; showRange(); }
      loadSelected().then(renderTimeline);
    }
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
  NMMCore.setViewTabs(ids, selectedMapIds);
}

NMMCore.hoverCard({ root: root, selector: '.nmm-tl-node', info: function(el) {
  const n = el._node;
  return n && { title: n.title, subs: n.subs, date: n.date, end: n.end, tag: n.tag, sefirot: n.sefirot, where: n._map_title };
} });

$('nmm-tl-apply-btn').addEventListener('click', function() {
  const s = $('nmm-tl-start').value, e = $('nmm-tl-end').value;
  if (!s || !e) { alert('Please set both a start and end date.'); return; }
  if (dayNum(s) >= dayNum(e)) { alert('End date must be after start date.'); return; }
  tlStart = dayNum(s); tlEnd = dayNum(e); rangeChosen = true;
  renderTimeline();
});

// Says what the chosen dates leave out, with a way to see it
function showRangeNote(dated, before, after) {
  const note = $('nmm-tl-range-note');
  note.innerHTML = '';
  if (!before && !after) { note.style.display = 'none'; return; }
  const parts = [];
  if (before) parts.push(before + (before === 1 ? ' card' : ' cards') + ' before ' + dayLabel(tlStart));
  if (after)  parts.push(after + (after === 1 ? ' card' : ' cards') + ' after ' + dayLabel(tlEnd));
  const span = document.createElement('span');
  span.textContent = parts.join(' · ');
  const btn = document.createElement('button');
  btn.type = 'button'; btn.textContent = 'Show all dates';
  btn.addEventListener('click', function() {
    fitRange(dated); rangeChosen = true; renderTimeline();
  });
  note.appendChild(span); note.appendChild(btn);
  note.style.display = 'flex';
}

// The closest distance from the line where a card fits, given the cards already on that side
function fitDistance(placed, x, h, start) {
  let d = start;
  placed.filter(function(r) { return x < r.x + CARD_W + CARD_GAP && r.x < x + CARD_W + CARD_GAP; })
    .sort(function(a, b) { return a.d - b.d; })
    .some(function(r) {
      if (d + h + CARD_GAP <= r.d) return true; // fits in the gap before this one
      d = Math.max(d, r.d + r.h + CARD_GAP);
      return false;
    });
  return d;
}

function renderTimeline() {
  const canvas = $('nmm-tl-canvas'), markers = $('nmm-tl-markers'), spine = $('nmm-tl-spine'), nodesEl = $('nmm-tl-nodes');
  markers.innerHTML = ''; spine.innerHTML = ''; nodesEl.innerHTML = '';

  const dated = selectedMapIds.reduce(function(acc, id) { return acc.concat(mapNodes[id] || []); }, [])
    .filter(function(n) { return /^\d{4}-\d{2}-\d{2}$/.test(n.date || ''); });
  $('nmm-tl-canvas-wrap').style.display = dated.length ? 'block' : 'none';
  $('nmm-tl-empty').style.display       = dated.length ? 'none' : 'block';
  if (!dated.length) { showRangeNote([], 0, 0); return; }
  if (!rangeChosen && !dated.some(function(n) { const d = dayNum(n.date); return d >= tlStart && d <= tlEnd; })) fitRange(dated);

  const nodes = dated.filter(function(n) { const d = dayNum(n.date); return d >= tlStart && d <= tlEnd; });
  showRangeNote(dated,
    dated.filter(function(n) { return dayNum(n.date) < tlStart; }).length,
    dated.filter(function(n) { return dayNum(n.date) > tlEnd; }).length);

  // Sort by date, then by Kanban stage
  nodes.sort(function(a, b) {
    const ka = KANBAN_ORDER[a.tag || ''] ?? 5, kb = KANBAN_ORDER[b.tag || ''] ?? 5;
    return a.date.localeCompare(b.date) || ka - kb;
  });

  const viewW = $('nmm-tl-canvas-wrap').clientWidth, span = tlEnd - tlStart;
  // Wide enough for the busiest stretch of dates, within reason, so stacks stay short
  let busiest = 0;
  nodes.forEach(function(n, i) {
    const later = nodes[i + CARDS_PER_WIDTH];
    if (later) busiest = Math.max(busiest, (CARD_W + CARD_GAP) / Math.max(1, dayNum(later.date) - dayNum(n.date)));
  });
  const canvasW = Math.max(viewW, nodes.length * (CARD_W + CARD_GAP) / CARDS_PER_WIDTH + PAD * 2,
    Math.min(MAX_WIDTH, Math.min(busiest, MAX_DAY_PX) * span + PAD * 2));
  const pxPerDay = (canvasW - PAD * 2) / span;
  function dayX(d) { return PAD + (d - tlStart) * pxPerDay; }
  canvas.style.width = canvasW + 'px';
  const manyMaps = selectedMapIds.length > 1;
  const thisYear = new Date(tlStart * DAY_MS).getUTCFullYear();

  // Build the cards first: where each one goes depends on its real height
  const cards = nodes.map(function(n) {
    const tc = TAG_COLORS[n.tag || ''], day = dayNum(n.date), subs = (n.subs || []).filter(Boolean);
    const el = document.createElement('div');
    el.className = 'nmm-tl-node';
    el.style.width = CARD_W + 'px';
    el.style.borderLeftColor = tc ? tc.bg : (n._map_color || '#0BBFBF');
    el._node = n; // read by the hover card
    el.innerHTML =
      '<div class="nmm-tl-title">' + esc(n.title) + '</div>' +
      '<div class="nmm-tl-meta"><span data-date class="nmm-tl-date">' + esc(dayLabel(day, new Date(day * DAY_MS).getUTCFullYear() !== thisYear)) + '</span>' +
      (tc ? '<span class="nmm-tl-tag" style="background:' + tc.bg + ';color:' + tc.text + '">' + tc.label + '</span>' : '') +
      (subs.length ? '<span class="nmm-tl-notes">' + subs.length + (subs.length === 1 ? ' note' : ' notes') + '</span>' : '') + '</div>' +
      (manyMaps ? '<div class="nmm-tl-map">' + esc(n._map_title) + '</div>' : '');
    nodesEl.appendChild(el);
    return { n: n, el: el, day: day, h: el.offsetHeight };
  });

  // Each card takes the nearest free spot above or below the line
  const above = [], below = [];
  let nextAbove = true;
  cards.forEach(function(c) {
    c.x = dayX(c.day) - CARD_W / 2;
    const up = fitDistance(above, c.x, c.h, GAP_ABOVE), down = fitDistance(below, c.x, c.h, GAP_BELOW);
    const upCost = up - GAP_ABOVE, downCost = down - GAP_BELOW;
    c.above = upCost < downCost || (upCost === downCost && nextAbove);
    if (upCost === downCost) nextAbove = !nextAbove;
    c.d = c.above ? up : down;
    (c.above ? above : below).push({ x: c.x, d: c.d, h: c.h });
  });
  function reach(list, min) { return list.reduce(function(m, r) { return Math.max(m, r.d + r.h); }, min); }
  const spineY = reach(above, GAP_ABOVE) + MARGIN;
  canvas.style.height = (spineY + reach(below, GAP_BELOW) + MARGIN) + 'px';

  spine.style.cssText = 'top:' + spineY + 'px;left:' + (PAD - 40) + 'px;width:' + (canvasW - PAD * 2 + 80) + 'px;';

  // Date labels: as many as comfortably fit, on whole days
  const every = Math.max(1, Math.round(span / Math.max(2, Math.floor(canvasW / 150))));
  for (let d = tlStart; d <= tlEnd; d += every) {
    const mk = document.createElement('div');
    mk.className = 'nmm-tl-tick';
    mk.style.cssText = 'left:' + dayX(d) + 'px;top:' + (spineY - 4) + 'px;';
    mk.innerHTML = '<i></i><span>' + dayLabel(d) + '</span>';
    markers.appendChild(mk);
  }

  cards.forEach(function(c) {
    const n = c.n, el = c.el, x = dayX(c.day);
    el.style.left = c.x + 'px';
    el.style.top  = (c.above ? spineY - c.d - c.h : spineY + c.d) + 'px';

    // A line from the card back to its date
    const conn = document.createElement('div');
    conn.className = 'nmm-tl-conn';
    conn.style.cssText = 'left:' + x + 'px;top:' + (c.above ? spineY - c.d : spineY) + 'px;height:' + c.d + 'px;';
    const dot = document.createElement('div');
    dot.className = 'nmm-tl-dot';
    dot.style.cssText = 'left:' + x + 'px;top:' + spineY + 'px;';
    nodesEl.insertBefore(conn, nodesEl.firstChild);
    nodesEl.insertBefore(dot, nodesEl.firstChild);

    // Drag sideways to reschedule, a day at a time; a plain click opens the card
    el.addEventListener('mousedown', function(e) {
      if (e.button !== 0) return;
      e.preventDefault(); e.stopPropagation();
      const startX = e.clientX;
      let moved = false, newDay = c.day;
      function onMove(ev) {
        if (Math.abs(ev.clientX - startX) > 3) moved = true;
        if (!moved) return;
        newDay = Math.max(tlStart, Math.min(tlEnd, c.day + Math.round((ev.clientX - startX) / pxPerDay)));
        const nx = dayX(newDay);
        el.style.left = (nx - CARD_W / 2) + 'px'; el.classList.add('nmm-tl-dragging');
        conn.style.left = nx + 'px'; dot.style.left = nx + 'px';
        el.querySelector('[data-date]').textContent = dayLabel(newDay);
      }
      function onUp() {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        if (!moved) { openEdit(n); return; }
        if (newDay === c.day) { renderTimeline(); return; }
        const oldDate = n.date;
        n.date = dayStr(newDay); renderTimeline();
        NMMCore.patchNode(n._map_id, n.id, { date: n.date }).catch(function(err) {
          n.date = oldDate; renderTimeline();
          alert('Could not reschedule "' + n.title + '": ' + err.message);
        });
      }
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });
  });
}

// Drag empty space to slide along a wide timeline
(function() {
  const wrap = $('nmm-tl-canvas-wrap');
  let grab = null;
  wrap.addEventListener('mousedown', function(e) {
    if (e.button !== 0) return;
    e.preventDefault();
    grab = { x: e.clientX, sl: wrap.scrollLeft };
    wrap.classList.add('nmm-grabbing');
  });
  document.addEventListener('mousemove', function(e) { if (grab) wrap.scrollLeft = grab.sl - (e.clientX - grab.x); });
  document.addEventListener('mouseup', function() { grab = null; wrap.classList.remove('nmm-grabbing'); });
  let resizeTimer = null;
  window.addEventListener('resize', function() { clearTimeout(resizeTimer); resizeTimer = setTimeout(renderTimeline, 200); });
})();

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

// The More menu (shared with the other views)
NMMMore.init({
  view: 'timeline',
  maps: function() {
    return selectedMapIds.map(function(id) {
      const m = allMaps.find(function(x) { return x.id === id; }) || {};
      return { id: id, title: m.title || 'Untitled', category: m.category || '', nodes: mapNodes[id] || [] };
    });
  },
  imageEl: function() { return $('nmm-tl-canvas'); },
  cards: function() { return Array.prototype.slice.call(root.querySelectorAll('.nmm-tl-node')); }
});

loadAll();
})();
