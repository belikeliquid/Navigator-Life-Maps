/* Navigator Mind Maps — shared helpers for every app page.
 * Exposes window.NMMCore. Config comes from the inline `NMM` object printed in <head>. */
(function() {
  if (typeof window.NMM === 'undefined') {
    console.warn('Navigator Mind Maps: NMM config not found.');
    return;
  }

  // Keep the REST nonce fresh. This has to be WordPress's admin-ajax refresh: a REST request
  // sent without a nonce counts as logged out, so a REST route can only hand out a nonce that
  // fails for a logged-in user. Logged out, this gets a 400 and the current nonce stays.
  function refreshNonce() {
    return fetch(NMM.ajaxUrl + '?action=rest-nonce', { credentials: 'same-origin' })
      .then(function(r) { return r.ok ? r.text() : ''; })
      .then(function(t) { if (/^[0-9a-f]{10}$/.test(t.trim())) NMM.nonce = t.trim(); return NMM.nonce; })
      .catch(function() { return NMM.nonce; });
  }
  setInterval(refreshNonce, 600000);

  // Returns parsed JSON. Errors from WordPress come back as {code, message, data}.
  // A rejected nonce is refreshed and the call sent once more: WordPress refuses those before
  // doing anything, so nothing can happen twice. It also goes stale if you log in or out in another tab.
  function api(method, endpoint, data) {
    function send() {
      return fetch(NMM.restUrl + endpoint, {
        method: method,
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NMM.nonce },
        body: data ? JSON.stringify(data) : undefined
      }).then(function(r) { return r.json(); });
    }
    function staleNonce(res) { return res && res.code === 'rest_cookie_invalid_nonce'; }
    return send().then(function(res) {
      if (!staleNonce(res)) return res;
      return refreshNonce().then(send).then(function(again) {
        if (staleNonce(again)) again.message = 'You seem to be logged out. Please log in again in another tab, then try again here.';
        return again;
      });
    });
  }

  // Change fields on one node from a page other than the editor (Kanban, timeline).
  // Loads the latest map first so it never writes back stale nodes.
  async function patchNode(mapId, nodeId, fields) {
    return patchMap(mapId, function(map) {
      const node = map.nodes.find(function(n) { return n.id === nodeId; });
      if (!node) throw new Error('Card not found in map');
      Object.assign(node, fields);
    });
  }
  // Change anything in a map (mutate(map) edits it in place) on top of the latest saved version
  async function patchMap(mapId, mutate) {
    const map = await api('GET', 'maps/' + mapId);
    if (!map || !map.nodes) throw new Error((map && map.message) || 'Map not found');
    map.settings = map.settings || {};
    mutate(map);
    const saved = await api('PUT', 'maps/' + mapId, { nodes: map.nodes, title: map.title, settings: map.settings, islands: map.islands || [] });
    if (!saved || !saved.id) throw new Error((saved && saved.message) || 'Save failed');
    return saved;
  }

  // Map ids in the current URL: ?map_id=12 or ?map_id=12,15
  function mapIdsFromUrl() {
    const raw = new URLSearchParams(location.search).get('map_id') || '';
    return raw.split(',').map(function(x) { return parseInt(x, 10); }).filter(Boolean);
  }
  function pageUrl(base, mapIds) {
    return mapIds && mapIds.length ? base + '?map_id=' + mapIds.join(',') : base;
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // html2canvas is ~200 KB, so it loads only when someone exports a PNG.
  let h2cPromise = null;
  function loadHtml2Canvas() {
    if (window.html2canvas) return Promise.resolve(window.html2canvas);
    if (!h2cPromise) {
      h2cPromise = new Promise(function(resolve, reject) {
        const s = document.createElement('script');
        s.src = NMM.pluginUrl + 'assets/html2canvas.min.js';
        s.onload = function() { resolve(window.html2canvas); };
        s.onerror = function() { h2cPromise = null; reject(new Error('Could not load the PNG exporter')); };
        document.head.appendChild(s);
      });
    }
    return h2cPromise;
  }

  // Only cards marked as Kanban tasks appear on the board. Cards saved before the
  // checkbox existed have no `task` field: a tag made them a task.
  // Keep the shared view tabs pointing at the maps on screen. linkIds: [] means all maps;
  // shownIds: the maps actually showing, so Mind Map works whenever exactly one is.
  function setViewTabs(linkIds, shownIds) {
    shownIds = shownIds || linkIds;
    document.querySelectorAll('.nmm-view-tabs .nmm-vtab').forEach(function(a) {
      const v = a.dataset.view;
      if (v === 'map') {
        if (shownIds.length === 1) {
          a.href = NMM.editorUrl + '?map_id=' + shownIds[0]; a.removeAttribute('aria-disabled'); a.removeAttribute('title');
        } else if (!a.classList.contains('active')) {
          a.removeAttribute('href'); a.setAttribute('aria-disabled', 'true');
          a.title = shownIds.length ? 'Pick one map to open its mind map' : 'Open one map to see its mind map';
        }
      } else if (v === 'roadmap') {
        a.href = NMM.roadmapUrl + (shownIds.length === 1 ? '?map_id=' + shownIds[0] : '');
      } else {
        a.href = pageUrl(v === 'kanban' ? NMM.kanbanUrl : NMM.timelineUrl, linkIds);
      }
    });
  }

  // A menu that opens under its button (fixed, so toolbars can't clip it) and closes on a pick,
  // a click elsewhere or Escape. onOpen runs each time it opens.
  function dropdown(button, menu, onOpen) {
    button.setAttribute('aria-haspopup', 'true'); button.setAttribute('aria-expanded', 'false');
    function close() { menu.classList.remove('open'); button.setAttribute('aria-expanded', 'false'); }
    button.addEventListener('click', function(e) {
      e.stopPropagation();
      if (menu.classList.contains('open')) { close(); return; }
      document.querySelectorAll('.nmm-menu.open').forEach(function(m) { m.classList.remove('open'); });
      menu.classList.add('open'); button.setAttribute('aria-expanded', 'true');
      if (onOpen) onOpen();
      const r = button.getBoundingClientRect();
      menu.style.top = r.bottom + 6 + 'px';
      menu.style.left = Math.max(8, Math.min(r.left, innerWidth - menu.offsetWidth - 8)) + 'px';
    });
    document.addEventListener('click', function(e) { if (!menu.contains(e.target)) close(); });
    menu.addEventListener('click', function(e) { if (e.target.closest('button, a')) close(); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') close(); });
  }

  // ---- Dates as whole days, in the viewer's own time zone ----
  function dayOf(str) {
    const p = String(str || '').split('-');
    return p.length === 3 ? Date.UTC(+p[0], +p[1] - 1, +p[2]) / 86400000 : null;
  }
  // All-day calendar events end on the following day: '2026-11-30' -> '20261201'
  function dayAfter(str) { const d = dayOf(str); return d === null ? '' : new Date((d + 1) * 86400000).toISOString().slice(0, 10).replace(/-/g, ''); }
  function today() { const t = new Date(); return Date.UTC(t.getFullYear(), t.getMonth(), t.getDate()) / 86400000; }
  function longDate(str) {
    const d = dayOf(str);
    return d === null ? str : new Date(d * 86400000).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
  }
  function whenText(days) {
    if (days < -1) return 'Overdue by ' + -days + ' days';
    if (days === -1) return 'Was due yesterday';
    if (days === 0) return 'Due today';
    if (days === 1) return 'Due tomorrow';
    return 'Due in ' + days + ' days';
  }

  // Dated cards still being worked on, most pressing first: overdue (oldest first), then today, then soonest.
  // Review and Done are left out: the work there is finished or waiting on someone else.
  function attention(nodes) {
    const t = today();
    return nodes.filter(function(n) { return n.date && n.tag !== 'done' && n.tag !== 'review' && !n._readonly && dayOf(n.date) !== null; })
      .map(function(n) { const days = dayOf(n.date) - t; return { n: n, days: days, tag: n.tag || '', when: whenText(days) }; })
      .sort(function(a, b) { return a.days - b.days; });
  }

  // ---- Hover card: the whole card in an easy-to-read window (mouse and trackpad only) ----
  // opts: root (where it lives), selector (the cards), info(el) -> {title, subs, date, tag, sefirot, where} or null
  const CAN_HOVER = window.matchMedia('(hover: hover) and (pointer: fine)');
  function hoverCard(opts) {
    if (!CAN_HOVER.matches) return;
    const box = document.createElement('div');
    box.className = 'nmm-hovercard'; box.setAttribute('role', 'tooltip');
    opts.root.appendChild(box);
    let timer = null, current = null;
    function hide() { clearTimeout(timer); timer = null; current = null; box.classList.remove('open'); }
    function show(el) {
      const n = opts.info(el);
      if (!n || !el.isConnected) return;
      const tc = TAG_COLORS[n.tag], subs = (n.subs || []).filter(Boolean);
      box.innerHTML =
        '<div class="nmm-hc-title">' + esc(n.title || 'Untitled') + '</div>' +
        ((n.date || tc) ? '<div class="nmm-hc-meta">' + (n.date ? '<span class="nmm-hc-date">📅 ' + esc(longDate(n.date)) + (n.end ? ' → ' + esc(longDate(n.end)) : '') + '</span>' : '') +
          (tc ? '<span class="nmm-hc-tag" style="background:' + tc.bg + ';color:' + tc.text + '">' + tc.label + '</span>' : '') + '</div>' : '') +
        (subs.length ? '<ul class="nmm-hc-notes">' + subs.map(function(s) { return '<li>' + esc(s) + '</li>'; }).join('') + '</ul>' : '') +
        (n.sefirot && SEFIROT_LABELS[n.sefirot] ? '<div class="nmm-hc-where">✦ ' + esc(SEFIROT_LABELS[n.sefirot]) + '</div>' : '') +
        (n.where ? '<div class="nmm-hc-where">' + esc(n.where) + '</div>' : '');
      box.classList.add('open');
      const r = el.getBoundingClientRect(), w = box.offsetWidth, h = box.offsetHeight;
      const left = r.right + 12 + w < innerWidth ? r.right + 12 : Math.max(8, r.left - 12 - w);
      box.style.left = left + 'px';
      box.style.top = Math.max(8, Math.min(r.top, innerHeight - h - 8)) + 'px';
      current = el;
    }
    opts.root.addEventListener('mouseover', function(e) {
      const el = e.target.closest(opts.selector);
      if (!el || el === current || e.buttons) return;
      hide();
      timer = setTimeout(function() { show(el); }, opts.delay || 450);
    });
    opts.root.addEventListener('mouseout', function(e) {
      const el = e.target.closest(opts.selector);
      if (el && !el.contains(e.relatedTarget)) hide();
    });
    ['mousedown', 'wheel', 'keydown'].forEach(function(ev) { document.addEventListener(ev, hide, true); });
    window.addEventListener('scroll', hide, true);
  }

  // ---- Notice: one card that needs a look, faded in at the bottom right (computer screens only) ----
  // opts: root, key (remembers a dismissal for this visit), label(item), detail(item), onOpen(item)
  const WIDE = window.matchMedia('(min-width: 900px) and (hover: hover)');
  function notice(opts) {
    const el = document.createElement('div');
    el.className = 'nmm-notice'; el.setAttribute('role', 'status');
    opts.root.appendChild(el);
    let items = [], idx = 0, shownOnce = false;
    function dismissed() { try { return sessionStorage.getItem('nmm_notice_' + opts.key) === '1'; } catch(e) { return false; } }
    function render() {
      const it = items[idx];
      if (!it || dismissed() || !WIDE.matches) { el.classList.remove('open'); return; }
      const tc = TAG_COLORS[it.n.tag];
      el.innerHTML =
        '<button type="button" class="nmm-notice-x" aria-label="Dismiss" data-act="close">×</button>' +
        '<div class="nmm-notice-label' + (it.days < 0 ? ' nmm-notice-late' : '') + '">' + esc(opts.label(it)) +
          (items.length > 1 ? ' <span>· ' + (idx + 1) + ' of ' + items.length + '</span>' : '') + '</div>' +
        '<div class="nmm-notice-title">' + esc(it.n.title || 'Untitled') + '</div>' +
        '<div class="nmm-notice-detail">' + (tc ? '<span class="nmm-hc-tag" style="background:' + tc.bg + ';color:' + tc.text + '">' + tc.label + '</span> ' : '') + esc(opts.detail(it)) + '</div>' +
        '<div class="nmm-notice-actions"><button type="button" class="nmm-notice-btn nmm-notice-open" data-act="open">Open card</button>' +
          (items.length > 1 ? '<button type="button" class="nmm-notice-btn" data-act="next">Next ›</button>' : '') + '</div>';
      if (!shownOnce) { shownOnce = true; setTimeout(function() { el.classList.add('open'); }, 1200); }
      else el.classList.add('open');
    }
    el.addEventListener('click', function(e) {
      const b = e.target.closest('[data-act]'), act = b ? b.dataset.act : 'open';
      if (act === 'close') { try { sessionStorage.setItem('nmm_notice_' + opts.key, '1'); } catch(err) {} el.classList.remove('open'); return; }
      if (act === 'next') { idx = (idx + 1) % items.length; render(); return; }
      if (items[idx]) opts.onOpen(items[idx]);
    });
    return {
      // New list: stay on the same card while it is unchanged; once it has been dealt with
      // (new date, done, gone) go back to the most pressing one
      update: function(list) {
        const cur = items[idx];
        items = list || [];
        const keep = cur ? items.findIndex(function(it) { return it.n.id === cur.n.id && it.days === cur.days && it.tag === cur.tag; }) : -1;
        idx = keep >= 0 ? keep : 0;
        render();
      }
    };
  }

  // Copy a map (a featured one, or your own) into a new map of yours, then open it
  function copyMap(id, btn) {
    const label = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Copying…'; }
    return api('POST', 'maps/' + id + '/copy').then(function(map) {
      if (map && map.id) { location.href = NMM.editorUrl + '?map_id=' + map.id; return; }
      alert((map && map.message) || 'Could not copy this map.');
      if (btn) { btn.disabled = false; btn.textContent = label; }
    }).catch(function() {
      alert('Could not copy this map.');
      if (btn) { btn.disabled = false; btn.textContent = label; }
    });
  }

  function isTask(n) { return n.task === undefined ? !!n.tag : !!n.task; }

  const TAG_COLORS = {
    start:  { bg: '#B5D4F4', text: '#0C447C', label: 'Start'  },
    doing:  { bg: '#FAC775', text: '#633806', label: 'Doing'  },
    sprint: { bg: '#EF9F27', text: '#412402', label: 'Sprint' },
    review: { bg: '#F4C0D1', text: '#4B1528', label: 'Review' },
    done:   { bg: '#9FE1CB', text: '#04342C', label: 'Done'   }
  };

  const SEFIROT_LABELS = {
    kether: 'Kether', chochma: 'Chochma', binah: 'Binah', chesed: 'Chesed',
    geburah: 'Geburah', tiphereth: 'Tiphereth', netzach: 'Netzach',
    hod: 'Hod', yesod: 'Yesod', malkuth: 'Malkuth'
  };

  // Shared map-filter dropdown used by Kanban and Timeline.
  // opts: { button, panel, list, maps, selected (array of ids), onChange(selectedIds) }
  function mapFilter(opts) {
    let selected = opts.selected.slice();
    function label() {
      if (selected.length === opts.maps.length) return '⊞ All maps ▾';
      if (!selected.length) return '⊞ No maps ▾';
      if (selected.length === 1) {
        const m = opts.maps.find(function(x) { return x.id === selected[0]; });
        return '⊞ ' + (m ? m.title : '1 map') + ' ▾';
      }
      return '⊞ ' + selected.length + ' maps ▾';
    }
    function sync() {
      opts.list.querySelectorAll('input').forEach(function(cb) { cb.checked = selected.includes(+cb.dataset.mapId); });
      opts.button.textContent = label();
      opts.onChange(selected.slice());
    }
    opts.maps.forEach(function(m) {
      const row = document.createElement('label');
      row.className = 'nmm-kb-filter-row';
      row.innerHTML = '<input type="checkbox" data-map-id="' + m.id + '" /> ' + esc(m.title);
      row.querySelector('input').addEventListener('change', function() {
        selected = this.checked ? selected.concat(m.id) : selected.filter(function(x) { return x !== m.id; });
        sync();
      });
      opts.list.appendChild(row);
    });
    opts.panel.querySelector('[data-select="all"]').addEventListener('click', function() {
      selected = opts.maps.map(function(m) { return m.id; }); sync();
    });
    opts.panel.querySelector('[data-select="none"]').addEventListener('click', function() {
      selected = []; sync();
    });
    // Fixed position escapes the toolbar's overflow clip
    opts.button.addEventListener('click', function(e) {
      e.stopPropagation();
      if (opts.panel.style.display === 'none') {
        const rect = this.getBoundingClientRect();
        opts.panel.style.position = 'fixed';
        opts.panel.style.top = rect.bottom + 4 + 'px';
        opts.panel.style.left = rect.left + 'px';
        opts.panel.style.display = 'block';
      } else {
        opts.panel.style.display = 'none';
      }
    });
    opts.panel.addEventListener('click', function(e) { e.stopPropagation(); });
    document.addEventListener('click', function() { opts.panel.style.display = 'none'; });
    sync();
  }

  window.NMMCore = {
    api: api, refreshNonce: refreshNonce, patchNode: patchNode, patchMap: patchMap,
    mapIdsFromUrl: mapIdsFromUrl, pageUrl: pageUrl, esc: esc,
    loadHtml2Canvas: loadHtml2Canvas, mapFilter: mapFilter,
    TAG_COLORS: TAG_COLORS, SEFIROT_LABELS: SEFIROT_LABELS, isTask: isTask,
    setViewTabs: setViewTabs, dropdown: dropdown,
    copyMap: copyMap, dayOf: dayOf, dayAfter: dayAfter, today: today, longDate: longDate, attention: attention, hoverCard: hoverCard, notice: notice
  };
})();
