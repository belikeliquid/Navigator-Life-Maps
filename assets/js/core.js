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
    const map = await api('GET', 'maps/' + mapId);
    if (!map || !map.nodes) throw new Error((map && map.message) || 'Map not found');
    const node = map.nodes.find(function(n) { return n.id === nodeId; });
    if (!node) throw new Error('Card not found in map');
    Object.assign(node, fields);
    const saved = await api('PUT', 'maps/' + mapId, { nodes: map.nodes, title: map.title, settings: map.settings || {}, islands: map.islands || [] });
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
    api: api, refreshNonce: refreshNonce, patchNode: patchNode,
    mapIdsFromUrl: mapIdsFromUrl, pageUrl: pageUrl, esc: esc,
    loadHtml2Canvas: loadHtml2Canvas, mapFilter: mapFilter,
    TAG_COLORS: TAG_COLORS, SEFIROT_LABELS: SEFIROT_LABELS, isTask: isTask
  };
})();
