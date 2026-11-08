/* Navigator Mind Maps — map canvas rendering shared by the editor and the share view.
 * NMMCanvas.create(opts) returns helpers bound to one canvas.
 *   opts.getNodes()        current node array
 *   opts.editable          show Edit / + Child / Delete buttons
 *   opts.decorate(el, n)   called for each rendered node element (event wiring)
 *   opts.selectedId()      id to highlight (optional)
 * Moving around: drag empty space or use the arrow keys (Shift for bigger steps).
 * Zoom: Ctrl/⌘ + scroll (and trackpad pinch), two-finger pinch on touch screens,
 * and the − / % / + / Fit buttons or the + / − keys. Node x, y stay in unzoomed canvas pixels;
 * toCanvas() turns a pointer position into those.
 * Animate: animate() reveals the map card by card, each top-level branch in turn, down through its
 * cards; a Stop / speed bar shows meanwhile, and Escape or a click on the map ends it. */
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
    let revealed = null, anim = null; // set while animating
    function isVisible(id) {
      if (revealed && !revealed.has(id)) return false;
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
      const date     = n.date ? '<div class="nmm-node-date">📅 ' + esc(n.date) + (n.end ? ' → ' + esc(n.end) : '') + '</div>' : '';
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
        const s = LINE_STYLES[Math.min(depth(n.id), LINE_STYLES.length - 1)];
        const pw = pEl.offsetWidth, ph = pEl.offsetHeight, cw = cEl.offsetWidth, ch = cEl.offsetHeight;
        let d;
        // Mind Map style grows sideways: join the facing sides of cards that sit beside each other
        const side = settings.layout === 'mind' ? (n.x >= p.x + pw ? 1 : n.x + cw <= p.x ? -1 : 0) : 0;
        if (side) {
          const sx = side > 0 ? p.x + pw : p.x, sy = p.y + ph / 2, ex = side > 0 ? n.x : n.x + cw, ey = n.y + ch / 2;
          d = 'M' + sx + ',' + sy + ' C' + (sx + ex) / 2 + ',' + sy + ' ' + (sx + ex) / 2 + ',' + ey + ' ' + ex + ',' + ey;
        } else {
          const px = p.x + pw / 2, py = p.y + ph, cx = n.x + cw / 2, cy = n.y;
          const dx = Math.abs(cx - px), dy = Math.abs(cy - py);
          d = dy > 0 && dx / dy < 0.10 ? 'M' + px + ',' + py + ' L' + px + ',' + cy
            : 'M' + px + ',' + py + ' C' + px + ',' + (py + cy) / 2 + ' ' + cx + ',' + (py + cy) / 2 + ' ' + cx + ',' + cy;
        }
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

    function centerOn(id) {
      const el = getEl(id), n = find(id);
      if (!el || !n || !wrap) return;
      wrap.scrollLeft = Math.max(0, (n.x + el.offsetWidth / 2) * zoom - wrap.clientWidth / 2);
      wrap.scrollTop  = Math.max(0, (n.y + el.offsetHeight / 2) * zoom - wrap.clientHeight / 2);
    }
    // ---- Animate ----
    const STILL = window.matchMedia('(prefers-reduced-motion: reduce)');
    function readingOrder(list, parentId) {
      return list.filter(function(n) { return (n.parent || null) === parentId; })
        .sort(function(a, b) { return Math.round(a.y / 100) - Math.round(b.y / 100) || a.x - b.x; });
    }
    // Keep the newest card in view without jumping when it already is
    function follow(el) {
      const r = el.getBoundingClientRect(), w = wrap.getBoundingClientRect(), m = 60;
      if (r.left >= w.left + m && r.right <= w.right - m && r.top >= w.top + m && r.bottom <= w.bottom - m) return;
      glideTo(wrap.scrollLeft + (r.left + r.width / 2) - (w.left + w.width / 2),
              wrap.scrollTop + (r.top + r.height / 2) - (w.top + w.height / 2));
    }
    // A short eased glide; a timer makes sure it lands even where animation frames don't run
    let glide = null;
    function glideTo(x, y) {
      x = Math.max(0, Math.min(x, wrap.scrollWidth - wrap.clientWidth));
      y = Math.max(0, Math.min(y, wrap.scrollHeight - wrap.clientHeight));
      if (glide) { cancelAnimationFrame(glide.raf); clearTimeout(glide.end); }
      if (STILL.matches) { wrap.scrollLeft = x; wrap.scrollTop = y; return; }
      const x0 = wrap.scrollLeft, y0 = wrap.scrollTop, t0 = performance.now(), dur = 450;
      glide = {};
      (function frame(now) {
        const p = Math.min(1, (now - t0) / dur), e = 1 - Math.pow(1 - p, 3);
        wrap.scrollLeft = x0 + (x - x0) * e; wrap.scrollTop = y0 + (y - y0) * e;
        if (p < 1) glide.raf = requestAnimationFrame(frame);
      })(t0);
      glide.end = setTimeout(function() { cancelAnimationFrame(glide.raf); wrap.scrollLeft = x; wrap.scrollTop = y; glide = null; }, dur + 60);
    }
    // o.restore: put the collapsed flags back afterwards (the editor saves them); o.onStop()
    function animate(o) {
      o = o || {};
      if (anim) stopAnimation();
      const list = nodes(), ids = {};
      list.forEach(function(n) { ids[n.id] = true; });
      const order = [];
      (function walk(items) {
        items.forEach(function(n) { order.push(n); walk(readingOrder(list, n.id)); });
      })(list.filter(function(n) { return !n.parent || !ids[n.parent]; })
        .sort(function(a, b) { return (a._readonly ? 1 : 0) - (b._readonly ? 1 : 0) || a.y - b.y || a.x - b.x; }));
      if (!order.length) return;

      const saved = list.map(function(n) { return [n, n.collapsed]; });
      list.forEach(function(n) { n.collapsed = false; });
      revealed = new Set();
      const bar = document.createElement('div');
      bar.className = 'nmm-anim-bar'; bar.setAttribute('role', 'group'); bar.setAttribute('aria-label', 'Animation');
      bar.innerHTML = '<span class="nmm-anim-count" aria-live="polite"></span>' +
        '<button type="button" data-act="speed" title="Play faster">2×</button>' +
        '<button type="button" data-act="skip" title="Show everything now">Skip to end</button>' +
        '<button type="button" data-act="stop">■ Stop</button>';
      wrap.parentNode.appendChild(bar);
      anim = { i: 0, timer: null, speed: 1, bar: bar, saved: saved, o: o };
      bar.addEventListener('click', function(e) {
        const b = e.target.closest('[data-act]');
        if (!b) return;
        if (b.dataset.act === 'speed') { anim.speed = anim.speed === 1 ? 2 : 1; b.textContent = anim.speed === 1 ? '2×' : '1×'; b.title = anim.speed === 1 ? 'Play faster' : 'Normal speed'; }
        else stopAnimation(b.dataset.act === 'stop');
      });
      render();

      function step() {
        const n = order[anim.i++];
        revealed.add(n.id);
        render();
        const el = getEl(n.id);
        if (el) {
          el.classList.add('nmm-reveal');
          el.querySelectorAll('.nmm-node-sub span').forEach(function(s, k) { s.style.animationDelay = (0.35 + k * 0.16) + 's'; });
          follow(el);
        }
        bar.querySelector('.nmm-anim-count').textContent = anim.i + ' of ' + order.length;
        if (anim.i >= order.length) { anim.timer = setTimeout(function() { stopAnimation(); }, 1200); return; }
        const d = depth(n.id), subs = (n.subs || []).length;
        const wait = (d === 0 ? 1100 : d === 1 ? 1000 : 750) + Math.min(subs, 5) * 160;
        anim.timer = setTimeout(step, wait / anim.speed);
      }
      anim.timer = setTimeout(step, 300);
    }
    // stopped: the person ended it; either way everything is shown again
    function stopAnimation() {
      if (!anim) return;
      clearTimeout(anim.timer);
      anim.bar.remove();
      revealed = null;
      if (anim.o.restore) anim.saved.forEach(function(p) { p[0].collapsed = p[1]; });
      const done = anim.o.onStop;
      anim = null;
      render();
      if (done) done();
    }
    if (wrap) wrap.addEventListener('mousedown', function(e) { if (anim && !e.target.closest('.nmm-anim-bar')) stopAnimation(); }, true);
    document.addEventListener('keydown', function(e) { if (anim && e.key === 'Escape') stopAnimation(); });

    function autoCenter() {
      setTimeout(centerNow, 300);
    }
    function centerNow() {
      const b = bounds();
      if (!b || !wrap) return;
      wrap.scrollLeft = Math.max(0, (b.minX + b.maxX) / 2 * zoom - wrap.clientWidth / 2);
      wrap.scrollTop  = Math.max(0, (b.minY + b.maxY) / 2 * zoom - wrap.clientHeight / 2);
    }

    // ---- Zoom ----
    // The canvas is scaled with a CSS transform inside a sizer that takes the
    // scaled size, so the scroll area always matches what you see.
    const ZOOM_MIN = 0.25, ZOOM_MAX = 2;
    let zoom = 1, sizer = null, zoomLabel = null;
    function setupZoom() {
      if (!canvas || !wrap) return;
      sizer = document.createElement('div');
      sizer.className = 'nmm-canvas-sizer';
      canvas.parentNode.insertBefore(sizer, canvas);
      sizer.appendChild(canvas);
      // The motif sits outside the scrolling canvas so it stays at the top of the map area
      if (motif) {
        wrap.parentNode.insertBefore(motif, wrap);
        const placeMotif = function() { motif.style.top = wrap.offsetTop + 'px'; };
        placeMotif();
        window.addEventListener('resize', placeMotif);
      }

      const bar = document.createElement('div');
      bar.className = 'nmm-zoom';
      bar.innerHTML = '<button type="button" data-zoom="out" title="Zoom out (Ctrl + scroll)">−</button>' +
        '<button type="button" data-zoom="reset" class="nmm-zoom-pct" title="Back to 100%">100%</button>' +
        '<button type="button" data-zoom="in" title="Zoom in (Ctrl + scroll)">+</button>' +
        '<button type="button" data-zoom="fit" title="Fit the whole map on screen">Fit</button>';
      wrap.parentNode.insertBefore(bar, wrap.nextSibling);
      zoomLabel = bar.querySelector('.nmm-zoom-pct');
      bar.addEventListener('click', function(e) {
        const b = e.target.closest('[data-zoom]'); if (!b) return;
        const a = b.dataset.zoom;
        if (a === 'fit') zoomToFit();
        else setZoom(a === 'in' ? zoom * 1.2 : a === 'out' ? zoom / 1.2 : 1);
      });
      applyZoom();

      // Ctrl/⌘ + wheel. Trackpad pinch arrives as a wheel event with ctrlKey set.
      wrap.addEventListener('wheel', function(e) {
        if (!e.ctrlKey && !e.metaKey) return;
        e.preventDefault();
        setZoom(zoom * Math.exp(-e.deltaY * (e.deltaMode === 1 ? 0.05 : 0.002)), e.clientX, e.clientY);
      }, { passive: false });

      // Two-finger pinch on touch screens
      let pinch = null;
      function dist(t) { return Math.hypot(t[0].clientX - t[1].clientX, t[0].clientY - t[1].clientY); }
      wrap.addEventListener('touchstart', function(e) {
        if (e.touches.length === 2) pinch = { d: dist(e.touches), z: zoom };
      }, { passive: true });
      wrap.addEventListener('touchmove', function(e) {
        if (!pinch || e.touches.length !== 2) return;
        e.preventDefault();
        setZoom(pinch.z * dist(e.touches) / pinch.d,
          (e.touches[0].clientX + e.touches[1].clientX) / 2, (e.touches[0].clientY + e.touches[1].clientY) / 2);
      }, { passive: false });
      wrap.addEventListener('touchend', function(e) { if (e.touches.length < 2) pinch = null; });

      // Grab empty space and drag to slide the map (cards keep their own drag)
      let grab = null;
      wrap.addEventListener('mousedown', function(e) {
        if (e.button !== 0 || e.target.closest('.nmm-node')) return;
        e.preventDefault();
        grab = { x: e.clientX, y: e.clientY, sl: wrap.scrollLeft, st: wrap.scrollTop };
        wrap.classList.add('nmm-grabbing');
      });
      document.addEventListener('mousemove', function(e) {
        if (!grab) return;
        wrap.scrollLeft = grab.sl - (e.clientX - grab.x);
        wrap.scrollTop  = grab.st - (e.clientY - grab.y);
      });
      document.addEventListener('mouseup', function() { grab = null; wrap.classList.remove('nmm-grabbing'); });

      // Arrow keys slide the map (Shift for bigger steps); + and − zoom
      document.addEventListener('keydown', function(e) {
        if (e.ctrlKey || e.metaKey || e.altKey || e.target.closest('input,textarea,select,[contenteditable]')) return;
        if (document.querySelector('.nmm-modal-overlay.open')) return;
        const step = e.shiftKey ? 400 : 120;
        const move = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];
        if (move) { e.preventDefault(); wrap.scrollBy({ left: move[0], top: move[1], behavior: 'smooth' }); return; }
        if (e.key === '+' || e.key === '=') setZoom(zoom * 1.2);
        if (e.key === '-' || e.key === '_') setZoom(zoom / 1.2);
      });
    }
    function applyZoom() {
      if (!sizer) return;
      canvas.style.transform = zoom === 1 ? '' : 'scale(' + zoom + ')';
      sizer.style.width  = canvas.offsetWidth * zoom + 'px';
      sizer.style.height = canvas.offsetHeight * zoom + 'px';
      zoomLabel.textContent = Math.round(zoom * 100) + '%';
    }
    // Zooms keeping the canvas point under (cx, cy) still; defaults to the middle of the view
    function setZoom(z, cx, cy) {
      if (!sizer) return;
      z = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, z));
      if (Math.abs(z - 1) < 0.03) z = 1;
      const wr = wrap.getBoundingClientRect();
      if (cx === undefined) { cx = wr.left + wrap.clientWidth / 2; cy = wr.top + wrap.clientHeight / 2; }
      const p = toCanvas(cx, cy);
      zoom = z; applyZoom();
      const r = canvas.getBoundingClientRect();
      wrap.scrollLeft += r.left + p.x * zoom - cx;
      wrap.scrollTop  += r.top  + p.y * zoom - cy;
    }
    function zoomToFit() {
      const b = bounds(); if (!b || !sizer) return;
      const pad = 60;
      zoom = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, Math.min(
        wrap.clientWidth / (b.maxX - b.minX + pad * 2), wrap.clientHeight / (b.maxY - b.minY + pad * 2), 1)));
      applyZoom(); centerNow();
    }
    // Pointer position → unzoomed canvas coordinates (what node x, y use)
    function toCanvas(clientX, clientY) {
      const r = canvas.getBoundingClientRect();
      return { x: (clientX - r.left) / zoom, y: (clientY - r.top) / zoom };
    }
    setupZoom();

    function exportPNG(filename) {
      const overlay = document.getElementById('nmm-export-overlay');
      overlay.style.display = 'flex';
      canvas.style.transform = ''; // export at full size whatever the zoom
      NMMCore.loadHtml2Canvas().then(function(h2c) {
        const b = bounds(), pad = 80;
        if (!b) throw new Error('Nothing to export');
        return h2c(canvas, { backgroundColor: '#e8e4db', x: b.minX - pad, y: b.minY - pad,
          width: b.maxX - b.minX + pad * 2, height: b.maxY - b.minY + pad * 2, scale: 2, logging: false });
      }).then(function(c) {
        applyZoom();
        overlay.style.display = 'none';
        const a = document.createElement('a');
        a.download = filename; a.href = c.toDataURL('image/png'); a.click();
      }).catch(function(e) {
        applyZoom();
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
      autoCenter: autoCenter, centerOn: centerOn, exportPNG: exportPNG,
      animate: animate, stopAnimation: stopAnimation, isAnimating: function() { return !!anim; }, loadLinkedIslands: loadLinkedIslands,
      toCanvas: toCanvas, setZoom: setZoom, zoomToFit: zoomToFit
    };
  }

  window.NMMCanvas = { create: create };
})();
