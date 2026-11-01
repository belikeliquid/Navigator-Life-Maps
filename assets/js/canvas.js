/* Navigator Mind Maps — map canvas rendering shared by the editor and the share view.
 * NMMCanvas.create(opts) returns helpers bound to one canvas.
 *   opts.getNodes()        current node array
 *   opts.editable          show Edit / + Child / Delete buttons
 *   opts.decorate(el, n)   called for each rendered node element (event wiring)
 *   opts.selectedId()      id to highlight (optional) */
(function() {
  const esc = NMMCore.esc;
  const LINE_STYLES = [
    { stroke: '#0BBFBF', width: 2,   dash: '5 4' },
    { stroke: '#0BBFBF', width: 1.5, dash: '5 4' },
    { stroke: '#5DCAA5', width: 1.5, dash: '4 4' },
    { stroke: '#EF9F27', width: 1.5, dash: '4 4' },
    { stroke: '#FAC775', width: 1,   dash: '3 4' }
  ];

  function create(opts) {
    const canvas = document.getElementById('nmm-canvas');
    const svg    = document.getElementById('nmm-lines');
    const motif  = document.getElementById('nmm-motif');
    const wrap   = document.getElementById('nmm-canvas-wrap');
    let quoteIdx = 0;

    function nodes() { return opts.getNodes(); }
    function find(id) { return nodes().find(function(x) { return x.id === id; }); }
    function depth(id) {
      let d = 0, n = find(id);
      while (n && n.parent) { d++; n = find(n.parent); }
      return d;
    }
    function isVisible(id) {
      const n = find(id);
      if (!n) return false;
      if (!n.parent) return true;
      const p = find(n.parent);
      return !!p && !p.collapsed && isVisible(p.id);
    }
    function hasChildren(id) { return nodes().some(function(n) { return n.parent === id; }); }
    function isDescendant(id, ancestorId) {
      let n = find(id);
      while (n && n.parent) { if (n.parent === ancestorId) return true; n = find(n.parent); }
      return false;
    }
    function getEl(id) { return canvas.querySelector('.nmm-node[data-id="' + id + '"]'); }

    function nodeHtml(n) {
      const ro       = !!n._readonly;
      const expander = hasChildren(n.id) ? '<span class="nmm-expander" data-eid="' + esc(n.id) + '">' + (n.collapsed ? '+' : '−') + '</span>' : '';
      const subs     = (n.subs && n.subs.length) ? '<div class="nmm-node-sub">' + n.subs.map(function(s) { return '<span>' + esc(s) + '</span>'; }).join('') + '</div>' : '';
      const date     = n.date ? '<div class="nmm-node-date">📅 ' + esc(n.date) + '</div>' : '';
      const linked   = ro ? '<div class="nmm-linked-badge">⛓ ' + (opts.editable
        ? '<a href="' + NMM.editorUrl + '?map_id=' + n._island_id + '" target="_blank" style="color:inherit">Edit source: ' + esc(n._island_title) + '</a>'
        : esc(n._island_title)) + '</div>' : '';
      const tc       = n.tag && NMMCore.TAG_COLORS[n.tag];
      const tag      = tc ? '<span class="nmm-node-tag nmm-tag-' + n.tag + '" style="background:' + tc.bg + ';color:' + tc.text + '">' + tc.label + '</span>' : '';
      const sef      = n.sefirot && NMMCore.SEFIROT_LABELS[n.sefirot] ? '<div class="nmm-node-sefirot">✦ ' + NMMCore.SEFIROT_LABELS[n.sefirot] + '</div>' : '';
      const actions  = opts.editable && !ro ? '<div class="nmm-node-actions">' +
        '<button class="nmm-na-btn" data-action="edit">Edit</button>' +
        '<button class="nmm-na-btn" data-action="add">+ Child</button>' +
        '<button class="nmm-na-btn nmm-na-red" data-action="del">Delete</button></div>' : '';
      return '<div class="nmm-node-title-row"><span class="nmm-node-title">' + esc(n.title) + '</span>' + expander + '</div>' +
        subs + date + linked + actions + tag + sef;
    }

    function render() {
      canvas.querySelectorAll('.nmm-node').forEach(function(el) { el.remove(); });
      const sel = opts.selectedId ? opts.selectedId() : null;
      nodes().forEach(function(n) {
        if (!isVisible(n.id)) return;
        const el = document.createElement('div');
        el.className = 'nmm-node' + (sel === n.id ? ' nmm-selected' : '') + (n._readonly ? ' nmm-linked' : '');
        el.dataset.depth = Math.min(depth(n.id), 4);
        el.dataset.id = n.id;
        el.style.left = n.x + 'px'; el.style.top = n.y + 'px';
        el.innerHTML = nodeHtml(n);
        if (opts.decorate) opts.decorate(el, n);
        canvas.appendChild(el);
      });
      drawLines();
      renderMotif();
    }

    function drawLines() {
      svg.innerHTML = '';
      nodes().forEach(function(n) {
        if (!n.parent || !isVisible(n.id)) return;
        const p = find(n.parent);
        if (!p || p.collapsed) return;
        const pEl = getEl(p.id), cEl = getEl(n.id);
        if (!pEl || !cEl) return;
        const px = p.x + pEl.offsetWidth / 2, py = p.y + pEl.offsetHeight, cx = n.x + cEl.offsetWidth / 2, cy = n.y;
        const dx = Math.abs(cx - px), dy = Math.abs(cy - py);
        const s = LINE_STYLES[Math.min(depth(n.id), LINE_STYLES.length - 1)];
        const d = dy > 0 && dx / dy < 0.10 ? 'M' + px + ',' + py + ' L' + px + ',' + cy
          : 'M' + px + ',' + py + ' C' + px + ',' + (py + cy) / 2 + ' ' + cx + ',' + (py + cy) / 2 + ' ' + cx + ',' + cy;
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', d); path.setAttribute('fill', 'none'); path.setAttribute('stroke', s.stroke);
        path.setAttribute('stroke-width', s.width); path.setAttribute('stroke-dasharray', s.dash); path.setAttribute('stroke-linecap', 'round');
        svg.appendChild(path);
      });
    }

    // Motif is fixed text; otherwise rotate through the quotes every 8 seconds.
    let settings = {};
    function setSettings(s) { settings = s || {}; }
    function renderMotif() {
      const m = settings.motif || (opts.defaults && opts.defaults.motif) || '';
      const q = settings.quotes || (opts.defaults && opts.defaults.quotes) || '';
      if (m) { motif.textContent = m; return; }
      const qs = q.split(',').map(function(x) { return x.trim(); }).filter(Boolean);
      motif.textContent = qs.length ? qs[quoteIdx % qs.length] : '';
    }
    setInterval(function() { quoteIdx++; renderMotif(); }, 8000);

    function bounds() {
      let minX = Infinity, minY = Infinity, maxX = 0, maxY = 0;
      nodes().forEach(function(n) {
        if (!isVisible(n.id)) return;
        const el = getEl(n.id);
        minX = Math.min(minX, n.x); minY = Math.min(minY, n.y);
        maxX = Math.max(maxX, n.x + (el ? el.offsetWidth : 180));
        maxY = Math.max(maxY, n.y + (el ? el.offsetHeight : 80));
      });
      return minX === Infinity ? null : { minX: minX, minY: minY, maxX: maxX, maxY: maxY };
    }

    function autoCenter() {
      setTimeout(function() {
        const b = bounds();
        if (!b || !wrap) return;
        wrap.scrollLeft = Math.max(0, (b.minX + b.maxX) / 2 - wrap.clientWidth / 2);
        wrap.scrollTop  = Math.max(0, (b.minY + b.maxY) / 2 - wrap.clientHeight / 2);
      }, 300);
    }

    function exportPNG(filename) {
      const overlay = document.getElementById('nmm-export-overlay');
      overlay.style.display = 'flex';
      NMMCore.loadHtml2Canvas().then(function(h2c) {
        const b = bounds(), pad = 80;
        if (!b) throw new Error('Nothing to export');
        return h2c(canvas, { backgroundColor: '#e8e4db', x: b.minX - pad, y: b.minY - pad,
          width: b.maxX - b.minX + pad * 2, height: b.maxY - b.minY + pad * 2, scale: 2, logging: false });
      }).then(function(c) {
        overlay.style.display = 'none';
        const a = document.createElement('a');
        a.download = filename; a.href = c.toDataURL('image/png'); a.click();
      }).catch(function(e) {
        overlay.style.display = 'none';
        alert('PNG export failed: ' + e.message);
      });
    }

    // Nodes from linked islands are shown read-only and never saved with this map.
    async function loadLinkedIslands(islandIds, list) {
      for (const islandId of islandIds || []) {
        const island = await NMMCore.api('GET', 'islands/' + islandId);
        if (!island || !island.nodes) continue;
        island.nodes.forEach(function(n) {
          n._island_id = islandId; n._island_title = island.title; n._readonly = true;
          if (!list.find(function(x) { return x.id === n.id; })) list.push(n);
        });
      }
    }

    return {
      find: find, depth: depth, isVisible: isVisible, hasChildren: hasChildren, isDescendant: isDescendant,
      getEl: getEl, render: render, drawLines: drawLines, setSettings: setSettings, renderMotif: renderMotif,
      autoCenter: autoCenter, exportPNG: exportPNG, loadLinkedIslands: loadLinkedIslands
    };
  }

  window.NMMCanvas = { create: create };
})();
