/* Navigator Life Maps — list view for phones, used by the editor and the share view.
 * Below 700px wide a List / Map switch appears; List shows the map as stacked cards: each island
 * (root) is a section with its main branches as cards, and tapping a card opens the cards under it.
 * Open/closed state here is separate from the canvas's collapsed flags, so it never changes the map. */
(function() {
  const esc = NMMCore.esc, TAG_COLORS = NMMCore.TAG_COLORS;
  const PHONE = window.matchMedia('(max-width: 700px)');
  const MODE_KEY = 'nmm_view_mode';

  function storedMode() { try { return localStorage.getItem(MODE_KEY) || 'list'; } catch(e) { return 'list'; } }
  function storeMode(m) { try { localStorage.setItem(MODE_KEY, m); } catch(e) {} }

  function shortDate(d) {
    const p = String(d).split('-');
    if (p.length !== 3) return d;
    return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  /* opts: root (the .nmm-app element), getNodes(), editable, onEdit(id), onAdd(parentId), onShowMap() */
  function create(opts) {
    const root = opts.root;
    const el = root.querySelector('.nmm-outline');
    const switchEl = root.querySelector('.nmm-view-switch');
    if (!el || !switchEl) return { render: function() {} };
    const open = new Set();
    let mode = storedMode();

    // Cards in reading order: a row (flow chart) reads left to right, a column (mind map) top to bottom
    function childrenOf(id, list) {
      return list.filter(function(n) { return (n.parent || null) === id; })
        .sort(function(a, b) { return Math.round(a.y / 100) - Math.round(b.y / 100) || a.x - b.x; });
    }

    function cardHtml(n, depth, list) {
      const kids = childrenOf(n.id, list);
      const isOpen = open.has(n.id);
      const canEdit = opts.editable && !n._readonly;
      const tag = TAG_COLORS[n.tag];
      const meta = [
        n.date ? '<span class="nmm-ol-date">📅 ' + esc(shortDate(n.date)) + (n.end ? ' → ' + esc(shortDate(n.end)) : '') + '</span>' : '',
        tag ? '<span class="nmm-ol-tag" style="background:' + tag.bg + ';color:' + tag.text + '">' + tag.label + '</span>' : '',
        n._readonly ? '<span class="nmm-ol-linked">Linked: ' + esc(n._island_title || '') + '</span>' : ''
      ].join('');
      const subs = (n.subs || []).filter(Boolean);
      let html = '<li class="nmm-ol-item" data-depth="' + Math.min(depth, 4) + '">' +
        '<div class="nmm-ol-card' + (kids.length ? ' nmm-ol-has-kids' : '') + '" data-id="' + esc(n.id) + '"' +
          (kids.length ? ' role="button" tabindex="0" aria-expanded="' + isOpen + '"' : '') + '>' +
          '<div class="nmm-ol-title">' + esc(n.title || 'Untitled') + '</div>' +
          (meta ? '<div class="nmm-ol-meta">' + meta + '</div>' : '') +
          (subs.length ? '<ul class="nmm-ol-subs">' + subs.map(function(s) { return '<li>' + esc(s) + '</li>'; }).join('') + '</ul>' : '') +
          '<div class="nmm-ol-actions">' +
            (kids.length ? '<span class="nmm-ol-toggle">' + (isOpen ? '▾ Hide cards' : '▸ ' + kids.length + (kids.length === 1 ? ' card' : ' cards')) + '</span>' : '') +
            (canEdit ? '<button type="button" class="nmm-ol-btn" data-act="edit">Edit</button><button type="button" class="nmm-ol-btn" data-act="add">+ Card</button>' : '') +
          '</div>' +
        '</div>';
      if (kids.length && isOpen) {
        html += '<ul class="nmm-ol-list">' + kids.map(function(k) { return cardHtml(k, depth + 1, list); }).join('') + '</ul>';
      }
      return html + '</li>';
    }

    function render() {
      if (!root.classList.contains('nmm-list-mode')) return;
      const list = opts.getNodes();
      const ids = {};
      list.forEach(function(n) { ids[n.id] = true; });
      const roots = list.filter(function(n) { return !n.parent || !ids[n.parent]; })
        .sort(function(a, b) { return (a._readonly ? 1 : 0) - (b._readonly ? 1 : 0) || a.y - b.y || a.x - b.x; });
      if (!roots.length) { el.innerHTML = '<p class="nmm-ol-empty">This map has no cards yet.</p>'; return; }
      el.innerHTML = roots.map(function(r) {
        const kids = childrenOf(r.id, list);
        const canEdit = opts.editable && !r._readonly;
        const subs = (r.subs || []).filter(Boolean);
        return '<section class="nmm-ol-section">' +
          '<div class="nmm-ol-head" data-id="' + esc(r.id) + '">' +
            '<h2>' + esc(r.title || 'Untitled') + '</h2>' +
            (subs.length ? '<p>' + esc(subs.join(' · ')) + '</p>' : '') +
            (canEdit ? '<div class="nmm-ol-actions"><button type="button" class="nmm-ol-btn" data-act="edit">Edit</button><button type="button" class="nmm-ol-btn" data-act="add">+ Card</button></div>' : '') +
          '</div>' +
          (kids.length ? '<ul class="nmm-ol-list">' + kids.map(function(k) { return cardHtml(k, 1, list); }).join('') + '</ul>' : '') +
        '</section>';
      }).join('');
    }

    el.addEventListener('click', function(e) {
      const holder = e.target.closest('[data-id]');
      if (!holder) return;
      const id = holder.dataset.id, btn = e.target.closest('[data-act]');
      if (btn && btn.dataset.act === 'edit') { opts.onEdit(id); return; }
      if (btn && btn.dataset.act === 'add') { open.add(id); opts.onAdd(id); return; }
      if (!holder.classList.contains('nmm-ol-has-kids')) return;
      if (open.has(id)) open.delete(id); else open.add(id);
      render();
    });
    el.addEventListener('keydown', function(e) {
      if ((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('nmm-ol-has-kids')) { e.preventDefault(); e.target.click(); }
    });

    function apply() {
      const list = PHONE.matches && mode === 'list';
      const wasList = root.classList.contains('nmm-list-mode');
      root.classList.toggle('nmm-list-mode', list);
      switchEl.querySelectorAll('[data-view]').forEach(function(b) {
        const on = b.dataset.view === mode;
        b.classList.toggle('active', on); b.setAttribute('aria-pressed', on);
      });
      if (list) render();
      else if (wasList && opts.onShowMap) opts.onShowMap(); // the canvas was hidden, so lines and centring need redoing
    }
    switchEl.addEventListener('click', function(e) {
      const b = e.target.closest('[data-view]');
      if (!b || b.dataset.view === mode) return;
      mode = b.dataset.view; storeMode(mode); apply(); window.scrollTo(0, 0);
    });
    if (PHONE.addEventListener) PHONE.addEventListener('change', apply); else PHONE.addListener(apply);
    apply();
    return { render: render };
  }

  window.NMMOutline = { create: create };
})();
