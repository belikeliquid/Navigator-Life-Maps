/* Navigator Mind Maps — view-only mind map (/map-share/?share=KEY, or ?map_id=N for a featured map).
 * Markup: templates/view.php. The toolbar matches the editor's; anything that would change the map
 * says it is view only instead (NMMCore.viewOnlyNotice). */
(function() {
const root = document.getElementById('nmm-view');
if (!root) return;

const MAP_ID = parseInt(root.dataset.mapId, 10) || 0;
let nodes = [], map = null;

const C = NMMCanvas.create({
  getNodes: function() { return nodes; },
  editable: false,
  decorate: function(el) {
    el.addEventListener('mousedown', function(e) {
      const n = e.target.dataset.eid && C.find(e.target.dataset.eid);
      if (n) { n.collapsed = !n.collapsed; C.render(); }
    });
  }
});

const outline = NMMOutline.create({
  root: root, getNodes: function() { return nodes; }, editable: false,
  onShowMap: function() { C.render(); C.autoCenter(); }
});

NMMCore.hoverCard({ root: root, selector: '.nmm-node', info: function(el) {
  const n = C.find(el.dataset.id);
  return n && { title: n.title, subs: n.subs, date: n.date, end: n.end, tag: n.tag, sefirot: n.sefirot };
} });

async function loadMap() {
  try {
    map = (NMMCore.shareKey || MAP_ID) ? await NMMCore.getMap(MAP_ID) : null;
    if (!map || !map.id) { showError(); return; }
    // Your own map (or one shared with you) opens in the editor instead
    if (!NMMCore.shareKey && map.can_edit) { location.replace(NMM.editorUrl + '?map_id=' + map.id); return; }
    nodes = map.nodes || [];
    // Featured maps can be copied by any member
    const copyBtn = document.getElementById('nmm-view-copy');
    if (copyBtn && map.featured && NMM.isLoggedIn) {
      copyBtn.style.display = '';
      copyBtn.onclick = function() { NMMCore.copyMap(map.id, copyBtn); };
    }
    document.getElementById('nmm-viewonly-badge').onclick = function() { NMMCore.viewOnlyNotice(map.id); };
    NMMCore.setViewTabs(NMMCore.shareKey ? [] : [map.id], [map.id]);
    C.setSettings(map.settings);
    document.getElementById('nmm-view-title').textContent = map.title || '';
    document.title = (map.title || 'Map') + ' · Life Maps';
    await C.loadLinkedIslands(map.islands, nodes);
    C.render();
    C.autoCenter();
    outline.render();
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
}

window.nmmCenter       = function() { C.autoCenter(); };
window.nmmExpandAll    = function() { nodes.forEach(function(n) { n.collapsed = false; }); C.render(); };
window.nmmCollapseAll  = function() { nodes.forEach(function(n) { if (C.hasChildren(n.id)) n.collapsed = true; }); C.render(); };
window.nmmAnimate      = function() { if (C.isAnimating()) C.stopAnimation(); else C.animate({}); };

NMMMore.init({
  view: 'map',
  maps: function() { return map ? [{ id: map.id, title: map.title, category: map.category, layout: (map.settings || {}).layout, nodes: nodes }] : []; },
  image: function(print, name) { return C.exportPNG(name, { print: print }); }
});

loadMap();
})();
