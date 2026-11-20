/* Navigator Life Maps — shared helpers for every app page.
 * Exposes window.NMMCore. Config comes from the inline `NMM` object printed in <head>. */
(function() {
  if (typeof window.NMM === 'undefined') {
    console.warn('Navigator Life Maps: NMM config not found.');
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
  // ---- View-only maps: a featured map someone else made (signed in, ?map_id=N), or a
  // view link (?share=KEY, signed in or not). Every view can show them; nothing saves.
  const SHARE_KEY = (new URLSearchParams(location.search).get('share') || '').replace(/[^A-Za-z0-9]/g, '');
  const viewOnly = {}; // map id -> the map's summary
  function isViewOnly(id) { return !!viewOnly[id]; }
  // One map, through the public share route when there is a view link
  async function getMap(id) {
    const map = SHARE_KEY ? await fetch(NMM.restUrl + 'share/' + SHARE_KEY).then(function(r) { return r.json(); })
                          : await api('GET', 'maps/' + id);
    if (map && map.id && (SHARE_KEY || map.can_edit === false)) {
      map.can_edit = false;
      viewOnly[map.id] = { id: map.id, title: map.title, color: map.color, featured: !!map.featured, viewOnly: true };
    }
    return map;
  }
  // The maps for a view's picker: your own, plus any view-only map the URL asks for
  async function listMaps() {
    let maps = SHARE_KEY ? [] : await api('GET', 'maps');
    if (!Array.isArray(maps)) maps = [];
    const wanted = SHARE_KEY ? [0] : mapIdsFromUrl().filter(function(id) { return !maps.some(function(m) { return m.id === id; }); });
    for (const id of wanted) {
      const m = await getMap(id);
      if (m && m.id && viewOnly[m.id]) maps.push(viewOnly[m.id]);
    }
    return maps;
  }
  // Said when something would change a view-only map
  function viewOnlyNotice(id) {
    const m = viewOnly[id] || {};
    let box = document.getElementById('nmm-viewonly-modal');
    if (!box) {
      box = document.createElement('div');
      box.id = 'nmm-viewonly-modal'; box.className = 'nmm-modal-overlay';
      (document.querySelector('.nmm-app') || document.body).appendChild(box);
      box.addEventListener('click', function(e) { if (e.target === box || e.target.dataset.act === 'close') box.classList.remove('open'); });
    }
    const canCopy = NMM.isLoggedIn && m.featured;
    box.innerHTML = '<div class="nmm-modal nmm-viewonly"><h3>This map is view only</h3>' +
      '<p>You can look through every view and use the tools that don’t change it, like exports and Animate. ' +
      (canCopy ? 'To change it, make your own copy, or start a fresh map.' : NMM.isLoggedIn ? 'To work on your own version, start a fresh map.' : 'To make maps of your own, sign in or join.') + '</p>' +
      '<div class="nmm-modal-actions"><button type="button" class="nmm-btn" data-act="close">Close</button>' +
      (canCopy ? '<button type="button" class="nmm-btn" data-act="fresh">Start fresh</button><button type="button" class="nmm-btn nmm-btn-primary" data-act="copy">Use this map</button>'
               : '<a class="nmm-btn nmm-btn-primary" href="' + esc(NMM.mapsUrl) + '">' + (NMM.isLoggedIn ? 'Start a fresh map' : 'Sign in or join') + '</a>') +
      '</div></div>';
    const copy = box.querySelector('[data-act="copy"]');
    if (copy) copy.onclick = function() { copyMap(m.id, copy); };
    const fresh = box.querySelector('[data-act="fresh"]');
    if (fresh) fresh.onclick = function() { location.href = NMM.mapsUrl; };
    box.classList.add('open');
  }

  // A card dialog opened on a view-only map: fields read only, no Save, a line saying why
  function lockModal(modal, mapId) {
    const locked = isViewOnly(mapId), h = modal.querySelector('h3');
    modal.querySelectorAll('input,textarea,select').forEach(function(el) { el.disabled = locked; });
    modal.querySelectorAll('.nmm-modal-actions .nmm-btn-primary').forEach(function(b) { b.style.display = locked ? 'none' : ''; });
    if (h && locked) { if (h.textContent !== 'Card') h.dataset.text = h.textContent; h.textContent = 'Card'; }
    else if (h && h.textContent === 'Card' && h.dataset.text) h.textContent = h.dataset.text;
    let note = modal.querySelector('.nmm-viewonly-note');
    if (locked && !note) {
      note = document.createElement('p'); note.className = 'nmm-viewonly-note';
      note.innerHTML = 'This map is view only. <button type="button" class="nmm-link-btn">How to edit it</button>';
      if (h) h.after(note); else modal.prepend(note);
    }
    if (note) { note.style.display = locked ? '' : 'none'; note.querySelector('button').onclick = function() { viewOnlyNotice(mapId); }; }
  }
  // Where a map opens as a mind map: the editor, or the view-only page
  function mapUrl(id) {
    if (SHARE_KEY) return pageUrl(NMM.shareUrl);
    return (isViewOnly(id) ? NMM.shareUrl : NMM.editorUrl) + '?map_id=' + id;
  }

  // Change anything in a map (mutate(map) edits it in place) on top of the latest saved version
  async function patchMap(mapId, mutate) {
    if (isViewOnly(mapId)) { viewOnlyNotice(mapId); const err = new Error('This map is view only.'); err.viewOnly = true; throw err; }
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
    if (SHARE_KEY) return base + '?share=' + SHARE_KEY; // a view link carries its key from view to view
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
          a.href = SHARE_KEY ? pageUrl(NMM.shareUrl) : isViewOnly(shownIds[0]) ? NMM.shareUrl + '?map_id=' + shownIds[0] : NMM.editorUrl + '?map_id=' + shownIds[0]; a.removeAttribute('aria-disabled'); a.removeAttribute('title');
        } else if (!a.classList.contains('active')) {
          a.removeAttribute('href'); a.setAttribute('aria-disabled', 'true');
          a.title = shownIds.length ? 'Pick one map to open its mind map' : 'Open one map to see its mind map';
        }
      } else if (v === 'roadmap') {
        a.href = SHARE_KEY ? pageUrl(NMM.roadmapUrl) : NMM.roadmapUrl + (shownIds.length === 1 ? '?map_id=' + shownIds[0] : '');
      } else {
        a.href = pageUrl(v === 'kanban' ? NMM.kanbanUrl : v === 'calendar' ? NMM.calendarUrl : NMM.timelineUrl, linkIds);
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
  // Cards more than STALE_DAYS past their date (or end date) are left out: an old map
  // that is still worth keeping shouldn't keep calling for attention.
  const STALE_DAYS = 10;
  function attention(nodes) {
    const t = today();
    return nodes.filter(function(n) {
      if (!n.date || n.tag === 'done' || n.tag === 'review' || n._readonly || dayOf(n.date) === null) return false;
      const last = Math.max(dayOf(n.date), (n.end && dayOf(n.end)) || dayOf(n.date));
      return last >= t - STALE_DAYS;
    })
      .map(function(n) { const days = dayOf(n.date) - t; return { n: n, days: days, tag: n.tag || '', when: whenText(days) }; })
      .sort(function(a, b) { return a.days - b.days; });
  }

  // ---- Card links ----
  // node.link = { type: 'map' | 'nav' | 'web', id, url, title, kind, locked } (resolved by the server per viewer).
  // A map opens in the editor (it sends people who can only read it to the read-only view), with
  // ?from= so that map offers a way back. Navigator and web links open in a new tab.
  const LINK_ICONS = {
    map: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4" fill="currentColor"/><circle cx="4.5" cy="5" r="2.4"/><circle cx="19.5" cy="5" r="2.4"/><circle cx="12" cy="20.5" r="2.4"/><path d="M9.3 9.4 6.3 6.7M14.7 9.4l3-2.7M12 16v2"/></svg>',
    nav: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M12 3.5l2.2 8.5L12 20.5 9.8 12z" fill="currentColor" stroke="none"/><path d="M3.5 12l8.5-2.2L20.5 12 12 14.2z" fill="currentColor" stroke="none" opacity=".55"/></svg>',
    web: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M2.5 12h19M12 2.5c3 3.2 3 15.8 0 19M12 2.5c-3 3.2-3 15.8 0 19"/></svg>'
  };
  const LINK_KIND = { map: 'Linked map', nav: 'On Navigator', web: 'Web page' };
  function safeUrl(u) { return /^https?:\/\//i.test(String(u || '')) ? String(u) : ''; }
  // Where a link goes, or '' when it can't be opened here
  function linkHref(link, fromId) {
    if (!link || link.locked || !LINK_ICONS[link.type]) return '';
    if (link.type === 'map') {
      if (!link.id || !window.NMM || !NMM.isLoggedIn) return '';
      const from = fromId || new URLSearchParams(location.search).get('map_id');
      return NMM.editorUrl + '?map_id=' + encodeURIComponent(link.id) + (from && String(from) !== String(link.id) ? '&from=' + encodeURIComponent(from) : '');
    }
    return safeUrl(link.url);
  }
  function linkTitle(link) {
    if (!link) return '';
    if (link.title) return link.title;
    if (link.type === 'web') { try { return new URL(link.url).hostname.replace(/^www\./, ''); } catch (e) { return 'Web page'; } }
    return '';
  }
  // The small chip shown on a card (or a round icon in the Bubble layout): an <a>, so it works anywhere
  function linkChip(link, fromId) {
    const href = linkHref(link, fromId);
    if (!href) return '';
    const t = linkTitle(link), ext = link.type !== 'map';
    return '<a class="nmm-link nmm-link-' + link.type + '" href="' + esc(href) + '"' + (ext ? ' target="_blank" rel="noopener"' : '') +
      ' title="' + esc((link.type === 'map' ? 'Open the map ' : 'Open ') + t + (ext ? ' (new tab)' : '')) + '" data-link="1">' +
      '<i>' + LINK_ICONS[link.type] + '</i><span>' + esc(t) + '</span></a>';
  }
  function hoverLink(link) {
    const href = linkHref(link);
    if (!href) return '';
    const ext = link.type !== 'map';
    return '<a class="nmm-hc-link nmm-link-' + link.type + '" href="' + esc(href) + '"' + (ext ? ' target="_blank" rel="noopener"' : '') + '>' +
      '<i>' + LINK_ICONS[link.type] + '</i><span><small>' + esc(link.kind && link.type === 'nav' ? link.kind : LINK_KIND[link.type]) + '</small>' +
      '<strong>' + esc(linkTitle(link)) + '</strong></span><b>Open →</b></a>';
  }
  // Notes text with web addresses made clickable (escaped first)
  function linkify(text) {
    return esc(text).replace(/\b(https?:\/\/[^\s<]+[^\s<.,;:!?)\]'"]|www\.[^\s<]+[^\s<.,;:!?)\]'"])/gi, function(u) {
      const href = /^www\./i.test(u) ? 'https://' + u : u;
      return '<a href="' + href + '" target="_blank" rel="noopener">' + u + '</a>';
    });
  }

  // A map opened from a card link (?from=ID): the toolbar's Maps button becomes "← Back to <that map>"
  function fromParam() { const f = new URLSearchParams(location.search).get('from'); return f && /^\d+$/.test(f) ? f : ''; }
  // The maps to offer a way back to: just the one you came from (?from=) when it links here, else all of them
  function backTargets(linkedFrom) {
    const list = linkedFrom || [], from = fromParam();
    const came = from && list.filter(function(x) { return String(x.id) === from; });
    return came && came.length ? came : list;
  }
  // Opened some other way, it points to the first map that links here (linkedFrom, from the server).
  async function backLink(root, linkedFrom) {
    const from = fromParam(), btn = root && root.querySelector('.nmm-btn-nav');
    if (!btn || !window.NMM || !NMM.isLoggedIn) return;
    const known = (linkedFrom || []).find(function(x) { return !from || String(x.id) === from; });
    if (!from && !known) return;
    const m = known || await api('GET', 'maps/' + from);
    if (!m || !m.id) return;
    btn.href = NMM.editorUrl + '?map_id=' + m.id;
    btn.textContent = '← Back to ' + (m.title || 'the map');
    btn.classList.add('nmm-btn-back');
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
        (subs.length ? '<ul class="nmm-hc-notes">' + subs.map(function(s) { return '<li>' + linkify(s) + '</li>'; }).join('') + '</ul>' : '') +
        (n.sefirot && SEFIROT_LABELS[n.sefirot] ? '<div class="nmm-hc-where">✦ ' + esc(SEFIROT_LABELS[n.sefirot]) + '</div>' : '') +
        (n.where ? '<div class="nmm-hc-where">' + esc(n.where) + '</div>' : '') +
        hoverLink(n.link);
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
    // Leaving the card closes it after a moment, so the pointer can move onto it to click a link
    opts.root.addEventListener('mouseout', function(e) {
      const el = e.target.closest(opts.selector);
      if (el && !el.contains(e.relatedTarget) && !box.contains(e.relatedTarget)) { clearTimeout(timer); timer = setTimeout(hide, 220); }
    });
    box.addEventListener('mouseenter', function() { clearTimeout(timer); });
    box.addEventListener('mouseleave', hide);
    ['mousedown', 'wheel', 'keydown'].forEach(function(ev) {
      document.addEventListener(ev, function(e) { if (!box.contains(e.target)) hide(); }, true);
    });
    window.addEventListener('scroll', hide, true);
  }

  // ---- Notice: one card that needs a look, faded in at the bottom right (computer screens only) ----
  // opts: root, key (remembers a dismissal for this visit), label(item), detail(item), onOpen(item)
  const WIDE = window.matchMedia('(min-width: 900px) and (hover: hover)');
  function notice(opts) {
    const el = document.createElement('div');
    el.className = 'nmm-notice'; el.setAttribute('role', 'status');
    // On <body>, in its own .nmm-app (for the styles): inside the page content the theme's
    // stacking keeps it under the site footer, whatever its z-index
    const host = document.createElement('div');
    host.className = 'nmm-app nmm-notice-host';
    host.appendChild(el); document.body.appendChild(host);
    let all = [], items = [], idx = 0, shownOnce = false;
    // "Don't remind me": muted cards are kept in this browser, by card and date,
    // so a card that gets a new date reminds again
    const MUTE_KEY = 'nmm_notice_muted';
    function muteId(it) { return it.n.id + '|' + (it.n.date || ''); }
    function muted() { try { return JSON.parse(localStorage.getItem(MUTE_KEY) || '[]'); } catch(e) { return []; } }
    function mute(it) {
      try { const m = muted().filter(function(x) { return x !== muteId(it); }); m.push(muteId(it)); localStorage.setItem(MUTE_KEY, JSON.stringify(m.slice(-500))); } catch(e) {}
    }
    function unmuted(list) { const m = muted(); return list.filter(function(it) { return m.indexOf(muteId(it)) < 0; }); }
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
          (items.length > 1 ? '<button type="button" class="nmm-notice-btn" data-act="next">Next ›</button>' : '') +
          '<button type="button" class="nmm-notice-btn nmm-notice-mute" data-act="mute" title="Stop reminding me about this card (it comes back if its date changes)">Don&rsquo;t remind me</button></div>';
      if (!shownOnce) { shownOnce = true; setTimeout(function() { el.classList.add('open'); }, 1200); }
      else el.classList.add('open');
    }
    el.addEventListener('click', function(e) {
      const b = e.target.closest('[data-act]'), act = b ? b.dataset.act : 'open';
      if (act === 'close') { try { sessionStorage.setItem('nmm_notice_' + opts.key, '1'); } catch(err) {} el.classList.remove('open'); return; }
      if (act === 'next') { idx = (idx + 1) % items.length; render(); return; }
      if (act === 'mute') { if (items[idx]) mute(items[idx]); items = unmuted(all); if (idx >= items.length) idx = 0; render(); return; }
      if (items[idx]) opts.onOpen(items[idx]);
    });
    return {
      // New list: stay on the same card while it is unchanged; once it has been dealt with
      // (new date, done, gone) go back to the most pressing one
      update: function(list) {
        const cur = items[idx];
        all = list || [];
        items = unmuted(all);
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
      if (selected.length === opts.maps.length && opts.maps.length !== 1) return '⊞ All maps ▾';
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

  // Every dialog (.nmm-modal-overlay) moves to its own .nmm-app host on <body>: inside the page content
  // the theme's stacking puts the site footer over it whatever its z-index (its links showed through
  // and covered the buttons). Page scripts find dialogs by id, so moving them changes nothing else.
  (function liftDialogs() {
    const dialogs = document.querySelectorAll('.nmm-app .nmm-modal-overlay');
    if (!dialogs.length) return;
    const host = document.createElement('div');
    host.className = 'nmm-app nmm-modal-host';
    dialogs.forEach(function(d) { host.appendChild(d); });
    document.body.appendChild(host);
  })();

  window.NMMCore = {
    api: api, refreshNonce: refreshNonce, patchNode: patchNode, patchMap: patchMap,
    shareKey: SHARE_KEY, getMap: getMap, listMaps: listMaps, isViewOnly: isViewOnly, viewOnlyNotice: viewOnlyNotice, lockModal: lockModal, mapUrl: mapUrl,
    mapIdsFromUrl: mapIdsFromUrl, pageUrl: pageUrl, esc: esc,
    loadHtml2Canvas: loadHtml2Canvas, mapFilter: mapFilter,
    TAG_COLORS: TAG_COLORS, SEFIROT_LABELS: SEFIROT_LABELS, isTask: isTask,
    setViewTabs: setViewTabs, dropdown: dropdown,
    copyMap: copyMap, dayOf: dayOf, dayAfter: dayAfter, today: today, longDate: longDate, attention: attention, hoverCard: hoverCard, notice: notice,
    LINK_ICONS: LINK_ICONS, linkHref: linkHref, linkTitle: linkTitle, linkChip: linkChip, linkify: linkify, safeUrl: safeUrl, backLink: backLink, fromParam: fromParam, backTargets: backTargets
  };
})();
