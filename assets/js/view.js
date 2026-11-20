/* Navigator Life Maps — view-only mind map (/map-share/?share=KEY, or ?map_id=N for a featured map).
 * Markup: templates/view.php. The toolbar matches the editor's; anything that would change the map
 * says it is view only instead (NMMCore.viewOnlyNotice).
 * Layout ▾ (templates/layout-menu.php): see the map as a Flow Chart, Mind Map, Bubble, Tree of Life
 * or Outline. It opens the way the owner left it; another choice arranges it on this screen only
 * (assets/js/layouts.js, assets/js/doc.js read only) and is never saved. */
(function() {
const root = document.getElementById('nmm-view');
if (!root) return;

const MAP_ID = parseInt(root.dataset.mapId, 10) || 0;
let nodes = [], map = null, view = {};   // view = the settings this screen draws with (layout, outline)

const C = NMMCanvas.create({
  getNodes: function() { return nodes; },
  editable: false,
  portals: function() { return map && !NMMCore.shareKey ? NMMCore.backTargets(map.linked_from) : []; },
  decorate: function(el) {
    el.addEventListener('mousedown', function(e) {
      const n = e.target.dataset.eid && C.find(e.target.dataset.eid);
      if (n) { n.collapsed = !n.collapsed; draw(); }
    });
  }
});
const L = NMMLayouts.create(C, function() { return nodes; });

const outline = NMMOutline.create({
  root: root, getNodes: function() { return nodes; }, editable: false,
  onShowMap: function() { draw(); C.autoCenter(); }
});

// Outline, read only: arrows open and close branches here; nothing else changes
const doc = NMMDoc.create({
  el: document.getElementById('nmm-doc'), getNodes: function() { return nodes; }, find: function(id) { return C.find(id); },
  editable: false, pushUndo: function() {}, changed: function() {}, redraw: function() { draw(); },
  openEdit: function() {}, newId: function() { return ''; }
});
function outlineOn() { return view.view === 'outline'; }
function draw() {
  root.classList.toggle('nmm-doc-mode', outlineOn());
  if (outlineOn()) doc.render(); else C.render();
  outline.render();
}

NMMCore.hoverCard({ root: root, selector: '.nmm-node, .nmm-doc-line, .nmm-doc-title', info: function(el) {
  const n = C.find(el.dataset.id);
  return n && { title: n.title, link: n.link, subs: n.subs, date: n.date, end: n.end, tag: n.tag, sefirot: n.sefirot };
} });

async function loadMap() {
  try {
    map = (NMMCore.shareKey || MAP_ID) ? await NMMCore.getMap(MAP_ID) : null;
    if (!map || !map.id) { showError(); return; }
    // Your own map (or one shared with you) opens in the editor instead
    if (!NMMCore.shareKey && map.can_edit) { location.replace(NMM.editorUrl + '?map_id=' + map.id + (NMMCore.fromParam() ? '&from=' + NMMCore.fromParam() : '')); return; }
    NMMCore.backLink(root, map.linked_from || []);
    nodes = map.nodes || [];
    // Featured maps can be copied by any member
    const copyBtn = document.getElementById('nmm-view-copy');
    if (copyBtn && map.featured && NMM.isLoggedIn) {
      copyBtn.style.display = '';
      copyBtn.onclick = function() { NMMCore.copyMap(map.id, copyBtn); };
    }
    document.getElementById('nmm-viewonly-badge').onclick = function() { NMMCore.viewOnlyNotice(map.id); };
    NMMCore.setViewTabs(NMMCore.shareKey ? [] : [map.id], [map.id]);
    view = Object.assign({}, map.settings || {});
    C.setSettings(view);
    showLayoutStyle();
    document.getElementById('nmm-view-title').textContent = map.title || '';
    document.title = (map.title || 'Map') + ' · Life Maps';
    await C.loadLinkedIslands(map.islands, nodes);
    draw();
    C.autoFit();
  } catch(e) { showError(); }
}

// Missing, deleted, or (signed out) a featured map that needs a sign-in: say so, with a way on
function showError() {
  const signedOut = !NMM.isLoggedIn && !NMMCore.shareKey;
  const box = document.createElement('div');
  box.className = 'nmm-view-missing';
  box.innerHTML = '<p>' + (signedOut ? 'Sign in to see this map.' : 'Map not found or no longer available.') + '</p>' +
    '<a class="nmm-btn nmm-btn-primary" href="' + NMMCore.esc(NMM.mapsUrl) + '">' + (signedOut ? 'Sign in or join' : 'Go to your maps to get started') + '</a>';
  document.getElementById('nmm-canvas-wrap').replaceChildren(box);
  const outlineEl = root.querySelector('.nmm-outline'); if (outlineEl) outlineEl.replaceChildren(box.cloneNode(true));
  const btn = document.getElementById('nmm-layout-btn'); if (btn) btn.style.display = 'none';
}

// ---- Layout menu: on this screen only ----
const layoutMenu = document.getElementById('nmm-layout-menu');
function showLayoutStyle() {
  const style = outlineOn() ? 'outline' : L.styleOf(view), opt = layoutMenu.querySelector('[data-layout="' + style + '"]');
  if (!opt) return;
  document.getElementById('nmm-layout-icon').innerHTML = opt.querySelector('svg').outerHTML;
  layoutMenu.querySelectorAll('[data-layout]').forEach(function(b) { b.classList.toggle('active', b === opt); });
}
window.nmmToggleLayoutMenu = function(e) {
  e.stopPropagation();
  if (layoutMenu.classList.toggle('open')) {
    const r = document.getElementById('nmm-layout-btn').getBoundingClientRect();
    layoutMenu.style.left = Math.max(8, Math.min(r.left, innerWidth - layoutMenu.offsetWidth - 8)) + 'px';
    layoutMenu.style.top = (r.bottom + 6) + 'px';
  }
};
layoutMenu.querySelectorAll('[data-layout]').forEach(function(b) {
  b.addEventListener('click', function() {
    layoutMenu.classList.remove('open');
    const style = b.dataset.layout;
    if (style === 'outline') {
      if (outlineOn()) return;
      nodes = L.readingOrder(L.styleOf(view)); // the Outline lists cards in the order the map reads
      view.view = 'outline';
      showLayoutStyle(); draw();
      return;
    }
    if (style === 'tol' && !L.hasSefirot()) {
      alert('No cards in this map have a Tree of Life position, so it can\'t be shown that way.');
      return;
    }
    const fromOutline = outlineOn();
    if (fromOutline || view.layout !== style) L.listOrder(true);
    if (fromOutline) nodes.filter(function(n) { return !n._readonly; }).forEach(function(n, i) { n.x = i * 1000; n.y = i * 1000; });
    delete view.view;
    view.layout = style;
    C.setSettings(view); showLayoutStyle();
    draw(); // render first: the layouts measure the cards as drawn in the new style
    L.run(style, nodes.filter(function(n) { return !n._readonly; }));
    draw(); C.autoFit();
  });
});
document.addEventListener('click', function(e) { if (!layoutMenu.contains(e.target)) layoutMenu.classList.remove('open'); });

window.nmmCenter       = function() { C.autoCenter(); };
window.nmmExpandAll    = function() { nodes.forEach(function(n) { n.collapsed = false; }); draw(); };
window.nmmCollapseAll  = function() { nodes.forEach(function(n) { if (C.hasChildren(n.id)) n.collapsed = true; }); draw(); };
window.nmmAnimate      = function() { if (C.isAnimating()) C.stopAnimation(); else C.animate({}); };

NMMMore.init({
  view: 'map',
  maps: function() { return map ? [{ id: map.id, title: map.title, category: map.category, layout: (map.settings || {}).layout, nodes: nodes }] : []; },
  image: function(print, name) { return C.exportPNG(name, { print: print }); }
});

loadMap();
})();
