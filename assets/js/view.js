/* Navigator Mind Maps — read-only share view (/map-share/?share=KEY). Markup: templates/view.php */
(function() {
const root = document.getElementById('nmm-view');
if (!root) return;

const MAP_ID    = parseInt(root.dataset.mapId, 10) || 0;
const SHARE_KEY = root.dataset.shareKey || '';
let nodes = [];

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
  return n && { title: n.title, subs: n.subs, date: n.date, tag: n.tag, sefirot: n.sefirot };
} });

async function loadMap() {
  try {
    // The share endpoint is public; no nonce, since a cached page could carry a stale one.
    const map = SHARE_KEY ? await fetch(NMM.restUrl + 'share/' + SHARE_KEY).then(function(r) { return r.json(); })
              : MAP_ID    ? await NMMCore.api('GET', 'maps/' + MAP_ID) : null;
    if (!map || map.code) { showError(); return; }
    nodes = map.nodes || [];
    // Featured maps can be copied by any member
    const copyBtn = document.getElementById('nmm-view-copy');
    if (copyBtn && map.featured && NMM.isLoggedIn && !SHARE_KEY) {
      copyBtn.style.display = '';
      copyBtn.onclick = function() { NMMCore.copyMap(map.id, copyBtn); };
    }
    C.setSettings(map.settings);
    document.getElementById('nmm-view-title').textContent = map.title || '';
    await C.loadLinkedIslands(map.islands, nodes);
    C.render();
    outline.render();
  } catch(e) { showError(); }
}

function showError() {
  document.getElementById('nmm-canvas').innerHTML = '<div style="padding:60px;text-align:center;color:#999;font-size:18px;">Map not found or no longer available.</div>';
}

window.nmmViewExpandAll   = function() { nodes.forEach(function(n) { n.collapsed = false; }); C.render(); };
window.nmmViewCollapseAll = function() { nodes.forEach(function(n) { if (C.hasChildren(n.id)) n.collapsed = true; }); C.render(); };
window.nmmViewExportPNG   = function() { C.exportPNG('mindmap_share.png'); };
window.nmmViewAnimate     = function() {
  const btn = document.getElementById('nmm-view-animate');
  if (C.isAnimating()) { C.stopAnimation(); return; }
  btn.textContent = '■ Stop';
  C.animate({ onStop: function() { btn.textContent = '▶ Animate'; } });
};

loadMap();
})();
